<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\Filter;

use DateTimeImmutable;
use Espo\Modules\PdfFilterHelper\Filter\Ast\ArrayNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\ComparisonNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\FieldNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\InNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\LiteralNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\LogicalNode;
use Espo\Modules\PdfFilterHelper\Filter\Ast\Node;
use Espo\Modules\PdfFilterHelper\Filter\Ast\SpecialNode;
use Espo\Modules\PdfFilterHelper\Filter\Exception\CompilerException;
use Espo\ORM\Query\Part\WhereClause;

/**
 * Walks the AST and produces an Espo ORM WhereClause array.
 *
 * WhereClause format (Espo native):
 *   ['fieldName' => value]                     // equals
 *   ['fieldName!=' => value]                   // not equals
 *   ['fieldName>' => value]                    // greater than
 *   ['fieldName>=' => value]                   // gte
 *   ['fieldName<' => value]                    // less than
 *   ['fieldName<=' => value]                   // lte
 *   ['fieldName' => [v1, v2]]                  // IN list (Espo uses array for IN)
 *   ['AND' => [..., ...]]                      // logical AND
 *   ['OR'  => [..., ...]]                      // logical OR
 */
class WhereClauseCompiler
{
    /**
     * Entry point. Returns a raw array suitable for WhereClause::fromRaw().
     *
     * @return array<string, mixed>
     */
    public function compile(Node $node): array
    {
        return $this->walk($node);
    }

    // -----------------------------------------------------------------------
    // Node dispatch
    // -----------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function walk(Node $node): array
    {
        return match (true) {
            $node instanceof LogicalNode    => $this->walkLogical($node),
            $node instanceof ComparisonNode => $this->walkComparison($node),
            $node instanceof InNode         => $this->walkIn($node),
            default                         => throw new CompilerException(
                sprintf("Unsupported AST node: %s", get_class($node))
            ),
        };
    }

    private function walkLogical(LogicalNode $node): array
    {
        $operator = $node->operator === '&&' ? 'AND' : 'OR';

        return [
            $operator => [
                $this->walk($node->left),
                $this->walk($node->right),
            ],
        ];
    }

    private function walkComparison(ComparisonNode $node): array
    {
        $field = $this->fieldName($node->field);
        $value = $this->resolveValue($node->value);

        $suffix = match ($node->operator) {
            '=='    => '',
            '!='    => '!=',
            '>'     => '>',
            '>='    => '>=',
            '<'     => '<',
            '<='    => '<=',
            default => throw new CompilerException("Unknown operator: {$node->operator}"),
        };

        return [$field . $suffix => $value];
    }

    private function walkIn(InNode $node): array
    {
        $field  = $this->fieldName($node->field);
        $values = array_map(fn(LiteralNode $n) => $n->value, $node->list->items);

        // Espo uses plain array for IN
        return [$field => $values];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Converts dot notation (e.g. "contact.name") to Espo's camelCase link
     * field format. Simple fields pass through unchanged.
     */
    private function fieldName(FieldNode $field): string
    {
        // Espo field names must not contain SQL-injection chars
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $field->name)) {
            throw new CompilerException("Invalid field name: '{$field->name}'");
        }

        return $field->name;
    }

    private function resolveValue(Node $node): mixed
    {
        if ($node instanceof LiteralNode) {
            return $node->value;
        }

        if ($node instanceof SpecialNode) {
            return match (strtolower($node->keyword)) {
                'today' => (new DateTimeImmutable('today'))->format('Y-m-d'),
                'now'   => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                default => throw new CompilerException("Unknown special value: '{$node->keyword}'"),
            };
        }

        throw new CompilerException(
            sprintf("Cannot resolve value for node type: %s", get_class($node))
        );
    }
}
