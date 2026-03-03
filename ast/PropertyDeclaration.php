<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class PropertyDeclaration extends Statement
{
    public function __construct(
        public readonly string $visibility,
        public readonly string $name,
        public readonly ?Expression $defaultValue = null,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        $defaultStr = $this->defaultValue ? (string)$this->defaultValue : 'null';
        return sprintf("PropertyDeclaration(visibility: '%s', name: '%s', default: %s)",
            $this->visibility, $this->name, $defaultStr);
    }
}
