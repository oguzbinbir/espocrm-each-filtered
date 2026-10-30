<?php

declare(strict_types=1);

namespace Espo\Modules\PdfFilterHelper\TemplateHelper;

use Espo\Core\Htmlizer\Helper;
use Espo\Core\Htmlizer\Helper\Data;
use Espo\Core\Htmlizer\Helper\Result;
use Espo\Modules\PdfFilterHelper\Filter\FilterParser;
use Espo\Modules\PdfFilterHelper\Filter\Exception\FilterParseException;
use Espo\ORM\Entity;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\Order;

/**
 * Template helper: {{#eachFiltered link='' where='' orderBy='' order='' limit=N}}...{{/eachFiltered}}
 *
 * Parameters (all passed as Handlebars hash arguments):
 *   link    (string, required) – relation name on the current entity
 *   where   (string, optional) – filter expression in the mini-language
 *   orderBy (string, optional) – field name to sort by
 *   order   (string, optional) – 'asc' | 'desc'  (default: 'asc')
 *   limit   (int,    optional) – max rows to return (default: 20, max: 200)
 *
 * Registration:
 *   metadata/app/templateHelpers.json →
 *     { "eachFiltered": "Espo\\Modules\\PdfFilterHelper\\TemplateHelper\\EachFiltered" }
 */
class EachFiltered implements Helper
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT     = 200;

    private FilterParser $filterParser;

    public function __construct()
    {
        $this->filterParser = new FilterParser();
    }

    public function render(Data $data): Result
    {
        $entity = $data->getEntity();

        if (!$entity) {
            return Result::createSafeString('');
        }

        // ----------------------------------------------------------------
        // Read & validate hash parameters
        // ----------------------------------------------------------------

        $link    = trim((string) ($data->getHash()['link'] ?? ''));
        $where   = trim((string) ($data->getHash()['where'] ?? ''));
        $orderBy = trim((string) ($data->getHash()['orderBy'] ?? ''));
        $order   = strtolower(trim((string) ($data->getHash()['order'] ?? 'asc')));
        $limit   = (int) ($data->getHash()['limit'] ?? self::DEFAULT_LIMIT);

        if ($link === '') {
            return $this->error('eachFiltered: missing required parameter "link"');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $link)) {
            return $this->error('eachFiltered: invalid link name');
        }

        $limit = min(max(1, $limit), self::MAX_LIMIT);

        if (!in_array($order, ['asc', 'desc'], true)) {
            $order = 'asc';
        }

        // ----------------------------------------------------------------
        // Build the relation query
        // ----------------------------------------------------------------

        try {
            $builder = $entity->getRelation($link)->findBuilder();
        } catch (\Throwable $e) {
            return $this->error(sprintf('eachFiltered: relation "%s" not found', $link));
        }

        // Apply serversided filter
        if ($where !== '') {
            try {
                $whereArray = $this->filterParser->parse($where);
                $builder->where(WhereClause::fromRaw($whereArray));
            } catch (FilterParseException $e) {
                return $this->error('eachFiltered: ' . $e->getMessage());
            }
        }

        // Apply order
        if ($orderBy !== '') {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $orderBy)) {
                return $this->error('eachFiltered: invalid orderBy field name');
            }

            $direction = $order === 'desc' ? Order::DESC : Order::ASC;
            $builder->order($orderBy, $direction);
        }

        // Apply limit
        $builder->limit(0, $limit);

        // ----------------------------------------------------------------
        // Fetch records
        // ----------------------------------------------------------------

        try {
            $collection = $builder->find();
        } catch (\Throwable $e) {
            return $this->error('eachFiltered: query failed – ' . $e->getMessage());
        }

        // ----------------------------------------------------------------
        // Render inner template block for each record
        // ----------------------------------------------------------------

        $innerTemplate = $data->getInnerTemplate();

        if ($innerTemplate === null) {
            return Result::createSafeString('');
        }

        $html = '';
        $list = iterator_to_array($collection);

        foreach ($list as $index => $relatedEntity) {
            /** @var Entity $relatedEntity */

            // Build a context that Espo's Htmlizer understands:
            // expose entity data + @index / @first / @last meta
            $entityData = $this->entityToArray($relatedEntity);

            $entityData['@index'] = $index;
            $entityData['@first'] = $index === 0;
            $entityData['@last']  = $index === count($list) - 1;

            $html .= $data->renderInnerTemplate($relatedEntity, $entityData);
        }

        return Result::createSafeString($html);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function entityToArray(Entity $entity): array
    {
        $data = $entity->getValueMap();
        return (array) $data;
    }

    private function error(string $message): Result
    {
        // Render an HTML comment so it's visible in debug but harmless in output
        return Result::createSafeString(sprintf('<!-- [eachFiltered error] %s -->', htmlspecialchars($message)));
    }
}
