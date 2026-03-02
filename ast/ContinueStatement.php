<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class ContinueStatement extends Statement
{
    public function __construct(
        int $line,
        int $column
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return "ContinueStatement()";
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'ContinueStatement',
            'line' => $this->line,
            'column' => $this->column,
        ];
    }
}
