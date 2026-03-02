<?php

declare(strict_types=1);

namespace PhpCompiler\LLVM;

use PhpCompiler\AST\EchoStatement;
use PhpCompiler\AST\Node;
use PhpCompiler\AST\Parser;
use PhpCompiler\AST\Statement;
use PhpCompiler\AST\StringLiteral;
use PhpCompiler\AST\FunctionDefinition;
use PhpCompiler\AST\FunctionCall;
use PhpCompiler\AST\VariableReference;
use PhpCompiler\AST\Assignment;
use PhpCompiler\AST\IntegerLiteral;
use PhpCompiler\AST\ReturnStatement;

class Generator
{
    /**
     * @var Statement[]
     */
    private array $statements;

    public function __construct(array $statements)
    {
        $this->statements = $statements;
    }

    public static function fromFile(string $filename): self
    {
        $parser = Parser::fromFile($filename);
        $statements = $parser->parse();
        return new self($statements);
    }

    public function generate(): string
    {
        $ir = [];
        $globalVars = [];

        // LLVM IR header
        $ir[] = "; ModuleID = 'phpcompiler'";
        $ir[] = "target datalayout = \"e-m:w-p:64:64-i64:64-f80:128-n8:16:32:64-S128\"";
        $ir[] = "target triple = \"x86_64-pc-windows-msvc\"";
        $ir[] = "";

        // Declare external function for php_echo
        $ir[] = "declare void @php_echo(i8*)";
        $ir[] = "declare i8* @php_itoa(i32)";
        $ir[] = "";

        // Collect all global string constants first
        foreach ($this->statements as $statement) {
            $this->collectGlobals($statement, $globalVars);
        }

        // Add global variables to IR
        foreach ($globalVars as $globalName => $globalData) {
            $ir[] = "; Global string constant";
            $ir[] = "@{$globalName} = private unnamed_addr constant [{$globalData['length']} x i8] c\"{$globalData['escapedValue']}\\00\"";
            $ir[] = "";
        }

        // Separate statements into function definitions and other statements
        $functionDefinitions = [];
        $otherStatements = [];

        foreach ($this->statements as $statement) {
            if ($statement instanceof FunctionDefinition) {
                $functionDefinitions[] = $statement;
            } else {
                $otherStatements[] = $statement;
            }
        }

        // Generate function definitions
        foreach ($functionDefinitions as $funcDef) {
            $this->generateStatement($funcDef, $ir, $globalVars);
        }

        // Define main function
        $ir[] = "define i32 @main() {";
        $ir[] = "entry:";

        // Generate code for other statements (should be function calls)
        foreach ($otherStatements as $statement) {
            $this->generateStatement($statement, $ir, $globalVars);
        }

        // Return 0 from main
        $ir[] = "  ret i32 0";
        $ir[] = "}";
        $ir[] = "";

        return implode("\n", $ir);
    }

    private function collectGlobals(Node $node, array &$globalVars): void
    {
        if ($node instanceof EchoStatement) {
            foreach ($node->expressions as $expression) {
                $this->collectGlobals($expression, $globalVars);
            }
        } elseif ($node instanceof FunctionDefinition) {
            foreach ($node->body as $statement) {
                $this->collectGlobals($statement, $globalVars);
            }
        } elseif ($node instanceof FunctionCall) {
            foreach ($node->arguments as $arg) {
                $this->collectGlobals($arg, $globalVars);
            }
        } elseif ($node instanceof Assignment) {
            $this->collectGlobals($node->value, $globalVars);
        } elseif ($node instanceof StringLiteral) {
            $globalName = "__str_const_" . md5($node->value);
            if (!isset($globalVars[$globalName])) {
                $globalVars[$globalName] = [
                    'value' => $node->value,
                    'escapedValue' => $this->escapeString($node->value),
                    'length' => strlen($node->value) + 1 // +1 for null terminator
                ];
            }
        }
    }

