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
    case T_FUNCTION = 6;
    case T_IDENTIFIER = 7;
    case T_LPAREN = 8;
    case T_RPAREN = 9;
    case T_LBRACE = 10;
    case T_RBRACE = 11;
    case T_FUNCTION_CALL = 12;
    case T_NEWLINE = 13;
    case T_VARIABLE = 14;
    case T_DOLLAR = 15;
    case T_ASSIGN = 16;
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
