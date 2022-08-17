<?php

/**
 * NovaeZSolrSearchExtraBundle.
 *
 * @package   NovaeZSolrSearchExtraBundle
 *
 * @author    Novactive
 * @copyright 2020 Novactive
 * @license   https://github.com/Novactive/NovaeZSolrSearchExtraBundle/blob/master/LICENSE
 */

namespace Novactive\EzSolrSearchExtra\Query\SortClauseHandler;

use eZ\Publish\API\Repository\Values\Content\Query\SortClause as APISortClause;
use Doctrine\DBAL\Query\QueryBuilder;
use eZ\Publish\Core\Search\Legacy\Content\Common\Gateway\SortClauseHandler;
use Novactive\EzSolrSearchExtra\Query\SortClause;

/**
 * Class CustomField.
 */
class CustomField extends SortClauseHandler
{
    /**
     * {@inheritdoc}
     */
    public function accept(APISortClause $sortClause)
    {
        return $sortClause instanceof SortClause\CustomField;
    }

    /**
     * {@inheritdoc}
     */
    public function applySelect(QueryBuilder $query, APISortClause $sortClause, int $number): array
    {
        return [];
    }
}
