<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class ArrayLiteral extends Expression
{
    /**
     * @param Expression[] $elements - Array of expressions for numeric arrays
     */
    public function __construct(
        public readonly array $elements,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        $elementsStr = implode(', ', array_map(fn($el) => (string)$el, $this->elements));
        return "[{$elementsStr}]";
    }
}
