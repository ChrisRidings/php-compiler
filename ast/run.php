<?php

// Register autoloader for all PhpCompiler classes
spl_autoload_register(function ($className) {
    $className = ltrim($className, '\\');
    $fileName  = '';
    $namespace = '';
    if ($lastNsPos = strrpos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

    $paths = [
        __DIR__ . '/' . $fileName,
        __DIR__ . '/../lexer/' . $fileName,
        __DIR__ . '/../llvm/' . $fileName
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }

    // Fallback to include all possible files if class not found
    $allFiles = array_merge(
        glob(__DIR__ . '/*.php'),
        glob(__DIR__ . '/../lexer/*.php'),
        glob(__DIR__ . '/../llvm/*.php')
    );

    foreach ($allFiles as $file) {
        if (strpos($file, $className) !== false) {
            require_once $file;
            return;
        }
    }
});

if ($argc < 2) {
    echo "Usage: php run.php <filename>\n";
    exit(1);
}

$filename = $argv[1];

try {
    $parser = PhpCompiler\AST\Parser::fromFile($filename);
    $statements = $parser->parse();

    echo "Parsing complete. Found " . count($statements) . " statements:\n\n";

    foreach ($statements as $statement) {
        echo $statement;

        // Check if this is the string literal we're looking for
        if (is_a($statement, PhpCompiler\AST\EchoStatement::class)) {
            foreach ($statement->expressions as $expr) {
                if (is_a($expr, PhpCompiler\AST\StringLiteral::class)) {
                    echo "\n";
                    var_dump($expr);
                    echo "Value length: " . strlen($expr->value) . "\n";
                    echo "MD5 hash of value: " . md5($expr->value) . "\n";
                } elseif (is_a($expr, PhpCompiler\AST\BinaryOperation::class)) {
                    if (is_a($expr->left, PhpCompiler\AST\StringLiteral::class)) {
                        var_dump($expr->left);
                        echo "Value length: " . strlen($expr->left->value) . "\n";
                        echo "MD5 hash of value: " . md5($expr->left->value) . "\n";
                    }
                    if (is_a($expr->right, PhpCompiler\AST\StringLiteral::class)) {
                        var_dump($expr->right);
                        echo "Value length: " . strlen($expr->right->value) . "\n";
                        echo "MD5 hash of value: " . md5($expr->right->value) . "\n";
                    }
                }
            }
        }

        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
