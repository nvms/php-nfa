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

class Condition
{
    public $conditions;

    public function __construct()
    {
        $conditions = \func_get_args();
        $this->conditions = $conditions;

        return $this->conditions;
    }
}
