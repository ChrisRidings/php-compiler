<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class VariableFunctionCall extends Expression
{
    /**
     * @var Expression The expression that evaluates to the function name
     */
    public readonly Expression $nameExpression;

    /**
     * @var Expression[] Arguments to pass to the function
     */
    public readonly array $arguments;

    public function __construct(Expression $nameExpression, array $arguments, int $line = 1, int $column = 1)
    {
        parent::__construct($line, $column);
        $this->nameExpression = $nameExpression;
        $this->arguments = $arguments;
    }

    public function __toString(): string
    {
        $argStr = implode(', ', array_map(fn($a) => (string)$a, $this->arguments));
        return sprintf("VariableFunctionCall(name: %s, arguments: [%s])", $this->nameExpression, $argStr);
    }
}
