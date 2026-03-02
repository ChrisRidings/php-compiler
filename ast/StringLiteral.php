<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class StringLiteral extends Expression
{
    public function __construct(
        public readonly string $value,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public static function fromQuotedString(string $quotedValue, int $line = 1, int $column = 1): self
    {
        $value = trim($quotedValue, '\'"');
        return new self($value, $line, $column);
    }

    public function __toString(): string
    {
        return sprintf('StringLiteral(value: "%s")', $this->value);
    }
}
