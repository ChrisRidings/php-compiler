<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class PropertyAccess extends Expression
{
    public function __construct(
        public readonly Expression $object,
        public readonly string $propertyName,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return sprintf("PropertyAccess(object: %s, property: '%s')",
            (string)$this->object, $this->propertyName);
    }
}
