<?php
// Manually include all required files
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

// Test string literal handling
$input = '"i=$i,j=$j ";"';
$lit = PhpCompiler\AST\StringLiteral::fromQuotedString($input);
echo "String Literal Value: ";
var_dump($lit->value);
echo "Value length: " . strlen($lit->value) . "\n";
echo "MD5 hash: " . md5($lit->value) . "\n";
