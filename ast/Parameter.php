<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class Parameter extends Node
{
    public function __construct(
        public readonly string $name,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
