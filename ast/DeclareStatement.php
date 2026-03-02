<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

/**
 * Represents a PHP declare statement (e.g., declare(strict_types=1);)
 * This is treated as a no-op since it's a PHP runtime directive.
 */
class DeclareStatement extends Statement
{
    /**
     * @param array<string, mixed> $directives Key-value pairs of directives
     */
    public function __construct(
        public readonly array $directives,
        int $line = 0,
        int $column = 0
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return "DeclareStatement(directives: " . json_encode($this->directives) . ")";
    }
}
