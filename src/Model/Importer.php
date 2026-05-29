<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Model;

use Magento\Framework\Indexer\StateInterface;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError;

/**
 * Class Importer
 *
 * @package Ho\Import\Model
 */
class Importer
{

    /**
     * @var Import
     */
    protected $importModel;

    /**
     * @var
     */
    protected $errorMessages;

    /**
     * @var ArrayAdapterFactory
     */
    protected $arrayAdapterFactory;

    /**
     * Reference to the data array passed into processImport — kept around
     * so getErrorMessages() can resolve a row number back to its
     * identifier (typically `sku`, falls back to `email` for customers).
     * Magento's `ProcessingError` only carries the row number.
     *
     * @var array|null
     */
    private $lastDataArray;

    /**
     * @var \Magento\Indexer\Model\Indexer\Collection
     */
    private $indexerCollection;

    /**
     * Importer constructor.
     *
     * @param Import                                           $importModel
     * @param ArrayAdapterFactory                              $arrayAdapterFactory
     * @param \Magento\Indexer\Model\Indexer\Collection $indexerCollection
     */
    public function __construct(
        Import $importModel,
        ArrayAdapterFactory $arrayAdapterFactory,
        \Magento\Indexer\Model\Indexer\Collection $indexerCollection
    ) {
        $this->importModel = $importModel;
        $this->arrayAdapterFactory = $arrayAdapterFactory;
        $this->indexerCollection = $indexerCollection;
    }


    /**
     * Set config values on the importModel
     *
     * @param \[]|string $key
     * @param null $value
     * @return void
     */
    public function setConfig($key, $value = null)
    {
        if (is_array($key)) {
            $this->importModel->addData($key);
        } else {
            $this->importModel->setData($key, $value);
        }
    }


    /**
     * Actually run the import
     *
     * @param \[] &$dataArray
     * @return string
     */
    public function processImport(&$dataArray)
    {
        $this->lastDataArray = &$dataArray;
        $sourceAdapter = $this->loadData($dataArray);
        $validationResult = $this->importModel->validateSource($sourceAdapter);
        if (!$validationResult) {
            return $this->importModel->getFormatedLogTrace();
        }

//        $this->lockIndexers();
        $this->importModel->importSource();
//        $this->unlockIndexers();
        if (!$this->importModel->getErrorAggregator()->hasToBeTerminated()) {
            $this->importModel->invalidateIndex();
        }
    }


    /**
     * Validate the imported data
     *
     * @param \[] &$dataArray
     * @return bool
     */
    public function validateData(&$dataArray)
    {
        $sourceAdapter = $this->loadData($dataArray);
        return $this->importModel->validateSource($sourceAdapter);
    }


    /**
     * Create the ArrayAdapter so Magento is able to handle the dataArray
     * @param \[] &$dataArray
     *
     * @return \Magento\ImportExport\Model\Import\AbstractSource
     */
    protected function loadData(&$dataArray)
    {
        return $this->arrayAdapterFactory->create(['data' => $dataArray]);
    }



    /**
     * Get the steps done in the product import.
     * @todo make sure we stream the log data to the console instead of outputting it after the fact.
     * @return string
     */
    public function getLogTrace()
    {
        return $this->importModel->getFormatedLogTrace();
    }


    /**
     * Formatted array of error messages — one string per validation /
     * import error, ready to print or log. Each line reads:
     *
     *     Line <rownum> (<id-field>=<value>): [<column>] <message>  (— <description> if present)
     *
     * Designed for issue #26: the importer surfaces a summary
     * ("Checked rows: 2822, invalid rows: 4, total errors: 4") but
     * doesn't tell you which row or column went wrong. With this
     * format a developer can grep straight to the offending row by
     * line number, SKU, or email.
     *
     * SKU/email lookup is best-effort: it requires the source to be
     * an in-memory array (the usual ArrayAdapter path). For other
     * source types — CSV files, HTTP streams — `lastDataArray` stays
     * null and the line number alone is shown.
     *
     * @return string[]
     */
    public function getErrorMessages()
    {
        $errorAggregator = $this->importModel->getErrorAggregator();
        $dataArray = $this->lastDataArray;
        return array_map(function (ProcessingError $error) use ($dataArray) {
            $rowNumber = $error->getRowNumber();
            $column = $error->getColumnName();
            $description = $error->getErrorDescription();
            return sprintf(
                'Line %s%s: %s%s%s',
                $rowNumber ?? '[?]',
                $this->formatRowIdentifier($dataArray, $rowNumber),
                $column ? "[$column] " : '',
                $error->getErrorMessage(),
                $description ? ' — ' . $description : ''
            );
        }, $errorAggregator->getAllErrors());
    }

    /**
     * Per-entity natural identifier columns, ordered from most-specific
     * to least. First match wins:
     *
     *   sku           — catalog_product, advanced_pricing, stock_items
     *   source_code   — MSI stock_sources
     *   email         — customer, customer_address, customer_composite
     *   increment_id  — sales_order, invoice, credit_memo, shipment
     *   code          — tax_rate, store / website CSV imports
     *
     * Override via DI (`<argument name="rowIdentifierFields" xsi:type="array">…`)
     * if a custom entity type needs another column surfaced.
     */
    private const DEFAULT_ROW_IDENTIFIER_FIELDS = [
        'sku',
        'source_code',
        'email',
        'increment_id',
        'code',
    ];

    /**
     * Look up a row's natural identifier (sku / email / source_code /
     * increment_id / code) and format it for inclusion in the error
     * message. Returns '' when the source isn't an in-memory array,
     * the row isn't found, or no recognised identifier is present —
     * the caller still has the line number to work with.
     *
     * @param array|null      $dataArray
     * @param int|string|null $rowNumber
     * @return string
     */
    private function formatRowIdentifier($dataArray, $rowNumber): string
    {
        if (!is_array($dataArray) || $rowNumber === null || !isset($dataArray[$rowNumber])) {
            return '';
        }
        $row = $dataArray[$rowNumber];
        foreach (self::DEFAULT_ROW_IDENTIFIER_FIELDS as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                return sprintf(' (%s=%s)', $field, $row[$field]);
            }
        }
        return '';
    }

    /**
     * Lock indexers
     * Prevent unwanted running of indexers, bad for performance and possible deadlock situation
     * @return void
     */
    public function lockIndexers()
    {
        foreach ($this->indexerCollection as $indexer) {
            /** @var \Magento\Indexer\Model\Indexer $indexer */
            $indexer->getState()->setStatus(StateInterface::STATUS_WORKING);
            $indexer->getState()->save();
        }
    }

    /**
     * Unlock indexers
     * @return void
     */
    protected function unlockIndexers()
    {
        foreach ($this->indexerCollection as $indexer) {
            /** @var \Magento\Indexer\Model\Indexer $indexer */
            $indexer->getState()->setStatus(StateInterface::STATUS_VALID);
            $indexer->getState()->save();
        }
    }

}
