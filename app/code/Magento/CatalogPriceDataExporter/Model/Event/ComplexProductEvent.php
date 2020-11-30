<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\CatalogPriceDataExporter\Model\Event;

use Magento\CatalogPriceDataExporter\Model\EventBuilder;
use Magento\CatalogPriceDataExporter\Model\Query\ComplexProductLink;
use Magento\DataExporter\Exception\UnableRetrieveData;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Api\Data\WebsiteInterface;
use Psr\Log\LoggerInterface;

class ComplexProductEvent implements ProductPriceEventInterface
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var EventBuilder
     */
    private $eventBuilder;

    /**
     * @var ComplexProductLink
     */
    private $complexProductLink;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $linkType;

    /**
     * @param ResourceConnection $resourceConnection
     * @param EventBuilder $eventBuilder
     * @param ComplexProductLink $complexProductLink
     * @param LoggerInterface $logger
     * @param string $linkType
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        EventBuilder $eventBuilder,
        ComplexProductLink $complexProductLink,
        LoggerInterface $logger,
        string $linkType
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->eventBuilder = $eventBuilder;
        $this->complexProductLink = $complexProductLink;
        $this->logger = $logger;
        $this->linkType = $linkType;
    }

    /**
     * @inheritdoc
     */
    public function retrieve(array $indexData): array
    {
        $events = [];

        try {
            $select = $this->complexProductLink->getQuery($indexData['entity_id'], $indexData['variation_id']);
            $result = $this->resourceConnection->getConnection()->fetchRow($select) ?: null;
            $events[] = $this->getEventData($indexData, $result);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
            throw new UnableRetrieveData('Unable to retrieve complex product link data.');
        }

        return $events;
    }

    /**
     * Retrieve event data.
     *
     * @param array $indexData
     * @param array|null $result
     *
     * @return array
     */
    private function getEventData(array $indexData, ?array $result): array
    {
        $additionalData = [
            'meta' => ['price_type' => $this->linkType],
            'data' => ['variation_id' => $indexData['variation_id']]
        ];

        $eventType = null === $result ? self::EVENT_VARIATION_DELETED : self::EVENT_VARIATION_CHANGED;

        return $this->eventBuilder->build(
            $eventType,
            $indexData['entity_id'],
            WebsiteInterface::ADMIN_CODE,
            null,
            null,
            $additionalData
        );
    }
}
