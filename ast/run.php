#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../lexer/Token.php';
require_once __DIR__ . '/../lexer/Lexer.php';
require_once __DIR__ . '/Node.php';
require_once __DIR__ . '/Expression.php';
require_once __DIR__ . '/Statement.php';
require_once __DIR__ . '/StringLiteral.php';
require_once __DIR__ . '/IntegerLiteral.php';
require_once __DIR__ . '/EchoStatement.php';
require_once __DIR__ . '/ReturnStatement.php';
require_once __DIR__ . '/BinaryOperation.php';
require_once __DIR__ . '/Parameter.php';
require_once __DIR__ . '/FunctionDefinition.php';
require_once __DIR__ . '/FunctionCall.php';
require_once __DIR__ . '/VariableReference.php';
require_once __DIR__ . '/Assignment.php';
require_once __DIR__ . '/IfStatement.php';
require_once __DIR__ . '/Parser.php';

use PhpCompiler\AST\Parser;

function main(): void
{
    global $argc, $argv;

    if ($argc < 2) {
        echo "Usage: php run.php <filename>\n";
        exit(1);
    }

    $filename = $argv[1];

    if (!file_exists($filename)) {
        echo "Error: File '$filename' does not exist\n";
        exit(1);
    }

    if (!is_readable($filename)) {
        echo "Error: File '$filename' is not readable\n";
        exit(1);
    }

    try {
        $parser = Parser::fromFile($filename);
        $statements = $parser->parse();

        echo "Parsing complete. Found " . count($statements) . " statements:\n\n";

        foreach ($statements as $statement) {
            echo $statement . "\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

main();
