<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\Filter;

use Espo\Modules\PdfFilterHelper\Filter\Ast\ArrayNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\ComparisonNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\FieldNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\InNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\LiteralNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\LogicalNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\Node;
use Espo\Modules\PdfFilterHelper\Filter\Ast\SpecialNode;
use Espo\Modules\PdfFilterHelper\Filter\Exception\ParseException;

/**
 * Recursive-descent parser for the filter mini-language.
 *
 * Grammar (simplified):
 *   expr      := or_expr
 *   or_expr   := and_expr ('||' and_expr)*
 *   and_expr  := primary ('&&' primary)*
 *   primary   := '(' expr ')'
 *              | field 'in' array
 *              | field op value
 *   field     := FIELD
 *   op        := '==' | '!=' | '>' | '>=' | '<' | '<='
 *   value     := STRING | NUMBER | BOOL | NULL | SPECIAL
 *   array     := '[' (value (',' value)*)? ']'
 */
class Parser
{
    public function parse(TokenStream $stream): Node
    {
        $node = $this->parseOr($stream);

        if (!$stream->isEof()) {
            $token = $stream->peek();
            throw new ParseException(
                sprintf("Unexpected token %s ('%s') after expression end", $token->type->value, $token->value)
            );
        }

        return $node;
    }

    // -----------------------------------------------------------------------
    // Precedence levels
    // -----------------------------------------------------------------------

    private function parseOr(TokenStream $stream): Node
    {
        $left = $this->parseAnd($stream);

        while ($stream->peek()->is(TokenType::OR)) {
            $stream->consume();
            $right = $this->parseAnd($stream);
            $left  = new LogicalNode('||', $left, $right);
        }

        return $left;
    }

    private function parseAnd(TokenStream $stream): Node
    {
        $left = $this->parsePrimary($stream);

        while ($stream->peek()->is(TokenType::AND)) {
            $stream->consume();
            $right = $this->parsePrimary($stream);
            $left  = new LogicalNode('&&', $left, $right);
        }

        return $left;
    }

    private function parsePrimary(TokenStream $stream): Node
    {
        // Grouped sub-expression
        if ($stream->peek()->is(TokenType::PAREN_OPEN)) {
            $stream->consume();
            $node = $this->parseOr($stream);
            $stream->expect(TokenType::PAREN_CLOSE);
            return $node;
        }

        // Must start with a field identifier
        $fieldToken = $stream->expect(TokenType::FIELD);
        $field      = new FieldNode($fieldToken->value);

        // in operator
        if ($stream->peek()->is(TokenType::IN)) {
            $stream->consume();
            $array = $this->parseArray($stream);
            return new InNode($field, $array);
        }

        // Comparison operator
        if ($stream->peek()->is(TokenType::OP)) {
            $opToken = $stream->consume();
            $value   = $this->parseValue($stream);
            return new ComparisonNode($field, $opToken->value, $value);
        }

        throw new ParseException(
            sprintf("Expected operator or 'in' after field '%s', got '%s'", $field->name, $stream->peek()->value)
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function parseValue(TokenStream $stream): Node
    {
        $token = $stream->peek();

        if ($token->is(TokenType::SPECIAL)) {
            $stream->consume();
            return new SpecialNode($token->value);
        }

        return new LiteralNode($this->extractLiteralValue($stream->consume()));
    }

    private function parseArray(TokenStream $stream): ArrayNode
    {
        $stream->expect(TokenType::ARRAY_OPEN);
        $items = [];

        if (!$stream->peek()->is(TokenType::ARRAY_CLOSE)) {
            $items[] = new LiteralNode($this->extractLiteralValue($stream->consume()));

            while ($stream->peek()->is(TokenType::COMMA)) {
                $stream->consume();
                $items[] = new LiteralNode($this->extractLiteralValue($stream->consume()));
            }
        }

        $stream->expect(TokenType::ARRAY_CLOSE);

        return new ArrayNode($items);
    }

    private function extractLiteralValue(Token $token): string|int|float|bool|null
    {
        return match ($token->type) {
            TokenType::STRING => $token->value,
            TokenType::NUMBER => str_contains($token->value, '.') ? (float) $token->value : (int) $token->value,
            TokenType::BOOL   => strtolower($token->value) === 'true',
            TokenType::NULL   => null,
            default           => throw new ParseException(
                sprintf("Expected literal value, got %s ('%s')", $token->type->value, $token->value)
            ),
        };
    }
}
