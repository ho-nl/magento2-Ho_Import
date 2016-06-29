<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Model;

use Ho\Import\Api\ImportProfileInterface;
use Magento\Framework\Phrase;
use Magento\Framework\ObjectManagerInterface;


use Magento\Framework\App\ObjectManager\ConfigLoader;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\App\State as AppState;
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
     * ImportProfile constructor.
     *
     * @param ObjectManagerFactory                            $objectManagerFactory
     * @param Stopwatch          $stopwatch
     * @param ConsoleOutput $consoleOutput
     */
    public function __construct(
        ObjectManagerFactory $objectManagerFactory,
        Stopwatch $stopwatch,
        ConsoleOutput $consoleOutput
    ) {
        $this->objectManagerFactory = $objectManagerFactory;
        $this->stopwatch = $stopwatch;
        $this->consoleOutput = $consoleOutput;
    }


    /**
     * Run the actual import
     * @return void
     */
    public function run()
    {
        try {
            $items = $this->getItemsMeasured();

            /** @var Importer $importer */
            $importer = $this->getObjectManager()->create(Importer::class);
            $importer->setConfig($this->getConfig());

            $this->stopwatch->start('importinstance');
            $importer->processImport($items);
            $stopwatchEvent = $this->stopwatch->stop('importinstance');
 
            $this->consoleOutput->writeln((string) new Phrase(
                '%1 items imported in %2 sec, <info>%3 items / sec</info> (%4mb used)', [
                count($items),
                round($stopwatchEvent->getDuration() / 1000, 1),
                round(count($items) / ($stopwatchEvent->getDuration() / 1000), 1),
                round($stopwatchEvent->getMemory() / 1024 / 1024, 1)
            ]));

            $this->consoleOutput->write($importer->getLogTrace());
            foreach ($importer->getErrorMessages() as $error) {
                $this->consoleOutput->writeln("<error>$error</error>");
            }
        } catch (\Exception $e) {
            $this->consoleOutput->writeln($e->getMessage());
        }
    }
    
    /**
     * Get all items that need to be imported
     *
     * @return \[]
     */
    protected function getItemsMeasured()
    {
        $this->stopwatch->start('profileinstance');
        $this->consoleOutput->writeln('Getting item data');
        $items = $this->getItems();
        $stopwatchEvent = $this->stopwatch->stop('profileinstance');
        
        if (! $stopwatchEvent->getDuration()) {
            return $items;
        }

        $this->consoleOutput->writeln((string)new Phrase('%1 items processed in %2 sec, <info>%3 items / sec</info> (%4mb used)',
            [
                count($items),
                round($stopwatchEvent->getDuration() / 1000, 1),
                round(count($items) / ($stopwatchEvent->getDuration() / 1000), 1),
                round($stopwatchEvent->getMemory() / 1024 / 1024, 1)
            ]));
        return $items;
    }


    /**
     * Gets initialized object manager
     *
     * @return ObjectManagerInterface
     */
    protected function getObjectManager()
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
