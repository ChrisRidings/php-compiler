<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class CastExpression extends Expression
{
    /**
     * @param string $type The target type (e.g., 'int', 'string', 'bool', 'float')
     * @param Expression $expression The expression to cast
     */
    public function __construct(
        public readonly string $type,
        public readonly Expression $expression,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
    }

    public function __toString(): string
    {
        return sprintf("(%s) %s", $this->type, $this->expression);
    }
}
