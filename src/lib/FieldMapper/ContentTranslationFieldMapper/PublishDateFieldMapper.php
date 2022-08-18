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

namespace Novactive\EzSolrSearchExtra\FieldMapper\ContentTranslationFieldMapper;

use eZ\Publish\SPI\Persistence\Content;
use eZ\Publish\SPI\Persistence\Content\Type\Handler as ContentTypeHandler;
use eZ\Publish\SPI\Search\Field;
use eZ\Publish\SPI\Search\FieldType;
use EzSystems\EzPlatformSolrSearchEngine\FieldMapper\ContentTranslationFieldMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PublishDateFieldMapper extends ContentTranslationFieldMapper
{
    /**
     * Field name, untyped.
     *
     * @var string
     */
    private static $fieldName = 'meta_publishdate__date';

    /**
     * @var array
     */
    protected $fieldIdentifiers = [];

    /**
     * @var \eZ\Publish\SPI\Persistence\Content\Type\Handler
     */
    protected $contentTypeHandler;

    /**
     * PublishDateFieldMapper constructor.
     */
    public function __construct(ContentTypeHandler $contentTypeHandler, ContainerInterface $container)
    {
        $this->contentTypeHandler = $contentTypeHandler;
        $this->container = $container;
    }

    public function setFieldIdentifiers(array $fieldIdentifiers): void
    {
        $this->fieldIdentifiers = $fieldIdentifiers;
    }

    /**
     * @param string $languageCode
     *
     * @return bool
     */
    public function accept(Content $content, $languageCode)
    {
        return true;
    }

    /**
     * @param string $languageCode
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *
     * @return array|Field[]
     */
    public function mapFields(Content $content, $languageCode)
    {
        $contentType = $this->contentTypeHandler->load(
            $content->versionInfo->contentInfo->contentTypeId
        );

        foreach ($content->fields as $field) {
            if ($field->languageCode !== $languageCode) {
                continue;
            }

            foreach ($contentType->fieldDefinitions as $fieldDefinition) {
                if (
                    $fieldDefinition->id !== $field->fieldDefinitionId
                    || (
                        !\in_array(
                            $fieldDefinition->identifier,
                            $this->fieldIdentifiers
                        )
                        && !\in_array(
                            "{$contentType->identifier}/{$fieldDefinition->identifier}",
                            $this->fieldIdentifiers
                        )
                    )
                ) {
                    continue;
                }

                $fieldRegistry = $this->container->get('ezpublish.search.common.field_registry');
                $fieldType   = $fieldRegistry->getType($field->type);
                $indexFields = $fieldType->getIndexData($field, $fieldDefinition);

                foreach ($indexFields as $indexField) {
                    if (null === $indexField->value || !$indexField->type instanceof FieldType\DateField) {
                        continue;
                    }

                    return [
                        new Field(
                            static::$fieldName,
                            $indexField->value,
                            $indexField->type
                        ),
                    ];
                }
            }
        }

        return [
            new Field(
                static::$fieldName,
                $content->versionInfo->contentInfo->publicationDate,
                new FieldType\DateField()
            ),
        ];
    }
}
