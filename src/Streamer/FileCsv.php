<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Streamer;

use Ho\Import\Logger\Log;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Get the data stream as \Generator
 * 1. Get the data location from disk
 * 2. Split the XML String in separate Nodes to get a single XML Node String
 * 3. Parse the XML Node String as an SimpleXMLElement
 * 4. Convert the SimpleXMLElement to an array
 *
 * @todo {Paul} implement a proper interface that can be directly picked up by the sourceIterator without requiring knowledge of the getIterator method.
 */
class FileCsv
{
    /** @var string $requestFile */
    private $requestFile;

    /** @var ConsoleOutput $consoleOutput */
    private $consoleOutput;

    /** @var DirectoryList $directoryList */
    private $directoryList;

    /** @var Log $log */
    private $log;

    /** @var string $delimiter */
    private $delimiter;

    /** @var array $headers */
    private $headers;

    /** @var string $escapeCharacter */
    private $escapeCharacter;

    /**
     * @param ConsoleOutput $consoleOutput
     * @param DirectoryList $directoryList
     * @param string        $requestFile Relative or absolute path to filename.
     * @param Log           $log
     * @param string        $delimiter
     * @param array         $headers
     * @param string        $escapeCharacter
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        DirectoryList $directoryList,
        string $requestFile,
        Log $log,
        string $delimiter,
        array $headers = [],
        string $escapeCharacter = ''
    ) {
        $this->consoleOutput = $consoleOutput;
        $this->directoryList = $directoryList;
        $this->requestFile = $requestFile;
        $this->log = $log;
        $this->headers = $headers;
        $this->delimiter = $delimiter;
    }

    /**
     * @throws FileSystemException
     * @throws \League\Csv\Exception
     *
     * @return \Generator
     */
    public function getIterator()
    {
        $this->consoleOutput->writeln(
            "<info>Streamer\FileCsv: Getting data from requestFile {$this->requestFile}</info>"
        );
        $this->log->addInfo('Streamer\FileCsv: Getting data from requestFile '.$this->requestFile);

        $requestFile = $this->getRequestFile();
        if (! file_exists($requestFile)) {
            $this->log->addCritical(sprintf('requestFile %s not found', $requestFile));
            throw new FileSystemException(__('requestFile %1 not found', $requestFile));
        }
        $requestFile = fopen($requestFile, 'r');

        $csvReader = \League\Csv\Reader::createFromStream($requestFile);
        $csvReader->setEscape($this->escapeCharacter);
        if (empty($this->headers)) {
            $csvReader->setHeaderOffset(0);
        }
        if ($this->delimiter) {
            $csvReader->setDelimiter($this->delimiter);
        }
        foreach ($csvReader->getIterator() as $row) {
            yield (empty($this->headers) ? $row : \array_combine($this->headers, \array_map('utf8_encode', $row)));
        }
    }

    /**
     * @return string
     */
    private function getRequestFile(): string
    {
        return $this->requestFile[0] == '/'
            ? $this->requestFile
            : $this->directoryList->getRoot() . '/' . trim($this->requestFile, '/');
    }
}
