<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Streamer;

use Prewk\XmlStringStreamer\Parser\StringWalker;
use Prewk\XmlStringStreamer\Parser\UniqueNode;
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
 * @todo {Paul} implement a proper interface that can be directly picked up by the sourceIterator without requiring knowledge of the getIterator method.
 */
class HttpXml
{
    /**
     * @var CachePool
     */
    private $cacheItemPool;

    /**
     * @var ConsoleOutput
     */
    private $consoleOutput;

    /**
     * @var string
     */
    private $requestUrl;

    /**
     * @var string
     */
    private $requestMethod;

    /**
     * @var []
     */
    private $requestOptions;

    /**
     * @var string
     */
    private $xmlParser;

    /**
     * @var []
     */
    private $xmlOptions;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var int
     */
    private $ttl;

    /**
     * Xml constructor.
     *
     * @param CachePool     $cacheItemPool
     * @param ConsoleOutput $consoleOutput
     *
     * @param string        $requestUrl
     * @param string        $requestMethod
     * @param array         $requestOptions
     * @param string        $xmlParser
     * @param array         $xmlOptions
     * @param int           $limit
     * @param int           $ttl
     *
     * @todo {Paul} Search for all references of options, uniqueNode, etc. So all imports are still working.
     * @todo {Paul} Replace the $parser argument with a Factory for the ParserInterface and default to the
     *       Parser\UniqueNode (Open/Closed Principle)
     */
    public function __construct(
        CachePool $cacheItemPool,
        ConsoleOutput $consoleOutput,
        string $requestUrl,
        string $requestMethod = 'get',
        array $requestOptions = [],
        array $xmlOptions = [],
        int $limit = PHP_INT_MAX,
        int $ttl = (12 * 3600)
    ) {
        $this->requestUrl     = $requestUrl;
        $this->requestMethod  = $requestMethod;
        $this->requestOptions = $requestOptions;
        $this->xmlOptions     = $xmlOptions;
        $this->limit          = $limit;
        $this->cacheItemPool  = $cacheItemPool;
        $this->consoleOutput  = $consoleOutput;
        $this->ttl            = $ttl;
    }

    /**
     * @todo {Paul} Refactor all direct instantiation of classes (Dependency Injection)
     * Get the source iterator
     * @return \Generator
     */
    public function getIterator()
    {
        $this->consoleOutput->write(
            "<info>Streamer\HttpXml: Getting data from URL {$this->requestUrl}</info>"
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

        $result = $httpClient->request(
            $this->requestMethod,
            $this->requestUrl,
            $this->requestOptions + ['stream' => true]
        );
        $stream = new \Prewk\XmlStringStreamer\Stream\Guzzle('');

        if ($result->getHeader('X-Kevinrob-Cache') && $result->getHeader('X-Kevinrob-Cache')[0] == 'HIT') {
            $this->consoleOutput->write(" <info>[Cache {$result->getHeader('X-Kevinrob-Cache')[0]}]</info>");
        } else {
            $this->consoleOutput->write(" <comment>[Cache {$result->getHeader('X-Kevinrob-Cache')[0]}]</comment>");
        }
        $this->consoleOutput->write("\n");

        $stream->setGuzzleStream($result->getBody());

        $class = isset($this->xmlOptions['uniqueNode']) ? UniqueNode::class : StringWalker::class;
        $parser = new $class($this->xmlOptions + [
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
