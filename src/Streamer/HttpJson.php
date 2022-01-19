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
 * This class is made to solve the issue that we want to download Json-files and parse them as they come in.
 * The Json parser needs some extra handling to support this scenario.
 *
 * Get the data stream as \Generator
 * 1. Get the data stream over HTTP
 * 2. Use a cached version of the downloaded file if available
 * 3. Convert the json blob to a php array
 *
 * @todo Currently the json is parsed entirely. Preferably we would like to stream it as it comes in.
 */
class HttpJson
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
     * @var array
     */
    private $auth;

    /**
     * Xml constructor.
     *
     * @param CachePool $cacheItemPool
     * @param ConsoleOutput $consoleOutput
     *
     * @param string $requestUrl
     * @param string $requestMethod
     * @param array $requestOptions
     * @param array $xmlOptions
     * @param int $limit
     * @param int $ttl
     * @param array|null $auth
     *
     */
    public function __construct(
        CachePool $cacheItemPool,
        ConsoleOutput $consoleOutput,
        string $requestUrl,
        string $requestMethod = 'get',
        array $requestOptions = [],
        array $xmlOptions = [],
        int $limit = PHP_INT_MAX,
        int $ttl = (12 * 3600),
        ?array $auth = null
    ) {
        $this->requestUrl     = $requestUrl;
        $this->requestMethod  = $requestMethod;
        $this->requestOptions = $requestOptions;
        $this->xmlOptions     = $xmlOptions;
        $this->limit          = $limit;
        $this->cacheItemPool  = $cacheItemPool;
        $this->consoleOutput  = $consoleOutput;
        $this->ttl            = $ttl;
        $this->auth = $auth;
    }

    /**
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
            $this->requestOptions + ['stream' => true, 'auth' => $this->auth]
        );

        $json = \json_decode($result->getBody(), true);

        $generator = function (array $json) {
            foreach ($json as $item) {
                yield $item;
            }
        };
        return $generator($json);
    }
}
