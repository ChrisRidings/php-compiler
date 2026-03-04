<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class BreakStatement extends Statement
{
    public function __construct(
        int $line,
        int $column
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return "BreakStatement()";
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'BreakStatement',
            'line' => $this->line,
            'column' => $this->column,
        ];
    }
}
