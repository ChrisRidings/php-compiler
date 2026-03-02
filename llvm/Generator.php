<?php

declare(strict_types=1);

namespace PhpCompiler\LLVM;

use PhpCompiler\AST\EchoStatement;
use PhpCompiler\AST\Node;
use PhpCompiler\AST\Parser;
use PhpCompiler\AST\Statement;
use PhpCompiler\AST\Expression;
use PhpCompiler\AST\StringLiteral;
use PhpCompiler\AST\FunctionDefinition;
use PhpCompiler\AST\FunctionCall;
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
use PhpCompiler\AST\ArrayAssignment;
use PhpCompiler\AST\ForeachStatement;
use PhpCompiler\AST\DeclareStatement;
use PhpCompiler\AST\ExpressionStatement;

class Generator
{
    /**
     * @var Statement[]
     */
    private array $statements;

    /**
     * @var array<string, bool>
     */
    private array $declaredVars = [];

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
        $ir[] = "declare i8* @php_concat_strings(i8*, i8*)";
        $ir[] = "declare void @php_array_create(%struct.zval*, i32)";
        $ir[] = "declare void @php_array_append(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_array_get(%struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_array_set(%struct.zval*, i8*, %struct.zval*)";
        $ir[] = "declare void @php_array_set_by_index(%struct.zval*, i32, %struct.zval*)";
        $ir[] = "declare i32 @php_array_size(%struct.zval*)";
        $ir[] = "declare i8* @php_array_get_key(%struct.zval*, i32)";
        $ir[] = "declare void @php_array_values(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_opendir(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_readdir(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_closedir(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_preg_match(%struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_natsort(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_print_r(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_zval_strict_ne(%struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_zval_strict_eq(%struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "";

        // Collect all global string constants first
        foreach ($this->statements as $statement) {
            $this->collectGlobals($statement, $globalVars);
        }

