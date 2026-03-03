<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class ClassDefinition extends Statement
{
    /**
     * @var PropertyDeclaration[]
     */
    public readonly array $properties;

    /**
     * @var MethodDefinition[]
     */
    public readonly array $methods;

    /**
     * @param string $name Class name
     * @param PropertyDeclaration[] $properties Property declarations
     * @param MethodDefinition[] $methods Method definitions
     * @param int $line Line number
     * @param int $column Column number
     */
    public function __construct(
        public readonly string $name,
        array $properties,
        array $methods = [],
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
        $this->properties = $properties;
        $this->methods = $methods;
    }

    public function __toString(): string
    {
        $propStr = implode(', ', array_map(fn($p) => (string)$p, $this->properties));
        $methodStr = implode(', ', array_map(fn($m) => (string)$m, $this->methods));
        return sprintf(
            "ClassDefinition(name: '%s', properties: [%s], methods: [%s])",
            $this->name,
            $propStr,
            $methodStr
        );
    }
}
