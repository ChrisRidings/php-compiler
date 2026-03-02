<?php
// Test the fixed Lexer
require 'lexer/Token.php';
require 'lexer/Lexer.php';

$code = "<?php\ndeclare(strict_types=1);\n\$x = 'php';";
echo "Testing:\n$code\n\n";

$lexer = new \PhpCompiler\Lexer\Lexer($code);
try {
    $tokens = $lexer->tokenize();
    echo "SUCCESS! Tokens:\n";
    foreach ($tokens as $token) {
        echo "  {$token->type->name} => " . var_export($token->value, true) . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
