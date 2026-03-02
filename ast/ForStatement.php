<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class ForStatement extends Statement
{
    /**
     * @param Statement[] $initializations
     * @param Expression|null $condition
     * @param Statement[] $updates
     * @param Statement[] $body
     */
    public function __construct(
        public readonly array $initializations,
        public readonly ?Expression $condition,
        public readonly array $updates,
        public readonly array $body,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        $initStr = implode(', ', array_map(fn($stmt) => (string)$stmt, $this->initializations));
        $condStr = $this->condition ? (string)$this->condition : '';
        $updateStr = implode(', ', array_map(fn($stmt) => (string)$stmt, $this->updates));
        $bodyStr = implode("\n", array_map(fn($stmt) => (string)$stmt, $this->body));

        return sprintf("for (%s; %s; %s) {\n%s\n}", $initStr, $condStr, $updateStr, $bodyStr);
    }
}
