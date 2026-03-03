<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class NewExpression extends Expression
{
    /**
     * @var Expression[]
     */
    public readonly array $arguments;

    public function __construct(
        public readonly string $className,
        array $arguments = [],
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
        $this->arguments = $arguments;
    }

    public function __toString(): string
    {
        $argStr = implode(', ', array_map(fn($a) => (string)$a, $this->arguments));
        return sprintf("NewExpression(class: '%s', arguments: [%s])", $this->className, $argStr);
    }
}
