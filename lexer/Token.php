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
    case T_FOR = 31;
    case T_CONCAT = 38;
    case T_PLUS_PLUS = 32;
    case T_MINUS_MINUS = 33;
    case T_ASSIGN_PLUS = 34;
    case T_ASSIGN_MINUS = 35;
    case T_ASSIGN_MULTIPLY = 36;
    case T_ASSIGN_DIVIDE = 37;
    case T_COMMA = 39;
    case T_CLOSE_TAG = 40;
    case T_COMMENT = 41;
    case T_WHILE = 42;
    case T_DO = 43;
    case T_LBRACKET = 44;
    case T_RBRACKET = 45;
    case T_DOUBLE_ARROW = 46;
    case T_FOREACH = 47;
    case T_AS = 48;
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
