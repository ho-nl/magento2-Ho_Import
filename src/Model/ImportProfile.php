<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Model;

use Ho\Import\Api\ImportProfileInterface;
use Ho\Import\Logger\Log;
use Magento\Framework\Phrase;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\ObjectManagerFactory;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Stopwatch\Stopwatch;

abstract class ImportProfile implements ImportProfileInterface
{
    /**
     * Usage of the objectManager
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Stopwatch
     */
    private $stopwatch;

    /**
     * @var ConsoleOutput
     */
    private $consoleOutput;

    /**
     * @var ObjectManagerFactory
     */
    private $objectManagerFactory;

    /**
     * @var Log
     */
    protected $log;

    /**
     * @var \Exception
     */
    private $exception;

    /**
     * @var ?string
     */
    private $errors = null;

    /**
     * @param ObjectManagerFactory $objectManagerFactory
     * @param Stopwatch            $stopwatch
     * @param ConsoleOutput        $consoleOutput
     * @param Log                  $log
     */
    public function __construct(
        ObjectManagerFactory $objectManagerFactory,
        Stopwatch $stopwatch,
        ConsoleOutput $consoleOutput,
        Log $log
    ) {
        $this->objectManagerFactory = $objectManagerFactory;
        $this->stopwatch = $stopwatch;
        $this->consoleOutput = $consoleOutput;
        $this->log = $log;
    }

    /**
     * Run the actual import
     *
     * @return int
     */
    public function run()
    {
        try {
            $items = $this->getItemsMeasured();

            /** @var Importer $importer */
            $importer = $this->getObjectManager()->create(Importer::class);
            $importer->setConfig($this->getConfig());

            $this->stopwatch->start('importinstance');
            $items = array_values($items);
            $errors = $importer->processImport($items);
            $stopwatchEvent = $this->stopwatch->stop('importinstance');

            $message = $errors
                ? 'Tried to import %1 items in %2 sec, <info>%3 items / sec</info> (%4mb used)'
                : '%1 items imported in %2 sec, <info>%3 items / sec</info> (%4mb used)';
            $output = (string) new Phrase($message, [
                count($items),
                round($stopwatchEvent->getDuration() / 1000, 1),
                round(count($items) / ($stopwatchEvent->getDuration() / 1000), 1),
                round($stopwatchEvent->getMemory() / 1024 / 1024, 1),
            ]);

            $this->consoleOutput->writeln($output);
            $this->log->info($output);

            $this->consoleOutput->writeln("<error>$errors</error>");
            $this->log->error($errors);

            $this->errors = $errors;

            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $this->consoleOutput->writeln($e->getMessage());
            $this->log->critical($e->getMessage());
            $innerExceptionIterator = $e->getPrevious();
            while ($innerExceptionIterator !== null) {
                $this->consoleOutput->writeln($innerExceptionIterator->getMessage());
                $this->log->critical($innerExceptionIterator->getMessage());
                $innerExceptionIterator = $innerExceptionIterator->getPrevious();
            }

            // Store the exception in case a calling class wants to inspect it.
            $this->exception = $e;

            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }

    public function getException(): \Exception
    {
        return $this->exception;
    }

    /**
     * @return string
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get all items that need to be imported
     *
     * @return array
     */
    private function getItemsMeasured()
    {
        $this->stopwatch->start('profileinstance');
        $this->consoleOutput->writeln('Getting item data');
        $this->log->info('Getting item data');
        $items = $this->getItems();
        $stopwatchEvent = $this->stopwatch->stop('profileinstance');

        if (!$stopwatchEvent->getDuration()) {
            return $items;
        }

        $output = (string) new Phrase('%1 items processed in %2 sec, <info>%3 items / sec</info> (%4mb used)', [
            count($items),
            round($stopwatchEvent->getDuration() / 1000, 1),
            round(count($items) / ($stopwatchEvent->getDuration() / 1000), 1),
            round($stopwatchEvent->getMemory() / 1024 / 1024, 1),
        ]);

        $this->consoleOutput->writeln($output);
        $this->log->info($output);

        return $items;
    }

    /**
     * Gets initialized object manager
     *  - Loads the Admin space from the CLI
     *  - Sets it to be a custom entry point
     *  - Set the area code to adminhtml.
     * @todo {Paul} Can this be removed?
     * @return ObjectManagerInterface
     */
    private function getObjectManager()
    {
        if (null == $this->objectManager) {
            $omParams = $_SERVER;
            $omParams[\Magento\Store\Model\StoreManager::PARAM_RUN_CODE] = 'admin';
            $omParams[\Magento\Store\Model\Store::CUSTOM_ENTRY_POINT_PARAM] = true;
            $this->objectManager = $this->objectManagerFactory->create($omParams);

            $area = \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE;
            /** @var \Magento\Framework\App\State $appState */
            $appState = $this->objectManager->get(\Magento\Framework\App\State::class);
            $appState->setAreaCode($area);
            $configLoader = $this->objectManager->get(\Magento\Framework\ObjectManager\ConfigLoaderInterface::class);
            $this->objectManager->configure($configLoader->load($area));
        }
        return $this->objectManager;
    }
}
