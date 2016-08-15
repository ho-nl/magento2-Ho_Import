<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Console\Command;

use Ho\Import\Api\ImportProfilePoolInterface;
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
class HoImportRunCommand extends Command
{
    /**
     * Input argument types
     */
    const INPUT_KEY_PROFILE = 'profile';

    protected $objectManagerFactory;

    /**
     * List of available import profiles
     *
     * @var ImportProfilePoolInterface
     */
    private $importProfilePool;

    /**
     * Measurement of timing information
     *
     * @var Stopwatch
     */
    private $stopwatch;



    /**
     * Output texts to the cli.
     *
     * @var ConsoleOutput
     */
    private $consoleOutput;


    /**
     * Constructor
     *
     * @param ImportProfilePoolInterface $importProfilePool
     * @param Stopwatch                  $stopwatch
     * @param ConsoleOutput              $consoleOutput
     */
    public function __construct(
        ImportProfilePoolInterface $importProfilePool,
        Stopwatch $stopwatch,
        ConsoleOutput $consoleOutput
    ) {
        $this->importProfilePool = $importProfilePool;
        $this->stopwatch         = $stopwatch;
        $this->consoleOutput     = $consoleOutput;
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
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $profiles = $this->importProfilePool->getProfiles();
        $profile = $input->getArgument('profile');
        if (!isset($profiles[$profile])) {
            $profileList = implode(", ", array_keys($profiles));
            $output->writeln((string) new Phrase("<error>Profile not in profilelist: %1</error>", [$profileList]));
            return;
        }

        $profileInstance = $profiles[$profile];
        $profileInstance->run();
    }


}
