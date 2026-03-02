<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class IfStatement extends Statement
{
    /**
     * @param Expression $condition The condition to evaluate
     * @param Statement[] $thenBody The statements to execute if the condition is true
     * @param Statement[] $elseBody The statements to execute if the condition is false (can be empty)
     */
    public function __construct(
        public readonly Expression $condition,
        public readonly array $thenBody,
        public readonly array $elseBody = [],
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        $thenStr = implode("\n", array_map(fn($stmt) => (string)$stmt, $this->thenBody));
        $elseStr = $this->elseBody ? "\nelse {\n" . implode("\n", array_map(fn($stmt) => (string)$stmt, $this->elseBody)) . "\n}" : '';
        return sprintf("if (%s) {\n%s\n}%s", $this->condition, $thenStr, $elseStr);
    }
}
