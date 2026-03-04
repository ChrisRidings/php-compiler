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
use PhpCompiler\AST\BooleanLiteral;
use PhpCompiler\AST\NullLiteral;
use PhpCompiler\AST\UnaryOperation;
use PhpCompiler\AST\ReturnStatement;
use PhpCompiler\AST\ContinueStatement;
use PhpCompiler\AST\BreakStatement;
use PhpCompiler\AST\BinaryOperation;
use PhpCompiler\AST\IfStatement;
use PhpCompiler\AST\ForStatement;
use PhpCompiler\AST\WhileStatement;
use PhpCompiler\AST\DoWhileStatement;
use PhpCompiler\AST\ArrayLiteral;
use PhpCompiler\AST\ArrayAccess;
use PhpCompiler\AST\ArrayAssignment;
use PhpCompiler\AST\ForeachStatement;
use PhpCompiler\AST\ExpressionStatement;
use PhpCompiler\AST\Constant;
use PhpCompiler\AST\ClassDefinition;
use PhpCompiler\AST\PropertyDeclaration;
use PhpCompiler\AST\NewExpression;
use PhpCompiler\AST\PropertyAccess;
use PhpCompiler\AST\MethodDefinition;
use PhpCompiler\AST\MethodCall;
use PhpCompiler\AST\CastExpression;
use PhpCompiler\AST\TernaryExpression;
use PhpCompiler\AST\VariableFunctionCall;

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
            // Create an expression statement to wrap the function call
            return new ExpressionStatement($call, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_VARIABLE && $this->peekToken() && $this->peekToken()->type === TokenType::T_ASSIGN) {
            // Assignment statement - wrap the expression in an ExpressionStatement
            $assignment = $this->parseAssignment();
            // Consume semicolon if present
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
                $this->consumeToken();
            }
            return new ExpressionStatement($assignment, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_VARIABLE && $this->peekToken() && $this->peekToken()->type === TokenType::T_LBRACKET) {
            // Array access assignment: $var[index] = value
            // We need to check if there's an assignment after the array access
            return $this->parseArrayAssignment();
        } elseif ($token->type === TokenType::T_VARIABLE && $this->peekToken() && in_array($this->peekToken()->type, [TokenType::T_PLUS_PLUS, TokenType::T_MINUS_MINUS])) {
            // Increment/decrement statement ($i++ or $i--)
            $varToken = $this->consumeToken();
            $operatorToken = $this->consumeToken();
            $variable = new VariableReference(ltrim($varToken->value, '$'), $varToken->line, $varToken->column);
            $operator = ($operatorToken->type === TokenType::T_PLUS_PLUS) ? '+=' : '-=';
            $assignment = new Assignment($variable, $operator, new IntegerLiteral(1, $operatorToken->line, $operatorToken->column), $varToken->line, $varToken->column);
            // Consume semicolon if present
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
                $this->consumeToken();
            }
            return new ExpressionStatement($assignment, $varToken->line, $varToken->column);
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
        } elseif ($token->type === TokenType::T_FOREACH) {
            // Foreach statement
            return $this->parseForeachStatement();
        } elseif ($token->type === TokenType::T_DECLARE) {
            // Declare statement - PHP runtime directive, treat as no-op
            return $this->parseDeclareStatement();
        } elseif ($token->type === TokenType::T_CONTINUE) {
            // Continue statement
            $continueToken = $this->consumeToken();
            // Consume semicolon if present
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
                $this->consumeToken();
            }
            return new ContinueStatement($continueToken->line, $continueToken->column);
        } elseif ($token->type === TokenType::T_BREAK) {
            // Break statement
            $breakToken = $this->consumeToken();
            // Consume semicolon if present
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
                $this->consumeToken();
            }
            return new BreakStatement($breakToken->line, $breakToken->column);
        } elseif ($token->type === TokenType::T_CLASS) {
            // Class definition
            return $this->parseClassDefinition();
        } elseif ($token->type === TokenType::T_UNSET) {
            // unset() statement
            return $this->parseUnsetStatement();
        } elseif ($token->type === TokenType::T_SETTYPE) {
            // settype() statement
            $settypeExpr = $this->parseSettypeExpression();
            // Consume semicolon if present
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
                $this->consumeToken();
            }
            return new ExpressionStatement($settypeExpr, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_VARIABLE && $this->peekToken() && $this->peekToken()->type === TokenType::T_LPAREN) {
            // Variable function call as statement: $functionName()
            $varFuncCall = $this->parsePrimary();
            // Check for semicolon
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
                $this->consumeToken();
            }
            // Create an expression statement to wrap the variable function call
            return new ExpressionStatement($varFuncCall, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_VARIABLE && $this->peekToken() && $this->peekToken()->type === TokenType::T_OBJECT_OPERATOR) {
            // Property access: $obj->property (potentially with assignment)
            return $this->parsePropertyAccessStatement();
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

        // Parse optional return type declaration (e.g., ": int")
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_COLON) {
            $this->consumeToken(); // Consume :
            // Skip the return type identifier (e.g., int, string, bool, void)
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_IDENTIFIER) {
                $this->consumeToken(); // Consume return type
            }
        }

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

    private function parseClassDefinition(): ClassDefinition
    {
        $classToken = $this->consumeToken(); // Consume 'class'

        $nameToken = $this->consumeTokenOfType(TokenType::T_IDENTIFIER);
        $this->consumeTokenOfType(TokenType::T_LBRACE);

        $properties = [];
        $methods = [];

        // Parse class body (properties and methods)
        while ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACE) {
            $token = $this->currentToken();

            // Check if it's a method (visibility + function)
            if ($token && in_array($token->type, [TokenType::T_PUBLIC, TokenType::T_PRIVATE, TokenType::T_PROTECTED])) {
                // Peek ahead to see if it's a function
                $peekToken = $this->peekToken();
                if ($peekToken && $peekToken->type === TokenType::T_FUNCTION) {
                    $methods[] = $this->parseMethodDefinition();
                } else {
                    // It's a property
                    $properties[] = $this->parsePropertyDeclaration();
                }
            } elseif ($token && $token->type === TokenType::T_FUNCTION) {
                // Method without visibility (treat as public)
                $methods[] = $this->parseMethodDefinition();
            } elseif ($token && $token->type === TokenType::T_VAR) {
                // Property with var keyword
                $properties[] = $this->parsePropertyDeclaration();
            } else {
                // Assume it's a property declaration
                $properties[] = $this->parsePropertyDeclaration();
            }
        }

        $this->consumeTokenOfType(TokenType::T_RBRACE);

        return new ClassDefinition(
            $nameToken->value,
            $properties,
            $methods,
            $classToken->line,
            $classToken->column
        );
    }

    private function parseMethodDefinition(): MethodDefinition
    {
        $token = $this->currentToken();
        $visibility = 'public';

        // Parse visibility modifier (optional)
        if ($token && in_array($token->type, [TokenType::T_PUBLIC, TokenType::T_PRIVATE, TokenType::T_PROTECTED])) {
            if ($token->type === TokenType::T_PUBLIC) {
                $visibility = 'public';
            } elseif ($token->type === TokenType::T_PRIVATE) {
                $visibility = 'private';
            } elseif ($token->type === TokenType::T_PROTECTED) {
                $visibility = 'protected';
            }
            $this->consumeToken();
            $token = $this->currentToken();
        }

        // Consume 'function' keyword
        $functionToken = $this->consumeTokenOfType(TokenType::T_FUNCTION);

        $nameToken = $this->consumeTokenOfType(TokenType::T_IDENTIFIER);
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        $parameters = $this->parseParameters();

        $this->consumeTokenOfType(TokenType::T_RPAREN);

        // Parse optional return type declaration (e.g., ": int")
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_COLON) {
            $this->consumeToken(); // Consume :
            // Skip the return type identifier (e.g., int, string, bool, void)
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_IDENTIFIER) {
                $this->consumeToken(); // Consume return type
            }
        }

        $this->consumeTokenOfType(TokenType::T_LBRACE);

        $body = $this->parseFunctionBody();

        $this->consumeTokenOfType(TokenType::T_RBRACE);

        return new MethodDefinition(
            $nameToken->value,
            $parameters,
            $body,
            $visibility,
            false, // isStatic - not implemented yet
            $functionToken->line,
            $functionToken->column
        );
    }

    private function parsePropertyDeclaration(): PropertyDeclaration
    {
        $token = $this->currentToken();

        // Parse visibility modifier
        $visibility = 'public'; // default
        if ($token && in_array($token->type, [TokenType::T_PUBLIC, TokenType::T_PRIVATE, TokenType::T_PROTECTED, TokenType::T_VAR])) {
            if ($token->type === TokenType::T_PUBLIC) {
                $visibility = 'public';
            } elseif ($token->type === TokenType::T_PRIVATE) {
                $visibility = 'private';
            } elseif ($token->type === TokenType::T_PROTECTED) {
                $visibility = 'protected';
            }
            // T_VAR is treated as public
            $this->consumeToken();
            $token = $this->currentToken();
        }

        // Skip optional type hint (e.g., int, string, bool)
        if ($token && $token->type === TokenType::T_IDENTIFIER) {
            $this->consumeToken(); // Skip type hint
            $token = $this->currentToken();
        }

        // Parse variable name
        if (!$token || $token->type !== TokenType::T_VARIABLE) {
            $line = $token ? $token->line : 'unknown';
            $column = $token ? $token->column : 'unknown';
            throw new \RuntimeException("Expected property name at line {$line}, column {$column}");
        }
        $varToken = $this->consumeToken();
        $propertyName = ltrim($varToken->value, '$');

        // Check for default value
        $defaultValue = null;
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_ASSIGN) {
            $this->consumeToken(); // Consume =
            $defaultValue = $this->parseExpression();
        }

        // Consume semicolon
        $this->consumeTokenOfType(TokenType::T_SEMICOLON);

        return new PropertyDeclaration(
            $visibility,
            $propertyName,
            $defaultValue,
            $varToken->line,
            $varToken->column
        );
    }

    private function parsePropertyAccessStatement(): Statement
    {
        // Parse the property access expression (e.g., $obj->property)
        $propAccess = $this->parsePrimary();

        // Check if this is an assignment
        if ($this->currentToken() && in_array($this->currentToken()->type, [
            TokenType::T_ASSIGN,
            TokenType::T_ASSIGN_PLUS,
            TokenType::T_ASSIGN_MINUS,
            TokenType::T_ASSIGN_MULTIPLY,
            TokenType::T_ASSIGN_DIVIDE
        ])) {
            // It's an assignment to a property
            $opToken = $this->consumeToken();
            $operator = match ($opToken->type) {
                TokenType::T_ASSIGN => '=',
                TokenType::T_ASSIGN_PLUS => '+=',
                TokenType::T_ASSIGN_MINUS => '-=',
                TokenType::T_ASSIGN_MULTIPLY => '*=',
                TokenType::T_ASSIGN_DIVIDE => '/=',
                default => '=',
            };

            $value = $this->parseExpression();

            // Consume semicolon if present
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
                $this->consumeToken();
            }

            return new ExpressionStatement(
                new Assignment($propAccess, $operator, $value, $propAccess->line, $propAccess->column),
                $propAccess->line,
                $propAccess->column
            );
        }

        // It's just a property access expression (no assignment)
        // Consume semicolon if present
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
            $this->consumeToken();
        }

        return new ExpressionStatement($propAccess, $propAccess->line, $propAccess->column);
    }

    private function parseParameters(): array
    {
        $parameters = [];

        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
            // Parse first parameter (may have type hint)
            $param = $this->parseSingleParameter();
            if ($param !== null) {
                $parameters[] = $param;
            }

            // Parse additional parameters separated by commas
            while ($this->currentToken() && $this->currentToken()->type === TokenType::T_COMMA) {
                $this->consumeToken(); // Consume comma
                $param = $this->parseSingleParameter();
                if ($param !== null) {
                    $parameters[] = $param;
                }
            }
        }

        return $parameters;
    }

    private function parseSingleParameter(): ?Parameter
    {
        // Skip type hints (identifiers like int, string, bool, etc.)
        while ($this->currentToken() && $this->currentToken()->type === TokenType::T_IDENTIFIER) {
            $this->consumeToken(); // Skip type hint
        }

        // Now expect the variable name
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_VARIABLE) {
            $paramToken = $this->consumeToken();
            return new Parameter(ltrim($paramToken->value, '$'), $paramToken->line, $paramToken->column);
        }

        return null;
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

    private function parseAssignmentExpression(): Assignment
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

        $value = $this->parseExpression();

        return new Assignment($variable, $operator, $value, $varToken->line, $varToken->column);
    }

    private function parseArrayAssignment(): ArrayAssignment
    {
        // Parse the array access: $var[index] or $var[] for array push
        $varToken = $this->consumeToken(); // Consume variable
        $variable = new VariableReference(ltrim($varToken->value, '$'), $varToken->line, $varToken->column);

        $this->consumeTokenOfType(TokenType::T_LBRACKET); // Consume [

        // Check for empty brackets (array push syntax)
        $index = null;
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_RBRACKET) {
            // Empty [] - consume the ]
            $this->consumeToken();
        } else {
            $index = $this->parseExpression();
            $this->consumeTokenOfType(TokenType::T_RBRACKET); // Consume ]
        }

        $arrayAccess = new ArrayAccess($variable, $index, $varToken->line, $varToken->column);

        // Parse the assignment operator
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

        $value = $this->parseExpression();

        // Consume semicolon
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
            $this->consumeToken();
        }

        return new ArrayAssignment($arrayAccess, $operator, $value, $varToken->line, $varToken->column);
    }

    private function parseArguments(): array
    {
        $arguments = [];

        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
            // Parse first argument
            $arguments[] = $this->parseExpression();

            // Parse additional arguments separated by commas
            while ($this->currentToken() && $this->currentToken()->type === TokenType::T_COMMA) {
                $this->consumeToken(); // Consume comma
                if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
                    $arguments[] = $this->parseExpression();
                }
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

    private function parseDeclareStatement(): DeclareStatement
    {
        $declareToken = $this->consumeToken(); // Consume 'declare'
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        $directives = [];

        // Parse directive name (identifier)
        $nameToken = $this->consumeTokenOfType(TokenType::T_IDENTIFIER);
        $this->consumeTokenOfType(TokenType::T_ASSIGN);

        // Parse directive value (integer)
        $valueToken = $this->consumeTokenOfType(TokenType::T_INTEGER);
        $directives[$nameToken->value] = (int)$valueToken->value;

        $this->consumeTokenOfType(TokenType::T_RPAREN);
        $this->consumeTokenOfType(TokenType::T_SEMICOLON);

        return new DeclareStatement($directives, $declareToken->line, $declareToken->column);
    }

    private function parseForeachStatement(): ForeachStatement
    {
        $foreachToken = $this->consumeToken(); // Consume 'foreach'
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        // Parse the array expression
        $arrayExpr = $this->parseExpression();

        $this->consumeTokenOfType(TokenType::T_AS);

        // Parse the first variable (could be key or value)
        $firstVarToken = $this->consumeTokenOfType(TokenType::T_VARIABLE);
        $firstVar = new VariableReference(ltrim($firstVarToken->value, '$'), $firstVarToken->line, $firstVarToken->column);

        // Check if this is key => value syntax
        $keyVar = null;
        $valueVar = $firstVar;
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_DOUBLE_ARROW) {
            $this->consumeToken(); // Consume =>
            $keyVar = $firstVar; // First var was actually the key
            $valueVarToken = $this->consumeTokenOfType(TokenType::T_VARIABLE);
            $valueVar = new VariableReference(ltrim($valueVarToken->value, '$'), $valueVarToken->line, $valueVarToken->column);
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

        return new ForeachStatement($arrayExpr, $valueVar, $body, $keyVar, $foreachToken->line, $foreachToken->column);
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
            TokenType::T_IDENTICAL,
            TokenType::T_NOT_IDENTICAL,
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
                TokenType::T_IDENTICAL => BinaryOperation::OP_IDENTICAL,
                TokenType::T_NOT_IDENTICAL => BinaryOperation::OP_NOT_IDENTICAL,
                TokenType::T_CONCAT => BinaryOperation::OP_CONCAT,
                default => throw new \RuntimeException("Unexpected operator at " . $operatorToken->line . ":" . $operatorToken->column),
            };
            $right = $this->parseTerm();
            $expression = new BinaryOperation($expression, $operator, $right, $operatorToken->line, $operatorToken->column);
        }

        // Handle ternary operator (right-associative)
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_QUESTION) {
            $questionToken = $this->consumeToken(); // Consume ?

            // Check if this is the Elvis operator (?:) by looking for : immediately
            $ifTrue = null;
            if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_COLON) {
                // Regular ternary: condition ? ifTrue : ifFalse
                $ifTrue = $this->parseExpression();
            }

            $this->consumeTokenOfType(TokenType::T_COLON); // Consume :
            $ifFalse = $this->parseExpression(); // Parse the else branch

            $expression = new TernaryExpression($expression, $ifTrue, $ifFalse, $questionToken->line, $questionToken->column);
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

        // Handle unary operators: !expr
        if ($token->type === TokenType::T_NOT) {
            $notToken = $this->consumeToken();
            $operand = $this->parsePrimary();
            return new UnaryOperation(UnaryOperation::OP_NOT, $operand, $notToken->line, $notToken->column);
        }

        // Handle new expression: new ClassName()
        if ($token->type === TokenType::T_NEW) {
            return $this->parseNewExpression();
        }

        // Handle isset() construct
        if ($token->type === TokenType::T_ISSET) {
            return $this->parseIssetExpression();
        }

        // Handle unset() construct
        if ($token->type === TokenType::T_UNSET) {
            return $this->parseUnsetExpression();
        }

        // Handle empty() construct
        if ($token->type === TokenType::T_EMPTY) {
            return $this->parseEmptyExpression();
        }

        // Handle gettype() construct
        if ($token->type === TokenType::T_GETTYPE) {
            return $this->parseGettypeExpression();
        }

        // Handle settype() construct
        if ($token->type === TokenType::T_SETTYPE) {
            return $this->parseSettypeExpression();
        }

        // Handle assignment as expression: $var = expr
        if ($token->type === TokenType::T_VARIABLE && $this->peekToken() &&
            ($this->peekToken()->type === TokenType::T_ASSIGN ||
             $this->peekToken()->type === TokenType::T_ASSIGN_PLUS ||
             $this->peekToken()->type === TokenType::T_ASSIGN_MINUS ||
             $this->peekToken()->type === TokenType::T_ASSIGN_MULTIPLY ||
             $this->peekToken()->type === TokenType::T_ASSIGN_DIVIDE)) {
            return $this->parseAssignmentExpression();
        }

        if ($token->type === TokenType::T_IDENTIFIER && $this->peekToken() && $this->peekToken()->type === TokenType::T_LPAREN) {
            return $this->parseFunctionCall();
        } elseif ($token->type === TokenType::T_IDENTIFIER) {
            // Could be a constant (all uppercase identifier not followed by parens)
            $this->consumeToken();
            return new Constant($token->value, $token->line, $token->column);
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
            // Handle array access: $var[expr] or $var[] for array push
            while ($this->currentToken() && $this->currentToken()->type === TokenType::T_LBRACKET) {
                $this->consumeToken(); // consume [
                // Check for empty brackets (array push syntax)
                if ($this->currentToken() && $this->currentToken()->type === TokenType::T_RBRACKET) {
                    // Empty [] - treat as push (we'll use a special marker or just handle in generator)
                    // For now, use null index which we'll interpret as "append" in the generator
                    $this->consumeToken(); // consume ]
                    $expression = new ArrayAccess($expression, null, $token->line, $token->column);
                } else {
                    $index = $this->parseExpression();
                    $this->consumeTokenOfType(TokenType::T_RBRACKET);
                    $expression = new ArrayAccess($expression, $index, $token->line, $token->column);
                }
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

            // Handle property access: $obj->property or method call: $obj->method()
            while ($this->currentToken() && $this->currentToken()->type === TokenType::T_OBJECT_OPERATOR) {
                $this->consumeToken(); // consume ->
                $propertyToken = $this->consumeTokenOfType(TokenType::T_IDENTIFIER);

                // Check if it's a method call (followed by parentheses)
                if ($this->currentToken() && $this->currentToken()->type === TokenType::T_LPAREN) {
                    $this->consumeToken(); // consume (
                    $arguments = $this->parseArguments();
                    $this->consumeTokenOfType(TokenType::T_RPAREN);
                    $expression = new MethodCall($expression, $propertyToken->value, $arguments, $token->line, $token->column);
                } else {
                    // It's a property access
                    $expression = new PropertyAccess($expression, $propertyToken->value, $token->line, $token->column);
                }
            }

            // Handle variable function call: $var(args) - call function by name stored in variable
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_LPAREN) {
                $this->consumeToken(); // consume (
                $arguments = $this->parseArguments();
                $this->consumeTokenOfType(TokenType::T_RPAREN);
                return new VariableFunctionCall($expression, $arguments, $token->line, $token->column);
            }

            // Handle postfix increment/decrement on property accesses and variables
            if ($this->currentToken() && in_array($this->currentToken()->type, [TokenType::T_PLUS_PLUS, TokenType::T_MINUS_MINUS])) {
                $operatorToken = $this->consumeToken();
                $operator = ($operatorToken->type === TokenType::T_PLUS_PLUS) ? '+=' : '-=';
                // Convert to assignment: $expr += 1 or $expr -= 1
                $expression = new Assignment($expression, $operator, new IntegerLiteral(1, $operatorToken->line, $operatorToken->column), $token->line, $token->column);
            }

            return $expression;
        } elseif ($token->type === TokenType::T_INTEGER) {
            $this->consumeToken();
            return new IntegerLiteral((int)$token->value, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_TRUE) {
            $this->consumeToken();
            return new BooleanLiteral(true, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_FALSE) {
            $this->consumeToken();
            return new BooleanLiteral(false, $token->line, $token->column);
        } elseif ($token->type === TokenType::T_NULL) {
            $this->consumeToken();
            return new NullLiteral($token->line, $token->column);
        } elseif ($token->type === TokenType::T_LBRACKET) {
            // Array literal [elem1, elem2, ...]
            return $this->parseArrayLiteral();
        } elseif ($token->type === TokenType::T_ARRAY) {
            // Array literal array(elem1, elem2, ...) or array(key => value, ...)
            return $this->parseArrayLiteralOldSyntax();
        } elseif ($token->type === TokenType::T_LPAREN) {
            // Check if this is a type cast like (int), (string), (bool), (float)
            $nextToken = $this->peekToken();
            if ($nextToken && $nextToken->type === TokenType::T_IDENTIFIER) {
                $castType = strtolower($nextToken->value);
                if (in_array($castType, ['int', 'integer', 'string', 'bool', 'boolean', 'float', 'double', 'array', 'object'])) {
                    // Check if it's followed by )
                    $afterType = $this->peekToken(2);
                    if ($afterType && $afterType->type === TokenType::T_RPAREN) {
                        // This is a cast expression
                        $this->consumeToken(); // Consume (
                        $typeToken = $this->consumeToken(); // Consume type
                        $this->consumeToken(); // Consume )
                        $expression = $this->parsePrimary(); // Parse the operand
                        return new CastExpression($castType, $expression, $token->line, $token->column);
                    }
                }
            }

            // Parenthesized expression (expr)
            $this->consumeToken(); // Consume (
            $expression = $this->parseExpression();
            $this->consumeTokenOfType(TokenType::T_RPAREN);
            return $expression;
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
        $keys = [];

        // Parse elements until we hit ]
        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACKET) {
            // Parse first element (could be key => value or just value)
            $firstExpr = $this->parseExpression();

            // Check if this is a key => value pair
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_DOUBLE_ARROW) {
                $this->consumeToken(); // Consume =>
                $keys[] = $firstExpr;
                $elements[] = $this->parseExpression();
            } else {
                $keys[] = null;
                $elements[] = $firstExpr;
            }

            // Parse additional elements separated by commas
            while ($this->currentToken() && $this->currentToken()->type === TokenType::T_COMMA) {
                $this->consumeToken(); // Consume comma
                if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RBRACKET) {
                    $expr = $this->parseExpression();

                    // Check if this is a key => value pair
                    if ($this->currentToken() && $this->currentToken()->type === TokenType::T_DOUBLE_ARROW) {
                        $this->consumeToken(); // Consume =>
                        $keys[] = $expr;
                        $elements[] = $this->parseExpression();
                    } else {
                        $keys[] = null;
                        $elements[] = $expr;
                    }
                }
            }
        }

        $this->consumeTokenOfType(TokenType::T_RBRACKET);

        return new ArrayLiteral($elements, $keys, $token->line, $token->column);
    }

    private function parseArrayLiteralOldSyntax(): ArrayLiteral
    {
        $token = $this->consumeToken(); // Consume 'array'
        $this->consumeTokenOfType(TokenType::T_LPAREN); // Consume (

        $elements = [];
        $keys = [];

        // Parse elements until we hit )
        if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
            // Parse first element (could be key => value or just value)
            $firstExpr = $this->parseExpression();

            // Check if this is a key => value pair
            if ($this->currentToken() && $this->currentToken()->type === TokenType::T_DOUBLE_ARROW) {
                $this->consumeToken(); // Consume =>
                $keys[] = $firstExpr;
                $elements[] = $this->parseExpression();
            } else {
                $keys[] = null;
                $elements[] = $firstExpr;
            }

            // Parse additional elements separated by commas
            while ($this->currentToken() && $this->currentToken()->type === TokenType::T_COMMA) {
                $this->consumeToken(); // Consume comma
                if ($this->currentToken() && $this->currentToken()->type !== TokenType::T_RPAREN) {
                    $expr = $this->parseExpression();

                    // Check if this is a key => value pair
                    if ($this->currentToken() && $this->currentToken()->type === TokenType::T_DOUBLE_ARROW) {
                        $this->consumeToken(); // Consume =>
                        $keys[] = $expr;
                        $elements[] = $this->parseExpression();
                    } else {
                        $keys[] = null;
                        $elements[] = $expr;
                    }
                }
            }
        }

        $this->consumeTokenOfType(TokenType::T_RPAREN);

        return new ArrayLiteral($elements, $keys, $token->line, $token->column);
    }

    private function parseNewExpression(): NewExpression
    {
        $newToken = $this->consumeToken(); // Consume 'new'

        $classNameToken = $this->consumeTokenOfType(TokenType::T_IDENTIFIER);

        // Parse constructor arguments (optional, for future support)
        $arguments = [];
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_LPAREN) {
            $this->consumeToken(); // Consume (
            $arguments = $this->parseArguments();
            $this->consumeTokenOfType(TokenType::T_RPAREN);
        }

        return new NewExpression(
            $classNameToken->value,
            $arguments,
            $newToken->line,
            $newToken->column
        );
    }

    private function parseIssetExpression(): FunctionCall
    {
        $issetToken = $this->consumeToken(); // Consume 'isset'
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        // Parse the variable argument
        $argument = $this->parseExpression();

        $this->consumeTokenOfType(TokenType::T_RPAREN);

        // Return as a FunctionCall node - the LLVM generator will handle it specially
        return new FunctionCall(
            'isset',
            [$argument],
            $issetToken->line,
            $issetToken->column
        );
    }

    private function parseUnsetExpression(): FunctionCall
    {
        $unsetToken = $this->consumeToken(); // Consume 'unset'
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        // Parse the variable argument
        $argument = $this->parseExpression();

        $this->consumeTokenOfType(TokenType::T_RPAREN);

        // Return as a FunctionCall node - the LLVM generator will handle it specially
        return new FunctionCall(
            'unset',
            [$argument],
            $unsetToken->line,
            $unsetToken->column
        );
    }

    private function parseEmptyExpression(): FunctionCall
    {
        $emptyToken = $this->consumeToken(); // Consume 'empty'
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        // Parse the argument
        $argument = $this->parseExpression();

        $this->consumeTokenOfType(TokenType::T_RPAREN);

        // Return as a FunctionCall node - the LLVM generator will handle it specially
        return new FunctionCall(
            'empty',
            [$argument],
            $emptyToken->line,
            $emptyToken->column
        );
    }

    private function parseUnsetStatement(): ExpressionStatement
    {
        $unsetToken = $this->consumeToken(); // Consume 'unset'
        $this->consumeTokenOfType(TokenType::T_LPAREN);

        // Parse the variable argument
        $argument = $this->parseExpression();

        $this->consumeTokenOfType(TokenType::T_RPAREN);

        // Consume semicolon if present
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_SEMICOLON) {
            $this->consumeToken();
        }

        // Return as an ExpressionStatement wrapping a FunctionCall
        $funcCall = new FunctionCall(
            'unset',
            [$argument],
            $unsetToken->line,
            $unsetToken->column
        );

        return new ExpressionStatement($funcCall, $unsetToken->line, $unsetToken->column);
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

    /**
     * Parse gettype() construct
     *
     * @param FunctionDefinition|null $enclosingFunction
     * @return FunctionCall
     */
    private function parseGettypeExpression(?FunctionDefinition $enclosingFunction = null): FunctionCall
    {
        $gettypeToken = $this->consumeToken(); // consume 'gettype'
        $startLine = $gettypeToken->line;
        $startColumn = $gettypeToken->column;

        // Check for opening parenthesis
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_LPAREN) {
            $this->consumeToken(); // consume '('
        } else {
            throw new \Exception("gettype requires opening parenthesis at line " . $this->currentToken()->line ?? $startLine);
        }

        // Parse the variable or expression
        $subExpr = $this->parseExpression($enclosingFunction);

        // Check for closing parenthesis
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_RPAREN) {
            $this->consumeToken(); // consume ')'
        } else {
            throw new \Exception("gettype requires closing parenthesis at line " . $this->currentToken()->line ?? $startLine);
        }

        // Create a function call with 'gettype' as the name
        // The LLVM generator will recognize this as a builtin and handle it specially
        return new FunctionCall(
            'gettype',
            [$subExpr],
            $startLine,
            $startColumn
        );
    }

    /**
     * Parse settype() construct
     *
     * @param FunctionDefinition|null $enclosingFunction
     * @return FunctionCall
     */
    private function parseSettypeExpression(?FunctionDefinition $enclosingFunction = null): FunctionCall
    {
        $settypeToken = $this->consumeToken(); // consume 'settype'
        $startLine = $settypeToken->line;
        $startColumn = $settypeToken->column;

        // Check for opening parenthesis
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_LPAREN) {
            $this->consumeToken(); // consume '('
        } else {
            throw new \Exception("settype requires opening parenthesis at line " . $this->currentToken()->line ?? $startLine);
        }

        // Parse the variable (first argument)
        $varExpr = $this->parseExpression($enclosingFunction);

        // Check for comma
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_COMMA) {
            $this->consumeToken(); // consume ','
        } else {
            throw new \Exception("settype requires two arguments separated by comma at line " . $this->currentToken()->line ?? $startLine);
        }

        // Parse the type string (second argument)
        $typeExpr = $this->parseExpression($enclosingFunction);

        // Check for closing parenthesis
        if ($this->currentToken() && $this->currentToken()->type === TokenType::T_RPAREN) {
            $this->consumeToken(); // consume ')'
        } else {
            throw new \Exception("settype requires closing parenthesis at line " . $this->currentToken()->line ?? $startLine);
        }

        // Create a function call with 'settype' as the name
        return new FunctionCall(
            'settype',
            [$varExpr, $typeExpr],
            $startLine,
            $startColumn
        );
    }
}
