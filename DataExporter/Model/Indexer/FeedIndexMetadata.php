<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\DataExporter\Model\Indexer;

/**
 * Feed indexer metadata provider
 */
class FeedIndexMetadata
{
    /**
     * @var string
     */
    protected $feedName;

    /**
     * @var string
     */
    protected $sourceTableName;

    /**
     * @var string
     */
    protected $sourceTableField;

    /**
     * @var string
     */
    protected $feedIdentity;

    /**
     * @var string
     */
    protected $feedTableName;

    /**
     * @var string
     */
    protected $feedTableField;

    /**
     * @var string[]
     */
    protected $feedTableMutableColumns;

    /**
     * @var int
     */
    protected $batchSize;

    /**
     * Field used in WHERE & ORDER statement during select IDs from source table
     *
     * @var string
     */
    private $sourceTableIdentityField;

    /**
     * @param string $feedName
     * @param string $sourceTableName
     * @param string $sourceTableField
     * @param string $feedIdentity
     * @param string $feedTableName
     * @param string $feedTableField
     * @param array $feedTableMutableColumns
     * @param int $batchSize
     * @param string|null $sourceTableIdentityField
     */
    public function __construct(
        string $feedName,
        string $sourceTableName,
        string $sourceTableField,
        string $feedIdentity,
        string $feedTableName,
        string $feedTableField,
        array $feedTableMutableColumns,
        int $batchSize = 100,
        string $sourceTableIdentityField = null
    ) {
        $this->feedName = $feedName;
        $this->sourceTableName = $sourceTableName;
        $this->sourceTableField = $sourceTableField;
        $this->feedIdentity = $feedIdentity;
        $this->feedTableName = $feedTableName;
        $this->feedTableField = $feedTableField;
        $this->feedTableMutableColumns = $feedTableMutableColumns;
        $this->batchSize = $batchSize;
        $this->sourceTableIdentityField = $sourceTableIdentityField ?? $sourceTableField;
    }

    /**
     * Get Feed Name
     *
     * @return string
     */
    public function getFeedName(): string
    {
        return $this->feedName;
    }

    /**
     * Get source table name
     *
     * @return string
     */
    public function getSourceTableName(): string
    {
        return $this->sourceTableName;
    }

    /**
     * Get source table field
     *
     * @return string
     */
    public function getSourceTableField(): string
    {
        return $this->sourceTableField;
    }

    /**
     * Get source table identity field
     *
     * @return string
     */
    public function getSourceTableIdentityField(): string
    {
        return $this->sourceTableIdentityField;
    }

    /**
     * Get feed identity
     *
     * @return string
     */
    public function getFeedIdentity(): string
    {
        return $this->feedIdentity;
    }

    /**
     * Get feed table name
     *
     * @return string
     */
    public function getFeedTableName(): string
    {
        return $this->feedTableName;
    }

    /**
     * Get feed table field
     *
     * @return string
     */
    public function getFeedTableField(): string
    {
        return $this->feedTableField;
    }

    /**
     * Get batch size
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * Get feed table mutable column names
     *
     * @return string[]
     */
    public function getFeedTableMutableColumns(): array
    {
        return $this->feedTableMutableColumns;
    }
}
