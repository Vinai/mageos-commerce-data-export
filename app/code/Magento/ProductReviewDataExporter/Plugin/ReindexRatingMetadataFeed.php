<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\ProductReviewDataExporter\Plugin;

use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Review\Model\Rating;

/**
 * Plugin for running rating metadata feed indexation during saving / deleting process
 */
class ReindexRatingMetadataFeed
{
    /**
     * Rating metadata feed indexer id
     */
    private const RATING_METADATA_FEED_INDEXER = 'catalog_data_exporter_rating_metadata';

    /**
     * @var IndexerRegistry
     */
    private $indexerRegistry;

    /**
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(
        IndexerRegistry $indexerRegistry
    ) {
        $this->indexerRegistry = $indexerRegistry;
    }

    /**
     * Execute reindex process on delete callback
     *
     * @param Rating $subject
     *
     * @return Rating
     */
    public function beforeAfterDeleteCommit(Rating $subject): Rating
    {
        $this->reindex($subject);

        return $subject;
    }

    /**
     * Execute reindex process on save commit callback
     *
     * @param Rating $subject
     *
     * @return Rating
     */
    public function beforeAfterCommitCallback(Rating $subject): Rating
    {
        $this->reindex($subject);

        return $subject;
    }

    /**
     * Re-indexation process of rating metadata feed
     *
     * @param Rating $rating
     *
     * @return void
     */
    public function reindex(Rating $rating): void
    {
        $indexer = $this->indexerRegistry->get(self::RATING_METADATA_FEED_INDEXER);
        if (!$indexer->isScheduled()) {
            $indexer->reindexRow($rating->getId());
        }
    }
}
