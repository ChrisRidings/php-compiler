<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class IntegerLiteral extends Expression
{
    public function __construct(
        public readonly int $value,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return "IntegerLiteral(value: {$this->value})";
    }
}
