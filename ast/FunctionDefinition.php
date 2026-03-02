<?php

declare(strict_types=1);

namespace PhpCompiler\AST;

class FunctionDefinition extends Statement
{
    /**
     * @var Parameter[]
     */
    public readonly array $parameters;

    /**
     * @var Statement[]
     */
    public readonly array $body;

    public function __construct(
        public readonly string $name,
        array $parameters,
        array $body,
        int $line = 1,
        int $column = 1
    ) {
        parent::__construct($line, $column);
        $this->parameters = $parameters;
        $this->body = $body;
    }

    public function __toString(): string
    {
        $paramStr = implode(', ', array_map(fn($p) => (string)$p, $this->parameters));
        $bodyStr = implode("\n    ", array_map(fn($s) => (string)$s, $this->body));

        return sprintf(
            "FunctionDefinition(name: '%s', parameters: [%s], body: [\n    %s\n])",
            $this->name,
            $paramStr,
            $bodyStr
        );
    }
}
