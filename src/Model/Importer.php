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
     * Formatted array of error messages
     * @return []\
     */
    public function getErrorMessages()
    {
        $errorAggregator = $this->importModel->getErrorAggregator();
        return array_map(function (ProcessingError $error) {
            return sprintf(
                'Line %s: %s %s',
                $error->getRowNumber() ?: '[?]',
                $error->getErrorMessage(),
                $error->getErrorDescription()
            );
        }, $errorAggregator->getAllErrors());
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
