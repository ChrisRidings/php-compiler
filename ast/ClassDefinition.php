<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class ClassDefinition extends Statement
{
    /**
     * @var PropertyDeclaration[]
     */
    public readonly array $properties;

    public function __construct(
        public readonly string $name,
        array $properties,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
        $this->properties = $properties;
    }

    public function __toString(): string
    {
        $propStr = implode(', ', array_map(fn($p) => (string)$p, $this->properties));
        return sprintf("ClassDefinition(name: '%s', properties: [%s])", $this->name, $propStr);
    }
}
