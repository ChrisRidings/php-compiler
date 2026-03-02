<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class WhileStatement extends Statement
{
    /**
     * @param Expression $condition
     * @param Statement[] $body
     */
    public function __construct(
        public readonly Expression $condition,
        public readonly array $body,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        $bodyStr = implode("\n", array_map(fn($stmt) => (string)$stmt, $this->body));
        return sprintf("while (%s) {\n%s\n}", $this->condition, $bodyStr);
    }
}
