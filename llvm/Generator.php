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
use PhpCompiler\AST\DeclareStatement;
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

    /**
     * Track variable types (class names for objects)
     * @var array<string, string>
     */
    private array $variableTypes = [];

    /**
     * Stack to track loop header blocks for continue statements
     * @var string[]
     */
    private array $loopHeaderStack = [];

    /**
     * Stack to track loop end blocks for break statements
     * @var string[]
     */
    private array $loopEndStack = [];

    /**
     * Class definitions for method lookup
     * @var array<string, ClassDefinition>
     */
    private array $classDefinitions = [];

    /**
     * Track local variables in current function scope for cleanup
     * @var array<string, bool>
     */
    private array $functionLocalVars = [];

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
        $ir[] = "declare void @php_zval_double(%struct.zval*, double)";
        $ir[] = "declare void @php_zval_string(%struct.zval*, i8*)";
        $ir[] = "declare void @php_zval_string_literal(%struct.zval*, i8*)";
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
         $ir[] = "declare void @php_array_change_key_case(%struct.zval*, i32, %struct.zval*)";
         $ir[] = "declare void @php_array_chunk(%struct.zval*, i32, i32, %struct.zval*)";
         $ir[] = "declare void @php_array_column(%struct.zval*, %struct.zval*, %struct.zval*)";
         $ir[] = "declare void @php_array_combine(%struct.zval*, %struct.zval*, %struct.zval*)";
         $ir[] = "declare void @php_array_fill(i32, i32, %struct.zval*, %struct.zval*)";
         $ir[] = "declare void @php_array_fill_keys(%struct.zval*, %struct.zval*, %struct.zval*)";
         $ir[] = "declare void @php_array_merge(%struct.zval*, %struct.zval*, %struct.zval*)";
         $ir[] = "declare void @php_array_merge_recursive(%struct.zval*, %struct.zval*, %struct.zval*)";
         $ir[] = "declare void @php_array_pad(%struct.zval*, i32, %struct.zval*, %struct.zval*)";
         $ir[] = "declare i32 @php_array_push(%struct.zval*, %struct.zval*)";
         $ir[] = "declare i32 @php_array_unshift(%struct.zval*, %struct.zval*)";
         $ir[] = "declare void @php_array_pop(%struct.zval*, %struct.zval*)";
         $ir[] = "declare void @php_array_shift(%struct.zval*, %struct.zval*)";
         $ir[] = "declare void @php_array_slice(%struct.zval*, i32, i32, i32, %struct.zval*)";
         $ir[] = "declare void @php_array_splice(%struct.zval*, i32, i32, %struct.zval*, %struct.zval*)";
         $ir[] = "declare void @php_opendir(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_readdir(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_closedir(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_preg_match(%struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_natsort(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_print_r(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_zval_strict_ne(%struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_zval_strict_eq(%struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_str_repeat(%struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_trim(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_file_exists(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_shell_exec(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_pathinfo(%struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_rename(%struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_unlink(%struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_str_replace(%struct.zval*, %struct.zval*, %struct.zval*, %struct.zval*)";
        $ir[] = "declare void @php_object_create(%struct.zval*, i8*)";
        $ir[] = "declare void @php_object_property_get(%struct.zval*, %struct.zval*, i8*)";
        $ir[] = "declare void @php_object_property_set(%struct.zval*, i8*, %struct.zval*)";
        $ir[] = "declare void @exit(i32) noreturn";
        $ir[] = "; Reference counting functions";
        $ir[] = "declare void @php_zval_copy(%struct.zval*)";
        $ir[] = "declare void @php_zval_destroy(%struct.zval*)";
         $ir[] = "; Type checking functions";
         $ir[] = "declare i32 @php_is_int(%struct.zval*)";
         $ir[] = "declare i32 @php_isset(%struct.zval*)";
         $ir[] = "declare void @php_unset(%struct.zval*)";
         $ir[] = "declare i32 @php_empty(%struct.zval*)";
         $ir[] = "declare void @php_gettype(%struct.zval*, %struct.zval*)";
         $ir[] = "declare i32 @php_settype(%struct.zval*, i8*)";
         $ir[] = "; Variable function call support";
         $ir[] = "declare void @php_variable_call(%struct.zval*, %struct.zval*, i32, %struct.zval*)";
         $ir[] = "";

        // First pass: collect class definitions for method lookup
        foreach ($this->statements as $statement) {
            if ($statement instanceof ClassDefinition) {
                $this->classDefinitions[$statement->name] = $statement;
            }
        }

        // Separate statements into function definitions, class definitions, and other statements
        $functionDefinitions = [];
        $classDefinitions = [];
        $otherStatements = [];

        foreach ($this->statements as $statement) {
            if ($statement instanceof FunctionDefinition) {
                $functionDefinitions[] = $statement;
            } elseif ($statement instanceof ClassDefinition) {
                $classDefinitions[] = $statement;
            } else {
                $otherStatements[] = $statement;
            }
        }

        // Collect all global string constants
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

        // Add global string constants for function names (for variable function call registration)
        foreach ($functionDefinitions as $funcDef) {
            $globalName = "__func_name_" . md5($funcDef->name);
            $escapedValue = $this->escapeString($funcDef->name);
            $funcNameLen = strlen($funcDef->name) + 1; // +1 for null terminator
            $ir[] = "; Function name constant for {$funcDef->name}";
            $ir[] = "@{$globalName} = private unnamed_addr constant [{$funcNameLen} x i8] c\"{$escapedValue}\\00\"";
            $ir[] = "";
        }

        // Generate function definitions
        foreach ($functionDefinitions as $funcDef) {
            $this->generateStatement($funcDef, $ir, $globalVars);
        }

        // Declare function registration helpers
        $ir[] = "; Function registration helpers for variable function calls";
        $ir[] = "declare void @php_register_function_zval_0(i8*, %struct.zval ()*)";
        $ir[] = "declare void @php_register_function_zval_1(i8*, %struct.zval (%struct.zval)*)";
        $ir[] = "declare void @php_register_function_zval_2(i8*, %struct.zval (%struct.zval, %struct.zval)*)";
        $ir[] = "declare void @php_register_function_zval_3(i8*, %struct.zval (%struct.zval, %struct.zval, %struct.zval)*)";
        $ir[] = "declare void @php_register_function_zval_4(i8*, %struct.zval (%struct.zval, %struct.zval, %struct.zval, %struct.zval)*)";
        $ir[] = "";

        // Generate class definitions (which generate method functions)
        foreach ($classDefinitions as $classDef) {
            $this->generateStatement($classDef, $ir, $globalVars);
        }

         // Define main function
        $ir[] = "define i32 @main() {";
        $ir[] = "entry:";

        // Pre-declare all variables at the entry block to ensure they dominate all uses
        $this->collectAndDeclareVariables($otherStatements, $ir);

        // Register user-defined functions for variable function call support
        foreach ($functionDefinitions as $funcDef) {
            $globalName = "__func_name_" . md5($funcDef->name);
            $funcNameLen = strlen($funcDef->name) + 1;
            $ir[] = "  %{$globalName}_ptr = getelementptr inbounds [{$funcNameLen} x i8], [{$funcNameLen} x i8]* @{$globalName}, i64 0, i64 0";
            $paramCount = count($funcDef->parameters);
            $regFunc = match ($paramCount) {
                0 => "php_register_function_zval_0",
                1 => "php_register_function_zval_1",
                2 => "php_register_function_zval_2",
                3 => "php_register_function_zval_3",
                default => "php_register_function_zval_4"
            };
            // Build LLVM function type signature: %struct.zval (%struct.zval, %struct.zval, ...)*
            $paramTypes = ($paramCount > 0) ? implode(', ', array_fill(0, $paramCount, '%struct.zval')) : '';
            $funcType = "{$paramTypes}";
            $funcTypeStr = $paramTypes ? "{$funcType})*" : ")*";
            $ir[] = "  call void @{$regFunc}(i8* %{$globalName}_ptr, %struct.zval ({$funcTypeStr} @{$funcDef->name})";
        }
        if (count($functionDefinitions) > 0) {
            $ir[] = "";
        }

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
        } elseif ($node instanceof ClassDefinition) {
            // Collect globals from method bodies
            foreach ($node->methods as $method) {
                foreach ($method->body as $statement) {
                    $this->collectGlobals($statement, $globalVars);
                }
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
        } elseif ($node instanceof BooleanLiteral) {
            // Boolean literals don't have string constants to collect
        } elseif ($node instanceof NullLiteral) {
            // Null literals don't have string constants to collect
        } elseif ($node instanceof NewExpression) {
            // Collect class name as a string constant
            $className = $node->className;
            $globalName = "__str_const_" . md5($className);
            if (!isset($globalVars[$globalName])) {
                $escapedValue = $this->escapeString($className);
                $globalVars[$globalName] = [
                    'value' => $className,
                    'escapedValue' => $escapedValue,
                    'length' => strlen($className)
                ];
            }
        } elseif ($node instanceof PropertyAccess) {
            // Collect property name as a string constant
            $propName = $node->propertyName;
            $globalName = "__str_const_" . md5($propName);
            if (!isset($globalVars[$globalName])) {
                $escapedValue = $this->escapeString($propName);
                $globalVars[$globalName] = [
                    'value' => $propName,
                    'escapedValue' => $escapedValue,
                    'length' => strlen($propName)
                ];
            }
            // Also collect from the object expression
            $this->collectGlobals($node->object, $globalVars);
        } elseif ($node instanceof MethodCall) {
            // Collect from the object expression and arguments
            $this->collectGlobals($node->object, $globalVars);
            foreach ($node->arguments as $arg) {
                $this->collectGlobals($arg, $globalVars);
            }
        } elseif ($node instanceof TernaryExpression) {
            // Collect from condition, ifTrue (if present), and ifFalse expressions
            $this->collectGlobals($node->condition, $globalVars);
            if ($node->ifTrue !== null) {
                $this->collectGlobals($node->ifTrue, $globalVars);
            }
            $this->collectGlobals($node->ifFalse, $globalVars);
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

    /**
     * Pre-collect all variable names from statements and declare them in the entry block.
     * This ensures all variables dominate their uses (LLVM SSA requirement).
     */
    private function collectAndDeclareVariables(array $statements, array &$ir): void
    {
        $varNames = [];
        $this->collectVariablesFromStatements($statements, $varNames);

        // Declare all collected variables in the entry block
        foreach ($varNames as $varName => $_) {
            if (!isset($this->declaredVars[$varName])) {
                $ir[] = "  %{$varName} = alloca %struct.zval";
                $this->declaredVars[$varName] = true;
            }
        }
    }

    /**
     * Recursively collect variable names from statements.
     * @param Statement[] $statements
     * @param array<string, bool> $varNames
     */
    private function collectVariablesFromStatements(array $statements, array &$varNames): void
    {
        foreach ($statements as $statement) {
            $this->collectVariablesFromNode($statement, $varNames);
        }
    }

    /**
     * Recursively collect variable names from an AST node.
     * @param array<string, bool> $varNames
     */
    private function collectVariablesFromNode(Node $node, array &$varNames): void
    {
        if ($node instanceof Assignment) {
            // For property assignments ($obj->prop = value), collect variables from the object expression
            if ($node->variable instanceof PropertyAccess) {
                $this->collectVariablesFromNode($node->variable->object, $varNames);
            } else {
                $varNames[$node->variable->name] = true;
            }
            $this->collectVariablesFromNode($node->value, $varNames);
        } elseif ($node instanceof ArrayAssignment) {
            $this->collectVariablesFromNode($node->arrayAccess, $varNames);
            $this->collectVariablesFromNode($node->value, $varNames);
        } elseif ($node instanceof IfStatement) {
            $this->collectVariablesFromNode($node->condition, $varNames);
            foreach ($node->thenBody as $stmt) {
                $this->collectVariablesFromNode($stmt, $varNames);
            }
            foreach ($node->elseBody as $stmt) {
                $this->collectVariablesFromNode($stmt, $varNames);
            }
        } elseif ($node instanceof ForStatement) {
            foreach ($node->initializations as $init) {
                $this->collectVariablesFromNode($init, $varNames);
            }
            if ($node->condition) {
                $this->collectVariablesFromNode($node->condition, $varNames);
            }
            foreach ($node->updates as $update) {
                $this->collectVariablesFromNode($update, $varNames);
            }
            foreach ($node->body as $stmt) {
                $this->collectVariablesFromNode($stmt, $varNames);
            }
        } elseif ($node instanceof WhileStatement) {
            $this->collectVariablesFromNode($node->condition, $varNames);
            foreach ($node->body as $stmt) {
                $this->collectVariablesFromNode($stmt, $varNames);
            }
        } elseif ($node instanceof DoWhileStatement) {
            $this->collectVariablesFromNode($node->condition, $varNames);
            foreach ($node->body as $stmt) {
                $this->collectVariablesFromNode($stmt, $varNames);
            }
        } elseif ($node instanceof ForeachStatement) {
            $this->collectVariablesFromNode($node->array, $varNames);
            $varNames[$node->valueVar->name] = true;
            if ($node->keyVar !== null) {
                $varNames[$node->keyVar->name] = true;
            }
            foreach ($node->body as $stmt) {
                $this->collectVariablesFromNode($stmt, $varNames);
            }
        } elseif ($node instanceof FunctionDefinition) {
            // Don't collect variables from function bodies - they're in a different scope
            // Parameters are local to the function
        } elseif ($node instanceof FunctionCall) {
            foreach ($node->arguments as $arg) {
                $this->collectVariablesFromNode($arg, $varNames);
            }
        } elseif ($node instanceof BinaryOperation) {
            $this->collectVariablesFromNode($node->left, $varNames);
            $this->collectVariablesFromNode($node->right, $varNames);
        } elseif ($node instanceof UnaryOperation) {
            $this->collectVariablesFromNode($node->operand, $varNames);
        } elseif ($node instanceof ArrayAccess) {
            $this->collectVariablesFromNode($node->array, $varNames);
            if ($node->index !== null) {
                $this->collectVariablesFromNode($node->index, $varNames);
            }
        } elseif ($node instanceof ArrayLiteral) {
            foreach ($node->elements as $element) {
                $this->collectVariablesFromNode($element, $varNames);
            }
            foreach ($node->keys as $key) {
                if ($key !== null) {
                    $this->collectVariablesFromNode($key, $varNames);
                }
            }
        } elseif ($node instanceof ReturnStatement) {
            if ($node->value !== null) {
                $this->collectVariablesFromNode($node->value, $varNames);
            }
        } elseif ($node instanceof EchoStatement) {
            foreach ($node->expressions as $expr) {
                $this->collectVariablesFromNode($expr, $varNames);
            }
        } elseif ($node instanceof ExpressionStatement) {
            $this->collectVariablesFromNode($node->expression, $varNames);
        } elseif ($node instanceof TernaryExpression) {
            $this->collectVariablesFromNode($node->condition, $varNames);
            if ($node->ifTrue !== null) {
                $this->collectVariablesFromNode($node->ifTrue, $varNames);
            }
            $this->collectVariablesFromNode($node->ifFalse, $varNames);
        }
        // StringLiteral, IntegerLiteral, BooleanLiteral, NullLiteral, Constant, VariableReference
        // don't need special handling as they don't introduce new variables
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
        } elseif ($statement instanceof ContinueStatement) {
            // Continue statement - jump to the current loop header
            if (empty($this->loopHeaderStack)) {
                throw new \RuntimeException("Continue statement outside of loop at line {$statement->line}");
            }
            $loopHeader = end($this->loopHeaderStack);
            $ir[] = "  br label %{$loopHeader}";
        } elseif ($statement instanceof BreakStatement) {
            // Break statement - jump to the current loop end
            if (empty($this->loopEndStack)) {
                throw new \RuntimeException("Break statement outside of loop at line {$statement->line}");
            }
            $loopEnd = end($this->loopEndStack);
            $ir[] = "  br label %{$loopEnd}";
        } elseif ($statement instanceof ExpressionStatement) {
            // Expression statement - evaluate the expression and discard the result
            // Special handling for unset() which is a statement-level construct
            if ($statement->expression instanceof FunctionCall && $statement->expression->name === 'unset') {
                $this->generateUnsetFunctionCall($statement->expression, $ir, $globalVars);
            } else {
                $this->generateExpression($statement->expression, $ir, $globalVars);
            }
        } elseif ($statement instanceof ClassDefinition) {
            // Store class definition for method lookup
            $this->classDefinitions[$statement->name] = $statement;

            // Generate method functions
            foreach ($statement->methods as $method) {
                $this->generateMethodFunction($statement->name, $method, $ir, $globalVars);
            }
        } elseif ($statement instanceof PropertyDeclaration) {
            // Property declarations inside classes are handled at parse time
            // Default values are set when 'new' creates the object
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

    /**
     * Generate cleanup code to destroy all local variables in the current function scope.
     * This should be called before returning from a function.
     */
    private function generateLocalVarCleanup(array &$ir): void
    {
        foreach ($this->functionLocalVars as $varName => $_) {
            $ir[] = "  call void @php_zval_destroy(%struct.zval* %{$varName})";
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

        // Clean up local variables before returning (only in function scope)
        if ($inFunction) {
            $this->generateLocalVarCleanup($ir);
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
        // Check if this is a property assignment: $obj->property = value
        if ($assignment->variable instanceof PropertyAccess) {
            $this->generatePropertyAssignment($assignment, $ir, $globalVars);
            return;
        }

        // All variables are stored as zval structs on the stack
        $varName = $assignment->variable->name;

        // Variables are pre-declared in the entry block via collectAndDeclareVariables
        // If somehow not declared yet, declare it here as a fallback
        if (!isset($this->declaredVars[$varName])) {
            $ir[] = "  %{$varName} = alloca %struct.zval";
            $this->declaredVars[$varName] = true;
        }

        // Track variable type if assigning a new object
        if ($assignment->value instanceof NewExpression) {
            $this->variableTypes[$varName] = $assignment->value->className;
        }

        if ($assignment->operator === '=') {
            // Simple assignment
            // First, destroy the old value if this variable already existed
            if (isset($this->declaredVars[$varName])) {
                $ir[] = "  call void @php_zval_destroy(%struct.zval* %{$varName})";
            }

            $valuePtr = $this->generateExpression($assignment->value, $ir, $globalVars);

            // Increment refcount on the new value
            $ir[] = "  call void @php_zval_copy(%struct.zval* {$valuePtr})";

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
        // Check if this is a property assignment: $obj->property = value
        if ($assignment->variable instanceof PropertyAccess) {
            return $this->generatePropertyAssignmentExpression($assignment, $ir, $globalVars);
        }

        // All variables are stored as zval structs on the stack
        $varName = $assignment->variable->name;

        // Variables are pre-declared in the entry block via collectAndDeclareVariables
        // If somehow not declared yet, declare it here as a fallback
        if (!isset($this->declaredVars[$varName])) {
            $ir[] = "  %{$varName} = alloca %struct.zval";
            $this->declaredVars[$varName] = true;
        }

        // Track variable type if assigning a new object
        if ($assignment->value instanceof NewExpression) {
            $this->variableTypes[$varName] = $assignment->value->className;
        }

        // Generate the value expression
        $valuePtr = $this->generateExpression($assignment->value, $ir, $globalVars);

        if ($assignment->operator === '=') {
            // Simple assignment
            // First, destroy the old value if this variable already existed
            if (isset($this->declaredVars[$varName])) {
                $ir[] = "  call void @php_zval_destroy(%struct.zval* %{$varName})";
            }

            // Increment refcount on the new value
            $ir[] = "  call void @php_zval_copy(%struct.zval* {$valuePtr})";

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
        // Save and reset declaredVars for function scope
        $savedDeclaredVars = $this->declaredVars;
        $savedFunctionLocalVars = $this->functionLocalVars;
        $this->declaredVars = [];
        $this->functionLocalVars = [];

        // All functions return zval and parameters are zval
        $paramTypes = implode(', ', array_fill(0, count($funcDef->parameters), '%struct.zval'));
        $ir[] = "define %struct.zval @{$funcDef->name}({$paramTypes}) {";
        $ir[] = "entry:";

        // Add parameters to declared vars (they're allocated separately)
        // Parameters are NOT in functionLocalVars - they are caller's responsibility
        foreach ($funcDef->parameters as $param) {
            $this->declaredVars[$param->name] = true;
        }

        // Pre-declare all local variables in the entry block
        // Track which variables are local (not parameters)
        $localVarNames = [];
        $this->collectVariablesFromStatements($funcDef->body, $localVarNames);

        // Filter out parameter names from local vars
        $paramNames = array_map(fn($p) => $p->name, $funcDef->parameters);
        foreach ($localVarNames as $varName => $_) {
            if (!in_array($varName, $paramNames)) {
                $this->functionLocalVars[$varName] = true;
            }
        }

        $this->collectAndDeclareVariables($funcDef->body, $ir);

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
            // Clean up local variables before implicit return
            $this->generateLocalVarCleanup($ir);

            $nullResult = $this->getNextTempVariable();
            $ir[] = "  {$nullResult} = alloca %struct.zval";
            $ir[] = "  call void @php_zval_null(%struct.zval* {$nullResult})";
            $loaded = $this->getNextTempVariable();
            $ir[] = "  {$loaded} = load %struct.zval, %struct.zval* {$nullResult}";
            $ir[] = "  ret %struct.zval {$loaded}";
        }

        $ir[] = "}";
        $ir[] = "";

        // Restore declaredVars and functionLocalVars for parent scope
        $this->declaredVars = $savedDeclaredVars;
        $this->functionLocalVars = $savedFunctionLocalVars;
    }

    private function generateMethodFunction(string $className, MethodDefinition $method, array &$ir, array $globalVars): void
    {
        // Save and reset declaredVars for method scope
        $savedDeclaredVars = $this->declaredVars;
        $savedFunctionLocalVars = $this->functionLocalVars;
        $this->declaredVars = [];
        $this->functionLocalVars = [];

        // Method name is prefixed with class name: ClassName_methodName
        $methodFuncName = $className . '_' . $method->name;

        // All methods return zval and parameters are zval
        // First parameter is always $this (the object pointer)
        $paramTypes = ['%struct.zval*']; // $this parameter
        foreach ($method->parameters as $param) {
            $paramTypes[] = '%struct.zval';
        }
        $paramTypesStr = implode(', ', $paramTypes);

        $ir[] = "define %struct.zval @{$methodFuncName}({$paramTypesStr}) {";
        $ir[] = "entry:";

        // Add parameters and $this to declared vars (they're allocated separately)
        // Parameters are NOT in functionLocalVars - they are caller's responsibility
        $this->declaredVars['this'] = true;
        foreach ($method->parameters as $param) {
            $this->declaredVars[$param->name] = true;
        }

        // Pre-declare all local variables in the entry block
        // Track which variables are local (not parameters or $this)
        $localVarNames = [];
        $this->collectVariablesFromStatements($method->body, $localVarNames);

        // Filter out parameter names and 'this' from local vars
        $paramNames = array_map(fn($p) => $p->name, $method->parameters);
        $paramNames[] = 'this';
        foreach ($localVarNames as $varName => $_) {
            if (!in_array($varName, $paramNames)) {
                $this->functionLocalVars[$varName] = true;
            }
        }

        $this->collectAndDeclareVariables($method->body, $ir);

        // Allocate stack space for $this parameter
        $ir[] = "  %this = alloca %struct.zval*";
        $ir[] = "  store %struct.zval* %0, %struct.zval** %this";

        // Allocate stack space for other parameters
        foreach ($method->parameters as $i => $param) {
            $ir[] = "  %{$param->name} = alloca %struct.zval";
            $ir[] = "  store %struct.zval %" . ($i + 1) . ", %struct.zval* %{$param->name}";
        }

        // Generate method body statements
        foreach ($method->body as $statement) {
            $this->generateStatement($statement, $ir, $globalVars, true);
        }

        // Check if method has explicit return
        $hasReturn = false;
        foreach ($method->body as $statement) {
            if ($statement instanceof ReturnStatement) {
                $hasReturn = true;
                break;
            }
        }

        // If no return statement, return null
        if (!$hasReturn) {
            // Clean up local variables before implicit return
            $this->generateLocalVarCleanup($ir);

            $nullResult = $this->getNextTempVariable();
            $ir[] = "  {$nullResult} = alloca %struct.zval";
            $ir[] = "  call void @php_zval_null(%struct.zval* {$nullResult})";
            $loaded = $this->getNextTempVariable();
            $ir[] = "  {$loaded} = load %struct.zval, %struct.zval* {$nullResult}";
            $ir[] = "  ret %struct.zval {$loaded}";
        }

        $ir[] = "}";
        $ir[] = "";

        // Restore declaredVars and functionLocalVars for parent scope
        $this->declaredVars = $savedDeclaredVars;
        $this->functionLocalVars = $savedFunctionLocalVars;
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
        } elseif ($funcCall->name === 'array_change_key_case') {
            $this->generateArrayChangeKeyCaseFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_chunk') {
            $this->generateArrayChunkFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_column') {
            $this->generateArrayColumnFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_combine') {
            $this->generateArrayCombineFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_fill') {
            $this->generateArrayFillFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_fill_keys') {
            $this->generateArrayFillKeysFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_merge') {
            $this->generateArrayMergeFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_merge_recursive') {
            $this->generateArrayMergeRecursiveFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_pad') {
            $this->generateArrayPadFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_push') {
            $this->generateArrayPushFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_unshift') {
            $this->generateArrayUnshiftFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_pop') {
            $this->generateArrayPopFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_shift') {
            $this->generateArrayShiftFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_slice') {
            $this->generateArraySliceFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'array_splice') {
            $this->generateArraySpliceFunctionCall($funcCall, $ir, $globalVars);
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
        } elseif ($funcCall->name === 'var_dump') {
            $this->generateVarDumpFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'str_repeat') {
            $this->generateStrRepeatFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'trim') {
            $this->generateTrimFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'file_exists') {
            $this->generateFileExistsFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'shell_exec') {
            $this->generateShellExecFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'pathinfo') {
            $this->generatePathinfoFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'rename') {
            $this->generateRenameFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'unlink') {
            $this->generateUnlinkFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'str_replace') {
            $this->generateStrReplaceFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'exit') {
            $this->generateExitFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'is_int') {
            $this->generateIsIntFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'isset') {
            $this->generateIssetFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'unset') {
            $this->generateUnsetFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'empty') {
            $this->generateEmptyFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'gettype') {
            $this->generateGettypeFunctionCall($funcCall, $ir, $globalVars);
            return;
        } elseif ($funcCall->name === 'settype') {
            $this->generateSettypeFunctionCall($funcCall, $ir, $globalVars);
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

    private function generateArrayColumnFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("array_column() expects exactly 2 arguments");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the column key argument
        $columnKeyPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_column function
        $ir[] = "  call void @php_array_column(%struct.zval* {$argPtr}, %struct.zval* {$columnKeyPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArrayCombineFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("array_combine() expects exactly 2 arguments");
        }

        // Generate the keys array argument
        $keysPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the values array argument
        $valuesPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_combine function
        $ir[] = "  call void @php_array_combine(%struct.zval* {$keysPtr}, %struct.zval* {$valuesPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArrayFillFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 3) {
            throw new \RuntimeException("array_fill() expects exactly 3 arguments");
        }

        // Generate the start_index argument
        $startIndexPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
        $startIndexInt = $this->getNextTempVariable();
        $ir[] = "  {$startIndexInt} = call i32 @php_zval_to_int(%struct.zval* {$startIndexPtr})";

        // Generate the num argument
        $numPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $numInt = $this->getNextTempVariable();
        $ir[] = "  {$numInt} = call i32 @php_zval_to_int(%struct.zval* {$numPtr})";

        // Generate the value argument
        $valuePtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_fill function
        $ir[] = "  call void @php_array_fill(i32 {$startIndexInt}, i32 {$numInt}, %struct.zval* {$valuePtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArrayFillKeysFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("array_fill_keys() expects exactly 2 arguments");
        }

        // Generate the keys array argument
        $keysPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the value argument
        $valuePtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_fill_keys function
        $ir[] = "  call void @php_array_fill_keys(%struct.zval* {$keysPtr}, %struct.zval* {$valuePtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArrayMergeFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) < 2) {
            throw new \RuntimeException("array_merge() expects at least 2 arguments");
        }

        // For simplicity, handle exactly 2 arrays (common case)
        // Generate the first array argument
        $arr1Ptr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the second array argument
        $arr2Ptr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_merge function
        $ir[] = "  call void @php_array_merge(%struct.zval* {$arr1Ptr}, %struct.zval* {$arr2Ptr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArrayMergeRecursiveFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) < 2) {
            throw new \RuntimeException("array_merge_recursive() expects at least 2 arguments");
        }

        // Generate the first array argument
        $arr1Ptr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the second array argument
        $arr2Ptr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_merge_recursive function
        $ir[] = "  call void @php_array_merge_recursive(%struct.zval* {$arr1Ptr}, %struct.zval* {$arr2Ptr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArrayPadFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 3) {
            throw new \RuntimeException("array_pad() expects exactly 3 arguments");
        }

        // Generate the array argument
        $arrPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the size argument
        $sizePtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $sizeInt = $this->getNextTempVariable();
        $ir[] = "  {$sizeInt} = call i32 @php_zval_to_int(%struct.zval* {$sizePtr})";

        // Generate the pad value argument
        $padValuePtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_pad function
        $ir[] = "  call void @php_array_pad(%struct.zval* {$arrPtr}, i32 {$sizeInt}, %struct.zval* {$padValuePtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArrayPushFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) < 2) {
            throw new \RuntimeException("array_push() expects at least 2 arguments");
        }

        // First argument must be a variable reference (the array to modify)
        $arrayArg = $funcCall->arguments[0];
        if (!$arrayArg instanceof VariableReference) {
            throw new \RuntimeException("array_push() first argument must be a variable");
        }

        $arrayVarName = $arrayArg->name;
        $arrayPtr = "%{$arrayVarName}";

        // Push each value to the array
        for ($i = 1; $i < count($funcCall->arguments); $i++) {
            $valuePtr = $this->generateExpression($funcCall->arguments[$i], $ir, $globalVars);
            $ir[] = "  call i32 @php_array_push(%struct.zval* {$arrayPtr}, %struct.zval* {$valuePtr})";
        }
        $ir[] = "";
    }

    private function generateArrayUnshiftFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) < 2) {
            throw new \RuntimeException("array_unshift() expects at least 2 arguments");
        }

        // First argument must be a variable reference (the array to modify)
        $arrayArg = $funcCall->arguments[0];
        if (!$arrayArg instanceof VariableReference) {
            throw new \RuntimeException("array_unshift() first argument must be a variable");
        }

        $arrayVarName = $arrayArg->name;
        $arrayPtr = "%{$arrayVarName}";

        // Unshift each value to the beginning of the array (in order, so last one ends up first)
        for ($i = count($funcCall->arguments) - 1; $i >= 1; $i--) {
            $valuePtr = $this->generateExpression($funcCall->arguments[$i], $ir, $globalVars);
            $ir[] = "  call i32 @php_array_unshift(%struct.zval* {$arrayPtr}, %struct.zval* {$valuePtr})";
        }
        $ir[] = "";
    }

    private function generateArrayPopFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("array_pop() expects exactly 1 argument");
        }

        // First argument must be a variable reference (the array to modify)
        $arrayArg = $funcCall->arguments[0];
        if (!$arrayArg instanceof VariableReference) {
            throw new \RuntimeException("array_pop() first argument must be a variable");
        }

        $arrayVarName = $arrayArg->name;
        $arrayPtr = "%{$arrayVarName}";

        // Pop the last element from the array - allocate result and call function
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_array_pop(%struct.zval* {$arrayPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArrayShiftFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("array_shift() expects exactly 1 argument");
        }

        // First argument must be a variable reference (the array to modify)
        $arrayArg = $funcCall->arguments[0];
        if (!$arrayArg instanceof VariableReference) {
            throw new \RuntimeException("array_shift() first argument must be a variable");
        }

        $arrayVarName = $arrayArg->name;
        $arrayPtr = "%{$arrayVarName}";

        // Shift the first element from the array - allocate result and call function
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_array_shift(%struct.zval* {$arrayPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArraySliceFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) < 2 || count($funcCall->arguments) > 4) {
            throw new \RuntimeException("array_slice() expects 2 to 4 arguments");
        }

        // Generate the array argument
        $arrPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the offset argument
        $offsetPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $offsetInt = $this->getNextTempVariable();
        $ir[] = "  {$offsetInt} = call i32 @php_zval_to_int(%struct.zval* {$offsetPtr})";

        // Get length (default 0 = all remaining)
        $lengthInt = 0;
        if (count($funcCall->arguments) > 2) {
            $lengthPtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);
            $lengthInt = $this->getNextTempVariable();
            $ir[] = "  {$lengthInt} = call i32 @php_zval_to_int(%struct.zval* {$lengthPtr})";
        }

        // Get preserve_keys flag (default to 0/false)
        $preserveKeys = 0;
        if (count($funcCall->arguments) > 3) {
            $preserveKeysPtr = $this->generateExpression($funcCall->arguments[3], $ir, $globalVars);
            $preserveKeysInt = $this->getNextTempVariable();
            $ir[] = "  {$preserveKeysInt} = call i32 @php_zval_to_int(%struct.zval* {$preserveKeysPtr})";
            $preserveKeys = $preserveKeysInt;
        }

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_slice
        if (is_int($lengthInt) && $lengthInt === 0 && is_int($preserveKeys) && $preserveKeys === 0) {
            // Simple case: only 2 arguments
            $ir[] = "  call void @php_array_slice(%struct.zval* {$arrPtr}, i32 {$offsetInt}, i32 0, i32 0, %struct.zval* {$resultPtr})";
        } else if (count($funcCall->arguments) > 3) {
            $ir[] = "  call void @php_array_slice(%struct.zval* {$arrPtr}, i32 {$offsetInt}, i32 {$lengthInt}, i32 {$preserveKeys}, %struct.zval* {$resultPtr})";
        } else {
            $ir[] = "  call void @php_array_slice(%struct.zval* {$arrPtr}, i32 {$offsetInt}, i32 {$lengthInt}, i32 0, %struct.zval* {$resultPtr})";
        }
        $ir[] = "";
    }

    private function generateArrayChangeKeyCaseFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) < 1 || count($funcCall->arguments) > 2) {
            throw new \RuntimeException("array_change_key_case() expects 1 or 2 arguments");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Get the case type (default to CASE_LOWER = 0)
        $caseType = 0;  // CASE_LOWER
        if (count($funcCall->arguments) > 1) {
            // If second argument is a constant like CASE_LOWER or CASE_UPPER
            $secondArg = $funcCall->arguments[1];
            if ($secondArg instanceof Constant) {
                $constName = strtoupper($secondArg->name);
                if ($constName === 'CASE_UPPER') {
                    $caseType = 1;
                } else {
                    $caseType = 0;  // CASE_LOWER or any other value
                }
            } else {
                // For other expressions, try to evaluate as int
                // For now, default to CASE_LOWER
                $caseType = 0;
            }
        }

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_change_key_case function
        $ir[] = "  call void @php_array_change_key_case(%struct.zval* {$argPtr}, i32 {$caseType}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateArrayChunkFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) < 2 || count($funcCall->arguments) > 3) {
            throw new \RuntimeException("array_chunk() expects 2 or 3 arguments");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Get the chunk size
        $sizePtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $sizeInt = $this->getNextTempVariable();
        $ir[] = "  {$sizeInt} = call i32 @php_zval_to_int(%struct.zval* {$sizePtr})";

        // Get preserve_keys flag (default to 0/false)
        $preserveKeys = 0;
        if (count($funcCall->arguments) > 2) {
            $preserveKeysPtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);
            $preserveKeysInt = $this->getNextTempVariable();
            $ir[] = "  {$preserveKeysInt} = call i32 @php_zval_to_int(%struct.zval* {$preserveKeysPtr})";
            $preserveKeys = $preserveKeysInt;  // Use the variable name directly
        }

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_chunk function
        if (count($funcCall->arguments) > 2) {
            $ir[] = "  call void @php_array_chunk(%struct.zval* {$argPtr}, i32 {$sizeInt}, i32 {$preserveKeys}, %struct.zval* {$resultPtr})";
        } else {
            $ir[] = "  call void @php_array_chunk(%struct.zval* {$argPtr}, i32 {$sizeInt}, i32 0, %struct.zval* {$resultPtr})";
        }
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

    private function generateVarDumpFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("var_dump() expects exactly 1 argument");
        }

        // Generate the value argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Call php_var_dump function (void return - outputs directly)
        $ir[] = "  call void @php_var_dump(%struct.zval* {$argPtr})";
        $ir[] = "";
    }

    private function generateStrRepeatFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("str_repeat() expects exactly 2 arguments");
        }

        // Generate the string argument
        $strPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the count argument
        $countPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_str_repeat function
        $ir[] = "  call void @php_str_repeat(%struct.zval* {$strPtr}, %struct.zval* {$countPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateTrimFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) < 1 || count($funcCall->arguments) > 2) {
            throw new \RuntimeException("trim() expects 1 or 2 arguments");
        }

        // Generate the string argument
        $strPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_trim function
        $ir[] = "  call void @php_trim(%struct.zval* {$strPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateFileExistsFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("file_exists() expects exactly 1 argument");
        }

        // Generate the path argument
        $pathPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_file_exists function
        $ir[] = "  call void @php_file_exists(%struct.zval* {$pathPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateShellExecFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("shell_exec() expects exactly 1 argument");
        }

        // Generate the command argument
        $cmdPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_shell_exec function
        $ir[] = "  call void @php_shell_exec(%struct.zval* {$cmdPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generatePathinfoFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) < 1 || count($funcCall->arguments) > 2) {
            throw new \RuntimeException("pathinfo() expects 1 or 2 arguments");
        }

        // Generate the path argument
        $pathPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the options argument (defaults to 0 if not provided)
        if (count($funcCall->arguments) > 1) {
            $optionsPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        } else {
            $optionsPtr = $this->getNextTempVariable();
            $ir[] = "  {$optionsPtr} = alloca %struct.zval";
            $ir[] = "  call void @php_zval_int(%struct.zval* {$optionsPtr}, i32 0)";
        }

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_pathinfo function
        $ir[] = "  call void @php_pathinfo(%struct.zval* {$pathPtr}, %struct.zval* {$optionsPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateRenameFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("rename() expects exactly 2 arguments");
        }

        // Generate the oldname argument
        $oldnamePtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the newname argument
        $newnamePtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_rename function
        $ir[] = "  call void @php_rename(%struct.zval* {$oldnamePtr}, %struct.zval* {$newnamePtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateUnlinkFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("unlink() expects exactly 1 argument");
        }

        // Generate the filename argument
        $filenamePtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_unlink function
        $ir[] = "  call void @php_unlink(%struct.zval* {$filenamePtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateStrReplaceFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 3) {
            throw new \RuntimeException("str_replace() expects exactly 3 arguments");
        }

        // Generate the search argument
        $searchPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the replace argument
        $replacePtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Generate the subject argument
        $subjectPtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_str_replace function
        $ir[] = "  call void @php_str_replace(%struct.zval* {$searchPtr}, %struct.zval* {$replacePtr}, %struct.zval* {$subjectPtr}, %struct.zval* {$resultPtr})";
        $ir[] = "";
    }

    private function generateExitFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        // exit() can take 0 or 1 argument
        $exitCode = 0;
        if (count($funcCall->arguments) > 0) {
            // Generate the exit code argument and convert to int
            $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
            $exitCodeVar = $this->getNextTempVariable();
            $ir[] = "  {$exitCodeVar} = call i32 @php_zval_to_int(%struct.zval* {$argPtr})";
            // Call exit with the provided code
            $ir[] = "  call void @exit(i32 {$exitCodeVar})";
        } else {
            // Call exit with code 0
            $ir[] = "  call void @exit(i32 0)";
        }
        // Note: exit is noreturn, so no code after this will execute
        $ir[] = "";
    }

    private function generateIsIntFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("is_int() expects exactly 1 argument");
        }

        // Generate the argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Call php_is_int to check if it's an integer
        $isIntResult = $this->getNextTempVariable();
        $ir[] = "  {$isIntResult} = call i32 @php_is_int(%struct.zval* {$argPtr})";

        // Store result in a zval (we need to allocate and store, but since this is a statement, we just discard)
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_bool(%struct.zval* {$resultPtr}, i32 {$isIntResult})";
        $ir[] = "";
    }

    private function generateIssetFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("isset() expects exactly 1 argument");
        }

        // Generate the argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Call php_isset to check if it's set (not null)
        $issetResult = $this->getNextTempVariable();
        $ir[] = "  {$issetResult} = call i32 @php_isset(%struct.zval* {$argPtr})";

        // Store result in a zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_bool(%struct.zval* {$resultPtr}, i32 {$issetResult})";
        $ir[] = "";
    }

    private function generateUnsetFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("unset() expects exactly 1 argument");
        }

        // Get the argument - it should be a variable reference
        $arg = $funcCall->arguments[0];
        if ($arg instanceof VariableReference) {
            // For variables, we can directly unset them
            $varName = $arg->name;
            // Call php_unset to destroy the value and set it to null
            $ir[] = "  call void @php_unset(%struct.zval* %{$varName})";
        } else {
            // For other expressions, evaluate and then unset the result
            $argPtr = $this->generateExpression($arg, $ir, $globalVars);
            $ir[] = "  call void @php_unset(%struct.zval* {$argPtr})";
        }
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

    private function generateArrayColumnExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("array_column() expects exactly 2 arguments");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the column key argument
        $columnKeyPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_column function
        $ir[] = "  call void @php_array_column(%struct.zval* {$argPtr}, %struct.zval* {$columnKeyPtr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateArrayChangeKeyCaseExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) < 1 || count($funcCall->arguments) > 2) {
            throw new \RuntimeException("array_change_key_case() expects 1 or 2 arguments");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Get the case type (default to CASE_LOWER = 0)
        $caseType = 0;  // CASE_LOWER
        if (count($funcCall->arguments) > 1) {
            // If second argument is a constant like CASE_LOWER or CASE_UPPER
            $secondArg = $funcCall->arguments[1];
            if ($secondArg instanceof Constant) {
                $constName = strtoupper($secondArg->name);
                if ($constName === 'CASE_UPPER') {
                    $caseType = 1;
                } else {
                    $caseType = 0;  // CASE_LOWER or any other value
                }
            } else {
                // For other expressions, try to evaluate as int
                // For now, default to CASE_LOWER
                $caseType = 0;
            }
        }

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_change_key_case function
        $ir[] = "  call void @php_array_change_key_case(%struct.zval* {$argPtr}, i32 {$caseType}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateArrayChunkExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) < 2 || count($funcCall->arguments) > 3) {
            throw new \RuntimeException("array_chunk() expects 2 or 3 arguments");
        }

        // Generate the array argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Get the chunk size
        $sizePtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $sizeInt = $this->getNextTempVariable();
        $ir[] = "  {$sizeInt} = call i32 @php_zval_to_int(%struct.zval* {$sizePtr})";

        // Get preserve_keys flag (default to 0/false)
        $preserveKeys = 0;
        if (count($funcCall->arguments) > 2) {
            $preserveKeysPtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);
            $preserveKeysInt = $this->getNextTempVariable();
            $ir[] = "  {$preserveKeysInt} = call i32 @php_zval_to_int(%struct.zval* {$preserveKeysPtr})";
            $preserveKeys = $preserveKeysInt;  // Use the variable name directly
        }

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_chunk function
        if (count($funcCall->arguments) > 2) {
            $ir[] = "  call void @php_array_chunk(%struct.zval* {$argPtr}, i32 {$sizeInt}, i32 {$preserveKeys}, %struct.zval* {$resultPtr})";
        } else {
            $ir[] = "  call void @php_array_chunk(%struct.zval* {$argPtr}, i32 {$sizeInt}, i32 0, %struct.zval* {$resultPtr})";
        }

        return $resultPtr;
    }

    private function generateArrayCombineExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("array_combine() expects exactly 2 arguments");
        }

        // Generate the keys array argument
        $keysPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the values array argument
        $valuesPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_combine function
        $ir[] = "  call void @php_array_combine(%struct.zval* {$keysPtr}, %struct.zval* {$valuesPtr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateArrayFillExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 3) {
            throw new \RuntimeException("array_fill() expects exactly 3 arguments");
        }

        // Generate the start_index argument
        $startIndexPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
        $startIndexInt = $this->getNextTempVariable();
        $ir[] = "  {$startIndexInt} = call i32 @php_zval_to_int(%struct.zval* {$startIndexPtr})";

        // Generate the num argument
        $numPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $numInt = $this->getNextTempVariable();
        $ir[] = "  {$numInt} = call i32 @php_zval_to_int(%struct.zval* {$numPtr})";

        // Generate the value argument
        $valuePtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_fill function
        $ir[] = "  call void @php_array_fill(i32 {$startIndexInt}, i32 {$numInt}, %struct.zval* {$valuePtr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateArrayFillKeysExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("array_fill_keys() expects exactly 2 arguments");
        }

        // Generate the keys array argument
        $keysPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the value argument
        $valuePtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_fill_keys function
        $ir[] = "  call void @php_array_fill_keys(%struct.zval* {$keysPtr}, %struct.zval* {$valuePtr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateArrayMergeExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) < 2) {
            throw new \RuntimeException("array_merge() expects at least 2 arguments");
        }

        // For simplicity, handle exactly 2 arrays (common case)
        // Generate the first array argument
        $arr1Ptr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the second array argument
        $arr2Ptr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_merge function
        $ir[] = "  call void @php_array_merge(%struct.zval* {$arr1Ptr}, %struct.zval* {$arr2Ptr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateArrayMergeRecursiveExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) < 2) {
            throw new \RuntimeException("array_merge_recursive() expects at least 2 arguments");
        }

        // Generate the first array argument
        $arr1Ptr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the second array argument
        $arr2Ptr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_merge_recursive function
        $ir[] = "  call void @php_array_merge_recursive(%struct.zval* {$arr1Ptr}, %struct.zval* {$arr2Ptr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateArrayPadExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 3) {
            throw new \RuntimeException("array_pad() expects exactly 3 arguments");
        }

        // Generate the array argument
        $arrPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the size argument
        $sizePtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $sizeInt = $this->getNextTempVariable();
        $ir[] = "  {$sizeInt} = call i32 @php_zval_to_int(%struct.zval* {$sizePtr})";

        // Generate the pad value argument
        $padValuePtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_pad function
        $ir[] = "  call void @php_array_pad(%struct.zval* {$arrPtr}, i32 {$sizeInt}, %struct.zval* {$padValuePtr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateArrayPushExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) < 2) {
            throw new \RuntimeException("array_push() expects at least 2 arguments");
        }

        // First argument must be a variable reference (the array to modify)
        $arrayArg = $funcCall->arguments[0];
        if (!$arrayArg instanceof VariableReference) {
            throw new \RuntimeException("array_push() first argument must be a variable");
        }

        $arrayVarName = $arrayArg->name;
        $arrayPtr = "%{$arrayVarName}";

        // Push each value to the array and track the return value (new count)
        $lastCount = null;
        for ($i = 1; $i < count($funcCall->arguments); $i++) {
            $valuePtr = $this->generateExpression($funcCall->arguments[$i], $ir, $globalVars);
            $countResult = $this->getNextTempVariable();
            $ir[] = "  {$countResult} = call i32 @php_array_push(%struct.zval* {$arrayPtr}, %struct.zval* {$valuePtr})";
            $lastCount = $countResult;
        }

        // Allocate result zval with the last count
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_int(%struct.zval* {$resultPtr}, i32 {$lastCount})";

        return $resultPtr;
    }

    private function generateArrayUnshiftExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) < 2) {
            throw new \RuntimeException("array_unshift() expects at least 2 arguments");
        }

        // First argument must be a variable reference (the array to modify)
        $arrayArg = $funcCall->arguments[0];
        if (!$arrayArg instanceof VariableReference) {
            throw new \RuntimeException("array_unshift() first argument must be a variable");
        }

        $arrayVarName = $arrayArg->name;
        $arrayPtr = "%{$arrayVarName}";

        // Unshift each value to the beginning of the array (in reverse order to maintain correct order)
        $lastCount = null;
        for ($i = count($funcCall->arguments) - 1; $i >= 1; $i--) {
            $valuePtr = $this->generateExpression($funcCall->arguments[$i], $ir, $globalVars);
            $countResult = $this->getNextTempVariable();
            $ir[] = "  {$countResult} = call i32 @php_array_unshift(%struct.zval* {$arrayPtr}, %struct.zval* {$valuePtr})";
            $lastCount = $countResult;
        }

        // Allocate result zval with the last count
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_int(%struct.zval* {$resultPtr}, i32 {$lastCount})";

        return $resultPtr;
    }

    private function generateArrayPopExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("array_pop() expects exactly 1 argument");
        }

        // First argument must be a variable reference (the array to modify)
        $arrayArg = $funcCall->arguments[0];
        if (!$arrayArg instanceof VariableReference) {
            throw new \RuntimeException("array_pop() first argument must be a variable");
        }

        $arrayVarName = $arrayArg->name;
        $arrayPtr = "%{$arrayVarName}";

        // Pop the last element from the array
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_array_pop(%struct.zval* {$arrayPtr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateArrayShiftExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("array_shift() expects exactly 1 argument");
        }

        // First argument must be a variable reference (the array to modify)
        $arrayArg = $funcCall->arguments[0];
        if (!$arrayArg instanceof VariableReference) {
            throw new \RuntimeException("array_shift() first argument must be a variable");
        }

        $arrayVarName = $arrayArg->name;
        $arrayPtr = "%{$arrayVarName}";

        // Shift the first element from the array
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_array_shift(%struct.zval* {$arrayPtr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateArraySliceExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) < 2 || count($funcCall->arguments) > 4) {
            throw new \RuntimeException("array_slice() expects 2 to 4 arguments");
        }

        // Generate the array argument
        $arrPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the offset argument
        $offsetPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $offsetInt = $this->getNextTempVariable();
        $ir[] = "  {$offsetInt} = call i32 @php_zval_to_int(%struct.zval* {$offsetPtr})";

        // Get length (default 0 = all remaining)
        $lengthInt = 0;
        if (count($funcCall->arguments) > 2) {
            $lengthPtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);
            $lengthInt = $this->getNextTempVariable();
            $ir[] = "  {$lengthInt} = call i32 @php_zval_to_int(%struct.zval* {$lengthPtr})";
        }

        // Get preserve_keys flag (default to 0/false)
        $preserveKeys = 0;
        if (count($funcCall->arguments) > 3) {
            $preserveKeysPtr = $this->generateExpression($funcCall->arguments[3], $ir, $globalVars);
            $preserveKeysInt = $this->getNextTempVariable();
            $ir[] = "  {$preserveKeysInt} = call i32 @php_zval_to_int(%struct.zval* {$preserveKeysPtr})";
            $preserveKeys = $preserveKeysInt;
        }

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_slice
        if (count($funcCall->arguments) > 3) {
            $ir[] = "  call void @php_array_slice(%struct.zval* {$arrPtr}, i32 {$offsetInt}, i32 {$lengthInt}, i32 {$preserveKeys}, %struct.zval* {$resultPtr})";
        } else if (count($funcCall->arguments) > 2) {
            $ir[] = "  call void @php_array_slice(%struct.zval* {$arrPtr}, i32 {$offsetInt}, i32 {$lengthInt}, i32 0, %struct.zval* {$resultPtr})";
        } else {
            $ir[] = "  call void @php_array_slice(%struct.zval* {$arrPtr}, i32 {$offsetInt}, i32 0, i32 0, %struct.zval* {$resultPtr})";
        }

        return $resultPtr;
    }

    private function generateArraySpliceFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) < 2 || count($funcCall->arguments) > 4) {
            throw new \RuntimeException("array_splice() expects 2 to 4 arguments");
        }

        // First argument must be a variable reference (the array to modify)
        $arrayArg = $funcCall->arguments[0];
        if (!$arrayArg instanceof VariableReference) {
            throw new \RuntimeException("array_splice() first argument must be a variable");
        }

        $arrayVarName = $arrayArg->name;
        $arrayPtr = "%{$arrayVarName}";

        // Generate the offset argument
        $offsetPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $offsetInt = $this->getNextTempVariable();
        $ir[] = "  {$offsetInt} = call i32 @php_zval_to_int(%struct.zval* {$offsetPtr})";

        // Get length (default = 0)
        $lengthInt = 0;
        if (count($funcCall->arguments) > 2) {
            $lengthPtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);
            $lengthInt = $this->getNextTempVariable();
            $ir[] = "  {$lengthInt} = call i32 @php_zval_to_int(%struct.zval* {$lengthPtr})";
        }

        // Get replacement array (optional)
        $replPtr = "null";
        if (count($funcCall->arguments) > 3) {
            $replPtr = $this->generateExpression($funcCall->arguments[3], $ir, $globalVars);
        }

        // Allocate result zval for removed elements
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_splice
        if (count($funcCall->arguments) > 3) {
            $ir[] = "  call void @php_array_splice(%struct.zval* {$arrayPtr}, i32 {$offsetInt}, i32 {$lengthInt}, %struct.zval* {$replPtr}, %struct.zval* {$resultPtr})";
        } else {
            $ir[] = "  call void @php_array_splice(%struct.zval* {$arrayPtr}, i32 {$offsetInt}, i32 {$lengthInt}, %struct.zval* null, %struct.zval* {$resultPtr})";
        }
        $ir[] = "";
    }

    private function generateArraySpliceExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) < 2 || count($funcCall->arguments) > 4) {
            throw new \RuntimeException("array_splice() expects 2 to 4 arguments");
        }

        // First argument must be a variable reference (the array to modify)
        $arrayArg = $funcCall->arguments[0];
        if (!$arrayArg instanceof VariableReference) {
            throw new \RuntimeException("array_splice() first argument must be a variable");
        }

        $arrayVarName = $arrayArg->name;
        $arrayPtr = "%{$arrayVarName}";

        // Generate the offset argument
        $offsetPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $offsetInt = $this->getNextTempVariable();
        $ir[] = "  {$offsetInt} = call i32 @php_zval_to_int(%struct.zval* {$offsetPtr})";

        // Get length (default = 0)
        $lengthInt = 0;
        if (count($funcCall->arguments) > 2) {
            $lengthPtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);
            $lengthInt = $this->getNextTempVariable();
            $ir[] = "  {$lengthInt} = call i32 @php_zval_to_int(%struct.zval* {$lengthPtr})";
        }

        // Get replacement array (optional)
        $replPtr = "null";
        if (count($funcCall->arguments) > 3) {
            $replPtr = $this->generateExpression($funcCall->arguments[3], $ir, $globalVars);
        }

        // Allocate result zval for removed elements
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_array_splice
        if (count($funcCall->arguments) > 3) {
            $ir[] = "  call void @php_array_splice(%struct.zval* {$arrayPtr}, i32 {$offsetInt}, i32 {$lengthInt}, %struct.zval* {$replPtr}, %struct.zval* {$resultPtr})";
        } else {
            $ir[] = "  call void @php_array_splice(%struct.zval* {$arrayPtr}, i32 {$offsetInt}, i32 {$lengthInt}, %struct.zval* null, %struct.zval* {$resultPtr})";
        }

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

    private function generateVarDumpExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("var_dump() expects exactly 1 argument");
        }

        // Generate the value argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Call php_var_dump function (void return - outputs directly)
        $ir[] = "  call void @php_var_dump(%struct.zval* {$argPtr})";

        // Return NULL zval since var_dump returns void
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_null(%struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateStrRepeatExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("str_repeat() expects exactly 2 arguments");
        }

        $strPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
        $countPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_str_repeat(%struct.zval* {$strPtr}, %struct.zval* {$countPtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generateTrimExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) < 1 || count($funcCall->arguments) > 2) {
            throw new \RuntimeException("trim() expects 1 or 2 arguments");
        }

        $strPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_trim(%struct.zval* {$strPtr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generateFileExistsExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("file_exists() expects exactly 1 argument");
        }

        $pathPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_file_exists(%struct.zval* {$pathPtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generateShellExecExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("shell_exec() expects exactly 1 argument");
        }

        $cmdPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Use a unique variable name that won't conflict
        static $shellExecCounter = 0;
        $shellExecCounter++;
        $resultPtr = "%shell_exec_result_" . $shellExecCounter;

        // Allocate in entry block to ensure it dominates all uses
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_shell_exec(%struct.zval* {$cmdPtr}, %struct.zval* {$resultPtr})";

        return $resultPtr;
    }

    private function generatePathinfoExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) < 1 || count($funcCall->arguments) > 2) {
            throw new \RuntimeException("pathinfo() expects 1 or 2 arguments");
        }

        // Generate the path argument
        $pathPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the options argument (defaults to 0 if not provided)
        if (count($funcCall->arguments) > 1) {
            $optionsPtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);
        } else {
            $optionsPtr = $this->getNextTempVariable();
            $ir[] = "  {$optionsPtr} = alloca %struct.zval";
            $ir[] = "  call void @php_zval_int(%struct.zval* {$optionsPtr}, i32 0)";
        }

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_pathinfo function
        $ir[] = "  call void @php_pathinfo(%struct.zval* {$pathPtr}, %struct.zval* {$optionsPtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generateRenameExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("rename() expects exactly 2 arguments");
        }

        // Generate the oldname argument
        $oldnamePtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the newname argument
        $newnamePtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_rename function
        $ir[] = "  call void @php_rename(%struct.zval* {$oldnamePtr}, %struct.zval* {$newnamePtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generateUnlinkExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("unlink() expects exactly 1 argument");
        }

        // Generate the filename argument
        $filenamePtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_unlink function
        $ir[] = "  call void @php_unlink(%struct.zval* {$filenamePtr}, %struct.zval* {$resultPtr})";
        return $resultPtr;
    }

    private function generateStrReplaceExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 3) {
            throw new \RuntimeException("str_replace() expects exactly 3 arguments");
        }

        // Generate the search argument
        $searchPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Generate the replace argument
        $replacePtr = $this->generateExpression($funcCall->arguments[1], $ir, $globalVars);

        // Generate the subject argument
        $subjectPtr = $this->generateExpression($funcCall->arguments[2], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_str_replace function
        $ir[] = "  call void @php_str_replace(%struct.zval* {$searchPtr}, %struct.zval* {$replacePtr}, %struct.zval* {$subjectPtr}, %struct.zval* {$resultPtr})";
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

    private function generateIsIntExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("is_int() expects exactly 1 argument");
        }

        // Generate the argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Call php_is_int to check if it's an integer
        $isIntResult = $this->getNextTempVariable();
        $ir[] = "  {$isIntResult} = call i32 @php_is_int(%struct.zval* {$argPtr})";

        // Store result in a zval and return pointer
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_bool(%struct.zval* {$resultPtr}, i32 {$isIntResult})";

        return $resultPtr;
    }

    private function generateIssetExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("isset() expects exactly 1 argument");
        }

        // Generate the argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Call php_isset to check if it's set (not null)
        $issetResult = $this->getNextTempVariable();
        $ir[] = "  {$issetResult} = call i32 @php_isset(%struct.zval* {$argPtr})";

        // Store result in a zval and return pointer
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_bool(%struct.zval* {$resultPtr}, i32 {$issetResult})";

        return $resultPtr;
    }

    private function generateEmptyFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("empty() expects exactly 1 argument");
        }

        // Generate the argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Call php_empty to check if it's empty
        $emptyResult = $this->getNextTempVariable();
        $ir[] = "  {$emptyResult} = call i32 @php_empty(%struct.zval* {$argPtr})";

        // Store result in a zval (we need to allocate and store, but since this is a statement, we just discard)
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_bool(%struct.zval* {$resultPtr}, i32 {$emptyResult})";
        $ir[] = "";
    }

    private function generateEmptyExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("empty() expects exactly 1 argument");
        }

        // Generate the argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Call php_empty to check if it's empty
        $emptyResult = $this->getNextTempVariable();
        $ir[] = "  {$emptyResult} = call i32 @php_empty(%struct.zval* {$argPtr})";

        // Store result in a zval and return pointer
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_bool(%struct.zval* {$resultPtr}, i32 {$emptyResult})";

        return $resultPtr;
    }

    private function generateGettypeFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("gettype() expects exactly 1 argument");
        }

        // Generate the argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_gettype(arg, result)
        $ir[] = "  call void @php_gettype(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";

        // Since this is a statement (not used), we don't need to return anything
        $ir[] = "";
    }

    private function generateGettypeExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 1) {
            throw new \RuntimeException("gettype() expects exactly 1 argument");
        }

        // Generate the argument
        $argPtr = $this->generateExpression($funcCall->arguments[0], $ir, $globalVars);

        // Allocate result zval
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";

        // Call php_gettype(arg, result)
        $ir[] = "  call void @php_gettype(%struct.zval* {$argPtr}, %struct.zval* {$resultPtr})";

        // Return the result pointer for further use
        return $resultPtr;
    }

    private function generateSettypeExpression(FunctionCall $funcCall, array &$ir, array $globalVars): string
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("settype() expects exactly 2 arguments");
        }

        // First argument should be a variable reference (the one to convert)
        $varExpr = $funcCall->arguments[0];
        if (!$varExpr instanceof VariableReference) {
            throw new \RuntimeException("settype() first argument must be a variable");
        }

        // Get the variable name and construct pointer
        $varName = $varExpr->name;
        $varPtr = "%{$varName}";

        // Second argument is the type string
        $typeExpr = $funcCall->arguments[1];
        $typePtr = $this->generateExpression($typeExpr, $ir, $globalVars);

        // Get the string value from the type zval using php_zval_to_string helper
        $typeStrVal = $this->getNextTempVariable();
        $ir[] = "  {$typeStrVal} = call i8* @php_zval_to_string(%struct.zval* {$typePtr})";

        // Call php_settype(var, type_string) - returns i32 (1 for success, 0 for failure)
        $resultVar = $this->getNextTempVariable();
        $ir[] = "  {$resultVar} = call i32 @php_settype(%struct.zval* {$varPtr}, i8* {$typeStrVal})";

        // Store result in a zval (boolean indicating success)
        $resultPtr = $this->getNextTempVariable();
        $ir[] = "  {$resultPtr} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_bool(%struct.zval* {$resultPtr}, i32 {$resultVar})";

        return $resultPtr;
    }

    private function generateSettypeFunctionCall(FunctionCall $funcCall, array &$ir, array $globalVars): void
    {
        if (count($funcCall->arguments) !== 2) {
            throw new \RuntimeException("settype() expects exactly 2 arguments");
        }

        // First argument should be a variable reference (the one to convert)
        $varExpr = $funcCall->arguments[0];
        if (!$varExpr instanceof VariableReference) {
            throw new \RuntimeException("settype() first argument must be a variable");
        }

        // Get the variable name and construct pointer
        $varName = $varExpr->name;
        $varPtr = "%{$varName}";

        // Second argument is the type string
        $typeExpr = $funcCall->arguments[1];
        $typePtr = $this->generateExpression($typeExpr, $ir, $globalVars);

        // Get the string value from the type zval using php_zval_to_string helper
        $typeStrVal = $this->getNextTempVariable();
        $ir[] = "  {$typeStrVal} = call i8* @php_zval_to_string(%struct.zval* {$typePtr})";

        // Call php_settype(var, type_string) - returns i32 (1 for success, 0 for failure)
        $resultVar = $this->getNextTempVariable();
        $ir[] = "  {$resultVar} = call i32 @php_settype(%struct.zval* {$varPtr}, i8* {$typeStrVal})";

        // Since settype is typically used as a statement, we don't need to store the result
        // But we should have it available if needed
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
        } elseif ($expression instanceof BooleanLiteral) {
            return $this->generateBooleanLiteral($expression, $ir, $globalVars);
        } elseif ($expression instanceof NullLiteral) {
            return $this->generateNullLiteral($expression, $ir, $globalVars);
        } elseif ($expression instanceof Constant) {
            return $this->generateConstant($expression, $ir, $globalVars);
        } elseif ($expression instanceof UnaryOperation) {
            return $this->generateUnaryOperation($expression, $ir, $globalVars);
        } elseif ($expression instanceof BinaryOperation) {
            return $this->generateBinaryOperation($expression, $ir, $globalVars);
        } elseif ($expression instanceof FunctionCall) {
            // Handle builtin functions specially
            if ($expression->name === 'count') {
                return $this->generateCountExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_values') {
                return $this->generateArrayValuesExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_change_key_case') {
                return $this->generateArrayChangeKeyCaseExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_chunk') {
                return $this->generateArrayChunkExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_column') {
                return $this->generateArrayColumnExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_combine') {
                return $this->generateArrayCombineExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_fill') {
                return $this->generateArrayFillExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_fill_keys') {
                return $this->generateArrayFillKeysExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_merge') {
                return $this->generateArrayMergeExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_merge_recursive') {
                return $this->generateArrayMergeRecursiveExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_pad') {
                return $this->generateArrayPadExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_push') {
                return $this->generateArrayPushExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_unshift') {
                return $this->generateArrayUnshiftExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_pop') {
                return $this->generateArrayPopExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_shift') {
                return $this->generateArrayShiftExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_slice') {
                return $this->generateArraySliceExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'array_splice') {
                return $this->generateArraySpliceExpression($expression, $ir, $globalVars);
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
            } elseif ($expression->name === 'var_dump') {
                return $this->generateVarDumpExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'str_repeat') {
                return $this->generateStrRepeatExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'trim') {
                return $this->generateTrimExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'file_exists') {
                return $this->generateFileExistsExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'shell_exec') {
                return $this->generateShellExecExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'pathinfo') {
                return $this->generatePathinfoExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'rename') {
                return $this->generateRenameExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'unlink') {
                return $this->generateUnlinkExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'str_replace') {
                return $this->generateStrReplaceExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'is_int') {
                return $this->generateIsIntExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'isset') {
                return $this->generateIssetExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'empty') {
                return $this->generateEmptyExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'gettype') {
                return $this->generateGettypeExpression($expression, $ir, $globalVars);
            } elseif ($expression->name === 'settype') {
                return $this->generateSettypeExpression($expression, $ir, $globalVars);
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
        } elseif ($expression instanceof NewExpression) {
            return $this->generateNewExpression($expression, $ir, $globalVars);
        } elseif ($expression instanceof PropertyAccess) {
            return $this->generatePropertyAccess($expression, $ir, $globalVars);
        } elseif ($expression instanceof MethodCall) {
            return $this->generateMethodCall($expression, $ir, $globalVars);
        } elseif ($expression instanceof CastExpression) {
            return $this->generateCastExpression($expression, $ir, $globalVars);
        } elseif ($expression instanceof TernaryExpression) {
            return $this->generateTernaryExpression($expression, $ir, $globalVars);
        } elseif ($expression instanceof VariableFunctionCall) {
            return $this->generateVariableFunctionCall($expression, $ir, $globalVars);
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

    private function generateCastExpression(CastExpression $cast, array &$ir, array $globalVars): string
    {
        // Generate the expression to cast
        $exprPtr = $this->generateExpression($cast->expression, $ir, $globalVars);

        // Allocate result zval
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";

        // Handle different cast types
        $castType = strtolower($cast->type);
        switch ($castType) {
            case 'int':
            case 'integer':
                // Convert to integer using php_zval_to_int and then back to zval
                $intVal = $this->getNextTempVariable();
                $ir[] = "  {$intVal} = call i32 @php_zval_to_int(%struct.zval* {$exprPtr})";
                $ir[] = "  call void @php_zval_int(%struct.zval* {$result}, i32 {$intVal})";
                break;

            case 'string':
                // Convert to string
                $strVal = $this->getNextTempVariable();
                $ir[] = "  {$strVal} = call i8* @php_zval_to_string(%struct.zval* {$exprPtr})";
                $ir[] = "  call void @php_zval_string(%struct.zval* {$result}, i8* {$strVal})";
                break;

            case 'bool':
            case 'boolean':
                // Convert to boolean: check if value is truthy
                $intVal = $this->getNextTempVariable();
                $ir[] = "  {$intVal} = call i32 @php_zval_to_int(%struct.zval* {$exprPtr})";
                $isTrue = $this->getNextTempVariable();
                $ir[] = "  {$isTrue} = icmp ne i32 {$intVal}, 0";
                $boolVal = $this->getNextTempVariable();
                $ir[] = "  {$boolVal} = zext i1 {$isTrue} to i32";
                $ir[] = "  call void @php_zval_bool(%struct.zval* {$result}, i32 {$boolVal})";
                break;

            default:
                // For unsupported cast types, just copy the value as-is
                $loadedVal = $this->getNextTempVariable();
                $ir[] = "  {$loadedVal} = load %struct.zval, %struct.zval* {$exprPtr}";
                $ir[] = "  store %struct.zval {$loadedVal}, %struct.zval* {$result}";
                break;
        }

        return $result;
    }

    private function generateTernaryExpression(TernaryExpression $ternary, array &$ir, array $globalVars): string
    {
        // Generate the condition
        $conditionPtr = $this->generateExpression($ternary->condition, $ir, $globalVars);

        // Convert condition to integer for boolean check
        $condInt = $this->getNextTempVariable();
        $ir[] = "  {$condInt} = call i32 @php_zval_to_int(%struct.zval* {$conditionPtr})";

        // Check if condition is true
        $isTrue = $this->getNextTempVariable();
        $ir[] = "  {$isTrue} = icmp ne i32 {$condInt}, 0";

        // Create basic blocks
        static $blockCounter = 0;
        $thenBlock = "ternary_then_" . ++$blockCounter;
        $elseBlock = "ternary_else_" . $blockCounter;
        $mergeBlock = "ternary_merge_" . $blockCounter;

        // Branch to then or else block
        $ir[] = "  br i1 {$isTrue}, label %{$thenBlock}, label %{$elseBlock}";

        // Allocate result zval (outside the branches to be available in both)
        $result = $this->getNextTempVariable();

        // Then block - evaluate ifTrue expression (or condition for Elvis operator)
        $ir[] = "{$thenBlock}:";
        if ($ternary->ifTrue !== null) {
            // Regular ternary: condition ? ifTrue : ifFalse
            $trueValuePtr = $this->generateExpression($ternary->ifTrue, $ir, $globalVars);
        } else {
            // Elvis operator: condition ?: ifFalse (returns condition if truthy)
            $trueValuePtr = $conditionPtr;
        }
        $trueValue = $this->getNextTempVariable();
        $ir[] = "  {$trueValue} = load %struct.zval, %struct.zval* {$trueValuePtr}";
        $ir[] = "  br label %{$mergeBlock}";

        // Else block - evaluate ifFalse expression
        $ir[] = "{$elseBlock}:";
        $falseValuePtr = $this->generateExpression($ternary->ifFalse, $ir, $globalVars);
        $falseValue = $this->getNextTempVariable();
        $ir[] = "  {$falseValue} = load %struct.zval, %struct.zval* {$falseValuePtr}";
        $ir[] = "  br label %{$mergeBlock}";

        // Merge block - phi to select the appropriate value
        $ir[] = "{$mergeBlock}:";
        $phiResult = $this->getNextTempVariable();
        $ir[] = "  {$phiResult} = phi %struct.zval [ {$trueValue}, %{$thenBlock} ], [ {$falseValue}, %{$elseBlock} ]";

        // Allocate and store the result
        $ir[] = "  {$result} = alloca %struct.zval";
        $ir[] = "  store %struct.zval {$phiResult}, %struct.zval* {$result}";

        return $result;
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

    private function generateBooleanLiteral(BooleanLiteral $literal, array &$ir, array $globalVars): string
    {
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";
        $boolVal = $literal->value ? 1 : 0;
        $ir[] = "  call void @php_zval_bool(%struct.zval* {$result}, i32 {$boolVal})";
        return $result;
    }

    private function generateNullLiteral(NullLiteral $literal, array &$ir, array $globalVars): string
    {
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_null(%struct.zval* {$result})";
        return $result;
    }

    private function generateConstant(Constant $constant, array &$ir, array $globalVars): string
    {
        // Define known constants
        $constantValues = [
            'PATHINFO_FILENAME' => 8,  // PHP's PATHINFO_FILENAME constant value
            'CASE_LOWER' => 0,         // PHP's CASE_LOWER constant value
            'CASE_UPPER' => 1,         // PHP's CASE_UPPER constant value
        ];

        // Check for null constant
        if (strtolower($constant->name) === 'null') {
            $result = $this->getNextTempVariable();
            $ir[] = "  {$result} = alloca %struct.zval";
            $ir[] = "  call void @php_zval_null(%struct.zval* {$result})";
            return $result;
        }

        $value = $constantValues[$constant->name] ?? 0;

        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";
        $ir[] = "  call void @php_zval_int(%struct.zval* {$result}, i32 {$value})";
        return $result;
    }

    private function generateUnaryOperation(UnaryOperation $op, array &$ir, array $globalVars): string
    {
        $operandPtr = $this->generateExpression($op->operand, $ir, $globalVars);
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";

        switch ($op->operator) {
            case '!':
                // Boolean NOT: convert operand to int, check if zero, then invert
                $operandInt = $this->getNextTempVariable();
                $ir[] = "  {$operandInt} = call i32 @php_zval_to_int(%struct.zval* {$operandPtr})";

                // Check if operand is false (0)
                $isZero = $this->getNextTempVariable();
                $ir[] = "  {$isZero} = icmp eq i32 {$operandInt}, 0";

                // Convert i1 to i32 (0 or 1)
                $notResult = $this->getNextTempVariable();
                $ir[] = "  {$notResult} = zext i1 {$isZero} to i32";

                $ir[] = "  call void @php_zval_bool(%struct.zval* {$result}, i32 {$notResult})";
                break;
            default:
                throw new \RuntimeException("Unsupported unary operation: " . $op->operator);
        }

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
                }

                $ir[] = "  call void @php_zval_int(%struct.zval* {$result}, i32 {$intResult})";
                break;

            case '/':
                // Division produces a double (floating-point)
                $leftInt = $this->getNextTempVariable();
                $rightInt = $this->getNextTempVariable();
                $ir[] = "  {$leftInt} = call i32 @php_zval_to_int(%struct.zval* {$leftZval})";
                $ir[] = "  {$rightInt} = call i32 @php_zval_to_int(%struct.zval* {$rightZval})";

                // Convert to double for floating-point division
                $leftDouble = $this->getNextTempVariable();
                $rightDouble = $this->getNextTempVariable();
                $ir[] = "  {$leftDouble} = sitofp i32 {$leftInt} to double";
                $ir[] = "  {$rightDouble} = sitofp i32 {$rightInt} to double";

                $doubleResult = $this->getNextTempVariable();
                $ir[] = "  {$doubleResult} = fdiv double {$leftDouble}, {$rightDouble}";

                $ir[] = "  call void @php_zval_double(%struct.zval* {$result}, double {$doubleResult})";
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

        // Get all variable names from initializations (skip property assignments)
        $loopVarNames = [];
        foreach ($forStmt->initializations as $init) {
            if ($init instanceof Assignment && !$init->variable instanceof PropertyAccess) {
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
                // Check if it's a property assignment
                if ($init->variable instanceof PropertyAccess) {
                    $this->generatePropertyAssignment($init, $ir, $globalVars);
                } else {
                    $varName = $init->variable->name;

                    // Destroy old value if variable already exists
                    if (isset($this->declaredVars[$varName])) {
                        $ir[] = "  call void @php_zval_destroy(%struct.zval* %{$varName})";
                    }

                    $valuePtr = $this->generateExpression($init->value, $ir, $globalVars);

                    // Increment refcount on the new value
                    $ir[] = "  call void @php_zval_copy(%struct.zval* {$valuePtr})";

                    $value = $this->getNextTempVariable();
                    $ir[] = "  {$value} = load %struct.zval, %struct.zval* {$valuePtr}";
                    $ir[] = "  store %struct.zval {$value}, %struct.zval* %{$varName}";
                }
            } else {
                $this->generateStatement($init, $ir, $globalVars);
            }
        }

        // Jump to loop header
        $ir[] = "  br label %{$loopHeaderBlock}";

        // Push loop header and end onto stacks for continue/break statements
        $this->loopHeaderStack[] = $loopHeaderBlock;
        $this->loopEndStack[] = $loopAfterBlock;

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

        // Pop loop header and end from stacks
        array_pop($this->loopHeaderStack);
        array_pop($this->loopEndStack);

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

        // Push loop header and end onto stacks for continue/break statements
        $this->loopHeaderStack[] = $loopHeaderBlock;
        $this->loopEndStack[] = $loopAfterBlock;

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

        // Pop loop header and end from stacks
        array_pop($this->loopHeaderStack);
        array_pop($this->loopEndStack);

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

        // Push loop header and end onto stacks for continue/break statements
        $this->loopHeaderStack[] = $loopHeaderBlock;
        $this->loopEndStack[] = $loopAfterBlock;

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

        // Pop loop header and end from stacks
        array_pop($this->loopHeaderStack);
        array_pop($this->loopEndStack);

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

        // Variables are pre-declared in the entry block via collectAndDeclareVariables
        // We just need to track the variable names for use in this method
        $valueVarName = $foreachStmt->valueVar->name;
        $keyVarName = $foreachStmt->keyVar !== null ? $foreachStmt->keyVar->name : null;

        // Get the array expression
        $arrayPtr = $this->generateExpression($foreachStmt->array, $ir, $globalVars);

        // Create index variable (hidden from user)
        $indexVar = "%foreach_idx_" . $blockCounter;
        $ir[] = "  {$indexVar} = alloca i32";
        $ir[] = "  store i32 0, i32* {$indexVar}";

        // Get array size
        $arraySize = $this->getNextTempVariable();
        $ir[] = "  {$arraySize} = call i32 @php_array_size(%struct.zval* {$arrayPtr})";

        // Jump to loop header
        $ir[] = "  br label %{$loopHeaderBlock}";

        // Push loop header and end onto stacks for continue/break statements
        $this->loopHeaderStack[] = $loopHeaderBlock;
        $this->loopEndStack[] = $loopAfterBlock;

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

        // Pop loop header and end from stacks
        array_pop($this->loopHeaderStack);
        array_pop($this->loopEndStack);

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
        $varName = $varRef->name;

        // Special handling for $this
        if ($varName === 'this') {
            // $this is stored as a pointer to pointer, we need to load it
            $thisPtr = $this->getNextTempVariable();
            $ir[] = "  {$thisPtr} = load %struct.zval*, %struct.zval** %this";
            return $thisPtr;
        }

        // Check if variable is declared
        if (!isset($this->declaredVars[$varName])) {
            // Variable not declared - this shouldn't happen for proper PHP code
            // but we'll declare it on-the-fly as a fallback
            error_log("[LLVM WARNING] Variable '\$" . $varName . "' used before declaration at line " . $varRef->line);
            $ir[] = "  %{$varName} = alloca %struct.zval";
            $this->declaredVars[$varName] = true;
        }

        return "%" . $varName;
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
        $ir[] = "  call void @php_zval_string_literal(%struct.zval* {$result}, i8* {$ptrName})";
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

    private function generateNewExpression(NewExpression $newExpr, array &$ir, array $globalVars): string
    {
        // Allocate result zval
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";

        // Get the class name as a string constant
        $className = $newExpr->className;
        $globalName = "__str_const_" . md5($className);

        // Check if we need to create the global string constant
        if (!isset($globalVars[$globalName])) {
            $escapedValue = $this->escapeString($className);
            $globalVars[$globalName] = [
                'value' => $className,
                'escapedValue' => $escapedValue,
                'length' => strlen($className)
            ];
        }

        $globalData = $globalVars[$globalName];
        $classNamePtr = $this->getNextTempVariable();
        $ir[] = "  {$classNamePtr} = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";

        // Call php_object_create to create the object
        $ir[] = "  call void @php_object_create(%struct.zval* {$result}, i8* {$classNamePtr})";

        return $result;
    }

    private function generatePropertyAccess(PropertyAccess $propAccess, array &$ir, array $globalVars): string
    {
        // Generate the object expression
        $objPtr = $this->generateExpression($propAccess->object, $ir, $globalVars);

        // Allocate result zval
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";

        // Get the property name as a string constant
        $propName = $propAccess->propertyName;
        $globalName = "__str_const_" . md5($propName);

        // Check if we need to create the global string constant
        if (!isset($globalVars[$globalName])) {
            $escapedValue = $this->escapeString($propName);
            $globalVars[$globalName] = [
                'value' => $propName,
                'escapedValue' => $escapedValue,
                'length' => strlen($propName)
            ];
        }

        $globalData = $globalVars[$globalName];
        $propNamePtr = $this->getNextTempVariable();
        $ir[] = "  {$propNamePtr} = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";

        // Call php_object_property_get to get the property value
        $ir[] = "  call void @php_object_property_get(%struct.zval* {$result}, %struct.zval* {$objPtr}, i8* {$propNamePtr})";

        return $result;
    }

    private function generateMethodCall(MethodCall $methodCall, array &$ir, array $globalVars): string
    {
        // Generate the object expression to get the object pointer
        $objPtr = $this->generateExpression($methodCall->object, $ir, $globalVars);

        // Get the class name from the object variable
        // For now, we use a simple heuristic: look up the variable name in our type map
        $className = null;
        if ($methodCall->object instanceof VariableReference) {
            $varName = $methodCall->object->name;
            $className = $this->variableTypes[$varName] ?? null;
        }

        // If we can't determine the class, use a generic approach
        // For now, we'll just call a function with the method name and class prefix
        if ($className === null) {
            // Fallback: try to use the method name directly (for simple cases)
            $methodName = $methodCall->methodName;
        } else {
            $methodName = $className . '_' . $methodCall->methodName;
        }

        // Build argument list - first argument is $this (the object)
        $args = [];
        $args[] = "%struct.zval* {$objPtr}";

        // Generate arguments
        foreach ($methodCall->arguments as $arg) {
            $argPtr = $this->generateExpression($arg, $ir, $globalVars);
            $argVal = $this->getNextTempVariable();
            $ir[] = "  {$argVal} = load %struct.zval, %struct.zval* {$argPtr}";
            $args[] = "%struct.zval {$argVal}";
        }

        $argStr = implode(', ', $args);

        // Allocate result
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";

        // Call the method function
        $callResult = $this->getNextTempVariable();
        $ir[] = "  {$callResult} = call %struct.zval @{$methodName}({$argStr})";
        $ir[] = "  store %struct.zval {$callResult}, %struct.zval* {$result}";

        return $result;
    }

    private function generatePropertyAssignment(Assignment $assignment, array &$ir, array $globalVars): void
    {
        /** @var PropertyAccess $propAccess */
        $propAccess = $assignment->variable;

        // Generate the object expression
        $objPtr = $this->generateExpression($propAccess->object, $ir, $globalVars);

        // Get the property name as a string constant
        $propName = $propAccess->propertyName;
        $globalName = "__str_const_" . md5($propName);

        // Check if we need to create the global string constant
        if (!isset($globalVars[$globalName])) {
            $escapedValue = $this->escapeString($propName);
            $globalVars[$globalName] = [
                'value' => $propName,
                'escapedValue' => $escapedValue,
                'length' => strlen($propName)
            ];
        }

        $globalData = $globalVars[$globalName];
        $propNamePtr = $this->getNextTempVariable();
        $ir[] = "  {$propNamePtr} = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";

        if ($assignment->operator === '=') {
            // Simple assignment - just set the property
            $valuePtr = $this->generateExpression($assignment->value, $ir, $globalVars);
            $ir[] = "  call void @php_object_property_set(%struct.zval* {$objPtr}, i8* {$propNamePtr}, %struct.zval* {$valuePtr})";
        } else {
            // Compound assignment (+=, -=, *=, /=)
            // First, get the current property value
            $currentValPtr = $this->getNextTempVariable();
            $ir[] = "  {$currentValPtr} = alloca %struct.zval";
            $ir[] = "  call void @php_object_property_get(%struct.zval* {$currentValPtr}, %struct.zval* {$objPtr}, i8* {$propNamePtr})";

            // Get current value as integer
            $currentInt = $this->getNextTempVariable();
            $ir[] = "  {$currentInt} = call i32 @php_zval_to_int(%struct.zval* {$currentValPtr})";

            // Generate the value expression
            $valuePtr = $this->generateExpression($assignment->value, $ir, $globalVars);
            $valueInt = $this->getNextTempVariable();
            $ir[] = "  {$valueInt} = call i32 @php_zval_to_int(%struct.zval* {$valuePtr})";

            // Compute the result
            $resultInt = $this->getNextTempVariable();
            switch ($assignment->operator) {
                case '+=':
                    $ir[] = "  {$resultInt} = add i32 {$currentInt}, {$valueInt}";
                    break;
                case '-=':
                    $ir[] = "  {$resultInt} = sub i32 {$currentInt}, {$valueInt}";
                    break;
                case '*=':
                    $ir[] = "  {$resultInt} = mul i32 {$currentInt}, {$valueInt}";
                    break;
                case '/=':
                    $ir[] = "  {$resultInt} = sdiv i32 {$currentInt}, {$valueInt}";
                    break;
            }

            // Store result in a zval
            $resultPtr = $this->getNextTempVariable();
            $ir[] = "  {$resultPtr} = alloca %struct.zval";
            $ir[] = "  call void @php_zval_int(%struct.zval* {$resultPtr}, i32 {$resultInt})";

            // Set the property to the new value
            $ir[] = "  call void @php_object_property_set(%struct.zval* {$objPtr}, i8* {$propNamePtr}, %struct.zval* {$resultPtr})";
        }
    }

    private function generatePropertyAssignmentExpression(Assignment $assignment, array &$ir, array $globalVars): string
    {
        /** @var PropertyAccess $propAccess */
        $propAccess = $assignment->variable;

        // Generate the object expression
        $objPtr = $this->generateExpression($propAccess->object, $ir, $globalVars);

        // Get the property name as a string constant
        $propName = $propAccess->propertyName;
        $globalName = "__str_const_" . md5($propName);

        // Check if we need to create the global string constant
        if (!isset($globalVars[$globalName])) {
            $escapedValue = $this->escapeString($propName);
            $globalVars[$globalName] = [
                'value' => $propName,
                'escapedValue' => $escapedValue,
                'length' => strlen($propName)
            ];
        }

        $globalData = $globalVars[$globalName];
        $propNamePtr = $this->getNextTempVariable();
        $ir[] = "  {$propNamePtr} = getelementptr inbounds [{$globalData['length']} x i8], [{$globalData['length']} x i8]* @{$globalName}, i64 0, i64 0";

        if ($assignment->operator === '=') {
            // Simple assignment - just set the property
            $valuePtr = $this->generateExpression($assignment->value, $ir, $globalVars);
            $ir[] = "  call void @php_object_property_set(%struct.zval* {$objPtr}, i8* {$propNamePtr}, %struct.zval* {$valuePtr})";

            // Return a pointer to the assigned value
            $resultPtr = $this->getNextTempVariable();
            $ir[] = "  {$resultPtr} = alloca %struct.zval";
            $loadedVal = $this->getNextTempVariable();
            $ir[] = "  {$loadedVal} = load %struct.zval, %struct.zval* {$valuePtr}";
            $ir[] = "  store %struct.zval {$loadedVal}, %struct.zval* {$resultPtr}";
            return $resultPtr;
        } else {
            // Compound assignment (+=, -=, *=, /=)
            // First, get the current property value
            $currentValPtr = $this->getNextTempVariable();
            $ir[] = "  {$currentValPtr} = alloca %struct.zval";
            $ir[] = "  call void @php_object_property_get(%struct.zval* {$currentValPtr}, %struct.zval* {$objPtr}, i8* {$propNamePtr})";

            // Get current value as integer
            $currentInt = $this->getNextTempVariable();
            $ir[] = "  {$currentInt} = call i32 @php_zval_to_int(%struct.zval* {$currentValPtr})";

            // Generate the value expression
            $valuePtr = $this->generateExpression($assignment->value, $ir, $globalVars);
            $valueInt = $this->getNextTempVariable();
            $ir[] = "  {$valueInt} = call i32 @php_zval_to_int(%struct.zval* {$valuePtr})";

            // Compute the result
            $resultInt = $this->getNextTempVariable();
            switch ($assignment->operator) {
                case '+=':
                    $ir[] = "  {$resultInt} = add i32 {$currentInt}, {$valueInt}";
                    break;
                case '-=':
                    $ir[] = "  {$resultInt} = sub i32 {$currentInt}, {$valueInt}";
                    break;
                case '*=':
                    $ir[] = "  {$resultInt} = mul i32 {$currentInt}, {$valueInt}";
                    break;
                case '/=':
                    $ir[] = "  {$resultInt} = sdiv i32 {$currentInt}, {$valueInt}";
                    break;
            }

            // Store result in a zval
            $resultPtr = $this->getNextTempVariable();
            $ir[] = "  {$resultPtr} = alloca %struct.zval";
            $ir[] = "  call void @php_zval_int(%struct.zval* {$resultPtr}, i32 {$resultInt})";

            // Set the property to the new value
            $ir[] = "  call void @php_object_property_set(%struct.zval* {$objPtr}, i8* {$propNamePtr}, %struct.zval* {$resultPtr})";

            // Return a pointer to the result value
            return $resultPtr;
        }
    }

    private function escapeString(string $str): string
    {
        $replacements = [
            '\\' => '\\\\',
            '"' => '\22',
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

    private function generateVariableFunctionCall(VariableFunctionCall $varFuncCall, array &$ir, array $globalVars): string
    {
        // Generate the variable expression (which contains the function name as a string)
        $funcNamePtr = $this->generateExpression($varFuncCall->nameExpression, $ir, $globalVars);

        // Allocate result zval
        $result = $this->getNextTempVariable();
        $ir[] = "  {$result} = alloca %struct.zval";

        // Generate arguments - pass as array of zvals
        $argCount = count($varFuncCall->arguments);
        if ($argCount > 0) {
            // Allocate array for arguments
            $argsArray = $this->getNextTempVariable();
            $ir[] = "  {$argsArray} = alloca [%struct.zval x {$argCount}]";

            foreach ($varFuncCall->arguments as $i => $arg) {
                $argPtr = $this->generateExpression($arg, $ir, $globalVars);
                $argVal = $this->getNextTempVariable();
                $ir[] = "  {$argVal} = load %struct.zval, %struct.zval* {$argPtr}";
                $elemPtr = $this->getNextTempVariable();
                $ir[] = "  {$elemPtr} = getelementptr inbounds [%struct.zval x {$argCount}], [%struct.zval x {$argCount}]* {$argsArray}, i64 0, i64 {$i}";
                $ir[] = "  store %struct.zval {$argVal}, %struct.zval* {$elemPtr}";
            }

            // Get pointer to first element for the call
            $argsPtr = $this->getNextTempVariable();
            $ir[] = "  {$argsPtr} = getelementptr inbounds [%struct.zval x {$argCount}], [%struct.zval x {$argCount}]* {$argsArray}, i64 0, i64 0";
        } else {
            // No arguments - pass null pointer
            $argsPtr = "null";
        }

        // Call the runtime dispatch function with the function name zval directly
        // void php_variable_call(zval* func_name_zval, zval* args, int arg_count, zval* result)
        $ir[] = "  call void @php_variable_call(%struct.zval* {$funcNamePtr}, %struct.zval* {$argsPtr}, i32 {$argCount}, %struct.zval* {$result})";

        return $result;
    }
}
