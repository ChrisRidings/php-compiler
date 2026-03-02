<?php
// Autoloader
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

    if (file_exists(__DIR__ . '/' . $fileName)) {
        require_once __DIR__ . '/' . $fileName;
    }
});

use PhpCompiler\AST\Parser;

// Test parser reading test.php
$filename = 'test.php';
echo "Parsing file: $filename\n";

try {
    $parser = Parser::fromFile($filename);
    $statements = $parser->parse();

    foreach ($statements as $statement) {
        echo "\n=== Statement: " . get_class($statement) . " ===\n";
        var_dump($statement);
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
