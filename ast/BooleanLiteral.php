<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class BooleanLiteral extends Expression
{
    public function __construct(
        public bool $value,
        int $line,
        int $column
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return "BooleanLiteral(value: " . ($this->value ? 'true' : 'false') . ")";
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'BooleanLiteral',
            'value' => $this->value,
            'line' => $this->line,
            'column' => $this->column,
        ];
    }
}
