<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class ArrayLiteral extends Expression
{
    /**
     * @param Expression[] $elements - Array of expressions for numeric arrays
     * @param array<int, Expression|null> $keys - Optional keys for associative arrays (null for numeric indices)
     */
    public function __construct(
        public readonly array $elements,
        public readonly array $keys = [],
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    /**
     * Check if this is an associative array
     */
    public function isAssociative(): bool
    {
        foreach ($this->keys as $key) {
            if ($key !== null) {
                return true;
            }
        }
        return false;
    }

    public function __toString(): string
    {
        $parts = [];
        foreach ($this->elements as $i => $element) {
            if (isset($this->keys[$i]) && $this->keys[$i] !== null) {
                $parts[] = $this->keys[$i] . ' => ' . $element;
            } else {
                $parts[] = (string)$element;
            }
        }
        return '[' . implode(', ', $parts) . ']';
    }
}
