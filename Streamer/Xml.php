<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Streamer;

use Psr\Cache\CacheItemPoolInterface as CachePool;
use Symfony\Component\Console\Output\ConsoleOutput;



/**
 * This class is made to solve the issue that we want to download XML-files and parse them as they come in.
 * The XML parser needs some extra handling to support this scenario.
 *
 * Get the data stream as \Generator
 * 1. Get the data stream over HTTP
 * 2. Use a cached version of the downloaded file if available
 * 3. Put the data stream into the XML String Streamer
 * 4. Split the XML String in separate Nodes to get a single XML Node String
 * 5. Parse the XML Node String as an SimpleXMLElement
 * 6. Convert the SimpleXMLElement to an array
 *
 * @todo Rename to Streamer\HttpXml
 * @todo Refactor to XmlFactory only. The factory will only return the \Generator instead of an intermediate iterator.
 */
class Xml
{

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $uniqueNode;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var CachePool
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
     * @var string
     */
    private $parser;

    /**
     * Xml constructor.
     *
     * @param CachePool     $cacheItemPool
     * @param ConsoleOutput $consoleOutput
     *
     * @param string        $url
     * @param string        $uniqueNode
     * @param string        $parser An alternative is \Prewk\XmlStringStreamer\Parser\StringWalker::class
     * @param int           $limit
     * @param int           $ttl
     */
    public function __construct(
        CachePool $cacheItemPool,
        ConsoleOutput $consoleOutput,
        string $url,
        string $uniqueNode,
        string $parser = \Prewk\XmlStringStreamer\Parser\UniqueNode::class,
        int $limit = PHP_INT_MAX,
        int $ttl = (12*3600)
    ) {
        $this->url = $url;
        $this->uniqueNode = $uniqueNode;
        $this->parser = $parser;
        $this->limit = $limit;
        $this->cacheItemPool = $cacheItemPool;
        $this->consoleOutput = $consoleOutput;
        $this->ttl = $ttl;
    }

    /**
     * Get the source iterator
     * @return \Generator
     */
    public function getIterator()
    {
        $this->consoleOutput->write(
            "<info>Streamer\HttpXml: Getting <{$this->uniqueNode}> from URL {$this->url}</info>"
        );

        $stack = \GuzzleHttp\HandlerStack::create();
        $stack->push(new \Kevinrob\GuzzleCache\CacheMiddleware(
            new \Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy(
                new \Kevinrob\GuzzleCache\Storage\Psr6CacheStorage(
                    $this->cacheItemPool
                ),
                $this->ttl
            )
        ), 'cache');

        $httpClient = new \GuzzleHttp\Client(['handler' => $stack]);

        $result = $httpClient->get($this->url, ['stream' => true]);
        $stream = new \Prewk\XmlStringStreamer\Stream\Guzzle('');


        if ($result->getHeader('X-Kevinrob-Cache') && $result->getHeader('X-Kevinrob-Cache')[0] == 'HIT') {
            $this->consoleOutput->write(" <info>[Cache {$result->getHeader('X-Kevinrob-Cache')[0]}]</info>");
        } else {
            $this->consoleOutput->write(" <comment>[Cache {$result->getHeader('X-Kevinrob-Cache')[0]}]</comment>");
        }
        $this->consoleOutput->write("\n");

        $stream->setGuzzleStream($result->getBody());

        $parser   = new $this->parser([
            'uniqueNode' => $this->uniqueNode,
            'checkShortClosing' => true
        ]);
        $streamer = new \Prewk\XmlStringStreamer($parser, $stream);

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
