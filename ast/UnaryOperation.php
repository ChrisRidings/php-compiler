<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class UnaryOperation extends Expression
{
    public const OP_NOT = '!';

    public function __construct(
        public string $operator,
        public Expression $operand,
        int $line,
        int $column
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return "UnaryOperation(operator: {$this->operator}, operand: {$this->operand})";
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'UnaryOperation',
            'operator' => $this->operator,
            'operand' => $this->operand,
            'line' => $this->line,
            'column' => $this->column,
        ];
    }
}
