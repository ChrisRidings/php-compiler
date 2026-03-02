#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/Token.php';
require_once __DIR__ . '/Lexer.php';

use PhpCompiler\Lexer\Lexer;

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
        $lexer = Lexer::fromFile($filename);
        $tokens = $lexer->tokenize();

        echo "Tokenization complete. Found " . count($tokens) . " tokens:\n\n";

        foreach ($tokens as $token) {
            echo $token . "\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

main();
