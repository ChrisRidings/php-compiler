<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

use PhpCompiler\Lexer\Lexer;
use PhpCompiler\Lexer\Token;
use PhpCompiler\Lexer\TokenType;
use PhpCompiler\AST\FunctionDefinition;
use PhpCompiler\AST\FunctionCall;
use PhpCompiler\AST\Parameter;
use PhpCompiler\AST\VariableReference;

class Parser
{
    /**
     * @var Token[]
     */
    private array $tokens;
    private int $position = 0;

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public static function fromFile(string $filename): self
    {
        $lexer = Lexer::fromFile($filename);
        $tokens = $lexer->tokenize();
        return new self($tokens);
    }

    /**
     * @return Statement[]
     */
    public function parse(): array
    {
        $statements = [];

        while ($this->currentToken() !== null) {
            $statements[] = $this->parseStatement();
        }

        return $statements;
    }

    private function parseStatement(): Statement
    {
        $token = $this->currentToken();

        if ($token === null) {
            throw new \RuntimeException("Parser Error: Unexpected end of input");
        }

        if ($token->type === TokenType::T_ECHO) {
            return $this->parseEchoStatement();
        } elseif ($token->type === TokenType::T_FUNCTION) {
            return $this->parseFunctionDefinition();
        } elseif ($token->type === TokenType::T_IDENTIFIER && $this->peekToken() && $this->peekToken()->type === TokenType::T_LPAREN) {
            // Function call as statement
            $call = $this->parseFunctionCall();
            // Check for semicolon
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
                $this->consumeToken();
            }
            return $call; // We'll treat function calls as statements for now
        }

        // Provide better error information
        $context = $this->getContext($token);

        throw new \RuntimeException(
            sprintf(
                "Parser Error: Unexpected token '%s' at line %d, column %d\n" .
                "Token value: '%s'\n" .
                "Context: %s",
                $token->type->name,
                $token->line,
                $token->column,
                $token->value,
                $context
            )
        );
    }

    private function parseFunctionDefinition(): FunctionDefinition
    {
        $functionToken = $this->consumeToken(); // Consume 'function'

        $nameToken = $this->consumeTokenOfType(TokenType::T_IDENTIFIER);
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        $parameters = $this->parseParameters();

        $this->consumeTokenOfType(TokenType::T_RPAREN);
        $this->consumeTokenOfType(TokenType::T_LBRACE);

        $body = $this->parseFunctionBody();

        $this->consumeTokenOfType(TokenType::T_RBRACE);

        return new FunctionDefinition(
            $nameToken->value,
            $parameters,
            $body,
            $functionToken->line,
            $functionToken->column
        );
    }

    private function parseParameters(): array
    {
        $parameters = [];

        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
            // Parse first parameter
            if ($this->currentToken()->type === TokenType::T_VARIABLE) {
                $paramToken = $this->consumeToken();
                $parameters[] = new Parameter(ltrim($paramToken->value, '$'), $paramToken->line, $paramToken->column);
            }

            // Parse additional parameters
            while ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
                // We'll skip any other token types for now (like type hints or commas)
                $this->consumeToken();
            }
        }

        return $parameters;
    }

    private function parseFunctionBody(): array
    {
        $body = [];

        while ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACE) {
            $body[] = $this->parseStatement();
        }

        return $body;
    }

    private function parseFunctionCall(): FunctionCall
    {
        $nameToken = $this->consumeTokenOfType(TokenType::T_IDENTIFIER);
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        $arguments = $this->parseArguments();

        $this->consumeTokenOfType(TokenType::T_RPAREN);

        return new FunctionCall(
            $nameToken->value,
            $arguments,
            $nameToken->line,
            $nameToken->column
        );
    }

    private function parseArguments(): array
    {
        $arguments = [];

        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
            // Parse first argument
            $arguments[] = $this->parseExpression();

            // Parse additional arguments
            while ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
                // Skip any other token types for now
                $this->consumeToken();
            }
        }

        return $arguments;
    }

    private function getContext(Token $token): string
    {
        $context = [];
        $pos = $this->position;

        // Get up to 3 previous tokens
        for ($i = max(0, $pos - 3); $i < $pos; $i++) {
            $context[] = $this->tokens[$i]->type->name;
        }

        $context[] = '[ERROR]';

        // Get up to 3 next tokens
        for ($i = $pos + 1; $i < min($pos + 4, count($this->tokens)); $i++) {
            $context[] = $this->tokens[$i]->type->name;
        }

        return implode(' → ', $context);
    }

    private function parseEchoStatement(): EchoStatement
    {
        $token = $this->consumeToken(); // Consume 'echo'

        $expressions = [];
        $expressions[] = $this->parseExpression();

        $this->consumeTokenOfType(TokenType::T_SEMICOLON);

        return new EchoStatement($expressions, $token->line, $token->column);
    }

    private function parseExpression(): Expression
    {
        $token = $this->currentToken();

        if ($token === null) {
            throw new \RuntimeException("Unexpected end of input when parsing expression");
        }

        if ($token->type === TokenType::T_STRING) {
            $this->consumeToken();
            return StringLiteral::fromQuotedString($token->value, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_VARIABLE) {
            $this->consumeToken();
            return new VariableReference(ltrim($token->value, '$'), $token->line, $token->column);
        }

        throw new \RuntimeException(
            sprintf(
                "Unexpected token '%s' at line %d, column %d",
                $token->type->name,
                $token->line,
                $token->column
            )
        );
    }

    private function consumeTokenOfType(TokenType $type): Token
    {
        $token = $this->currentToken();

        if ($token === null) {
            throw new \RuntimeException("Unexpected end of input");
        }

        if ($token->type !== $type) {
            throw new \RuntimeException(
                sprintf(
                    "Expected token '%s' but got '%s' at line %d, column %d",
                    $type->name,
                    $token->type->name,
                    $token->line,
                    $token->column
                )
            );
        }

        return $this->consumeToken();
    }

    private function consumeToken(): Token
    {
        $token = $this->currentToken();
        if ($token === null) {
            throw new \RuntimeException("Unexpected end of input");
        }
        $this->position++;
        return $token;
    }

    private function currentToken(): ?Token
    {
        return $this->position < count($this->tokens) ? $this->tokens[$this->position] : null;
    }

    private function peekToken(int $offset = 1): ?Token
    {
        $pos = $this->position + $offset;
        return $pos < count($this->tokens) ? $this->tokens[$pos] : null;
    }
}
