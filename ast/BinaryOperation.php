<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class BinaryOperation extends Expression
{
    public const OP_ADD = '+';
    public const OP_SUBTRACT = '-';
    public const OP_MULTIPLY = '*';
    public const OP_DIVIDE = '/';
    public const OP_GREATER = '>';
    public const OP_GREATER_EQUAL = '>=';
    public const OP_LESS = '<';
    public const OP_LESS_EQUAL = '<=';
    public const OP_EQUAL = '==';
    public const OP_NOT_EQUAL = '!=';
    public const OP_IDENTICAL = '===';
    public const OP_NOT_IDENTICAL = '!==';
    public const OP_CONCAT = '.';
    public const OP_MOD = '%';

    public function __construct(
        public Expression $left,
        public readonly string $operator,
        public Expression $right,
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
