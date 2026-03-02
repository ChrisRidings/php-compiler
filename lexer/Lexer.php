<?php

declare(strict_types=1);

namespace PhpCompiler\Lexer;

class Lexer
{
    private string $source;
    private int $position = 0;
    private int $line = 1;
    private int $column = 1;

    private const PATTERNS = [
        TokenType::T_OPEN_TAG->value => '/^<\?php/i',
        TokenType::T_CLOSE_TAG->value => '/^\?>/',
        // Single-line comments (// and #)
        TokenType::T_COMMENT->value => '/^(?:\/\/|#).*/',
        TokenType::T_ECHO->value => '/^echo\b/i',
        TokenType::T_RETURN->value => '/^return\b/i',
        TokenType::T_FUNCTION->value => '/^function\b/i',
        TokenType::T_IF->value => '/^if\b/i',
        TokenType::T_ELSE->value => '/^else\b/i',
        TokenType::T_FOR->value => '/^for\b/i',
        TokenType::T_WHILE->value => '/^while\b/i',
        TokenType::T_DO->value => '/^do\b/i',
        TokenType::T_FOREACH->value => '/^foreach\b/i',
        TokenType::T_AS->value => '/^as\b/i',
        TokenType::T_DECLARE->value => '/^declare\b/i',
        TokenType::T_TRUE->value => '/^true\b/i',
        TokenType::T_FALSE->value => '/^false\b/i',
        TokenType::T_CONTINUE->value => '/^continue\b/i',
        // String literals - simple pattern that matches anything between quotes
        // Double-quoted strings can have escaped quotes, single-quoted treat \' as escape
        TokenType::T_STRING->value => '/^(?:"[^"]*"|\'[^\']*\')/',
        TokenType::T_INTEGER->value => '/^\d+/',
        TokenType::T_VARIABLE->value => '/^\$[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*/',
        TokenType::T_IDENTIFIER->value => '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*/',
        TokenType::T_LPAREN->value => '/^\(/',
        TokenType::T_RPAREN->value => '/^\)/',
        TokenType::T_LBRACE->value => '/^\{/',
        TokenType::T_RBRACE->value => '/^\}/',
        TokenType::T_LBRACKET->value => '/^\[/',
        TokenType::T_RBRACKET->value => '/^\]/',
        TokenType::T_DOUBLE_ARROW->value => '/^=>/',
        TokenType::T_ASSIGN->value => '/^=/',
        TokenType::T_GREATER_EQUAL->value => '/^>=/',
        TokenType::T_GREATER->value => '/^>/',
        TokenType::T_LESS_EQUAL->value => '/^<=/',
        TokenType::T_LESS->value => '/^</',
        TokenType::T_IDENTICAL->value => '/^===/',
        TokenType::T_NOT_IDENTICAL->value => '/^!==/',
        TokenType::T_EQUAL->value => '/^==/',
        TokenType::T_NOT_EQUAL->value => '/^!=/',
        TokenType::T_NOT->value => '/^!/',
        TokenType::T_PLUS_PLUS->value => '/^\+\+/',
        TokenType::T_MINUS_MINUS->value => '/^--/',
        TokenType::T_ASSIGN_PLUS->value => '/^\+=/',
        TokenType::T_ASSIGN_MINUS->value => '/^-=/',
        TokenType::T_ASSIGN_MULTIPLY->value => '/^\*=/',
        TokenType::T_ASSIGN_DIVIDE->value => '/^\/=/',
        TokenType::T_CONCAT->value => '/^\./',
        TokenType::T_COMMA->value => '/^,/',
        TokenType::T_PLUS->value => '/^\+/',
        TokenType::T_MINUS->value => '/^-/',
        TokenType::T_MULTIPLY->value => '/^\*/',
        TokenType::T_DIVIDE->value => '/^\//',
        TokenType::T_SEMICOLON->value => '/^;/',
        TokenType::T_WHITESPACE->value => '/^\s+/',
    ];

    public function __construct(string $source)
    {
        $this->source = $source;
    }

    public static function fromFile(string $filename): self
    {
        $content = file_get_contents($filename);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: $filename");
        }
        return new self($content);
    }

    /**
     * @return Token[]
     */
    public function tokenize(): array
    {
        $tokens = [];

        while ($this->position < strlen($this->source)) {
            $token = $this->nextToken();
        if ($token !== null) {
            // Skip whitespace, open/close tags, and comments
            if ($token->type !== TokenType::T_WHITESPACE &&
                $token->type !== TokenType::T_OPEN_TAG &&
                $token->type !== TokenType::T_CLOSE_TAG &&
                $token->type !== TokenType::T_COMMENT) {
                $tokens[] = $token;
            }
            }
        }

        return $tokens;
    }

    private function nextToken(): ?Token
    {
        if ($this->position >= strlen($this->source)) {
            return null;
        }

        foreach (self::PATTERNS as $type => $pattern) {
            if (preg_match($pattern, substr($this->source, $this->position), $matches)) {
                $value = $matches[0];

                // Ensure we have TokenType instance
                $tokenType = $type instanceof TokenType ? $type : TokenType::from($type);

                $token = new Token($tokenType, $value, $this->line, $this->column);

                $this->updatePosition($value);

                return $token;
            }
        }

        // Provide better error information
        $currentChar = $this->source[$this->position];
        $context = $this->getContext($this->position, 10);

        throw new \RuntimeException(
            sprintf(
                "Lexer Error: Unexpected character '%s' (ASCII: %d) at line %d, column %d\n" .
                "Context: %s\n" .
                "         %s",
                $currentChar,
                ord($currentChar),
                $this->line,
                $this->column,
                $context,
                str_repeat(' ', $this->column - 1) . '^'
            )
        );
    }

    private function getContext(int $pos, int $length): string
    {
        $start = max(0, $pos);
        $end = min($pos + $length, strlen($this->source));
        $context = substr($this->source, $start, $end - $start);

        // Replace non-printable characters
        return preg_replace('/[\x00-\x1F\x7F]/', '.', $context);
    }

    private function updatePosition(string $value): void
    {
        $lines = substr_count($value, "\n");
        $this->line += $lines;

        if ($lines > 0) {
            $lastNewline = strrpos($value, "\n");
            $this->column = strlen($value) - $lastNewline;
        } else {
            $this->column += strlen($value);
        }

        $this->position += strlen($value);
    }
}
