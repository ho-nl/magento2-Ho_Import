<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Rewrite;

use Magento\Framework\Logger\Monolog;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorFactory;
use Psr\Log\LoggerInterface;

class ErrorProcessingAggregatorLogging
    extends \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregator
{
    /** @var LoggerInterface  */
    private $logger;

    /**
     * ErrorProcessingAggregatorLogging constructor.
     *
     * @param ProcessingErrorFactory $errorFactory
     * @param Monolog        $logger
     */
    public function __construct(ProcessingErrorFactory $errorFactory, LoggerInterface $logger)
    {
        $this->logger = $logger;
        parent::__construct($errorFactory);
    }

    /**
     * @param array $errorCode
     * @param array $excludedCodes
     * @param bool $replaceCodeWithMessage
     * @return array
     */
    public function getRowsGroupedByErrorCode(
        array $errorCode = [],
        array $excludedCodes = [],
        $replaceCodeWithMessage = true
    ) {
        if (empty($this->items)) {
            return [];
        }
        $allCodes = array_keys($this->items['codes']);
        if (!empty($excludedCodes)) {
            $allCodes = array_diff($allCodes, $excludedCodes);
        }
        if (!empty($errorCode)) {
            $allCodes = array_intersect($errorCode, $allCodes);
        }

        $result = [];
        foreach ($allCodes as $code) {
            $errors = $this->getErrorsByCode([$code]);
            foreach ($errors as $error) {
                $key = $replaceCodeWithMessage ? $error->getErrorMessage() : $code;
                $this->logger->info('export/import error, ErrorMessage:', [$error->getErrorMessage()]);
                $result[$key][] = $error->getRowNumber() + 1;
            }
        }

        return $result;
    }
}
