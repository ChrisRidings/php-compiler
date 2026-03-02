<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class DoWhileStatement extends Statement
{
    /**
     * @param Statement[] $body
     * @param Expression $condition
     */
    public function __construct(
        public readonly array $body,
        public readonly Expression $condition,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        $bodyStr = implode("\n", array_map(fn($stmt) => (string)$stmt, $this->body));
        return sprintf("do {\n%s\n} while (%s);", $bodyStr, $this->condition);
    }
}
