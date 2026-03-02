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
    case T_INTEGER = 17;
    case T_RETURN = 18;
    case T_PLUS = 19;
    case T_MINUS = 20;
    case T_MULTIPLY = 21;
    case T_DIVIDE = 22;
    case T_IF = 23;
    case T_ELSE = 24;
    case T_GREATER = 25;
    case T_GREATER_EQUAL = 26;
    case T_LESS = 27;
    case T_LESS_EQUAL = 28;
    case T_EQUAL = 29;
    case T_NOT_EQUAL = 30;
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
