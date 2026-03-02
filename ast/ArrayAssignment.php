<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

/**
 * Represents an assignment to an array element: $arr[index] = value
 */
class ArrayAssignment extends Statement
{
    public function __construct(
        public readonly ArrayAccess $arrayAccess,
        public readonly string $operator,
        public readonly Expression $value,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return "ArrayAssignment(arrayAccess: {$this->arrayAccess}, operator: {$this->operator}, value: {$this->value})";
    }
}
