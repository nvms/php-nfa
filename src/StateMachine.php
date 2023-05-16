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

    public $stateTicks;

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

    public function not($state)
    {
        return !array_key_exists($state, $this->states);
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
                    $inState = $this->isState($state);

                    if ('!' === $state[0]) {
                        $inState = !$inState;
                        $state = substr($state, 1);
                    }

                    if ($inState) {
                        ++$met;
                    }
                }
            }

            if ($expression && $this->evaluateExpression($str)) {
                ++$met;
            }
        }

        return count($conditions->conditions) === $met;
    }

    private function isState($state)
    {
        $ref = new \ReflectionClass($this);

        return $this->is($ref->getConstant($state));
    }

    private function evaluateExpression($str)
    {
        $eval = 'return '.$str.';';

        return eval($eval);
    }

    public function doPostTransition(PostTransition $postTransition)
    {
        foreach ($postTransition->operations as $operation) {
            if (is_callable($operation)) {
                $operation();
                continue;
            }

            if (!is_string($operation) || !preg_match_all('/<(.*?)>/', $operation, $matches)) {
                continue;
            }

            foreach ($matches[1] as $variable) {
                $property = (new \ReflectionClass($this))->getProperty($variable);
                $property->setAccessible(true);

                if (preg_match('/=(.+)/', $operation, $desiredValueMatches)) {
                    $valStr = $desiredValueMatches[1];

                    if (preg_match_all('/{(.*?)}/', $valStr, $desVal)) {
                        foreach ($desVal[1] as $var) {
                            $valStr = str_replace($desVal[0], $this->$var, $valStr);
                        }
                    }

                    $property->setValue($this, $this->evaluateExpression($valStr));
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
        foreach ($this->stateTicks as $tick) {
            if (isset($this->states[$tick->state])) {
                $secondsSinceLastTick = time() - strtotime($tick->lastTick);
                if ($secondsSinceLastTick >= $tick->interval) {
                    $numTicks = (int) ($secondsSinceLastTick / $tick->interval);
                    for ($i = 0; $i < $numTicks; ++$i) {
                        $postTransition = new PostTransition($tick->expression);
                        $this->doPostTransition($postTransition);
                    }
                    $tick->lastTick = date('Y-m-d H:i:s');
                }
            }
        }

        foreach ($this->transitions as $transition) {
            if (isset($this->states[$transition->from]) || null === $transition->from) {
                if ($this->conditionsMet($transition->conditions)) {
                    unset($this->states[$transition->from]);

                    if (null !== $transition->to && !in_array($transition->to, $this->states)) {
                        $this->states[$transition->to] = date('Y-m-d H:i:s');
                    }

                    if ($transition->postTransition) {
                        $this->doPostTransition($transition->postTransition);
                    }
                }
            }
        }

        if ($this->states !== $stateCopy && $recurse) {
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