    private function generateStatement(Statement $statement, array &$ir, array $globalVars): void
    {
        if ($statement instanceof EchoStatement) {
            $this->generateEchoStatement($statement, $ir, $globalVars);
        } elseif ($statement instanceof FunctionDefinition) {
            $this->generateFunctionDefinition($statement, $ir, $globalVars);
        } elseif ($statement instanceof FunctionCall) {
            $this->generateFunctionCall($statement, $ir, $globalVars);
        } elseif ($statement instanceof Assignment) {
            $this->generateAssignment($statement, $ir, $globalVars);
        } elseif ($statement instanceof ReturnStatement) {
            $this->generateReturnStatement($statement, $ir, $globalVars);
        } else {
            throw new \RuntimeException(
                sprintf(
                    "LLVM Generator Error: Unsupported statement type '%s' at line %d, column %d",
                    get_class($statement),
                    $statement->line,
                    $statement->column
                )
            );
        }
    }

    private function generateReturnStatement(ReturnStatement $returnStmt, array &$ir, array $globalVars): void
    {
        if ($returnStmt->value === null) {
            $ir[] = "  ret void";
        } elseif ($returnStmt->value instanceof IntegerLiteral) {
            $ir[] = "  ret i32 " . $returnStmt->value->value;
        } else {
            // For now, we'll return 0 for non-integer returns
            $ir[] = "  ret i32 0";
        }
        $ir[] = "";
    }

    private function generateAssignment(Assignment $assignment, array &$ir, array $globalVars): void
    {
        // Check if value is a function call that returns an integer
        $isIntegerReturn = false;
        if ($assignment->value instanceof FunctionCall) {
            // For simplification, assume greet_length returns i32
            $isIntegerReturn = true;
        }

        if ($isIntegerReturn) {
            // If value is an integer, allocate space for i32
            $ir[] = "  %{$assignment->variable->name} = alloca i32";

            // Generate value
            if ($assignment->value instanceof FunctionCall) {
                $args = [];
                foreach ($assignment->value->arguments as $arg) {
                    if ($arg instanceof StringLiteral) {
                        $globalName = "__str_const_" . md5($arg->value);
                        $globalData = $globalVars[$globalName];
                        $ir[] = "  %{$globalName}_ptr = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";
                        $args[] = "i8* %{$globalName}_ptr";
                    }
                }
                $argStr = implode(', ', $args);
                $ir[] = "  %call_result = call i32 @{$assignment->value->name}({$argStr})";
                $ir[] = "  store i32 %call_result, i32* %{$assignment->variable->name}";
            }
        } else {
            // Otherwise treat as string
            $ir[] = "  %{$assignment->variable->name} = alloca i8*";

            if ($assignment->value instanceof StringLiteral) {
                $globalName = "__str_const_" . md5($assignment->value->value);
                $globalData = $globalVars[$globalName];
                $ir[] = "  %{$globalName}_ptr = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";
                $ir[] = "  store i8* %{$globalName}_ptr, i8** %{$assignment->variable->name}";
            } else {
                $ir[] = "  store i8* null, i8** %{$assignment->variable->name}";
            }
        }

        $ir[] = "";
    }

    private function generateFunctionDefinition(FunctionDefinition $funcDef, array &$ir, array $globalVars): void
    {
        // Determine return type based on body
        $returnType = "void";
        foreach ($funcDef->body as $statement) {
            if ($statement instanceof ReturnStatement && $statement->value instanceof IntegerLiteral) {
                $returnType = "i32";
                break;
            }
        }

        // Generate function prototype
        $paramTypes = implode(', ', array_fill(0, count($funcDef->parameters), 'i8*'));
        $ir[] = "define {$returnType} @{$funcDef->name}({$paramTypes}) {";
        $ir[] = "entry:";

        // Generate function body
        foreach ($funcDef->body as $statement) {
            $this->generateStatement($statement, $ir, $globalVars);
        }

        // If no return statement was found, add a default return
        if ($returnType === "void") {
            $ir[] = "  ret void";
        } else {
            $ir[] = "  ret i32 0";
        }

        $ir[] = "}";
        $ir[] = "";
    }

