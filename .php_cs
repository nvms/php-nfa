<?php

$header = <<<'EOF'
┌----------------------------------------------------------------------┐
| This file is part of php-nfa (https://github.com/nvms/php-nfa)       |
├----------------------------------------------------------------------┤
| Copyright (c) nvms (https://github.com/nvms/php-nfa)                 |
| Licensed under the MIT License (https://opensource.org/licenses/MIT) |
└----------------------------------------------------------------------┘
EOF;

$finder = PhpCsFixer\Finder::create()
    ->exclude(['build', 'vendor'])
    ->in(__DIR__);

return PhpCsFixer\Config::create()
    ->setFinder($finder)
    ->setUsingCache(true)
    ->setRules([
        '@Symfony' => true,
        'header_comment' => ['header' => $header],
        'phpdoc_align' => false,
        'phpdoc_order' => true,
        'ordered_imports' => true,
        'array_syntax' => ['syntax' => 'short'],
    ]);
