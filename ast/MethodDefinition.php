<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class MethodDefinition extends Node
{
    /**
     * @param string $name Method name
     * @param Parameter[] $parameters Method parameters
     * @param Statement[] $body Method body statements
     * @param string $visibility public, private, or protected
     * @param bool $isStatic Whether this is a static method
     * @param int $line Line number
     * @param int $column Column number
     */
    public function __construct(
        public readonly string $name,
        public readonly array $parameters,
        public readonly array $body,
        public readonly string $visibility = 'public',
        public readonly bool $isStatic = false,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        $params = implode(', ', array_map(fn($p) => (string)$p, $this->parameters));
        $body = implode("; ", array_map(fn($s) => (string)$s, $this->body));
        return sprintf(
            "MethodDefinition(%s function %s(%s) { %s })",
            $this->visibility,
            $this->name,
            $params,
            $body
        );
    }
}
