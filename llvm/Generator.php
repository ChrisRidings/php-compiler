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
use PhpCompiler\AST\BinaryOperation;

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

    private function getNextTempVariable(): string
    {
        static $counter = 0;
        $var = '%tmp_' . ++$counter;
        return $var;
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

        // Define zval struct type (matches C struct layout exactly for x86_64)
        $ir[] = "%struct.zval = type { i32, %union.zval_value }";
        $ir[] = "%union.zval_value = type { i64 }"; // on x86_64, all union members are 8 bytes
        $ir[] = "";

        // Declare external zval functions (pass by pointer)
        $ir[] = "declare void @php_echo_zval(%struct.zval*)";
        $ir[] = "declare void @php_zval_null(%struct.zval*)";
        $ir[] = "declare void @php_zval_bool(%struct.zval*, i32)";
        $ir[] = "declare void @php_zval_int(%struct.zval*, i32)";
        $ir[] = "declare void @php_zval_string(%struct.zval*, i8*)";
        $ir[] = "declare i8* @php_zval_to_string(%struct.zval*)";
        $ir[] = "declare i32 @php_zval_to_int(%struct.zval*)";
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

        // Generate code for other statements
        foreach ($otherStatements as $statement) {
            if ($statement instanceof FunctionCall) {
                // Call function and discard result
                $args = [];
                foreach ($statement->arguments as $arg) {
                    $argPtr = $this->generateExpression($arg, $ir, $globalVars);
                    $argVal = $this->getNextTempVariable();
                    $ir[] = "  {$argVal} = load %struct.zval, %struct.zval* {$argPtr}";
                    $args[] = "%struct.zval {$argVal}";
                }
                $argStr = implode(', ', $args);
                $ir[] = "  %call_result = call %struct.zval @{$statement->name}({$argStr})";
                // Discard the result - store in stack space and then do nothing
                $ir[] = "  %result_ptr = alloca %struct.zval";
                $ir[] = "  store %struct.zval %call_result, %struct.zval* %result_ptr";
            } elseif ($statement instanceof ReturnStatement) {
                // If it's a return statement at top level, we need to handle it specially
                // because main must return i32, not zval
                if ($statement->value !== null) {
                    // Generate the expression and then ignore it (we'll return 0 anyway)
                    $this->generateExpression($statement->value, $ir, $globalVars);
                }
            } else {
                $this->generateStatement($statement, $ir, $globalVars);
            }
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
        } elseif ($node instanceof ReturnStatement) {
            if ($node->value !== null) {
                $this->collectGlobals($node->value, $globalVars);
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
        // Handle all other statement types to ensure arguments are collected
        if ($node instanceof Statement) {
            if ($node instanceof FunctionCall) {
                foreach ($node->arguments as $arg) {
                    $this->collectGlobals($arg, $globalVars);
                }
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
        // Always return a zval
        if ($returnStmt->value === null) {
            $nullResult = $this->getNextTempVariable();
            $ir[] = "  {$nullResult} = alloca %struct.zval";
            $ir[] = "  call void @php_zval_null(%struct.zval* {$nullResult})";
            $loaded = $this->getNextTempVariable();
            $ir[] = "  {$loaded} = load %struct.zval, %struct.zval* {$nullResult}";
            $ir[] = "  ret %struct.zval {$loaded}";
        } else {
            $resultPtr = $this->generateExpression($returnStmt->value, $ir, $globalVars);
            $resultVal = $this->getNextTempVariable();
            $ir[] = "  {$resultVal} = load %struct.zval, %struct.zval* {$resultPtr}";
            $ir[] = "  ret %struct.zval {$resultVal}";
        }

        $ir[] = "";
    }

    private function generateAssignment(Assignment $assignment, array &$ir, array $globalVars): void
    {
        // All variables are stored as zval structs on the stack
        $varName = $assignment->variable->name;
        $ir[] = "  %{$varName} = alloca %struct.zval";

        // Generate value to assign
        $valuePtr = $this->generateExpression($assignment->value, $ir, $globalVars);

        // Load the value from the generated expression's pointer and store it
        $value = $this->getNextTempVariable();
        $ir[] = "  {$value} = load %struct.zval, %struct.zval* {$valuePtr}";
        $ir[] = "  store %struct.zval {$value}, %struct.zval* %{$varName}";

        $ir[] = "";
    }

    private function generateFunctionDefinition(FunctionDefinition $funcDef, array &$ir, array $globalVars): void
    {
        // All functions return zval and parameters are zval
        $paramTypes = implode(', ', array_fill(0, count($funcDef->parameters), '%struct.zval'));
        $ir[] = "define %struct.zval @{$funcDef->name}({$paramTypes}) {";
        $ir[] = "entry:";

        // Allocate stack space for parameters
        foreach ($funcDef->parameters as $i => $param) {
            $ir[] = "  %{$param->name} = alloca %struct.zval";
            $ir[] = "  store %struct.zval %{$i}, %struct.zval* %{$param->name}";
        }

        foreach ($funcDef->body as $statement) {
            $this->generateStatement($statement, $ir, $globalVars);
        }

        $hasReturn = false;
        foreach ($funcDef->body as $statement) {
            if ($statement instanceof ReturnStatement) {
                $hasReturn = true;
                break;
            }
        }

        if (!$hasReturn) {
            $nullResult = $this->getNextTempVariable();
            $ir[] = "  {$nullResult} = alloca %struct.zval";
            $ir[] = "  call void @php_zval_null(%struct.zval* {$nullResult})";
            $loaded = $this->getNextTempVariable();
            $ir[] = "  {$loaded} = load %struct.zval, %struct.zval* {$nullResult}";
            $ir[] = "  ret %struct.zval {$loaded}";
        }

        $ir[] = "}";
        $ir[] = "";
    }

    private function generateFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        // Generate arguments
        $args = [];
        foreach ($funcCall->arguments as $arg) {
            $argPtr = $this->generateExpression($arg, $ir, $globalVars);
            $argVal = $this->getNextTempVariable();
            $ir[] = "  {$argVal} = load %struct.zval, %struct.zval* {$argPtr}";
            $args[] = "%struct.zval {$argVal}";
        }

        $argStr = implode(', ', $args);
        $ir[] = "  call %struct.zval @{$funcCall->name}({$argStr})";
        $ir[] = "";
    }

    private function generateEchoStatement(EchoStatement $statement, array &$ir, array $globalVars): void
    {
        foreach ($statement->expressions as $expression) {
            $zvalPtr = $this->generateExpression($expression, $ir, $globalVars);
            $ir[] = "  call void @php_echo_zval(%struct.zval* {$zvalPtr})";
        }
        $ir[] = "";
    }

    private function generateExpression(Node $expression, array &$ir, array $globalVars): string
    {
        if ($expression instanceof StringLiteral) {
            return $this->generateStringLiteral($expression, $ir, $globalVars);
        } elseif ($expression instanceof VariableReference) {
            return $this->generateVariableReference($expression, $ir, $globalVars);
        } elseif ($expression instanceof IntegerLiteral) {
            return $this->generateIntegerLiteral($expression, $ir, $globalVars);
        } elseif ($expression instanceof BinaryOperation) {
            return $this->generateBinaryOperation($expression, $ir, $globalVars);
        } elseif ($expression instanceof FunctionCall) {
            // Function calls as expressions: generate call and return pointer to result zval
            $result = $this->getNextTempVariable();
            $ir[] = "  {$result} = alloca %struct.zval";

            $args = [];
            foreach ($expression->arguments as $arg) {
                $argPtr = $this->generateExpression($arg, $ir, $globalVars);
                $argVal = $this->getNextTempVariable();
                $ir[] = "  {$argVal} = load %struct.zval, %struct.zval* {$argPtr}";
                $args[] = "%struct.zval {$argVal}";
            }

            $argStr = implode(', ', $args);
            $ir[] = "  %call_result = call %struct.zval @{$expression->name}({$argStr})";
            $ir[] = "  store %struct.zval %call_result, %struct.zval* {$result}";

            return $result;
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

    private function generateIntegerLiteral(IntegerLiteral $literal, array &$ir, array $globalVars): string
    {
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_int(%struct.zval* {$result}, i32 " . $literal->value . ")";
        // We can't return the struct directly, but since we'll be passing pointers around,
        // return the pointer here
        return $result;
    }

    private function generateBinaryOperation(BinaryOperation $op, array &$ir, array $globalVars): string
    {
        $leftZval = $this->generateExpression($op->left, $ir, $globalVars);
        $rightZval = $this->generateExpression($op->right, $ir, $globalVars);

        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";

        switch ($op->operator) {
            case '+':
            case '-':
            case '*':
            case '/':
                // Convert both operands to integers
                $leftInt = $this->getNextTempVariable();
                $rightInt = $this->getNextTempVariable();
                $ir[] = "  {$leftInt} = call i32 @php_zval_to_int(%struct.zval* {$leftZval})";
                $ir[] = "  {$rightInt} = call i32 @php_zval_to_int(%struct.zval* {$rightZval})";

                $intResult = $this->getNextTempVariable();

                switch ($op->operator) {
                    case '+':
                        $ir[] = "  {$intResult} = add i32 {$leftInt}, {$rightInt}";
                        break;
                    case '-':
                        $ir[] = "  {$intResult} = sub i32 {$leftInt}, {$rightInt}";
                        break;
                    case '*':
                        $ir[] = "  {$intResult} = mul i32 {$leftInt}, {$rightInt}";
                        break;
                    case '/':
                        $ir[] = "  {$intResult} = sdiv i32 {$leftInt}, {$rightInt}"; // signed division
                        break;
                }

                $ir[] = "  call void @php_zval_int(%struct.zval* {$result}, i32 {$intResult})";
                break;
            case '.':
                // String concatenation - convert both to strings (this is simplification for now)
                $leftStr = $this->getNextTempVariable();
                $rightStr = $this->getNextTempVariable();
                $ir[] = "  {$leftStr} = call i8* @php_zval_to_string(%struct.zval* {$leftZval})";
                $ir[] = "  {$rightStr} = call i8* @php_zval_to_string(%struct.zval* {$rightZval})";

                // For string concatenation, we'd need a helper function - for now, just treat as string literal
                $ir[] = "  call void @php_zval_string(%struct.zval* {$result}, i8* {$leftStr})";
                break;
            case '==':
                // Comparison - this is a simplification for now
                // We'll convert both to integers for comparison
                $leftInt = $this->getNextTempVariable();
                $rightInt = $this->getNextTempVariable();
                $ir[] = "  {$leftInt} = call i32 @php_zval_to_int(%struct.zval* {$leftZval})";
                $ir[] = "  {$rightInt} = call i32 @php_zval_to_int(%struct.zval* {$rightZval})";

                $compareResult = $this->getNextTempVariable();
                $ir[] = "  {$compareResult} = icmp eq i32 {$leftInt}, {$rightInt}";
                $ir[] = "  %cmp_bool = zext i1 {$compareResult} to i32";
                $ir[] = "  call void @php_zval_bool(%struct.zval* {$result}, i32 %cmp_bool)";
                break;
            default:
                throw new \RuntimeException("Unsupported binary operation: " . $op->operator);
        }

        return $result;
    }

    private function generateExpressionAsInteger(\PhpCompiler\AST\Expression $expr, array &$ir, array $globalVars): string
    {
        $var = $this->getNextTempVariable();

        if ($expr instanceof IntegerLiteral) {
            $ir[] = "  {$var} = add i32 0, " . $expr->value;
        } elseif ($expr instanceof VariableReference) {
            if ($expr->name === 'x') {
                $ir[] = "  {$var} = load i32, i32* %x";
            } else {
                throw new \RuntimeException("Only \$x variable is supported for arithmetic");
            }
        } elseif ($expr instanceof BinaryOperation) {
            $result = $this->generateBinaryOperation($expr, $ir, $globalVars);
            return $result;
        } else {
            throw new \RuntimeException("Unsupported expression type for arithmetic: " . get_class($expr));
        }

        return $var;
    }

    private function generateVariableReference(VariableReference $varRef, array &$ir, array $globalVars): string
    {
        // Variable is already a pointer to zval, just return it
        return "%" . $varRef->name;
    }

    private function generateStringLiteral(StringLiteral $literal, array &$ir, array $globalVars): string
    {
        $globalName = "__str_const_" . md5($literal->value);
        $globalData = $globalVars[$globalName];
        $ir[] = "  %{$globalName}_ptr = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";

        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_string(%struct.zval* {$result}, i8* %{$globalName}_ptr)";
        return $result;
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
