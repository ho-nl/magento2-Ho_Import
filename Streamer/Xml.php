<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Streamer;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Get the data stream as \Generator
 * 1. Get the data stream over HTTP
 * 2. Put the data stream into the XML String Streamer
 * 3. Split the XML String in separate Nodes to get a single XML Node String
 * 4. Parse the XML Node String as an SimpleXMLElement
 * 5. Convert the SimpleXMLElement to an array
 *
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
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    private $cacheItemPool;

    /**
     * @var \Symfony\Component\Console\Output\ConsoleOutput
     */
    private $consoleOutput;

    /**
     * @var int
     */
    private $ttl;

    /**
     * Xml constructor.
     *
     * @param \Psr\Cache\CacheItemPoolInterface               $cacheItemPool
     * @param \Symfony\Component\Console\Output\ConsoleOutput $consoleOutput
     *
     * @param string                                          $url
     * @param string                                          $uniqueNode
     * @param int                                             $limit
     * @param int                                             $ttl
     */
    public function __construct(
        \Psr\Cache\CacheItemPoolInterface $cacheItemPool,
        \Symfony\Component\Console\Output\ConsoleOutput $consoleOutput,
        string $url,
        string $uniqueNode,
        int $limit = PHP_INT_MAX,
        int $ttl = (12*3600)
    ) {
        $this->url = $url;
        $this->uniqueNode = $uniqueNode;
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
        $this->consoleOutput->write("<info></info>Getting <{$this->uniqueNode}> from URL {$this->url}");

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

        $parser   = new \Prewk\XmlStringStreamer\Parser\UniqueNode([
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
