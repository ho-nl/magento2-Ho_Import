<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Console\Command;

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
    const INPUT_KEY_AHEAD = 'ahead';

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
     * @param \Magento\Cron\Model\ConfigInterface\Proxy            $config
     * @param \Magento\Cron\Model\ScheduleFactory                  $scheduleFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     */
    public function __construct(
        \Magento\Cron\Model\ConfigInterface\Proxy $config,
        \Magento\Cron\Model\ScheduleFactory $scheduleFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface\Proxy $timezone
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

        $this->addArgument(
            self::INPUT_KEY_AHEAD,
            InputArgument::OPTIONAL,
            'Plan time ahead: 2H'
        );

        parent::configure();
    }

    /**
     * Run the selected profile.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $jobName = $input->getArgument(self::INPUT_KEY_JOB_NAME);
        $ahead = $input->getArgument(self::INPUT_KEY_AHEAD);

        $job = $this->getJobFromName($jobName);

        if (! $job) {
            $output->writeln("<error>".((string) new Phrase("Job not found"))."</error>");
            return;
        }


        $existingJob = $this->getExistingJob($job);
        if ($existingJob->getId()) {
            $output->writeln(
                (string) new Phrase("Job is already %1", ["<error>".$existingJob->getStatus()."</error>"])
            );
            return;
        }

        try {
            $ahead = new \DateInterval($ahead ?: 'P0M');
        } catch (\Exception $e) {
            $output->writeln("<error>".
                (string) new Phrase(
                    "Ahead schedule is in the wrong format: %1 (see https://en.wikipedia.org/wiki/ISO_8601#Durations )",
                    [$ahead]
                )
            ."</error>");
            return;
        }

        $jobSchedule = $this->generateSchedule($job, $ahead);



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
        foreach ($this->config->getJobs() as $jobs) {
            foreach ($jobs as $job) {
                if ($jobName == $job['name']) {
                    return $job;
                }
            }
        }

        return null;
    }

    /**
     * @param string[]      $job
     * @param \DateInterval $ahead
     *
     * @throws \Magento\Framework\Exception\CronException
     *
     * @return Schedule
     */
    private function generateSchedule($job, \DateInterval $ahead)
    {
        $scheduleTime = $this->timezone->scopeDate(null, null, true);
        $scheduleTime->add($ahead);
        $scheduleTime = $scheduleTime->format('Y-m-d H:i:s');

        return $this->scheduleFactory->create()
            ->setCronExpr('* * * * *')
            ->setJobCode($job['name'])
            ->setStatus(Schedule::STATUS_PENDING)
            ->setCreatedAt(strftime('%Y-%m-%d %H:%M:%S', $this->timezone->scopeTimeStamp()))
            ->setScheduledAt($scheduleTime);
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
