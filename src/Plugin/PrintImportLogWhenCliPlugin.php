<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

/**
 * Created by PhpStorm.
 * User: simonprins
 * Date: 17/05/2019
 * Time: 14:27
 */

namespace Ho\Import\Plugin;

use Ho\Import\Logger\Log;
use Symfony\Component\Console\Output\ConsoleOutput;

class PrintImportLogWhenCliPlugin
{
    protected $consoleOutput;

    protected $log;

    public function __construct(ConsoleOutput $consoleOutput, Log $log)
    {
        $this->consoleOutput = $consoleOutput;
        $this->log = $log;
    }

    public function beforeAddLogComment(
        \Magento\ImportExport\Model\Import $subject,
        $debugData
    ) {
        $debugData = is_array($debugData) ? implode(', ', $debugData) : $debugData;
        $this->consoleOutput->writeln("<info>{$debugData}</info>");
        $this->log->addInfo((string)$debugData);
    }
}
