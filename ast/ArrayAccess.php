<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class ArrayAccess extends Expression
{
    public function __construct(
        public readonly Expression $array,
        public readonly ?Expression $index,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        if ($this->index === null) {
            return "{$this->array}[]";
        }
        return "{$this->array}[{$this->index}]";
    }
}
