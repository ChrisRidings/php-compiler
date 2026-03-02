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

    private function generateFunctionDefinition(FunctionDefinition $funcDef, array &$ir, array $globalVars): void
    {
        // Generate function prototype
        $paramTypes = implode(', ', array_fill(0, count($funcDef->parameters), 'i8*'));
        $ir[] = "define void @{$funcDef->name}({$paramTypes}) {";
        $ir[] = "entry:";

        // Generate function body
        foreach ($funcDef->body as $statement) {
            $this->generateStatement($statement, $ir, $globalVars);
        }

        $ir[] = "  ret void";
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

    private function generateVariableReference(VariableReference $varRef, array &$ir, array $globalVars): void
    {
        // For now, we'll just treat variables as parameters passed by value
        // We assume the variable name matches the parameter index
        // So $name becomes %0 (first parameter)
        $ir[] = "  call void @php_echo(i8* %0)";
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
