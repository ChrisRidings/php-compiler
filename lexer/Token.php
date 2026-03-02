<?php

declare(strict_types=1);

namespace PhpCompiler\Lexer;

enum TokenType: int
{
    case T_OPEN_TAG = 1;
    case T_ECHO = 2;
    case T_STRING = 3;
    case T_SEMICOLON = 4;
    case T_WHITESPACE = 5;
}

class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $line = 1,
        public readonly int $column = 1
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            "[%s] '%s' (line: %d, column: %d)",
            $this->type->name,
            $this->value,
            $this->line,
            $this->column
        );
    }
}
