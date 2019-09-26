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

class Transition
{
    public $from;
    public $to;
    public $conditions;
    public $postTransition;
    public $writer;

    public function __construct($from = null, $to = null, ...$extras)
    {
        if ($from) {
            $this->from = $from;
        }

        if ($to) {
            $this->to = $to;
        }

        if ($extras) {
            foreach ($extras as $extra) {
                if ($extra instanceof Condition) {
                    $this->conditions = $extra;
                }

                if ($extra instanceof PostTransition) {
                    $this->postTransition = $extra;
                }
            }
        }
    }
}
