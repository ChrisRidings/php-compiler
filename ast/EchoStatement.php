<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class EchoStatement extends Statement
{
    /**
     * @var Expression[]
     */
    public readonly array $expressions;

    public function __construct(
        array $expressions,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
        $this->expressions = $expressions;
    }

    public function __toString(): string
    {
        $exprStr = implode(', ', array_map(fn($e) => (string)$e, $this->expressions));
        return sprintf('EchoStatement(expressions: [%s])', $exprStr);
    }
}
