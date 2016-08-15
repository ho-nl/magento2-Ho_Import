<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Console\Command;

use Ho\Import\Api\ImportProfileListInterface;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Phrase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RunCommand
 *
 * @package Ho\Import\Console\Command
 */
class CronScheduleCommand extends Command
{
    /**
     * Input argument types
     */
    const INPUT_KEY_JOB_NAME = 'jobName';

    /**
     * @var \Magento\Cron\Model\ConfigInterface
     */
    private $config;

    /**
     * @var \Magento\Cron\Model\ScheduleFactory
     */
    private $scheduleFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    private $timezone;

    /**
     * HoImportRunCommand constructor.
     *
     * @param \Magento\Cron\Model\ConfigInterface                  $config
     * @param \Magento\Cron\Model\ScheduleFactory                  $scheduleFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     */
    public function __construct(
        \Magento\Cron\Model\ConfigInterface $config,
        \Magento\Cron\Model\ScheduleFactory $scheduleFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    ) {
        parent::__construct();
        $this->config = $config;
        $this->scheduleFactory = $scheduleFactory;
        $this->timezone = $timezone;
    }

    /**
     * Configures the current command.
     * @return void
     */
    public function configure()
    {
        $this->setName('cron:schedule')
             ->setDescription('Schedule a job to run immediately');

        $this->addArgument(
            self::INPUT_KEY_JOB_NAME,
            InputArgument::REQUIRED,
            'Name of cron job to run'
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

        $jobName = $input->getArgument(self::INPUT_KEY_JOB_NAME);
        $job = $this->getJobFromName($jobName);

        if (! $job) {
            $output->writeln("<error>".((string) new Phrase("Job not found"))."</error>");
            return;
        }


        $existingJob = $this->getExistingJob($job);
        if ($existingJob->getId()) {
            $output->writeln("<error>".((string) new Phrase("Job is already %1", [$existingJob->getStatus()]))."</error>");
            return;
        }

        $jobSchedule = $this->generateSchedule($job);
        $jobSchedule->getResource()->save($jobSchedule);
        $output->writeln("<info>".((string) new Phrase("Job scheduled"))."</info>");
    }

    /**
     * Get the job from the name provided
     *
     * @param string $jobName
     * @return string[]
     */
    protected function getJobFromName($jobName)
    {
        $job = null;
        foreach ($this->config->getJobs() as $jobs) {
            foreach ($jobs as $job) {
                if ($jobName == $job['name']) {
                    break 2;
                }
            }
        }
        return $job;
    }

    /**
     * @param string[] $job
     * @return Schedule
     */
    private function generateSchedule($job)
    {
        return $this->scheduleFactory->create()
            ->setCronExpr('* * * * *')
            ->setJobCode($job['name'])
            ->setStatus(Schedule::STATUS_PENDING)
            ->setCreatedAt(strftime('%Y-%m-%d %H:%M:%S', $this->timezone->scopeTimeStamp()))
            ->setScheduledAt(strftime('%Y-%m-%d %H:%M', $this->timezone->scopeTimeStamp()));
    }

    /**
     * Return job collection from data base with status 'pending'
     * @return \Magento\Cron\Model\Schedule|null
     */
    private function getExistingJob($job)
    {
        return $this->scheduleFactory->create()->getCollection()
            ->addFieldToFilter('status', ['in' => [Schedule::STATUS_PENDING, Schedule::STATUS_RUNNING]])
            ->addFieldToFilter('job_code', $job)
            ->getFirstItem();
    }
}