        // Add global variables to IR
        foreach ($globalVars as $globalName => $globalData) {
            $ir[] = "; Global string constant";
            $ev = $globalData['escapedValue'] . '\00';
            $evlen = $globalData['length'] + 1;
            $ir[] = "@{$globalName} = private unnamed_addr constant [{$evlen} x i8] c\"{$ev}\"";
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
                $callResult = $this->getNextTempVariable();
                $ir[] = "  {$callResult} = call %struct.zval @{$statement->name}({$argStr})";
                // Discard the result - store in stack space and then do nothing
                $resultPtr = $this->getNextTempVariable();
                $ir[] = "  {$resultPtr} = alloca %struct.zval";
                $ir[] = "  store %struct.zval {$callResult}, %struct.zval* {$resultPtr}";
            } elseif ($statement instanceof ReturnStatement) {
                // If it's a return statement at top level, we need to handle it specially
                // because main must return i32, not zval
                if ($statement->value !== null) {
                    // Generate the expression and then ignore it (we'll return 0 anyway)
                    $this->generateExpression($statement->value, $ir, $globalVars);
                }
                // Skip further processing - don't call generateStatement which would emit a ret
                continue;
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

    public function collectGlobals(Node $node, array &$globalVars): void
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
        } elseif ($node instanceof ArrayAssignment) {
            $this->collectGlobals($node->arrayAccess, $globalVars);
            $this->collectGlobals($node->value, $globalVars);
        } elseif ($node instanceof ReturnStatement) {
            if ($node->value !== null) {
                $this->collectGlobals($node->value, $globalVars);
            }
        } elseif ($node instanceof IfStatement) {
            $this->collectGlobals($node->condition, $globalVars);
            foreach ($node->thenBody as $statement) {
                $this->collectGlobals($statement, $globalVars);
            }
            foreach ($node->elseBody as $statement) {
                $this->collectGlobals($statement, $globalVars);
            }
        } elseif ($node instanceof ForStatement) {
            foreach ($node->initializations as $init) {
                $this->collectGlobals($init, $globalVars);
            }
            if ($node->condition) {
                $this->collectGlobals($node->condition, $globalVars);
            }
            foreach ($node->updates as $update) {
                $this->collectGlobals($update, $globalVars);
            }
            foreach ($node->body as $statement) {
                $this->collectGlobals($statement, $globalVars);
            }
        } elseif ($node instanceof WhileStatement) {
            $this->collectGlobals($node->condition, $globalVars);
            foreach ($node->body as $statement) {
                $this->collectGlobals($statement, $globalVars);
            }
        } elseif ($node instanceof DoWhileStatement) {
            $this->collectGlobals($node->condition, $globalVars);
            foreach ($node->body as $statement) {
                $this->collectGlobals($statement, $globalVars);
            }
        } elseif ($node instanceof ForeachStatement) {
            $this->collectGlobals($node->array, $globalVars);
            foreach ($node->body as $statement) {
                $this->collectGlobals($statement, $globalVars);
            }
        } elseif ($node instanceof ExpressionStatement) {
            $this->collectGlobals($node->expression, $globalVars);
        } elseif ($node instanceof ArrayLiteral) {
            foreach ($node->elements as $element) {
                $this->collectGlobals($element, $globalVars);
            }
            // Also collect keys for associative arrays
            foreach ($node->keys as $key) {
                if ($key !== null) {
                    $this->collectGlobals($key, $globalVars);
                }
            }
        } elseif ($node instanceof ArrayAccess) {
            $this->collectGlobals($node->array, $globalVars);
            if ($node->index !== null) {
                $this->collectGlobals($node->index, $globalVars);
            }
        } elseif ($node instanceof BinaryOperation) {
            $this->collectGlobals($node->left, $globalVars);
            $this->collectGlobals($node->right, $globalVars);
        } elseif ($node instanceof StringLiteral) {
            $globalName = "__str_const_" . md5($node->value);
            if (!isset($globalVars[$globalName])) {
                $escapedValue = $this->escapeString($node->value);
                $globalVars[$globalName] = [
                'value' => $node->value,
                'escapedValue' => $escapedValue,
                'length' => strlen($node->value)
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

    private function generateStatement(Statement $statement, array &$ir, array $globalVars, bool $inFunction = false): void
    {
        if ($statement instanceof EchoStatement) {
            $this->generateEchoStatement($statement, $ir, $globalVars);
        } elseif ($statement instanceof FunctionDefinition) {
            $this->generateFunctionDefinition($statement, $ir, $globalVars);
        } elseif ($statement instanceof FunctionCall) {
            $this->generateFunctionCall($statement, $ir, $globalVars);
        } elseif ($statement instanceof Assignment) {
            $this->generateAssignment($statement, $ir, $globalVars);
        } elseif ($statement instanceof ArrayAssignment) {
            $this->generateArrayAssignment($statement, $ir, $globalVars);
        } elseif ($statement instanceof ReturnStatement) {
            $this->generateReturnStatement($statement, $ir, $globalVars, $inFunction);
        } elseif ($statement instanceof IfStatement) {
            $this->generateIfStatement($statement, $ir, $globalVars, $inFunction);
        } elseif ($statement instanceof ForStatement) {
            $this->generateForStatement($statement, $ir, $globalVars, $inFunction);
        } elseif ($statement instanceof WhileStatement) {
            $this->generateWhileStatement($statement, $ir, $globalVars, $inFunction);
        } elseif ($statement instanceof DoWhileStatement) {
            $this->generateDoWhileStatement($statement, $ir, $globalVars, $inFunction);
        } elseif ($statement instanceof ForeachStatement) {
            $this->generateForeachStatement($statement, $ir, $globalVars, $inFunction);
        } elseif ($statement instanceof DeclareStatement) {
            // Declare statement is a PHP runtime directive - no code generation needed
            // It's treated as a no-op since it affects PHP's runtime behavior, not compiled code
        } elseif ($statement instanceof ExpressionStatement) {
            // Expression statement - evaluate the expression and discard the result
            $this->generateExpression($statement->expression, $ir, $globalVars);
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

    private function generateReturnStatement(ReturnStatement $returnStmt, array &$ir, array $globalVars, bool $inFunction = false): void
    {
        // If this ReturnStatement is being used as an expression statement wrapper
        // at the top level (not inside a function), just evaluate the expression without returning
        // The parser wraps expression-statements in ReturnStatements, so at top level
        // we should just evaluate the expression, not actually return
        if (!$inFunction && $returnStmt->value !== null) {
            // Just evaluate the expression, don't return
            $this->generateExpression($returnStmt->value, $ir, $globalVars);
            return;
        }

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

        // Check if variable is already declared - if not, declare it
        if (!isset($this->declaredVars[$varName])) {
            $ir[] = "  %{$varName} = alloca %struct.zval";
            $this->declaredVars[$varName] = true;
        }

        if ($assignment->operator === '=') {
            // Simple assignment
            $valuePtr = $this->generateExpression($assignment->value, $ir, $globalVars);
            $value = $this->getNextTempVariable();
            $ir[] = "  {$value} = load %struct.zval, %struct.zval* {$valuePtr}";
            $ir[] = "  store %struct.zval {$value}, %struct.zval* %{$varName}";
        } else {
            // Compound assignment (+=, -=, *=, /=)
            $currentVal = $this->getNextTempVariable();
            $ir[] = "  {$currentVal} = call i32 @php_zval_to_int(%struct.zval* %{$varName})";

            $valuePtr = $this->generateExpression($assignment->value, $ir, $globalVars);
            $valueVal = $this->getNextTempVariable();
            $ir[] = "  {$valueVal} = call i32 @php_zval_to_int(%struct.zval* {$valuePtr})";

            $resultVal = $this->getNextTempVariable();
            switch ($assignment->operator) {
                case '+=':
                    $ir[] = "  {$resultVal} = add i32 {$currentVal}, {$valueVal}";
                    break;
                case '-=':
                    $ir[] = "  {$resultVal} = sub i32 {$currentVal}, {$valueVal}";
                    break;
                case '*=':
                    $ir[] = "  {$resultVal} = mul i32 {$currentVal}, {$valueVal}";
                    break;
                case '/=':
                    $ir[] = "  {$resultVal} = sdiv i32 {$currentVal}, {$valueVal}";
                    break;
            }

            $ir[] = "  call void @php_zval_int(%struct.zval* %{$varName}, i32 {$resultVal})";
        }

        $ir[] = "";
    }

    private function generateAssignmentExpression(Assignment $assignment, array &$ir, array $globalVars): string
    {
        // All variables are stored as zval structs on the stack
        $varName = $assignment->variable->name;

        // Check if variable is already declared - if not, declare it
        if (!isset($this->declaredVars[$varName])) {
            $ir[] = "  %{$varName} = alloca %struct.zval";
            $this->declaredVars[$varName] = true;
        }

        // Generate the value expression
        $valuePtr = $this->generateExpression($assignment->value, $ir, $globalVars);

        if ($assignment->operator === '=') {
            // Simple assignment
            $value = $this->getNextTempVariable();
            $ir[] = "  {$value} = load %struct.zval, %struct.zval* {$valuePtr}";
            $ir[] = "  store %struct.zval {$value}, %struct.zval* %{$varName}";
        } else {
            // Compound assignment (+=, -=, *=, /=)
            $currentVal = $this->getNextTempVariable();
            $ir[] = "  {$currentVal} = call i32 @php_zval_to_int(%struct.zval* %{$varName})";

            $valueVal = $this->getNextTempVariable();
            $ir[] = "  {$valueVal} = call i32 @php_zval_to_int(%struct.zval* {$valuePtr})";

            $resultVal = $this->getNextTempVariable();
            switch ($assignment->operator) {
                case '+=':
                    $ir[] = "  {$resultVal} = add i32 {$currentVal}, {$valueVal}";
                    break;
                case '-=':
                    $ir[] = "  {$resultVal} = sub i32 {$currentVal}, {$valueVal}";
                    break;
                case '*=':
                    $ir[] = "  {$resultVal} = mul i32 {$currentVal}, {$valueVal}";
                    break;
                case '/=':
                    $ir[] = "  {$resultVal} = sdiv i32 {$currentVal}, {$valueVal}";
                    break;
            }

            $ir[] = "  call void @php_zval_int(%struct.zval* %{$varName}, i32 {$resultVal})";
        }

        // Return a pointer to the assigned value (for use in parent expressions)
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $loadedVal = $this->getNextTempVariable();
        $ir[] = "  {$loadedVal} = load %struct.zval, %struct.zval* %{$varName}";
        $ir[] = "  store %struct.zval {$loadedVal}, %struct.zval* {$resultPtr}";

        return $resultPtr;
    }

    private function generateArrayAssignment(ArrayAssignment $arrayAssignment, array &$ir, array $globalVars): void
    {
        // Get the array pointer
        $arrayPtr = $this->generateExpression($arrayAssignment->arrayAccess->array, $ir, $globalVars);

        // Get the value pointer
        $valuePtr = $this->generateExpression($arrayAssignment->value, $ir, $globalVars);

        // Check if this is an array push (null index) or indexed assignment
        if ($arrayAssignment->arrayAccess->index === null) {
            // Array push - use php_array_append
            $ir[] = "  call void @php_array_append(%struct.zval* {$arrayPtr}, %struct.zval* {$valuePtr})";
        } elseif ($arrayAssignment->arrayAccess->index instanceof StringLiteral) {
            // For string keys, use php_array_set
            $globalName = "__str_const_" . md5($arrayAssignment->arrayAccess->index->value);
            $globalData = $globalVars[$globalName];
            $keyPtr = $this->getNextTempVariable();
            $ir[] = "  {$keyPtr} = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";
            $ir[] = "  call void @php_array_set(%struct.zval* {$arrayPtr}, i8* {$keyPtr}, %struct.zval* {$valuePtr})";
        } else {
            // For numeric keys, use php_array_set_by_index
            $indexPtr = $this->generateExpression($arrayAssignment->arrayAccess->index, $ir, $globalVars);
            $indexInt = $this->getNextTempVariable();
            $ir[] = "  {$indexInt} = call i32 @php_zval_to_int(%struct.zval* {$indexPtr})";
            $ir[] = "  call void @php_array_set_by_index(%struct.zval* {$arrayPtr}, i32 {$indexInt}, %struct.zval* {$valuePtr})";
        }

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
            $this->generateStatement($statement, $ir, $globalVars, true);
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
        // Handle builtin functions specially
        if ($funcCall->name === 'count') {
            $this->generateCountFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_values') {
            $this->generateArrayValuesFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'opendir') {
            $this->generateOpendirFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'readdir') {
            $this->generateReaddirFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'closedir') {
            $this->generateClosedirFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'preg_match') {
            $this->generatePregMatchFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'natsort') {
            $this->generateNatsortFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'print_r') {
            $this->generatePrintRFunctionCall($funcCall, $ir, $globalVars);
            return;
        }

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

    private function generateCountFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("count() expects exactly 1 argument");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Call php_array_size to get the count
        $countResult = $this->getNextTempVariable();
        $ir[] = "  {$countResult} = call i32 @php_array_size(%struct.zval* {$argPtr})";

        // Store result in a zval (we need to allocate and store, but since this is a statement, we just discard)
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_int(%struct.zval* {$resultPtr}, i32 {$countResult})";
        $ir[] = "";
    }

    private function generateArrayValuesFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("array_values() expects exactly 1 argument");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_values function
        $ir[] = "  call void @php_array_values(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateOpendirFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("opendir() expects exactly 1 argument");
        }

        // Generate the path argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_opendir function
        $ir[] = "  call void @php_opendir(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateReaddirFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("readdir() expects exactly 1 argument");
        }

        // Generate the handle argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_readdir function
        $ir[] = "  call void @php_readdir(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateClosedirFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("closedir() expects exactly 1 argument");
        }

        // Generate the handle argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_closedir function
        $ir[] = "  call void @php_closedir(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generatePregMatchFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("preg_match() expects exactly 2 arguments, got " . count($funcCall->arguments));
        }

        // Generate the pattern argument
        $patternPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the subject argument
        $subjectPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_preg_match function
        $ir[] = "  call void @php_preg_match(%struct.zval* {$patternPtr}, %struct.zval* {$subjectPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateNatsortFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("natsort() expects exactly 1 argument");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_natsort function
        $ir[] = "  call void @php_natsort(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generatePrintRFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("print_r() expects exactly 1 argument");
        }

