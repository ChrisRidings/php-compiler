<?php

require_once __DIR__ . '/lexer/Token.php';
require_once __DIR__ . '/lexer/Lexer.php';

use PhpCompiler\Lexer\Lexer;
use PhpCompiler\Lexer\TokenType;

// Test lexing test.php
$filename = __DIR__ . '/test.php';
$lexer = Lexer::fromFile($filename);
$tokens = $lexer->tokenize();

echo "Tokens:\n";
foreach ($tokens as $i => $token) {
    echo "  [$i] " . $token->type->name . "='" . $token->value . "'\n";
}
?>
