<?php

declare(strict_types=1);

namespace PhpCompiler\LLVM;

use PhpCompiler\AST\EchoStatement;
use PhpCompiler\AST\Node;
use PhpCompiler\AST\Parser;
use PhpCompiler\AST\Statement;
use PhpCompiler\AST\StringLiteral;

class Generator
{
    /**
     * @var Statement[]
     */
    private array $statements;

    public function __construct(array $statements)
    {
        $this->statements = $statements;
    }

    public static function fromFile(string $filename): self
    {
        $parser = Parser::fromFile($filename);
        $statements = $parser->parse();
        return new self($statements);
    }

    public function generate(): string
    {
        $ir = [];

        // LLVM IR header
        $ir[] = "; ModuleID = 'phpcompiler'";
        $ir[] = "target datalayout = \"e-m:w-p:64:64-i64:64-f80:128-n8:16:32:64-S128\"";
        $ir[] = "target triple = \"x86_64-pc-windows-msvc\"";
        $ir[] = "";

        // Declare external function for php_echo
        $ir[] = "declare void @php_echo(i8*)";
        $ir[] = "";

        // Define main function
        $ir[] = "define i32 @main() {";
        $ir[] = "entry:";

        // Generate code for each statement
        foreach ($this->statements as $statement) {
            $this->generateStatement($statement, $ir);
        }

        // Return 0 from main
        $ir[] = "  ret i32 0";
        $ir[] = "}";
        $ir[] = "";

        return implode("\n", $ir);
    }

    private function generateStatement(Statement $statement, array &$ir): void
    {
        if ($statement instanceof EchoStatement) {
            $this->generateEchoStatement($statement, $ir);
        } else {
            throw new \RuntimeException(
                sprintf("Unsupported statement type: %s", get_class($statement))
            );
        }
    }

    private function generateEchoStatement(EchoStatement $statement, array &$ir): void
    {
        foreach ($statement->expressions as $expression) {
            $this->generateExpression($expression, $ir);
        }
    }

    private function generateExpression(Node $expression, array &$ir): void
    {
        if ($expression instanceof StringLiteral) {
            $this->generateStringLiteral($expression, $ir);
        } else {
            throw new \RuntimeException(
                sprintf("Unsupported expression type: %s", get_class($expression))
            );
        }
    }

    private function generateStringLiteral(StringLiteral $literal, array &$ir): void
    {
        // Create a global string constant
        $globalName = "__str_const_" . md5($literal->value);
        $escapedValue = $this->escapeString($literal->value);

        $ir[] = "; Global string constant";
        $ir[] = "@{$globalName} = private unnamed_addr constant [". (strlen($literal->value) + 1) . " x i8] c\"{$escapedValue}\\00\"";
        $ir[] = "";

        // Get pointer to the string
        $ir[] = "  %{$globalName}_ptr = getelementptr inbounds [". (strlen($literal->value) + 1) . " x i8], [". (strlen($literal->value) + 1) . " x i8]* @{$globalName}, i64 0, i64 0";

        // Call php_echo function
        $ir[] = "  call void @php_echo(i8* %{$globalName}_ptr)";
        $ir[] = "";
    }

    private function escapeString(string $str): string
    {
        $replacements = [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            "\0" => '\\0',
        ];

        return strtr($str, $replacements);
    }
}
