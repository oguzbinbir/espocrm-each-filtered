<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\Filter;

enum TokenType: string
{
    case FIELD       = 'FIELD';
    case STRING      = 'STRING';
    case NUMBER      = 'NUMBER';
    case BOOL        = 'BOOL';
    case NULL        = 'NULL';
    case SPECIAL     = 'SPECIAL';
    case ARRAY_OPEN  = 'ARRAY_OPEN';
    case ARRAY_CLOSE = 'ARRAY_CLOSE';
    case COMMA       = 'COMMA';
    case PAREN_OPEN  = 'PAREN_OPEN';
    case PAREN_CLOSE = 'PAREN_CLOSE';
    case OP          = 'OP';
    case IN          = 'IN';
    case AND         = 'AND';
    case OR          = 'OR';
    case EOF         = 'EOF';
}
