<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

use PhpCompiler\AST\Expression;

class ExpressionStatement extends Statement
{
    public function __construct(
        public Expression $expression,
        int $line,
        int $column
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return $this->expression->__toString() . ";";
    }
}
