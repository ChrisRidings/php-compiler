<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class ReturnStatement extends Statement
{
    public function __construct(
        public readonly ?Expression $value,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        if ($this->value === null) {
            return "ReturnStatement()";
        }
        return "ReturnStatement(value: {$this->value})";
    }
}
