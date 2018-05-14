<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Streamer;

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

    /**
     * @var string
     */
    private $requestFile;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var ConsoleOutput
     */
    private $consoleOutput;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var array
     */
    private $csvOptions;

    /**
     * @var array
     */
    private $header;


    /**
     * @var array
     */
    private $file;

    /**
     * Xml constructor.
     *
     * @param ConsoleOutput $consoleOutput
     * @param DirectoryList $directoryList
     * @param string        $requestFile  Relative or absolute path to filename.
     * @param array         $xmlOptions Passed trough to the XmlStringStreamer
     * @param int           $limit Add a limit how may rows we want to parse
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        DirectoryList $directoryList,
        array $header = [],
        string $requestFile,
        array $csvOptions = [],
        int $limit = PHP_INT_MAX
    ) {
        $this->consoleOutput = $consoleOutput;
        $this->directoryList = $directoryList;
        $this->requestFile   = $requestFile;
        $this->header = $header;
        $this->csvOptions    = $csvOptions;
        $this->limit         = $limit;
    }

    /**
     * Get the source iterator
     *
     * @return \Generator
     * @throws FileSystemException
     */ 
    public function getIterator()
    {
        $this->consoleOutput->writeln(
            "<info>Streamer\FileCsv: Getting data from requestFile {$this->requestFile}</info>"
        );

        $requestFile = $this->getRequestFile();
        if (! file_exists($requestFile)) {
            throw new FileSystemException(__("requestFile %1 not found", $requestFile));
        }
        $requestFile = fopen($requestFile, 'r');
        while (!feof($requestFile)) {
            $row = array_map('trim', (array)fgetcsv($requestFile, 4096));
            if (count($this->header) !== count($row)) {
                continue;
            }
            $row = array_combine($this->header, $row);
            yield $row;
        }
        return;
    }

    /**
     * @return string
     */
    private function getRequestFile():string
    {
        return $this->requestFile[0] == '/'
            ? $this->requestFile
            : $this->directoryList->getRoot() . '/' . trim($this->requestFile, '/');
    }
}
