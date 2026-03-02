<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class BinaryOperation extends Expression
{
    public const OP_ADD = '+';
    public const OP_SUBTRACT = '-';
    public const OP_MULTIPLY = '*';
    public const OP_DIVIDE = '/';

    public function __construct(
        public readonly Expression $left,
        public readonly string $operator,
        public readonly Expression $right,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return sprintf("BinaryOperation(left: %s, operator: '%s', right: %s)", $this->left, $this->operator, $this->right);
    }
}
