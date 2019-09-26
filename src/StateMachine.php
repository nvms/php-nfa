<?php

/*
 * ┌----------------------------------------------------------------------┐
 * | This file is part of php-nfa (https://github.com/nvms/php-nfa)       |
 * ├----------------------------------------------------------------------┤
 * | Copyright (c) nvms (https://github.com/nvms/php-nfa)                 |
 * | Licensed under the MIT License (https://opensource.org/licenses/MIT) |
 * └----------------------------------------------------------------------┘
 */

namespace NVMS\NFA;

class StateMachine
{
    /** @var array array of states the machine is in */
    public $states;
    /** @var Transition[] transitions assigned to this StateMachine */
    public $transitions;
    /** @var int time in ms between update ticks */
    public $tickRate = 1000;

    public $stateTimes;

    public function __construct()
    {
        $this->transitions = [];
        $this->states = [];
        $this->stateTicks = [];
        $this->setStates($this->initial());
    }

    public function setStates(array $states)
    {
        foreach ($states as $state) {
            $this->states[strval($state)] = date('Y-m-d H:i:s');
        }
    }

    /**
     * Define the initial states of the machine.
     *
     * @return array
     */
    public static function initial()
    {
        return [];
    }

    public function addTransition(Transition $transition)
    {
        \array_push($this->transitions, $transition);

        return $transition;
    }

    public function is($state)
    {
        return array_key_exists($state, $this->states);
    }

    public function getStateNameById($state)
    {
        if (null === $state) {
            return null;
        }

        $class = new \ReflectionClass($this);
        $constants = array_flip($class->getConstants());

        return $constants[$state];
    }

    public function tick(StateTick $stateTick)
    {
        \array_push($this->stateTicks, $stateTick);

        return $stateTick;
    }

    public function conditionsMet(Condition $conditions)
    {
        $numConditions = count($conditions->conditions);
        $met = 0;

        foreach ($conditions->conditions as $condition) {
            $str = $condition;
            $expression = false;

            if (\preg_match_all('/{(.*?)}/', $condition, $m)) {
                $expression = true;
                foreach ($m[1] as $i => $var) {
                    $str = str_replace($m[0][$i], $this->$var, $str);
                }
            }

            if (\preg_match_all("/\[(.*?)\]/", $condition, $m)) {
                foreach ($m[1] as $i => $state) {
                    // https://stackoverflow.com/a/28582713
                    $ref = new \ReflectionClass($this);
                    $inState = $this->is($ref->getConstant($state));

                    if ($inState) {
                        ++$met;
                        continue;
                    }
                }
            }

            if ($expression) {
                $eval = 'return '.$str.';';
                $truthiness = eval($eval);

                if ($truthiness) {
                    ++$met;
                }
            }
        }

        if ($numConditions == $met) {
            return true;
        }

        return false;
    }

    public function doPostTransition(PostTransition $postTransition)
    {
        foreach ($postTransition->operations as $operation) {
            if (is_callable($operation)) {
                call_user_func($operation);
            }

            if (is_string($operation)) {
                $str = $operation;

                if (\preg_match_all('/<(.*?)>/', $operation, $m)) {
                    foreach ($m[1] as $i => $variable) {
                        $ref = new \ReflectionClass($this);
                        $property = $ref->getProperty($variable);
                        $property->setAccessible(true);

                        /**
                         * Get everything after the equal sign.
                         */
                        $desiredValue = preg_match('/=(.+)/', $operation, $desiredValueMatches);

                        if ($desiredValueMatches) {
                            $valStr = $desiredValueMatches[1];

                            /*
                             * Check if we need to convert any pieces of this expression.
                             */
                            if (\preg_match_all('/{(.*?)}/', $desiredValueMatches[1], $desVal)) {
                                foreach ($desVal[1] as $i => $var) {
                                    $valStr = str_replace($desVal[0][$i], $this->$var, $valStr);
                                }
                            }

                            /**
                             * Evaluate the expression and assign the result to the class instance's $property.
                             */
                            $eval = 'return '.$valStr.';';
                            $newValue = eval($eval);
                            $property->setValue($this, $newValue);
                        }
                    }
                }
            }
        }
    }

    /**
     * Checks for any possible state transitions
     * and commits them.
     *
     * @param bool $recurse if true, calls turn again if a transition occurs
     */
    public function turn($recurse = false)
    {
        $stateCopy = $this->states;

        /**
         * Before we check for possible transitions, we should handle
         * any StateTicks if we have any.
         */
        foreach ($this->stateTicks as $tick) {
            if (array_key_exists($tick->state, $this->states)) {
                $start = strtotime($tick->lastTick);
                $now = strtotime(date('Y-m-d H:i:s'));
                $diff = $now - $start;

                if ($diff >= $tick->interval) {
                    $numTicks = $diff / $tick->interval;
                    for ($i = 0; $i < $numTicks; ++$i) {
                        $pt = new PostTransition($tick->expression);
                        $this->doPostTransition($pt);
                    }
                    $tick->lastTick = date('Y-m-d H:i:s');
                }
            }
        }

        foreach ($this->transitions as $transition) {
            if (array_key_exists($transition->from, $this->states) || null === $transition->from) {
                if ($this->conditionsMet($transition->conditions)) {
                    if (null !== $transition->from) {
                        unset($this->states[$transition->from]);
                    }

                    if (!in_array($transition->to, $this->states) && null !== $transition->to) {
                        $this->states[$transition->to] = date('Y-m-d H:i:s');
                    }

                    if ($transition->postTransition) {
                        $this->doPostTransition($transition->postTransition);
                    }
                }
            }
        }

        if (($this->states != $stateCopy) && $recurse) {
            $this->turn();
        }
    }

    /**
     * Returns an array of state names.
     *
     * @return array
     */
    public function getStateNames()
    {
        $names = [];
        $keys = array_keys($this->states);

        foreach ($keys as $stateId) {
            array_push($names, $this->getStateNameById($stateId));
        }

        return $names;
    }

    /**
     * Iterate indefinitely until this state machine does NOT have $state state.
     *
     * e.g. $human->activate(Human::ALIVE);
     *
     * @param int $state
     */
    public function activate(int $state)
    {
        do {
            $this->turn();

            sleep($this->tickRate / 1000);
        } while ($this->is($state));
    }

    public function save(string $filename)
    {
        $obj = \serialize($this);
        $fp = \getcwd().DIRECTORY_SEPARATOR.'.phpnfa'.DIRECTORY_SEPARATOR.$filename.'.txt';

        return \file_put_contents($fp, $obj);
    }

    public static function load(string $filename)
    {
        $fp = \getcwd().DIRECTORY_SEPARATOR.'.phpnfa'.DIRECTORY_SEPARATOR.$filename.'.txt';
        $objData = \file_get_contents($fp);
        $obj = \unserialize($objData);

        return $obj;
    }

    public static function delete(string $filename)
    {
        return \unlink(\getcwd().DIRECTORY_SEPARATOR.'.phpnfa'.DIRECTORY_SEPARATOR.$filename.'.txt');
    }

    /**
     * Deep copy this object instance.
     */
    public function new()
    {
        return unserialize(serialize($this));
    }
}
