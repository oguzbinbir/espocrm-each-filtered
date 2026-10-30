<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\Filter\Ast;

// ---------------------------------------------------------------------------
// Base
// ---------------------------------------------------------------------------

interface Node {}

// ---------------------------------------------------------------------------
// Leaf nodes
// ---------------------------------------------------------------------------

class FieldNode implements Node
{
    public function __construct(public readonly string $name) {}
}

class LiteralNode implements Node
{
    public function __construct(
        public readonly string|int|float|bool|null $value
    ) {}
}

/** Represents 'today' or 'now' – resolved at query time. */
class SpecialNode implements Node
{
    public function __construct(public readonly string $keyword) {}
}

/** Represents a literal array: ["a", "b"] */
class ArrayNode implements Node
{
    /** @param LiteralNode[] $items */
    public function __construct(public readonly array $items) {}
}

// ---------------------------------------------------------------------------
// Compound nodes
// ---------------------------------------------------------------------------

class ComparisonNode implements Node
{
    public function __construct(
        public readonly FieldNode $field,
        public readonly string    $operator,  // == != > >= < <=
        public readonly Node      $value,     // LiteralNode | SpecialNode
    ) {}
}

class InNode implements Node
{
    public function __construct(
        public readonly FieldNode $field,
        public readonly ArrayNode $list,
    ) {}
}

class LogicalNode implements Node
{
    public function __construct(
        public readonly string $operator, // && or ||
        public readonly Node   $left,
        public readonly Node   $right,
    ) {}
}
