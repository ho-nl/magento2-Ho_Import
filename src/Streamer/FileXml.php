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

class FileXml
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
    private $xmlOptions;

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
        string $requestFile,
        array $xmlOptions = [],
        int $limit = PHP_INT_MAX
    ) {
        $this->consoleOutput = $consoleOutput;
        $this->directoryList = $directoryList;
        $this->requestFile   = $requestFile;
        $this->xmlOptions    = $xmlOptions;
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
            "<info>Streamer\FileXml: Getting data from requestFile {$this->requestFile}</info>"
        );

        $requestFile = $this->getRequestFile();

        if (! file_exists($requestFile)) {
            throw new FileSystemException(__("requestFile %1 not found", $requestFile));
        }

        $method = isset($this->xmlOptions['uniqueNode']) ? 'createUniqueNodeParser' : 'createStringWalkerParser';
        $streamer = \Prewk\XmlStringStreamer::$method($requestFile, $this->xmlOptions + [
            'checkShortClosing' => true
        ]);

        $limit = $this->limit;
        $generator = function (\Prewk\XmlStringStreamer $streamer) use ($limit) {
            while (($node = $streamer->getNode()) && $limit > 0) {
                $limit--;
                yield array_filter(json_decode(json_encode(new \SimpleXMLElement($node, LIBXML_NOCDATA)), true));
            }
        };

        return $generator($streamer);
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
