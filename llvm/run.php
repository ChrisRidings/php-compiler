#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../lexer/Token.php';
require_once __DIR__ . '/../lexer/Lexer.php';
require_once __DIR__ . '/../ast/Node.php';
require_once __DIR__ . '/../ast/Expression.php';
require_once __DIR__ . '/../ast/Statement.php';
require_once __DIR__ . '/../ast/StringLiteral.php';
require_once __DIR__ . '/../ast/IntegerLiteral.php';
require_once __DIR__ . '/../ast/EchoStatement.php';
require_once __DIR__ . '/../ast/ReturnStatement.php';
require_once __DIR__ . '/../ast/Parameter.php';
require_once __DIR__ . '/../ast/FunctionDefinition.php';
require_once __DIR__ . '/../ast/FunctionCall.php';
require_once __DIR__ . '/../ast/VariableReference.php';
require_once __DIR__ . '/../ast/Assignment.php';
require_once __DIR__ . '/../ast/Parser.php';
require_once __DIR__ . '/Generator.php';

use PhpCompiler\LLVM\Generator;

function main(): void
{
    global $argc, $argv;

    if ($argc < 2) {
        echo "Usage: php run.php <filename> [output.ll]\n";
        exit(1);
    }

    $filename = $argv[1];
    $outputFile = $argc > 2 ? $argv[2] : 'output.ll';

    if (!file_exists($filename)) {
        echo "Error: File '$filename' does not exist\n";
        exit(1);
    }

    if (!is_readable($filename)) {
        echo "Error: File '$filename' is not readable\n";
        exit(1);
    }

    try {
        $generator = Generator::fromFile($filename);
        $ir = $generator->generate();

        file_put_contents($outputFile, $ir);

        echo "LLVM IR generation complete. Output written to '$outputFile'\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

main();
