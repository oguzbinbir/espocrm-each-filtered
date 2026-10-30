<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\Filter;

use Espo\Modules\PdfFilterHelper\Filter\Exception\FilterParseException;

/**
 * Public façade for the filter pipeline.
 *
 * Usage:
 *   $parser = new FilterParser();
 *   $where  = $parser->parse('dateStart >= today && status in ["Planned","Confirmed"]');
 *   // $where is an array ready for WhereClause::fromRaw($where)
 */
class FilterParser
{
    private Tokenizer           $tokenizer;
    private Parser              $parser;
    private WhereClauseCompiler $compiler;

    public function __construct()
    {
        $this->tokenizer = new Tokenizer();
        $this->parser    = new Parser();
        $this->compiler  = new WhereClauseCompiler();
    }

    /**
     * @return array<string, mixed>
     * @throws FilterParseException
     */
    public function parse(string $expression): array
    {
        try {
            $stream = $this->tokenizer->tokenize($expression);
            $ast    = $this->parser->parse($stream);
            return $this->compiler->compile($ast);
        } catch (\Throwable $e) {
            throw new FilterParseException(
                "Filter expression error: " . $e->getMessage(),
                previous: $e
            );
        }
    }
}