        // Generate the value argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_print_r function
        $ir[] = "  call void @php_print_r(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArrayValuesExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("array_values() expects exactly 1 argument");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_values function
        $ir[] = "  call void @php_array_values(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateOpendirExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("opendir() expects exactly 1 argument");
        }

        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_opendir(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generateReaddirExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("readdir() expects exactly 1 argument");
        }

        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_readdir(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generateClosedirExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("closedir() expects exactly 1 argument");
        }

        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_closedir(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generatePregMatchExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("preg_match() expects exactly 2 arguments");
        }

        $patternPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
        $subjectPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_preg_match(%struct.zval* {$patternPtr}, %struct.zval* {$subjectPtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generateNatsortExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("natsort() expects exactly 1 argument");
        }

        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_natsort(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generatePrintRExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("print_r() expects exactly 1 argument");
        }

        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_print_r(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generateCountExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("count() expects exactly 1 argument");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Call php_array_size to get the count
        $countResult = $this->getNextTempVariable();
        $ir[] = "  {$countResult} = call i32 @php_array_size(%struct.zval* {$argPtr})";

        // Store result in a zval and return pointer
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_int(%struct.zval* {$resultPtr}, i32 {$countResult})";

        return $resultPtr;
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
            // Handle builtin functions specially
            if ($expression->name === 'count') {
                return $this->generateCountExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_values') {
                return $this->generateArrayValuesExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'opendir') {
                return $this->generateOpendirExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'readdir') {
                return $this->generateReaddirExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'closedir') {
                return $this->generateClosedirExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'preg_match') {
                return $this->generatePregMatchExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'natsort') {
                return $this->generateNatsortExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'print_r') {
                return $this->generatePrintRExpression($expression, $ir, $globalVars);
            }

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
            $callResult = $this->getNextTempVariable();
            $ir[] = "  {$callResult} = call %struct.zval @{$expression->name}({$argStr})";
            $ir[] = "  store %struct.zval {$callResult}, %struct.zval* {$result}";

            return $result;
        } elseif ($expression instanceof ArrayLiteral) {
            return $this->generateArrayLiteral($expression, $ir, $globalVars);
        } elseif ($expression instanceof ArrayAccess) {
            return $this->generateArrayAccess($expression, $ir, $globalVars);
        } elseif ($expression instanceof Assignment) {
            return $this->generateAssignmentExpression($expression, $ir, $globalVars);
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
                // String concatenation - convert both to strings
                $leftStr = $this->getNextTempVariable();
                $rightStr = $this->getNextTempVariable();
                $ir[] = "  {$leftStr} = call i8* @php_zval_to_string(%struct.zval* {$leftZval})";
                $ir[] = "  {$rightStr} = call i8* @php_zval_to_string(%struct.zval* {$rightZval})";

                // Concatenate strings using php_concat_strings
                $concatResult = $this->getNextTempVariable();
                $ir[] = "  {$concatResult} = call i8* @php_concat_strings(i8* {$leftStr}, i8* {$rightStr})";
                $ir[] = "  call void @php_zval_string(%struct.zval* {$result}, i8* {$concatResult})";
                break;
            case '==':
            case '!=':
            case '>':
            case '>=':
            case '<':
            case '<=':
                // Comparison - convert both to integers for comparison
                $leftInt = $this->getNextTempVariable();
                $rightInt = $this->getNextTempVariable();
                $ir[] = "  {$leftInt} = call i32 @php_zval_to_int(%struct.zval* {$leftZval})";
                $ir[] = "  {$rightInt} = call i32 @php_zval_to_int(%struct.zval* {$rightZval})";

                $compareResult = $this->getNextTempVariable();

                switch ($op->operator) {
                    case '==':
                        $ir[] = "  {$compareResult} = icmp eq i32 {$leftInt}, {$rightInt}";
                        break;
                    case '!=':
                        $ir[] = "  {$compareResult} = icmp ne i32 {$leftInt}, {$rightInt}";
                        break;
                    case '>':
                        $ir[] = "  {$compareResult} = icmp sgt i32 {$leftInt}, {$rightInt}";
                        break;
                    case '>=':
                        $ir[] = "  {$compareResult} = icmp sge i32 {$leftInt}, {$rightInt}";
                        break;
                    case '<':
                        $ir[] = "  {$compareResult} = icmp slt i32 {$leftInt}, {$rightInt}";
                        break;
                    case '<=':
                        $ir[] = "  {$compareResult} = icmp sle i32 {$leftInt}, {$rightInt}";
                        break;
                }

                $cmpBool = $this->getNextTempVariable();
                $ir[] = "  {$cmpBool} = zext i1 {$compareResult} to i32";
                $ir[] = "  call void @php_zval_bool(%struct.zval* {$result}, i32 {$cmpBool})";
                break;
            case '===':
            case '!==':
                // Strict comparison - use helper functions that check type and value
                if ($op->operator === '===') {
                    $ir[] = "  call void @php_zval_strict_eq(%struct.zval* {$leftZval}, %struct.zval* {$rightZval}, %struct.zval* {$result})";
                } else {
                    $ir[] = "  call void @php_zval_strict_ne(%struct.zval* {$leftZval}, %struct.zval* {$rightZval}, %struct.zval* {$result})";
                }
                break;
            default:
                throw new \RuntimeException("Unsupported binary operation: " . $op->operator);
        }

        return $result;
    }

    private function generateForStatement(ForStatement $forStmt, array &$ir, array $globalVars): void
    {
        // Create basic blocks for for loop
        static $blockCounter = 0;
        $loopHeaderBlock = "loop_header_" . ++$blockCounter;
        $loopBodyBlock = "loop_body_" . $blockCounter;
        $loopAfterBlock = "loop_after_" . $blockCounter;

        // Get all variable names from initializations
        $loopVarNames = [];
        foreach ($forStmt->initializations as $init) {
            if ($init instanceof Assignment) {
                $loopVarNames[] = $init->variable->name;
            }
        }

        // Declare all loop variables before any branches (so they dominate all uses)
        foreach ($loopVarNames as $loopVarName) {
            if (!isset($this->declaredVars[$loopVarName])) {
                $ir[] = "  %{$loopVarName} = alloca %struct.zval";
                $this->declaredVars[$loopVarName] = true;
            }
        }

        // Generate all initialization statements
        foreach ($forStmt->initializations as $init) {
            if ($init instanceof Assignment) {
                $varName = $init->variable->name;
                $valuePtr = $this->generateExpression($init->value, $ir, $globalVars);

                $value = $this->getNextTempVariable();
                $ir[] = "  {$value} = load %struct.zval, %struct.zval* {$valuePtr}";
                $ir[] = "  store %struct.zval {$value}, %struct.zval* %{$varName}";
            } else {
                $this->generateStatement($init, $ir, $globalVars);
            }
        }

        // Jump to loop header
        $ir[] = "  br label %{$loopHeaderBlock}";

        // Loop header block
        $ir[] = "{$loopHeaderBlock}:";
        if ($forStmt->condition) {
            $conditionPtr = $this->generateExpression($forStmt->condition, $ir, $globalVars);

            // Convert condition to integer for boolean check
            $condInt = $this->getNextTempVariable();
            $ir[] = "  {$condInt} = call i32 @php_zval_to_int(%struct.zval* {$conditionPtr})";

            // Check if condition is false
            $isFalse = $this->getNextTempVariable();
            $ir[] = "  {$isFalse} = icmp eq i32 {$condInt}, 0";

            // Branch if false to after loop block
            $ir[] = "  br i1 {$isFalse}, label %{$loopAfterBlock}, label %{$loopBodyBlock}";
        } else {
            // If no condition, loop infinitely
            $ir[] = "  br label %{$loopBodyBlock}";
        }

        // Loop body block
        $ir[] = "{$loopBodyBlock}:";
        foreach ($forStmt->body as $statement) {
            $this->generateStatement($statement, $ir, $globalVars);
        }

        // Generate all update statements
        foreach ($forStmt->updates as $update) {
            // Check if update is assignment or expression
            if ($update instanceof Statement && !$update instanceof ReturnStatement) {
                $this->generateStatement($update, $ir, $globalVars);
            } elseif ($update instanceof ReturnStatement) {
                // If it's a ReturnStatement wrapping an expression, just evaluate the expression
                if ($update->value) {
                    $this->generateExpression($update->value, $ir, $globalVars);
                }
            } elseif ($update instanceof Expression) {
                // If it's an expression, just evaluate it and discard result
                $this->generateExpression($update, $ir, $globalVars);
            }
        }

        // Jump back to loop header
        $ir[] = "  br label %{$loopHeaderBlock}";

        // After loop block
        $ir[] = "{$loopAfterBlock}:";
        $ir[] = "";
    }

    private function generateWhileStatement(WhileStatement $whileStmt, array &$ir, array $globalVars): void
    {
        // Create basic blocks for while loop
        static $blockCounter = 0;
        $loopHeaderBlock = "while_header_" . ++$blockCounter;
        $loopBodyBlock = "while_body_" . $blockCounter;
        $loopAfterBlock = "while_after_" . $blockCounter;

        // Jump to loop header
        $ir[] = "  br label %{$loopHeaderBlock}";

        // Loop header block - evaluate condition
        $ir[] = "{$loopHeaderBlock}:";
        $conditionPtr = $this->generateExpression($whileStmt->condition, $ir, $globalVars);

        // Convert condition to integer for boolean check
        $condInt = $this->getNextTempVariable();
        $ir[] = "  {$condInt} = call i32 @php_zval_to_int(%struct.zval* {$conditionPtr})";

        // Check if condition is false
        $isFalse = $this->getNextTempVariable();
        $ir[] = "  {$isFalse} = icmp eq i32 {$condInt}, 0";

        // Branch if false to after loop block, otherwise to body
        $ir[] = "  br i1 {$isFalse}, label %{$loopAfterBlock}, label %{$loopBodyBlock}";

        // Loop body block
        $ir[] = "{$loopBodyBlock}:";
        foreach ($whileStmt->body as $statement) {
            $this->generateStatement($statement, $ir, $globalVars);
        }

        // Jump back to loop header
        $ir[] = "  br label %{$loopHeaderBlock}";

        // After loop block
        $ir[] = "{$loopAfterBlock}:";
        $ir[] = "";
    }

    private function generateDoWhileStatement(DoWhileStatement $doWhileStmt, array &$ir, array $globalVars): void
    {
        // Create basic blocks for do-while loop
        static $blockCounter = 0;
        $loopBodyBlock = "dowhile_body_" . ++$blockCounter;
        $loopHeaderBlock = "dowhile_header_" . $blockCounter;
        $loopAfterBlock = "dowhile_after_" . $blockCounter;

        // Jump directly to body (do-while executes body at least once)
        $ir[] = "  br label %{$loopBodyBlock}";

        // Loop body block
        $ir[] = "{$loopBodyBlock}:";
        foreach ($doWhileStmt->body as $statement) {
            $this->generateStatement($statement, $ir, $globalVars);
        }

        // Jump to header to evaluate condition
        $ir[] = "  br label %{$loopHeaderBlock}";

        // Loop header block - evaluate condition
        $ir[] = "{$loopHeaderBlock}:";
        $conditionPtr = $this->generateExpression($doWhileStmt->condition, $ir, $globalVars);

        // Convert condition to integer for boolean check
        $condInt = $this->getNextTempVariable();
        $ir[] = "  {$condInt} = call i32 @php_zval_to_int(%struct.zval* {$conditionPtr})";

        // Check if condition is true (continue looping) or false (exit)
        $isTrue = $this->getNextTempVariable();
        $ir[] = "  {$isTrue} = icmp ne i32 {$condInt}, 0";

        // Branch if true back to body, otherwise to after loop
        $ir[] = "  br i1 {$isTrue}, label %{$loopBodyBlock}, label %{$loopAfterBlock}";

        // After loop block
        $ir[] = "{$loopAfterBlock}:";
        $ir[] = "";
    }

    private function generateForeachStatement(ForeachStatement $foreachStmt, array &$ir, array $globalVars, bool $inFunction = false): void
    {
        // Create basic blocks for foreach loop
        static $blockCounter = 0;
        $loopHeaderBlock = "foreach_header_" . ++$blockCounter;
        $loopBodyBlock = "foreach_body_" . $blockCounter;
        $loopAfterBlock = "foreach_after_" . $blockCounter;

        // Get the array expression
        $arrayPtr = $this->generateExpression($foreachStmt->array, $ir, $globalVars);

        // Create index variable (hidden from user)
        $indexVar = "%foreach_idx_" . $blockCounter;
        $ir[] = "  {$indexVar} = alloca i32";
        $ir[] = "  store i32 0, i32* {$indexVar}";

        // Get array size
        $arraySize = $this->getNextTempVariable();
        $ir[] = "  {$arraySize} = call i32 @php_array_size(%struct.zval* {$arrayPtr})";

        // Declare the value variable if not already declared
        $valueVarName = $foreachStmt->valueVar->name;
        if (!isset($this->declaredVars[$valueVarName])) {
            $ir[] = "  %{$valueVarName} = alloca %struct.zval";
            $this->declaredVars[$valueVarName] = true;
        }

        // Declare the key variable if present and not already declared
        $keyVarName = null;
        if ($foreachStmt->keyVar !== null) {
            $keyVarName = $foreachStmt->keyVar->name;
            if (!isset($this->declaredVars[$keyVarName])) {
                $ir[] = "  %{$keyVarName} = alloca %struct.zval";
                $this->declaredVars[$keyVarName] = true;
            }
        }

        // Jump to loop header
        $ir[] = "  br label %{$loopHeaderBlock}";

        // Loop header block - check if index < array_size
        $ir[] = "{$loopHeaderBlock}:";
        $currentIndex = $this->getNextTempVariable();
        $ir[] = "  {$currentIndex} = load i32, i32* {$indexVar}";

        $indexLessThanSize = $this->getNextTempVariable();
        $ir[] = "  {$indexLessThanSize} = icmp slt i32 {$currentIndex}, {$arraySize}";
        $ir[] = "  br i1 {$indexLessThanSize}, label %{$loopBodyBlock}, label %{$loopAfterBlock}";

        // Loop body block
        $ir[] = "{$loopBodyBlock}:";

        // Get array element at current index: $array[$currentIndex]
        $indexZval = $this->getNextTempVariable();
        $ir[] = "  {$indexZval} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_int(%struct.zval* {$indexZval}, i32 {$currentIndex})";

        $elemZval = $this->getNextTempVariable();
        $ir[] = "  {$elemZval} = alloca %struct.zval";
        $ir[] = "  call void @php_array_get(%struct.zval* {$elemZval}, %struct.zval* {$arrayPtr}, %struct.zval* {$indexZval})";

        // Store element in value variable
        $elemVal = $this->getNextTempVariable();
        $ir[] = "  {$elemVal} = load %struct.zval, %struct.zval* {$elemZval}";
        $ir[] = "  store %struct.zval {$elemVal}, %struct.zval* %{$valueVarName}";

        // If key variable is present, populate it
        if ($keyVarName !== null) {
            // Get the key from the array element
            $rawKeyPtr = $this->getNextTempVariable();
            $ir[] = "  {$rawKeyPtr} = call i8* @php_array_get_key(%struct.zval* {$arrayPtr}, i32 {$currentIndex})";

            // Check if key is NULL (numeric index) or a string
            $isNull = $this->getNextTempVariable();
            $ir[] = "  {$isNull} = icmp eq i8* {$rawKeyPtr}, null";

            // Create labels for conditional
            $keyIsNullBlock = "foreach_key_null_" . $blockCounter;
            $keyIsStringBlock = "foreach_key_str_" . $blockCounter;
            $keyDoneBlock = "foreach_key_done_" . $blockCounter;

            $ir[] = "  br i1 {$isNull}, label %{$keyIsNullBlock}, label %{$keyIsStringBlock}";

            // Key is null - use numeric index as string
            $ir[] = "{$keyIsNullBlock}:";
            $indexKeyStr = $this->getNextTempVariable();
            $ir[] = "  {$indexKeyStr} = call i8* @php_itoa(i32 {$currentIndex})";
            $ir[] = "  call void @php_zval_string(%struct.zval* %{$keyVarName}, i8* {$indexKeyStr})";
            $ir[] = "  br label %{$keyDoneBlock}";

            // Key is a string - use it directly
            $ir[] = "{$keyIsStringBlock}:";
            $ir[] = "  call void @php_zval_string(%struct.zval* %{$keyVarName}, i8* {$rawKeyPtr})";
            $ir[] = "  br label %{$keyDoneBlock}";

            // Done
            $ir[] = "{$keyDoneBlock}:";
        }

        // Generate body statements
        foreach ($foreachStmt->body as $statement) {
            $this->generateStatement($statement, $ir, $globalVars, $inFunction);
        }

        // Increment index
        $nextIndex = $this->getNextTempVariable();
        $ir[] = "  {$nextIndex} = add i32 {$currentIndex}, 1";
        $ir[] = "  store i32 {$nextIndex}, i32* {$indexVar}";

        // Jump back to header
        $ir[] = "  br label %{$loopHeaderBlock}";

        // After loop block
        $ir[] = "{$loopAfterBlock}:";
        $ir[] = "";
    }

    private function generateIfStatement(IfStatement $ifStmt, array &$ir, array $globalVars, bool $inFunction = false): void
    {
        // Generate condition
        $conditionPtr = $this->generateExpression($ifStmt->condition, $ir, $globalVars);

        // Convert condition to boolean
        $conditionVal = $this->getNextTempVariable();
        $ir[] = "  {$conditionVal} = load %struct.zval, %struct.zval* {$conditionPtr}";

        // Convert zval to integer (for boolean check - 0 is false, other values true)
        $condInt = $this->getNextTempVariable();
        $ir[] = "  {$condInt} = call i32 @php_zval_to_int(%struct.zval* {$conditionPtr})";

        // Check if condition is true
        $isTrue = $this->getNextTempVariable();
        $ir[] = "  {$isTrue} = icmp ne i32 {$condInt}, 0";

        // Create basic blocks (without leading %)
        static $blockCounter = 0;
        $thenBlock = "then_" . ++$blockCounter;
        $elseBlock = "else_" . $blockCounter;
        $mergeBlock = "merge_" . $blockCounter;

        // Branch to then or else block
        $ir[] = "  br i1 {$isTrue}, label %{$thenBlock}, label %{$elseBlock}";

        // Generate then block
        $ir[] = "{$thenBlock}:";
        $thenHasReturn = false;
        foreach ($ifStmt->thenBody as $statement) {
            $this->generateStatement($statement, $ir, $globalVars, $inFunction);
            if ($statement instanceof ReturnStatement) {
                $thenHasReturn = true;
            }
        }
        if (!$thenHasReturn) {
            $ir[] = "  br label %{$mergeBlock}";
        }

        // Generate else block
        $ir[] = "{$elseBlock}:";
        $elseHasReturn = false;
        foreach ($ifStmt->elseBody as $statement) {
            $this->generateStatement($statement, $ir, $globalVars, $inFunction);
            if ($statement instanceof ReturnStatement) {
                $elseHasReturn = true;
            }
        }
        if (!$elseHasReturn) {
            $ir[] = "  br label %{$mergeBlock}";
        }

        // Generate merge block (only if at least one branch continues)
        if (!$thenHasReturn || !$elseHasReturn) {
            $ir[] = "{$mergeBlock}:";
            $ir[] = "";
        }
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
        // For now, just return the variable name (we'll handle unique names later)
        // We'll fix this properly in the future
        return "%" . $varRef->name;
    }

    private function generateStringLiteral(StringLiteral $literal, array &$ir, array $globalVars): string
    {
        $globalName = "__str_const_" . md5($literal->value);
        $globalData = $globalVars[$globalName];

        // Generate unique pointer name to avoid conflicts in different blocks
        $ptrName = $this->getNextTempVariable();
        $ir[] = "  {$ptrName} = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";

        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_string(%struct.zval* {$result}, i8* {$ptrName})";
        return $result;
    }

    private function generateArrayLiteral(ArrayLiteral $arrayLit, array &$ir, array $globalVars): string
    {
        // For now, we'll create a simple array representation using an alloca'd pointer
        // This is a simplified approach - arrays are stored as pointers to zval arrays
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";

        // Call runtime function to create array with given size
        $sizeVal = count($arrayLit->elements);
        $ir[] = "  call void @php_array_create(%struct.zval* {$result}, i32 {$sizeVal})";

        // Add each element to the array
        foreach ($arrayLit->elements as $index => $element) {
            $elemPtr = $this->generateExpression($element, $ir, $globalVars);

            // Check if this element has a key (associative array)
            if (isset($arrayLit->keys[$index]) && $arrayLit->keys[$index] !== null) {
                // Associative array: key => value
                $keyExpr = $arrayLit->keys[$index];
                if ($keyExpr instanceof StringLiteral) {
                    // Generate the key string
                    $globalName = "__str_const_" . md5($keyExpr->value);
                    $globalData = $globalVars[$globalName];
                    $keyPtr = $this->getNextTempVariable();
                    $ir[] = "  {$keyPtr} = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";
                    // Call php_array_set with key
                    $ir[] = "  call void @php_array_set(%struct.zval* {$result}, i8* {$keyPtr}, %struct.zval* {$elemPtr})";
                } else {
                    // For non-string keys, fall back to append
                    $ir[] = "  call void @php_array_append(%struct.zval* {$result}, %struct.zval* {$elemPtr})";
                }
            } else {
                // Numeric array: just append
                $ir[] = "  call void @php_array_append(%struct.zval* {$result}, %struct.zval* {$elemPtr})";
            }
        }

        return $result;
    }

    private function generateArrayAccess(ArrayAccess $arrayAccess, array &$ir, array $globalVars): string
    {
        // Generate the array expression first
        $arrayPtr = $this->generateExpression($arrayAccess->array, $ir, $globalVars);

        // Allocate result zval
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";

        // Check if this is an array push access (null index) - shouldn't happen in read context
        // but we handle it gracefully
        if ($arrayAccess->index === null) {
            // For null index in read context, return the array itself
            // This is a fallback - ideally array push should only be used in assignment
            return $arrayPtr;
        }

        // Generate the index expression
        $indexPtr = $this->generateExpression($arrayAccess->index, $ir, $globalVars);

        // Call runtime function to get array element
        $ir[] = "  call void @php_array_get(%struct.zval* {$result}, %struct.zval* {$arrayPtr}, %struct.zval* {$indexPtr})";

        return $result;
    }

    private function escapeString(string $str): string
    {
        $replacements = [
            '\\' => '\\\\',
            '"' => '\\"',
            '\n' => '\\n',
            '\r' => '\\r',
            '\t' => '\\t',
            '\0' => '\\0',
            '\b' => '\\b',
            '\f' => '\\f',
        ];

        return strtr($str, $replacements);
    }

    private function getStringLengthForLLVM(string $str): int
    {
        // Calculate length of the escaped string content (including escape sequences like \n)
        // but not the null terminator - we'll add that separately in generate()
        $escapedStr = $this->escapeString($str);
        return strlen($escapedStr) + 1; // +1 for null terminator
    }
}
