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

namespace Novactive\EzSolrSearchExtra\Query\Content\CriterionVisitor;

use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use EzSystems\EzPlatformSolrSearchEngine\Query\CriterionVisitor;
use Novactive\EzSolrSearchExtra\Query\Content\Criterion\MultipleFieldsFullText as MultipleFieldsFullTextCriterion;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MultipleFieldsFullText extends CriterionVisitor
{
    /**
     * Create from content type handler and field registry.
     */
    public function __construct(
        ContainerInterface $container
    ) {
        $this->container         = $container;
    }

    /**
     * Get field type information.
     *
     * @param string $fieldDefinitionIdentifier
     *
     * @return array
     */
    protected function getSearchFields(Criterion $criterion, $fieldDefinitionIdentifier)
    {
        $fieldNameResolver = $this->container->get('ezpublish.search.common.field_name_resolver');
        return $fieldNameResolver->getFieldTypes($criterion, $fieldDefinitionIdentifier);
    }

    /**
     * Check if visitor is applicable to current criterion.
     *
     * @return bool
     */
    public function canVisit(Criterion $criterion)
    {
        return $criterion instanceof MultipleFieldsFullTextCriterion;
    }

    /**
     * Map field value to a proper Solr representation.
     *
     * @param \EzSystems\EzPlatformSolrSearchEngine\Query\CriterionVisitor $subVisitor
     *
     * @return string
     */
    public function visit(Criterion $criterion, CriterionVisitor $subVisitor = null)
    {
        $tokenizer = $this->container->get('ezpublish.search.solr.query.query_translator.galach.tokenizer');
        /** @var \Novactive\EzSolrSearchExtra\Query\Content\Criterion\MultipleFieldsFullText $criterion */
        $tokenSequence = $tokenizer->tokenize($criterion->value);

        $parser = $this->container->get('ezpublish.search.solr.query.query_translator.galach.parser');
        $syntaxTree    = $parser->parse($tokenSequence);

        $options = [];
        if ($criterion->fuzziness < 1) {
            $options['fuzziness'] = $criterion->fuzziness;
        }

        $generator = $this->container->get('ezpublish.search.solr.query.query_translator.galach.generator.edismax');
        $queryString        = $generator->generate($syntaxTree, $options);
        $queryStringEscaped = $this->escapeQuote($queryString);
        $queryFields        = $this->getQueryFields($criterion);

        $queryParams = [
            'v'   => $queryStringEscaped,
            'qf'  => $queryFields,
            'pf'  => $queryFields,
            'tie' => 0.1,
            'uf'  => '-*',
        ];
        $boostFunction = $criterion->boostFunctions;
        if ($criterion->boostPublishDate) {
            $boostFunction[] = 'recip(ms(NOW/HOUR,meta_publishdate__date_dt),3.16e-11,1,1)';
        }
        if (!empty($boostFunction)) {
            $queryParams['bf'] = 1 === count($boostFunction) ?
                reset($boostFunction) :
                sprintf('sum(%s)', implode(',', $boostFunction));
        }

        $queryParamsString = implode(' ', array_map(function ($key, $value) {
            return "{$key}='{$value}'";
        }, array_keys($queryParams), $queryParams));

        return "{!edismax {$queryParamsString}}";
    }

    /**
     * @return string
     */
    private function getQueryFields(Criterion $criterion)
    {
        /** @var \Novactive\EzSolrSearchExtra\Query\Content\Criterion\MultipleFieldsFullText $criterion */
        $queryFields = ['meta_content__text_t', 'meta_content__text_t_raw'];

        foreach ($criterion->boost as $field => $boost) {
            $searchFields = $this->getSearchFields($criterion, $field);
            foreach (array_keys($searchFields) as $name) {
                $queryFields[] = "{$name}^{$boost}";
            }
        }
        foreach ($criterion->metaBoost as $field => $boost) {
            $queryFields[] = "meta_{$field}__text_t^{$boost}";
            $queryFields[] = "meta_{$field}__text_t_raw^{$boost}";
        }

        return implode(' ', $queryFields);
    }
}
