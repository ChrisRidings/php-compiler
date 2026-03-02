<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class Assignment extends Statement
{
    public function __construct(
        public readonly VariableReference $variable,
        public readonly Expression $value,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return "Assignment(variable: {$this->variable}, value: {$this->value})";
    }
}
