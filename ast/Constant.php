<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class Constant extends Expression
{
    public function __construct(
        public string $name,
        int $line = 0,
        int $column = 0
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
