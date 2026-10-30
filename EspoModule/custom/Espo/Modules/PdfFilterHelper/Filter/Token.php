<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\Filter;

class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
    ) {}

    public function is(TokenType $type): bool
    {
        return $this->type === $type;
    }

    public function __toString(): string
    {
        return sprintf('Token(%s, %s)', $this->type->value, $this->value);
    }
}
