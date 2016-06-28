<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Console\Command;

use Ho\Import\Api\ImportProfileInterface;
use Ho\Import\Api\ImportProfileListInterface;
use Ho\Import\Model\Importer;
use Magento\Framework\App\ObjectManager\ConfigLoader;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Phrase;
use Magento\ImportExport\Model\Import;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Class RunCommand
 *
 * @package Ho\Import\Console\Command
 */
class RunCommand extends Command
{
    /**
     * Input argument types
     */
    const INPUT_KEY_PROFILE = 'profile';

    protected $objectManagerFactory;

    /**
     * List of available import profiles
     *
     * @var ImportProfileListInterface
     */
    private $importProfileList;

    /**
     * Measurement of timing information
     *
     * @var Stopwatch
     */
    private $stopwatch;

    /**
     * Usage of the objectManager
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Output texts to the cli.
     *
     * @var ConsoleOutput
     */
    private $consoleOutput;

    /**
     * Constructor
     *
     * @param ImportProfileListInterface $importProfileList
     * @param Stopwatch                  $stopwatch
     * @param ObjectManagerFactory       $objectManagerFactory
     * @param ConsoleOutput              $consoleOutput
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        ImportProfileListInterface $importProfileList,
        Stopwatch $stopwatch,
        ObjectManagerFactory $objectManagerFactory,
        ConsoleOutput $consoleOutput
    ) {
        $this->importProfileList = $importProfileList;
        $this->objectManagerFactory = $objectManagerFactory;
        $this->stopwatch = $stopwatch;
        $this->consoleOutput = $consoleOutput;
        parent::__construct();
    }

    /**
     * Configures the current command.
     * @return void
     */
    public function configure()
    {
        $this->setName('ho:import:run')
             ->setDescription('Run a profile');

        $this->addArgument(
            self::INPUT_KEY_PROFILE,
            InputArgument::REQUIRED,
            'Name of profile to run'
        );

        parent::configure();
    }

    /**
     * Run the selected profile.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $profiles = $this->importProfileList->getProfiles();
        $profile = $input->getArgument('profile');
        if (!isset($profiles[$profile])) {
            $profileList = implode("\n", array_keys($profiles));
            $output->writeln((string) new Phrase("<error>Profile not in profilelist: %1</error>", [$profileList]));
            return;
        }

        $profileInstance = $profiles[$profile];
        $items = $this->getItems($profile, $profileInstance);


        /** @var Importer $importer */
        $importer = $this->getObjectManager()->create(Importer::class);
        $importer->setConfig($profileInstance->getConfig());

        try {
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
        } catch (\Exception $e) {
            $this->consoleOutput->writeln($e->getMessage());
        }
        $this->consoleOutput->write($importer->getLogTrace());
        foreach ($importer->getErrorMessages() as $error) {
            $this->consoleOutput->writeln("<error>$error</error>");
        }
    }

    /**
     * Get all items that need to be imported
     *
     * @param string $profile
     * @param ImportProfileInterface $profileInstance
     *
     * @return \[]
     */
    protected function getItems($profile, $profileInstance)
    {
        $this->stopwatch->start('profileinstance');
        $this->consoleOutput->writeln((string)new Phrase('Getting profile data from <info>%1</info>', [$profile]));
        $items = $profileInstance->getItems();
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
