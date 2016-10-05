<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Streamer;

use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Filesystem\DirectoryList;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Get the data stream as \Generator
 * 1. Get the data location from disk
 * 4. Split the XML String in separate Nodes to get a single XML Node String
 * 5. Parse the XML Node String as an SimpleXMLElement
 * 6. Convert the SimpleXMLElement to an array
 *
 * @todo Implement progress bar: https://github.com/prewk/xml-string-streamer#progress-bar
 */

class FileXml
{

    /**
     * @var string
     */
    private $file;

    /**
     * @var string
     */
    private $uniqueNode;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    private $cacheItemPool;

    /**
     * @var ConsoleOutput
     */
    private $consoleOutput;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * Xml constructor.
     *
     * @param ConsoleOutput $consoleOutput
     * @param DirectoryList $directoryList
     * @param string        $file
     * @param string        $uniqueNode
     * @param int           $limit
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        DirectoryList $directoryList,
        string $file,
        string $uniqueNode,
        int $limit = PHP_INT_MAX
    ) {
        $this->consoleOutput = $consoleOutput;
        $this->file          = $file;
        $this->uniqueNode    = $uniqueNode;
        $this->limit         = $limit;
        $this->directoryList = $directoryList;
    }

    /**
     * Get the source iterator
     * @return \Generator
     */
    public function getIterator()
    {
        $this->consoleOutput->writeln(
            "<info>Streamer\FileXml: Getting <{$this->uniqueNode}> from file {$this->file}</info>"
        );

        $file = $this->file[0] == '/' ? $this->file : $this->directoryList->getRoot() . '/' . trim($this->file, '/');

        if (! file_exists($file)) {
            throw new NotFoundException(__("File %1 not found", $this->file));
        }

        $streamer = \Prewk\XmlStringStreamer::createStringWalkerParser($file, [
            'uniqueNode' => $this->uniqueNode,
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
}
