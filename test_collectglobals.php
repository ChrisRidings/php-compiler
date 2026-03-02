<?php
require_once __DIR__ . '/ast/Node.php';
require_once __DIR__ . '/ast/Statement.php';
require_once __DIR__ . '/ast/Expression.php';
require_once __DIR__ . '/ast/StringLiteral.php';
require_once __DIR__ . '/ast/VariableReference.php';
require_once __DIR__ . '/ast/IntegerLiteral.php';
require_once __DIR__ . '/ast/FunctionDefinition.php';
require_once __DIR__ . '/ast/FunctionCall.php';
require_once __DIR__ . '/ast/Parameter.php';
require_once __DIR__ . '/ast/Assignment.php';
require_once __DIR__ . '/ast/ReturnStatement.php';
require_once __DIR__ . '/ast/BinaryOperation.php';
require_once __DIR__ . '/ast/EchoStatement.php';
require_once __DIR__ . '/ast/IfStatement.php';
require_once __DIR__ . '/ast/ForStatement.php';
require_once __DIR__ . '/ast/Parser.php';
require_once __DIR__ . '/lexer/Token.php';
require_once __DIR__ . '/lexer/Lexer.php';
require_once __DIR__ . '/llvm/Generator.php';

// Test collectGlobals
$filename = 'test.php';
$parser = PhpCompiler\AST\Parser::fromFile($filename);
$statements = $parser->parse();

echo "=== Statements from test.php ===\n";
foreach ($statements as $i => $statement) {
    echo "\n=== Statement $i ===\n";
    var_dump(get_class($statement));

    if (is_a($statement, PhpCompiler\AST\ForStatement::class)) {
        echo "\n  Body:\n";
        foreach ($statement->body as $j => $bodyStmt) {
            echo "  === Body Statement $j ===\n";
            var_dump(get_class($bodyStmt));

            if (is_a($bodyStmt, PhpCompiler\AST\EchoStatement::class)) {
                echo "  Expressions count: " . count($bodyStmt->expressions) . "\n";

                foreach ($bodyStmt->expressions as $k => $expr) {
                    echo "    === Expression $k ===\n";
                    var_dump(get_class($expr));

                    if (is_a($expr, PhpCompiler\AST\BinaryOperation::class)) {
                        echo "    Left: " . get_class($expr->left) . "\n";
                        echo "    Right: " . get_class($expr->right) . "\n";

                        if (is_a($expr->right, PhpCompiler\AST\StringLiteral::class)) {
                            echo "    String value: " . var_export($expr->right->value, true) . "\n";
                            echo "    MD5 hash: " . md5($expr->right->value) . "\n";
                        }
                    } elseif (is_a($expr, PhpCompiler\AST\StringLiteral::class)) {
                        echo "    String value: " . var_export($expr->value, true) . "\n";
                        echo "    MD5 hash: " . md5($expr->value) . "\n";
                    }
                }
            }
        }
    } elseif (is_a($statement, PhpCompiler\AST\EchoStatement::class)) {
        echo "\n  Expressions count: " . count($statement->expressions) . "\n";

        foreach ($statement->expressions as $k => $expr) {
            echo "    === Expression $k ===\n";
            var_dump(get_class($expr));

            if (is_a($expr, PhpCompiler\AST\BinaryOperation::class)) {
                echo "    Left: " . get_class($expr->left) . "\n";
                echo "    Right: " . get_class($expr->right) . "\n";

                if (is_a($expr->right, PhpCompiler\AST\StringLiteral::class)) {
                    echo "    String value: " . var_export($expr->right->value, true) . "\n";
                    echo "    MD5 hash: " . md5($expr->right->value) . "\n";
                }
            } elseif (is_a($expr, PhpCompiler\AST\StringLiteral::class)) {
                echo "    String value: " . var_export($expr->value, true) . "\n";
                echo "    MD5 hash: " . md5($expr->value) . "\n";
            }
        }
    }
}

echo "\n=== collectGlobals test ===\n";
$generator = new PhpCompiler\LLVM\Generator($statements);
$globalVars = [];
foreach ($statements as $statement) {
    $generator->collectGlobals($statement, $globalVars);
}

echo "\nGlobals found: " . count($globalVars) . "\n";

foreach ($globalVars as $globalName => $data) {
    echo "\n  Global name: " . $globalName . "\n";
    echo "  Value: " . var_export($data['value'], true) . "\n";
    echo "  Escaped value: " . $data['escapedValue'] . "\n";
    echo "  Length: " . $data['length'] . "\n";
}

// Check if " " string is collected
$hash = md5(" ");
$expectedGlobalName = "__str_const_" . $hash;
echo "\n=== Check if \" \" is collected ===\n";
echo "Expected global name: " . $expectedGlobalName . "\n";
if (isset($globalVars[$expectedGlobalName])) {
    echo "✅ Found: " . var_export($globalVars[$expectedGlobalName], true) . "\n";
} else {
    echo "❌ Not found\n";

    // Find all string literals with 1 character
    echo "\nAll single-character string literals in statements:\n";
    function findStringLiterals($node, &$strings) {
        if (is_a($node, PhpCompiler\AST\StringLiteral::class)) {
            $strings[] = $node->value;
        } elseif (is_object($node)) {
            $reflect = new ReflectionClass($node);
            foreach ($reflect->getProperties() as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($node);
                if (is_array($value)) {
                    foreach ($value as $item) {
                        findStringLiterals($item, $strings);
                    }
                } elseif (is_object($value)) {
                    findStringLiterals($value, $strings);
                }
            }
        } elseif (is_array($node)) {
            foreach ($node as $item) {
                findStringLiterals($item, $strings);
            }
        }
    }

    $allStringLiterals = [];
    findStringLiterals($statements, $allStringLiterals);

    echo "Strings found: " . count($allStringLiterals) . "\n";
    foreach ($allStringLiterals as $str) {
        if (strlen($str) === 1) {
            echo "  - " . var_export($str, true) . "\n";
            echo "    MD5: " . md5($str) . "\n";
        }
    }
}
