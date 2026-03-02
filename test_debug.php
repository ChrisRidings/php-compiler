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

// Tokenize test.php to see what's happening
$filename = 'test.php';
$lexer = PhpCompiler\Lexer\Lexer::fromFile($filename);
$tokens = $lexer->tokenize();

echo "=== Tokens from test.php ===\n";
echo "\n";

$stringTokens = [];

foreach ($tokens as $i => $token) {
    if ($token->type === PhpCompiler\Lexer\TokenType::T_STRING) {
        echo "Token $i: ";
        var_dump($token);
        $stringTokens[] = $token;
    }
}

echo "\n=== StringLiteral creation ===\n";

foreach ($stringTokens as $i => $token) {
    $lit = PhpCompiler\AST\StringLiteral::fromQuotedString($token->value);
    echo "\nToken $i ($token->value):\n";
    var_dump($lit->value);
    echo "Value length: " . strlen($lit->value) . "\n";
    echo "MD5 hash: " . md5($lit->value) . "\n";
}
