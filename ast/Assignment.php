<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class Assignment extends Statement
{
    public function __construct(
        public VariableReference $variable,
        public readonly string $operator,
        public readonly Expression $value,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return "Assignment(variable: {$this->variable}, operator: {$this->operator}, value: {$this->value})";
    }
}
