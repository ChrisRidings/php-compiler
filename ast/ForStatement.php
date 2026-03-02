<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class ForStatement extends Statement
{
    public function __construct(
        public readonly ?Statement $initialization,
        public readonly ?Expression $condition,
        public readonly mixed $update,
        public readonly array $body,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        $initStr = $this->initialization ? (string)$this->initialization : '';
        $condStr = $this->condition ? (string)$this->condition : '';
        $updateStr = $this->update ? (string)$this->update : '';
        $bodyStr = implode("\n", array_map(fn($stmt) => (string)$stmt, $this->body));

        return sprintf("for (%s; %s; %s) {\n%s\n}", $initStr, $condStr, $updateStr, $bodyStr);
    }
}