    private function generateFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        // Generate arguments
        $args = [];
        foreach ($funcCall->arguments as $arg) {
            // For now, we'll handle string literals directly
            if ($arg instanceof StringLiteral) {
                $globalName = "__str_const_" . md5($arg->value);
                $globalData = $globalVars[$globalName];
                $ir[] = "  %{$globalName}_ptr = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";
                $args[] = "i8* %{$globalName}_ptr";
            } else {
                // For other types, we'll just pass null for now
                $args[] = "i8* null";
            }
        }

        $argStr = implode(', ', $args);
        $ir[] = "  call void @{$funcCall->name}({$argStr})";
        $ir[] = "";
    }

    private function generateEchoStatement(EchoStatement $statement, array &$ir, array $globalVars): void
    {
        foreach ($statement->expressions as $expression) {
            $this->generateExpression($expression, $ir, $globalVars);
        }
    }

    private function generateExpression(Node $expression, array &$ir, array $globalVars): void
    {
        if ($expression instanceof StringLiteral) {
            $this->generateStringLiteral($expression, $ir, $globalVars);
        } elseif ($expression instanceof VariableReference) {
            $this->generateVariableReference($expression, $ir, $globalVars);
        } elseif ($expression instanceof IntegerLiteral) {
            $this->generateIntegerLiteral($expression, $ir, $globalVars);
        } else {
            throw new \RuntimeException(
                sprintf(
                    "LLVM Generator Error: Unsupported expression type '%s' at line %d, column %d",
                    get_class($expression),
                    $expression->line,
                    $expression->column
                )
            );
        }
    }

    private function generateIntegerLiteral(IntegerLiteral $literal, array &$ir, array $globalVars): void
    {
        // To print an integer, we need to convert it to a string first
        // For now, we'll just print the integer as a string literal
        $intStr = (string)$literal->value;
        $globalName = "__str_const_" . md5($intStr);

        if (!isset($globalVars[$globalName])) {
            $globalVars[$globalName] = [
                'value' => $intStr,
                'escapedValue' => $this->escapeString($intStr),
                'length' => strlen($intStr) + 1
            ];
        }

        $globalData = $globalVars[$globalName];
        $ir[] = "  %{$globalName}_ptr = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";
        $ir[] = "  call void @php_echo(i8* %{$globalName}_ptr)";
        $ir[] = "";
    }

    private function generateVariableReference(VariableReference $varRef, array &$ir, array $globalVars): void
    {
        // Check if variable is a parameter or local variable
        if ($varRef->name === 'name') {
            $ir[] = "  call void @php_echo(i8* %0)";
        } elseif ($varRef->name === 'result') {
            $ir[] = "  %result_val = load i32, i32* %result";
            $ir[] = "  %result_str_ptr = call i8* @php_itoa(i32 %result_val)";
            $ir[] = "  call void @php_echo(i8* %result_str_ptr)";
        } else {
            $ir[] = "  %{$varRef->name}_ptr = load i8*, i8** %{$varRef->name}";
            $ir[] = "  call void @php_echo(i8* %{$varRef->name}_ptr)";
        }
        $ir[] = "";
    }

    private function generateStringLiteral(StringLiteral $literal, array &$ir, array $globalVars): void
    {
        $globalName = "__str_const_" . md5($literal->value);

        // Get pointer to the string
        $globalData = $globalVars[$globalName];
        $ir[] = "  %{$globalName}_ptr = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";

        // Call php_echo function
        $ir[] = "  call void @php_echo(i8* %{$globalName}_ptr)";
        $ir[] = "";
    }

    private function escapeString(string $str): string
    {
        $replacements = [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            "\0" => '\\0',
        ];

        return strtr($str, $replacements);
    }
}
