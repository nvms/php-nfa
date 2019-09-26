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

class StateTick
{
    public $state;
    public $expression;
    public $interval;
    public $lastTick;

    public function __construct(int $state, string $expression, int $interval)
    {
        $this->state = $state;
        $this->expression = $expression;
        $this->interval = $interval;
        $this->lastTick = date('Y-m-d H:i:s');
    }
}
