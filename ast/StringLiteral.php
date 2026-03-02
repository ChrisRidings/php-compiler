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

        // Process escape sequences
        $value = preg_replace_callback('/\\\\([ntrbf"\\\'\\\\])/', function($matches) {
            $escaped = $matches[1];
            switch ($escaped) {
                case 'n': return "\n";
                case 't': return "\t";
                case 'r': return "\r";
                case 'b': return "\b";
                case 'f': return "\f";
                case '"': return '"';
                case '\'': return '\'';
                case '\\': return '\\';
                default: return $matches[0];
            }
        }, $value);

        return new self($value, $line, $column);
    }

    public function __toString(): string
    {
        return sprintf('StringLiteral(value: "%s")', $this->value);
    }
}
