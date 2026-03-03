<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class TernaryExpression extends Expression
{
    public function __construct(
        public readonly Expression $condition,
        public readonly ?Expression $ifTrue,  // null for Elvis operator ?:
        public readonly Expression $ifFalse,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        if ($this->ifTrue !== null) {
            return sprintf("(%s ? %s : %s)", $this->condition, $this->ifTrue, $this->ifFalse);
        } else {
            return sprintf("(%s ?: %s)", $this->condition, $this->ifFalse);
        }
    }
}
