<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

abstract class Node
{
    public function __construct(
        public readonly int $line = 1,
        public readonly int $column = 1
    ) {
    }

    abstract public function __toString(): string;
}
