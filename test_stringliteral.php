<?php
require_once __DIR__ . '/ast/StringLiteral.php';

$input = '"i=$i,j=$j ";"';
$lit = PhpCompiler\AST\StringLiteral::fromQuotedString($input);
echo "Value: ";
var_dump($lit->value);
echo "Value length: " . strlen($lit->value) . "\n";
echo "MD5 hash: " . md5($lit->value) . "\n";
