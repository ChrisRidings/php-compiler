<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class NullLiteral extends Expression
{
    public function __construct(
        int $line = 0,
        int $column = 0
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return "NullLiteral()";
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'NullLiteral',
            'line' => $this->line,
            'column' => $this->column,
        ];
    }
}
