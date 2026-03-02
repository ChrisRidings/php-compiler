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
use PhpCompiler\AST\Assignment;
use PhpCompiler\AST\IntegerLiteral;
use PhpCompiler\AST\ReturnStatement;
use PhpCompiler\AST\BinaryOperation;
use PhpCompiler\AST\IfStatement;
use PhpCompiler\AST\ForStatement;
use PhpCompiler\AST\WhileStatement;
use PhpCompiler\AST\DoWhileStatement;
use PhpCompiler\AST\ArrayLiteral;
use PhpCompiler\AST\ArrayAccess;

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
            // Create a dummy statement to wrap the expression
            return new ReturnStatement($call, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_VARIABLE && $this->peekToken() && $this->peekToken()->type === TokenType::T_ASSIGN) {
            // Assignment statement
            return $this->parseAssignment();
        } elseif ($token->type === TokenType::T_VARIABLE && $this->peekToken() && in_array($this->peekToken()->type, [TokenType::T_PLUS_PLUS, TokenType::T_MINUS_MINUS])) {
            // Increment/decrement statement ($i++ or $i--)
            $varToken = $this->consumeToken();
            $operatorToken = $this->consumeToken();
            $variable = new VariableReference(ltrim($varToken->value, '$'), $varToken->line, $varToken->column);
            $operator = ($operatorToken->type === TokenType::T_PLUS_PLUS) ? '+=' : '-=';
            // Consume semicolon if present
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
                $this->consumeToken();
            }
            return new Assignment($variable, $operator, new IntegerLiteral(1, $operatorToken->line, $operatorToken->column), $varToken->line, $varToken->column);
        } elseif ($token->type === TokenType::T_RETURN) {
            // Return statement
            return $this->parseReturnStatement();
        } elseif ($token->type === TokenType::T_IF) {
            // If statement
            return $this->parseIfStatement();
        } elseif ($token->type === TokenType::T_FOR) {
            // For statement
            return $this->parseForStatement();
        } elseif ($token->type === TokenType::T_WHILE) {
            // While statement
            return $this->parseWhileStatement();
        } elseif ($token->type === TokenType::T_DO) {
            // Do-while statement
            return $this->parseDoWhileStatement();
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

    private function parseReturnStatement(): ReturnStatement
    {
        $returnToken = $this->consumeToken();
        $value = null;

        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_SEMICOLON) {
            $value = $this->parseExpression();
        }

        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
            $this->consumeToken();
        }

        return new ReturnStatement($value, $returnToken->line, $returnToken->column);
    }

    private function parseAssignment(bool $consumeSemicolon = true): Assignment
    {
        $varToken = $this->consumeToken();
        $variable = new VariableReference(ltrim($varToken->value, '$'), $varToken->line, $varToken->column);

        $opToken = $this->currentToken();
        $operator = '';
        if ($opToken->type === TokenType::T_ASSIGN) {
            $operator = '=';
            $this->consumeToken();
        } elseif ($opToken->type === TokenType::T_ASSIGN_PLUS) {
            $operator = '+=';
            $this->consumeToken();
        } elseif ($opToken->type === TokenType::T_ASSIGN_MINUS) {
            $operator = '-=';
            $this->consumeToken();
        } elseif ($opToken->type === TokenType::T_ASSIGN_MULTIPLY) {
            $operator = '*=';
            $this->consumeToken();
        } elseif ($opToken->type === TokenType::T_ASSIGN_DIVIDE) {
            $operator = '/=';
            $this->consumeToken();
        } else {
            throw new \RuntimeException("Expected assignment operator at line {$opToken->line}, column {$opToken->column}");
        }

        $value = $this->parseExpression(); // parse the entire expression (left to right with precedence)

        if ($consumeSemicolon && $this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
            $this->consumeToken();
        }

        return new Assignment($variable, $operator, $value, $varToken->line, $varToken->column);
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

    private function parseForStatement(): ForStatement
    {
        $forToken = $this->consumeToken(); // Consume 'for'
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        // Initialization (optional) - support multiple statements separated by commas
        $initializations = [];
        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_SEMICOLON) {
            // Parse first initialization
            $initializations[] = $this->parseAssignment(false);
            while ($this->currentToken() && $this->currentToken()->type === TokenType::T_COMMA) {
                $this->consumeToken(); // Skip comma
                if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_SEMICOLON) {
                    // Parse additional initialization statements
                    $initializations[] = $this->parseAssignment(false);
                }
            }
        }
        $this->consumeTokenOfType(TokenType::T_SEMICOLON);

        // Condition (optional)
        $condition = null;
        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_SEMICOLON) {
            $condition = $this->parseExpression();
        }
        $this->consumeTokenOfType(TokenType::T_SEMICOLON);

        // Update (optional) - support multiple statements separated by commas
        $updates = [];
        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
            // Check if it's an assignment
            if ($this->currentToken()->type === TokenType::T_VARIABLE && in_array($this->peekToken()->type, [
                TokenType::T_ASSIGN,
                TokenType::T_ASSIGN_PLUS,
                TokenType::T_ASSIGN_MINUS,
                TokenType::T_ASSIGN_MULTIPLY,
                TokenType::T_ASSIGN_DIVIDE
            ])) {
                $updates[] = $this->parseAssignment(false);
            } elseif ($this->currentToken()->type === TokenType::T_VARIABLE && in_array($this->peekToken()->type, [TokenType::T_PLUS_PLUS, TokenType::T_MINUS_MINUS])) {
                // Handle $i++ or $i-- - create an assignment with += 1 or -= 1
                $varToken = $this->consumeToken();
                $operatorToken = $this->consumeToken();
                $variable = new VariableReference(ltrim($varToken->value, '$'), $varToken->line, $varToken->column);
                $operator = ($operatorToken->type === TokenType::T_PLUS_PLUS) ? '+=' : '-=';
                $updates[] = new Assignment($variable, $operator, new IntegerLiteral(1, $operatorToken->line, $operatorToken->column), $varToken->line, $varToken->column);
            } else {
                // It's an expression, wrap it in a ReturnStatement as a dummy statement
                $updates[] = new ReturnStatement($this->parseExpression(), $this->currentToken()->line, $this->currentToken()->column);
            }
            while ($this->currentToken() && $this->currentToken()->type === TokenType::T_COMMA) {
                $this->consumeToken(); // Skip comma
                if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
                    if ($this->currentToken()->type === TokenType::T_VARIABLE && in_array($this->peekToken()->type, [
                        TokenType::T_ASSIGN,
                        TokenType::T_ASSIGN_PLUS,
                        TokenType::T_ASSIGN_MINUS,
                        TokenType::T_ASSIGN_MULTIPLY,
                        TokenType::T_ASSIGN_DIVIDE
                    ])) {
                        $updates[] = $this->parseAssignment(false);
                    } elseif ($this->currentToken()->type === TokenType::T_VARIABLE && in_array($this->peekToken()->type, [TokenType::T_PLUS_PLUS, TokenType::T_MINUS_MINUS])) {
                        $varToken = $this->consumeToken();
                        $operatorToken = $this->consumeToken();
                        $variable = new VariableReference(ltrim($varToken->value, '$'), $varToken->line, $varToken->column);
                        $operator = ($operatorToken->type === TokenType::T_PLUS_PLUS) ? '+=' : '-=';
                        $updates[] = new Assignment($variable, $operator, new IntegerLiteral(1, $operatorToken->line, $operatorToken->column), $varToken->line, $varToken->column);
                    } else {
                        // It's an expression, wrap it in a ReturnStatement as a dummy statement
                        $updates[] = new ReturnStatement($this->parseExpression(), $this->currentToken()->line, $this->currentToken()->column);
                    }
                }
            }
        }

        $this->consumeTokenOfType(TokenType::T_RPAREN);

        // Body
        $body = [];
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_LBRACE) {
            $this->consumeTokenOfType(TokenType::T_LBRACE);
            while ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACE) {
                $body[] = $this->parseStatement();
            }
            $this->consumeTokenOfType(TokenType::T_RBRACE);
        } elseif ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACE && $this->currentToken()->type !== TokenType::T_SEMICOLON) {
            // Single statement body without braces
            $body[] = $this->parseStatement();
        }

        return new ForStatement($initializations, $condition, $updates, $body, $forToken->line, $forToken->column);
    }

    private function parseWhileStatement(): WhileStatement
    {
        $whileToken = $this->consumeToken(); // Consume 'while'
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        $condition = $this->parseExpression();

        $this->consumeTokenOfType(TokenType::T_RPAREN);

        // Body
        $body = [];
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_LBRACE) {
            $this->consumeTokenOfType(TokenType::T_LBRACE);
            while ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACE) {
                $body[] = $this->parseStatement();
            }
            $this->consumeTokenOfType(TokenType::T_RBRACE);
        } elseif ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACE && $this->currentToken()->type !== TokenType::T_SEMICOLON) {
            // Single statement body without braces
            $body[] = $this->parseStatement();
        }

        return new WhileStatement($condition, $body, $whileToken->line, $whileToken->column);
    }

    private function parseDoWhileStatement(): DoWhileStatement
    {
        $doToken = $this->consumeToken(); // Consume 'do'

        // Body
        $body = [];
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_LBRACE) {
            $this->consumeTokenOfType(TokenType::T_LBRACE);
            while ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACE) {
                $body[] = $this->parseStatement();
            }
            $this->consumeTokenOfType(TokenType::T_RBRACE);
        } elseif ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACE && $this->currentToken()->type !== TokenType::T_SEMICOLON) {
            // Single statement body without braces
            $body[] = $this->parseStatement();
        }

        $this->consumeTokenOfType(TokenType::T_WHILE);
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        $condition = $this->parseExpression();

        $this->consumeTokenOfType(TokenType::T_RPAREN);
        $this->consumeTokenOfType(TokenType::T_SEMICOLON);

        return new DoWhileStatement($body, $condition, $doToken->line, $doToken->column);
    }

    private function parseIfStatement(): IfStatement
    {
        $ifToken = $this->consumeToken(); // Consume 'if'
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        $condition = $this->parseExpression();

        $this->consumeTokenOfType(TokenType::T_RPAREN);

        // Then body - can be either a single statement or a block
        $thenBody = [];
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_LBRACE) {
            // Block with braces
            $this->consumeTokenOfType(TokenType::T_LBRACE);
            while ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACE) {
                $thenBody[] = $this->parseStatement();
            }
            $this->consumeTokenOfType(TokenType::T_RBRACE);
        } else {
            // Single statement without braces
            $thenBody[] = $this->parseStatement();
        }

        $elseBody = [];
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_ELSE) {
            $this->consumeToken(); // Consume 'else'

            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_LBRACE) {
                // Block with braces
                $this->consumeTokenOfType(TokenType::T_LBRACE);
                while ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACE) {
                    $elseBody[] = $this->parseStatement();
                }
                $this->consumeTokenOfType(TokenType::T_RBRACE);
            } else {
                // Single statement without braces
                $elseBody[] = $this->parseStatement();
            }
        }

        return new IfStatement($condition, $thenBody, $elseBody, $ifToken->line, $ifToken->column);
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
        $expression = $this->parseTerm();

        while ($this->currentToken() && in_array($this->currentToken()->type, [
            TokenType::T_PLUS,
            TokenType::T_MINUS,
            TokenType::T_GREATER,
            TokenType::T_GREATER_EQUAL,
            TokenType::T_LESS,
            TokenType::T_LESS_EQUAL,
            TokenType::T_EQUAL,
            TokenType::T_NOT_EQUAL,
            TokenType::T_CONCAT
        ])) {
            $operatorToken = $this->consumeToken();
            $operator = match ($operatorToken->type) {
                TokenType::T_PLUS => BinaryOperation::OP_ADD,
                TokenType::T_MINUS => BinaryOperation::OP_SUBTRACT,
                TokenType::T_GREATER => BinaryOperation::OP_GREATER,
                TokenType::T_GREATER_EQUAL => BinaryOperation::OP_GREATER_EQUAL,
                TokenType::T_LESS => BinaryOperation::OP_LESS,
                TokenType::T_LESS_EQUAL => BinaryOperation::OP_LESS_EQUAL,
                TokenType::T_EQUAL => BinaryOperation::OP_EQUAL,
                TokenType::T_NOT_EQUAL => BinaryOperation::OP_NOT_EQUAL,
                TokenType::T_CONCAT => BinaryOperation::OP_CONCAT,
                default => throw new \RuntimeException("Unexpected operator at " . $operatorToken->line . ":" . $operatorToken->column),
            };
            $right = $this->parseTerm();
            $expression = new BinaryOperation($expression, $operator, $right, $operatorToken->line, $operatorToken->column);
        }

        return $expression;
    }

    private function parseTerm(): Expression
    {
        $term = $this->parsePrimary();

        while ($this->currentToken() && in_array($this->currentToken()->type, [TokenType::T_MULTIPLY, TokenType::T_DIVIDE])) {
            $operatorToken = $this->consumeToken();
            $operator = match ($operatorToken->type) {
                TokenType::T_MULTIPLY => BinaryOperation::OP_MULTIPLY,
                TokenType::T_DIVIDE => BinaryOperation::OP_DIVIDE,
                default => throw new \RuntimeException("Unexpected operator at " . $operatorToken->line . ":" . $operatorToken->column),
            };
            $right = $this->parsePrimary();
            $term = new BinaryOperation($term, $operator, $right, $operatorToken->line, $operatorToken->column);
        }

        return $term;
    }

    private function parsePrimary(): Expression
    {
        $token = $this->currentToken();

        if ($token === null) {
            throw new \RuntimeException("Unexpected end of input when parsing primary expression");
        }

        if ($token->type === TokenType::T_IDENTIFIER && $this->peekToken() && $this->peekToken()->type === TokenType::T_LPAREN) {
            return $this->parseFunctionCall();
        } elseif ($token->type === TokenType::T_STRING) {
            $this->consumeToken();
            // Check if it's a double-quoted string (starts with ")
            if (str_starts_with($token->value, '"')) {
                return StringLiteral::parseDoubleQuoted($token->value, $token->line, $token->column);
            }
            return StringLiteral::fromQuotedString($token->value, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_VARIABLE) {
            $this->consumeToken();
            $expression = new VariableReference(ltrim($token->value, '$'), $token->line, $token->column);
            // Handle array access: $var[expr]
            while ($this->currentToken() && $this->currentToken()->type === TokenType::T_LBRACKET) {
                $this->consumeToken(); // consume [
                $index = $this->parseExpression();
                $this->consumeTokenOfType(TokenType::T_RBRACKET);
                $expression = new ArrayAccess($expression, $index, $token->line, $token->column);
            }
            // Handle postfix increment/decrement
            if ($this->currentToken() && in_array($this->currentToken()->type, [TokenType::T_PLUS_PLUS, TokenType::T_MINUS_MINUS])) {
                $operatorToken = $this->consumeToken();
                $operator = ($operatorToken->type === TokenType::T_PLUS_PLUS) ? '++' : '--';
                // For now, treat as expression
                // We'll need to add support for these in BinaryOperation and Generator later
                // but for now, let's just return the variable since we don't handle it yet
                return $expression;
            }
            return $expression;
        } elseif ($token->type === TokenType::T_INTEGER) {
            $this->consumeToken();
            return new IntegerLiteral((int)$token->value, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_LBRACKET) {
            // Array literal [elem1, elem2, ...]
            return $this->parseArrayLiteral();
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

    private function parseArrayLiteral(): ArrayLiteral
    {
        $token = $this->consumeToken(); // Consume [
        $elements = [];

        // Parse elements until we hit ]
        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACKET) {
            // Parse first element
            $elements[] = $this->parseExpression();

            // Parse additional elements separated by commas
            while ($this->currentToken() && $this->currentToken()->type === TokenType::T_COMMA) {
                $this->consumeToken(); // Consume comma
                if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACKET) {
                    $elements[] = $this->parseExpression();
                }
            }
        }

        $this->consumeTokenOfType(TokenType::T_RBRACKET);

        return new ArrayLiteral($elements, $token->line, $token->column);
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
