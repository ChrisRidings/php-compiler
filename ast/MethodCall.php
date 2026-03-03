<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class MethodCall extends Expression
{
    /**
     * @param Expression $object The object expression (e.g., $a in $a->hello())
     * @param string $methodName The method name
     * @param Expression[] $arguments Method arguments
     * @param int $line Line number
     * @param int $column Column number
     */
    public function __construct(
        public readonly Expression $object,
        public readonly string $methodName,
        public readonly array $arguments,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        $args = implode(', ', array_map(fn($a) => (string)$a, $this->arguments));
        return sprintf(
            "MethodCall(%s->%s(%s))",
            (string)$this->object,
            $this->methodName,
            $args
        );
    }
}
