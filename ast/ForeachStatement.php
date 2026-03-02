<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

/**
 * Represents a foreach loop: foreach ($array as $value) { ... }
 */
class ForeachStatement extends Statement
{
    /**
     * @param Expression $array The array expression to iterate over
     * @param VariableReference $valueVar The variable to hold the current value
     * @param Statement[] $body The loop body
     * @param VariableReference|null $keyVar Optional variable to hold the current key
     */
    public function __construct(
        public readonly Expression $array,
        public readonly VariableReference $valueVar,
        public readonly array $body,
        public readonly ?VariableReference $keyVar = null,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        $keyPart = $this->keyVar ? "{$this->keyVar} => " : "";
        $bodyStr = implode("\n", array_map(fn($s) => "  " . $s, $this->body));
        return "ForeachStatement(array: {$this->array}, as {$keyPart}{$this->valueVar}) {\n{$bodyStr}\n}";
    }
}
