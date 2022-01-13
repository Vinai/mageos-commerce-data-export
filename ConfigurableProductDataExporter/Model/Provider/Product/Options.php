<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\ConfigurableProductDataExporter\Model\Provider\Product;

use Exception;
use Generator;
use Magento\CatalogDataExporter\Model\Provider\Product\OptionProviderInterface;
use Magento\ConfigurableProductDataExporter\Model\Query\ProductOptionQuery;
use Magento\ConfigurableProductDataExporter\Model\Query\ProductOptionValueQuery;
use Magento\DataExporter\Exception\UnableRetrieveData;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Query\BatchIteratorInterface;
use Magento\Framework\DB\Query\Generator as QueryGenerator;
use Magento\Framework\DB\Select;
use Magento\DataExporter\Model\Logging\CommerceDataExportLoggerInterface as LoggerInterface;
use Throwable;

/**
 * Configurable product options data provider
 */
class Options implements OptionProviderInterface
{
    /**
     * Batch sizing for performing queries
     *
     * @var int
     */
    private $batchSize;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var ProductOptionQuery
     */
    private $productOptionQuery;

    /**
     * @var ProductOptionValueQuery
     */
    private $productOptionValueQuery;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var QueryGenerator
     */
    private $queryGenerator;

    /**
     * @var ConfigurableOptionValueUid
     */
    private $optionValueUid;

    /**
     * @param ResourceConnection $resourceConnection
     * @param ProductOptionQuery $productOptionQuery
     * @param ProductOptionValueQuery $productOptionValueQuery
     * @param QueryGenerator $queryGenerator
     * @param ConfigurableOptionValueUid $optionValueUid
     * @param LoggerInterface $logger
     * @param int $batchSize
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        ProductOptionQuery $productOptionQuery,
        ProductOptionValueQuery $productOptionValueQuery,
        QueryGenerator $queryGenerator,
        ConfigurableOptionValueUid $optionValueUid,
        LoggerInterface $logger,
        int $batchSize
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->productOptionQuery = $productOptionQuery;
        $this->productOptionValueQuery = $productOptionValueQuery;
        $this->queryGenerator = $queryGenerator;
        $this->optionValueUid = $optionValueUid;
        $this->logger = $logger;
        $this->batchSize = $batchSize;
    }

    /**
     * Retrieve query data in batches
     *
     * @param Select $select
     * @param string $rangeField
     * @return Generator
     * @throws UnableRetrieveData
     */
    private function getBatchedQueryData(Select $select, string $rangeField): Generator
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $iterator = $this->queryGenerator->generate(
                $rangeField,
                $select,
                $this->batchSize,
                BatchIteratorInterface::NON_UNIQUE_FIELD_ITERATOR
            );
            foreach ($iterator as $batchSelect) {
                yield $connection->fetchAll($batchSelect);
            }
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            throw new UnableRetrieveData('Unable to retrieve configurable option data');
        }
    }

    /**
     * Get option values
     *
     * @param array $arguments
     * @return array
     * @throws UnableRetrieveData
     */
    private function getOptionValuesData(array $arguments): array
    {
        $optionValues = [];
        $select = $this->productOptionValueQuery->getQuery($arguments);
        foreach ($this->getBatchedQueryData($select, 'attribute_id') as $batchData) {
            foreach ($batchData as $row) {
                $optionValues[$row['attribute_id']][$row['storeViewCode']][$row['optionId']] = [
                    'id' => $this->optionValueUid->resolve($row['attribute_id'], $row['optionId']),
                    'label' => $row['label'],

                    //TODO: should be deleted in catalog-storefront/issues/304
                    'default_label' => $row['label'],
                    'store_label' => $row['label'],
                ];
            }
        }
        return $optionValues;
    }

    /**
     * Format options row in appropriate format for feed data storage
     *
     * @param array $row
     * @return array
     */
    private function formatOptionsRow($row): array
    {
        return [
            'productId' => $row['productId'],
            'storeViewCode' => $row['storeViewCode'],
            'optionsV2' => [
                'id' => $row['attribute_code'],
                'type' => ConfigurableOptionValueUid::OPTION_TYPE,
                'label' => $row['label'],
                'sortOrder' => $row['position']
            ],

            //TODO: should be deleted in catalog-storefront/issues/304
            'options' => [
                'id' => $row['super_attribute_id'],
                'sort_order' => $row['position'],
                'type' => ConfigurableOptionValueUid::OPTION_TYPE,
                'attribute_id' => $row['attribute_id'],
                'attribute_code' => $row['attribute_code'],
                'use_default' => (bool)$row['use_default'],
                'title' => $row['label'],
            ],
        ];
    }

    /**
     * Generate option key by concatenating productId, storeViewCode and attributeId
     *
     * @param array $row
     * @return string
     */
    private function getOptionKey($row): string
    {
        return $row['productId'] . $row['storeViewCode'] . $row['attribute_id'];
    }

    /**
     * @inheritDoc
     */
    public function get(array $values): array
    {
        $queryArguments = [];
        foreach ($values as $value) {
            $queryArguments['productId'][$value['productId']] = $value['productId'];
            $queryArguments['storeViewCode'][$value['storeViewCode']] = $value['storeViewCode'];
        }

        try {
            $options = [];
            $setOptionValues = [];
            $optionValuesData = $this->getOptionValuesData($queryArguments);
            $select = $this->productOptionQuery->getQuery($queryArguments);
            foreach ($this->getBatchedQueryData($select, 'entity_id') as $batchData) {
                foreach ($batchData as $row) {
                    $key = $this->getOptionKey($row);
                    $options[$key] = $options[$key] ?? $this->formatOptionsRow($row);

                    if (!isset($setOptionValues[$key . $row['value']])) {
                        $setOptionValues[$key . $row['value']] = true;
                        if (isset($optionValuesData[$row['attribute_id']][$row['storeViewCode']][$row['value']])) {
                            $options[$key]['optionsV2']['values'][] =
                                $optionValuesData[$row['attribute_id']][$row['storeViewCode']][$row['value']];

                            //TODO: should be deleted in catalog-storefront/issues/304
                            $options[$key]['options']['values'][] =
                                $optionValuesData[$row['attribute_id']][$row['storeViewCode']][$row['value']];
                        }
                    }
                }
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            throw new UnableRetrieveData('Unable to retrieve configurable product options data');
        }
        return $options;
    }
}
