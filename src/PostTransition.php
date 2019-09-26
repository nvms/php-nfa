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

class PostTransition
{
    /** @var []string list of post-transition operations to perform */
    public $operations;

    public function __construct(...$operations)
    {
        $this->operations = $operations;
    }
}
