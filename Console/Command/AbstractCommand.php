<?php
/**
 * Copyright © 2016 FireGento e.V. - All rights reserved.
 * See LICENSE.md bundled with this module for license details.
 */
namespace Ho\Import\Console\Command;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\App\ObjectManager\ConfigLoader;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\App\State;
use Magento\ImportExport\Model\Import;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TestCommand
 * @package FireGento\FastSimpleImport2\Console\Command
 *
 */
abstract class AbstractCommand extends Command
{

    /**
     * @var string
     */
    protected $behavior;
    /**
     * @var string
     */
    protected $entityCode;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;
    /**
     * Object manager factory
     *
     * @var ObjectManagerFactory
     */
    private $objectManagerFactory;

    /**
     * Constructor
     *
     * @param ObjectManagerFactory $objectManagerFactory
     */
    public function __construct(ObjectManagerFactory $objectManagerFactory)
    {
        $this->objectManagerFactory = $objectManagerFactory;

        $omParams = $_SERVER;
        $omParams[StoreManager::PARAM_RUN_CODE] = 'admin';
        $omParams[Store::CUSTOM_ENTRY_POINT_PARAM] = true;
        $this->objectManager = $this->objectManagerFactory->create($omParams);

        $area = FrontNameResolver::AREA_CODE;

        /** @var \Magento\Framework\App\State $appState */
        $appState = $this->objectManager->get('Magento\Framework\App\State');
        $appState->setAreaCode($area);
        $configLoader = $this->objectManager->get('Magento\Framework\ObjectManager\ConfigLoaderInterface');
        $this->objectManager->configure($configLoader->load($area));

        parent::__construct();
    }

    public function arrayToAttributeString($array)
    {


        $attributes_str = NULL;
        foreach ($array as $attribute => $value) {

            $attributes_str .= "$attribute=$value,";

        }

        return $attributes_str;
    }


    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $productsArray = $this->getEntities($output);

        $output->writeln('Import started');

        $time = microtime(true);

        /** @var \FireGento\FastSimpleImport2\Model\Importer $importerModel */
        $importerModel = $this->objectManager->create(\FireGento\FastSimpleImport2\Model\Importer::class);

        $importerModel->setBehavior($this->getBehavior());
        $importerModel->setEntityCode($this->getEntityCode());

        try {
            $importerModel->processImport($productsArray);
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
        }

        $output->write($importerModel->getLogTrace());
        $output->write($importerModel->getErrorMessages());

        $output->writeln('Import finished. Elapsed time: ' . round(microtime(true) - $time, 2) . 's' . "\n");
    }

    /**
     * Get an array of the entity list
     * 
     * @return array
     */
    abstract protected function getEntities(OutputInterface $output);

    /**
     * Get the current behaviour of the importer
     * @see \Magento\ImportExport\Model\Import
     *
     * @return string
     */
    public function getBehavior()
    {
        return $this->behavior;
    }

    /**
     * Get the current behaviour of the importer
     * @param string $behavior
     * 
     * @see \Magento\ImportExport\Model\Import
     * @return string
     */
    public function setBehavior($behavior)
    {
        $this->behavior = $behavior;
    }

    /**
     * @return string
     */
    public function getEntityCode()
    {
        return $this->entityCode;
    }

    /**
     * @param string $entityCode
     */
    public function setEntityCode($entityCode)
    {
        $this->entityCode = $entityCode;
    }
}
