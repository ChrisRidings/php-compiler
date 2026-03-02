<?php
// Manually include all required files
require_once __DIR__ . '/ast/Node.php';
require_once __DIR__ . '/ast/Statement.php';
require_once __DIR__ . '/ast/Expression.php';
require_once __DIR__ . '/ast/StringLiteral.php';

// Test string literal handling
$input = '"i=$i,j=$j ";;';
$lit = PhpCompiler\AST\StringLiteral::fromQuotedString($input);
echo "=== Test String Literal ===\n";
echo "Input: " . var_export($input, true) . "\n";
echo "Value after trim: " . var_export(trim($input, '\'"'), true) . "\n";
echo "Value: ";
var_dump($lit->value);
echo "Value length: " . strlen($lit->value) . "\n";
echo "MD5 hash: " . md5($lit->value) . "\n";
echo "\n";

// Test what the actual token has
echo "=== Token Value ===\n";
$tokenValue = '"i=$i,j=$j ";"';
echo "Token value: " . var_export($tokenValue, true) . "\n";
$lit2 = PhpCompiler\AST\StringLiteral::fromQuotedString($tokenValue);
echo "Value: ";
var_dump($lit2->value);
echo "Value length: " . strlen($lit2->value) . "\n";
echo "MD5 hash: " . md5($lit2->value) . "\n";
echo "\n";

// Check the warning hash
$warningHash = '7215ee9c7d9dc229d2921a40e899ec5f';
echo "=== Warning Hash ($warningHash) ===\n";
$possibleValues = [
    "i=\$i,j=\$j ",
    'i=\'$i\',j=\'$j\' ',
    "i=$i,j=$j",
    'i=$i,j=$j '
];
foreach ($possibleValues as $val) {
    echo "Testing: " . var_export($val, true) . "\n";
    echo "MD5: " . md5($val) . "\n";
    if (md5($val) === $warningHash) {
        echo "✅ MATCH!\n";
    }
    echo "\n";
}

echo "=== MD5 of 7215ee9c7d9dc229d2921a40e899ec5f ===\n";
$hash = '7215ee9c7d9dc229d2921a40e899ec5f';
echo "Trying to reverse: " . $hash . "\n";
?>
