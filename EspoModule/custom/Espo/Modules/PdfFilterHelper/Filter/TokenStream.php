<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\Filter;

use Espo\Modules\PdfFilterHelper\Filter\Exception\TokenizerException;

/**
 * Wraps a flat token array and provides cursor-based navigation.
 */
class TokenStream
{
    private int $pos = 0;

    /** @param Token[] $tokens */
    public function __construct(private readonly array $tokens) {}

    public function peek(): Token
    {
        return $this->tokens[$this->pos];
    }

    public function consume(): Token
    {
        $token = $this->tokens[$this->pos];
        $this->pos++;
        return $token;
    }

    public function expect(TokenType $type): Token
    {
        $token = $this->consume();

        if ($token->type !== $type) {
            throw new TokenizerException(
                sprintf("Expected token %s but got %s ('%s')", $type->value, $token->type->value, $token->value)
            );
        }

        return $token;
    }

    public function isEof(): bool
    {
        return $this->tokens[$this->pos]->type === TokenType::EOF;
    }
}
