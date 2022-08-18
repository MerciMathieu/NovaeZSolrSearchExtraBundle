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

namespace Novactive\EzSolrSearchExtra\FieldMapper;

use eZ\Publish\Core\IO\Exception\BinaryFileNotFoundException;
use eZ\Publish\Core\IO\IOService;
use eZ\Publish\Core\IO\Values\BinaryFile;
use eZ\Publish\SPI\Persistence\Content\Field as SPIField;
use eZ\Publish\SPI\Persistence\Content\Type as SPIContentType;
use eZ\Publish\SPI\Search\Field as SPISearchField;
use eZ\Publish\SPI\Search\FieldType as SPISearchFieldType;
use Novactive\EzSolrSearchExtra\TextExtractor\TextExtractorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BinaryFileFieldMapper.
 *
 * @package src\lib\FieldMapper
 */
class BinaryFileFieldMapper
{
    /**
     * Field name, untyped.
     *
     * @var string
     */
    private static $fieldName = 'meta_content__text';

    /** @var IOService */
    private $ioService;

    /** @var TextExtractorInterface */
    private $textExtractor;

    /** @var LoggerInterface */
    private $logger;

    private $container;

    /**
     * BinaryFileFieldMapper constructor.
     */
    public function __construct(
        IOService $ioService,
        TextExtractorInterface $textExtractor,
        LoggerInterface $logger,
        ContainerInterface $container
    ) {
        $this->ioService           = $ioService;
        $this->textExtractor       = $textExtractor;
        $this->logger              = $logger;
        $this->container           = $container;
    }

    /**
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentValue
     * @throws \eZ\Publish\Core\Base\Exceptions\NotFoundException
     */
    public function mapField(SPIField $field, SPIContentType $contentType): ?SPISearchField
    {
        foreach ($contentType->fieldDefinitions as $fieldDefinition) {
            if (
                $fieldDefinition->id !== $field->fieldDefinitionId
                || !$fieldDefinition->isSearchable
                || !$field->value->externalData
            ) {
                continue;
            }

            try {
                $binaryFile = $this->ioService->loadBinaryFile($field->value->externalData['id']);
                $plaintext  = $this->getBinaryFileText($binaryFile);

                return new SPISearchField(
                    self::$fieldName,
                    $plaintext ?? '',
                    $this->getIndexFieldType($contentType)
                );
            } catch (BinaryFileNotFoundException $e) {
                $this->logger->warning($e->getMessage());
            }
        }

        return null;
    }

    /**
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentValue
     *
     * @return string|null
     */
    private function getBinaryFileText(BinaryFile $binaryFile)
    {
        $resource         = $this->ioService->getFileInputStream($binaryFile);
        $resourceMetadata = stream_get_meta_data($resource);

        return $this->textExtractor->extract($resourceMetadata['uri']);
    }

    /**
     * Return index field type for the given $contentType.
     *
     * @return SPISearchFieldType\TextField
     */
    private function getIndexFieldType(SPIContentType $contentType)
    {
        $newFieldType        = new SPISearchFieldType\TextField();
        $boostFactorProvider = $this->container->get('ezpublish.search.solr.field_mapper.boost_factor_provider');
        $newFieldType->boost = $boostFactorProvider->getContentMetaFieldBoostFactor(
            $contentType,
            'text'
        );

        return $newFieldType;
    }
}
