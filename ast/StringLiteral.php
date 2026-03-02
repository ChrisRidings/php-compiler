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
        // Extract value between quotes
        $trimmed = trim($quotedValue, '\'"');

        // Find the actual end of the string (before any semicolon or other characters)
        $value = $trimmed;
        $semicolonPos = strpos($value, ';');
        if ($semicolonPos !== false) {
            $value = trim(substr($value, 0, $semicolonPos));
        }

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

    /**
     * Parse a double-quoted string and return an expression tree with variable interpolations.
     * Returns a BinaryOperation tree if variables are found, or a simple StringLiteral otherwise.
     */
    public static function parseDoubleQuoted(string $quotedValue, int $line = 1, int $column = 1): Expression
    {
        // Extract value between quotes
        $trimmed = trim($quotedValue, '"');

        // Find the actual end of the string (before any semicolon or other characters)
        $value = $trimmed;
        $semicolonPos = strpos($value, ';');
        if ($semicolonPos !== false) {
            $value = trim(substr($value, 0, $semicolonPos));
        }

        // Process escape sequences first
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

        // Check for variable interpolation: $varname
        // Split the string by variable references
        $parts = preg_split('/(\$[a-zA-Z_][a-zA-Z0-9_]*)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        // If no variables found, return simple string literal
        if (count($parts) === 1) {
            return new self($value, $line, $column);
        }

        // Build expression tree from parts
        $expressions = [];
        foreach ($parts as $i => $part) {
            if (empty($part)) {
                continue;
            }

            if (preg_match('/^\$([a-zA-Z_][a-zA-Z0-9_]*)$/', $part, $matches)) {
                // This is a variable reference
                $expressions[] = new VariableReference($matches[1], $line, $column);
            } else {
                // This is a literal string part
                $expressions[] = new self($part, $line, $column);
            }
        }

        // If only one expression, return it directly
        if (count($expressions) === 1) {
            return $expressions[0];
        }

        // Build binary operations for concatenation
        $result = array_shift($expressions);
        foreach ($expressions as $expr) {
            $result = new BinaryOperation($result, BinaryOperation::OP_CONCAT, $expr, $line, $column);
        }

        return $result;
    }

    public function __toString(): string
    {
        return sprintf('StringLiteral(value: "%s")', $this->value);
    }
}
