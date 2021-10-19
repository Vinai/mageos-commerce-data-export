<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\InventoryDataExporter\Model\Provider;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryDataExporter\Model\Query\InventoryStockQuery;
use Magento\InventoryIndexer\Indexer\SourceItem\GetSkuListInStock;

/**
 * Get inventory stock statuses
 * Fulfill fields for StockItemStatus record:
 *  [
 *    stockId,
 *    qty,
 *    isSalable,
 *    sku
 * ]
]
 */
class StockStatus
{
    /**
     * @var GetSkuListInStock
     */
    private $getSkuListInStock;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var InventoryStockQuery
     */
    private $query;

    public function __construct(
        GetSkuListInStock $getSkuListInStock,
        ResourceConnection $resourceConnection,
        InventoryStockQuery $query
    ) {
        $this->getSkuListInStock = $getSkuListInStock;
        $this->resourceConnection = $resourceConnection;
        $this->query = $query;
    }

    /**
     * Getting inventory stock statuses.
     *
     * @param array $values
     * @return array
     * @throws \Zend_Db_Statement_Exception
     */
    public function get(array $values): array
    {
        $sourceItemIds = \array_column($values, 'sourceItemId');
        $skuListInStock = $this->getSkuListInStock->execute($sourceItemIds);

        $connection = $this->resourceConnection->getConnection();
        $output = [];
        foreach ($skuListInStock as $skuInStock) {
            $select = $this->query->getQuery($skuInStock->getSkuList(), $skuInStock->getStockId());
            try {
                $cursor = $connection->query($select);
                while ($row = $cursor->fetch()) {
                    $row['stockId'] = $skuInStock->getStockId();
                    $row['id'] = $this->buildStockStatusId($row);
                    $output[] = $row;
                }
            } catch (\Throwable $e) {
                // handle case when view "inventory_stock_1" for default Stock does not exists
                $output += \array_map(function ($sku) use ($skuInStock){
                    $row = [
                        'qty' => 0,
                        'isSalable' => false,
                        'sku' => $sku,
                        'stockId' => $skuInStock->getStockId(),
                    ];
                    $row['id'] = $this->buildStockStatusId($row);
                    return $row;
                }, $skuInStock->getSkuList());
            }
        }
        return $output;
    }

    /**
     * @param array $row
     * @return string
     */
    private function buildStockStatusId(array $row): string
    {
        if (!isset($row['stockId'], $row['sku'])) {
            throw new \RuntimeException(
                sprintf(
                    "inventory_data_exporter_stock_status indexer error: cannot build unique id from %s",
                    \implode(", ", $row)
                )
            );
        }
        return \hash('md5', $row['stockId'] . "\0" . $row['sku']);
    }
}
