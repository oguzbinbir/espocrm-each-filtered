<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\Filter;

use Espo\Modules\PdfFilterHelper\Filter\Exception\TokenizerException;

/**
 * Tokenizes a filter expression string into a flat list of Token objects.
 *
 * Supported tokens:
 *   - FIELD      : identifier (letters, digits, underscores, dots)
 *   - STRING     : "..." or '...'
 *   - NUMBER     : integer or float
 *   - BOOL       : true / false
 *   - NULL       : null
 *   - SPECIAL    : today / now
 *   - ARRAY_OPEN : [
 *   - ARRAY_CLOSE: ]
 *   - COMMA      : ,
 *   - PAREN_OPEN : (
 *   - PAREN_CLOSE: )
 *   - OP         : == != >= <= > <
 *   - IN         : in keyword
 *   - AND        : &&
 *   - OR         : ||
 *   - EOF
 */
class Tokenizer
{
    private string $input;
    private int $pos;
    private int $length;

    /** @var Token[] */
    private array $tokens = [];

    public function tokenize(string $input): TokenStream
    {
        $this->input  = $input;
        $this->pos    = 0;
        $this->length = strlen($input);
        $this->tokens = [];

        while ($this->pos < $this->length) {
            $this->skipWhitespace();

            if ($this->pos >= $this->length) {
                break;
            }

            $char = $this->input[$this->pos];

            // Two-character operators first
            if ($this->pos + 1 < $this->length) {
                $two = substr($this->input, $this->pos, 2);
                if (in_array($two, ['==', '!=', '>=', '<=', '&&', '||'], true)) {
                    $type = match ($two) {
                        '==', '!=', '>=', '<=' => TokenType::OP,
                        '&&'                    => TokenType::AND,
                        '||'                    => TokenType::OR,
                    };
                    $this->tokens[] = new Token($type, $two);
                    $this->pos += 2;
                    continue;
                }
            }

            // Single-character tokens
            if (in_array($char, ['>', '<'], true)) {
                $this->tokens[] = new Token(TokenType::OP, $char);
                $this->pos++;
                continue;
            }

            if ($char === '[') {
                $this->tokens[] = new Token(TokenType::ARRAY_OPEN, '[');
                $this->pos++;
                continue;
            }

            if ($char === ']') {
                $this->tokens[] = new Token(TokenType::ARRAY_CLOSE, ']');
                $this->pos++;
                continue;
            }

            if ($char === ',') {
                $this->tokens[] = new Token(TokenType::COMMA, ',');
                $this->pos++;
                continue;
            }

            if ($char === '(') {
                $this->tokens[] = new Token(TokenType::PAREN_OPEN, '(');
                $this->pos++;
                continue;
            }

            if ($char === ')') {
                $this->tokens[] = new Token(TokenType::PAREN_CLOSE, ')');
                $this->pos++;
                continue;
            }

            // String literals
            if ($char === '"' || $char === "'") {
                $this->tokens[] = $this->readString($char);
                continue;
            }

            // Numbers
            if (ctype_digit($char) || ($char === '-' && $this->pos + 1 < $this->length && ctype_digit($this->input[$this->pos + 1]))) {
                $this->tokens[] = $this->readNumber();
                continue;
            }

            // Identifiers, keywords, booleans, null, special values
            if (ctype_alpha($char) || $char === '_') {
                $this->tokens[] = $this->readIdentifier();
                continue;
            }

            throw new TokenizerException(
                sprintf("Unexpected character '%s' at position %d", $char, $this->pos)
            );
        }

        $this->tokens[] = new Token(TokenType::EOF, '');

        return new TokenStream($this->tokens);
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->length && ctype_space($this->input[$this->pos])) {
            $this->pos++;
        }
    }

    private function readString(string $quote): Token
    {
        $this->pos++; // skip opening quote
        $value = '';

        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if ($char === '\\' && $this->pos + 1 < $this->length) {
                $next = $this->input[$this->pos + 1];
                $value .= match ($next) {
                    'n'  => "\n",
                    't'  => "\t",
                    'r'  => "\r",
                    '\\' => '\\',
                    default => $next,
                };
                $this->pos += 2;
                continue;
            }

            if ($char === $quote) {
                $this->pos++;
                return new Token(TokenType::STRING, $value);
            }

            $value .= $char;
            $this->pos++;
        }

        throw new TokenizerException("Unterminated string literal");
    }

    private function readNumber(): Token
    {
        $start = $this->pos;

        if ($this->input[$this->pos] === '-') {
            $this->pos++;
        }

        while ($this->pos < $this->length && ctype_digit($this->input[$this->pos])) {
            $this->pos++;
        }

        if ($this->pos < $this->length && $this->input[$this->pos] === '.') {
            $this->pos++;
            while ($this->pos < $this->length && ctype_digit($this->input[$this->pos])) {
                $this->pos++;
            }
        }

        return new Token(TokenType::NUMBER, substr($this->input, $start, $this->pos - $start));
    }

    private function readIdentifier(): Token
    {
        $start = $this->pos;

        while ($this->pos < $this->length && (ctype_alnum($this->input[$this->pos]) || in_array($this->input[$this->pos], ['_', '.'], true))) {
            $this->pos++;
        }

        $word = substr($this->input, $start, $this->pos - $start);

        $type = match (strtolower($word)) {
            'true', 'false'  => TokenType::BOOL,
            'null'           => TokenType::NULL,
            'today', 'now'   => TokenType::SPECIAL,
            'in'             => TokenType::IN,
            default          => TokenType::FIELD,
        };

        return new Token($type, $word);
    }
}
