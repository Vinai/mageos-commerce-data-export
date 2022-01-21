<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\DataExporter\Model\Logging;

/**
 * Interface used to provide custom log handlers defined in di.xml
 */
interface CommerceDataExportLoggerInterface extends \Psr\Log\LoggerInterface {
    /**
     * Pass environment variable "EXPORTER_PROFILER" to enable profiler, for example:
     * EXPORTER_PROFILER=1 bin/magento index:reindex catalog_data_exporter_products
     *
     * Profiler data will be stored in var/log/commerce-data-export.log in format:
     * Provider class name, processed entities, execution time, memory consumption
     */
    public const EXPORTER_PROFILER = 'EXPORTER_PROFILER';
}