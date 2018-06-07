<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Streamer;

use Bakame\Psr7\Factory\StreamWrapper;
use Psr\Cache\CacheItemPoolInterface as CachePool;
use Symfony\Component\Console\Output\ConsoleOutput;


/**
 * This class is made to solve the issue that we want to download CSV-files and parse them as they come in.
 * The CSV parser needs some extra handling to support this scenario.
 *
 * Get the data stream as \Generator
 * 1. Get the data stream over HTTP
 * 2. Use a cached version of the downloaded file if available
 * 3. Put the data stream into the XML String Streamer
 * 4. Split the XML String in separate Nodes to get a single XML Node String
 * 5. Parse the XML Node String as an SimpleXMLElement
 * 6. Convert the SimpleXMLElement to an array
 *
 * @todo {Paul} implement a proper interface that can be directly picked up by the sourceIterator without requiring
 *       knowledge of the getIterator method.
 */
class HttpCsv
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

//
//    /**
//     * @var string
//     */
//    private $xmlParser;
//
//    /**
//     * @var []
//     */
//    private $xmlOptions;
//
//    /**
//     * @var int
//     */
//    private $limit;
//
    /**
     * @var int
     */
    private $ttl;

    /**
     * @var array
     */
    private $headers;

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
     */
    public function __construct(
        CachePool $cacheItemPool,
        ConsoleOutput $consoleOutput,
        string $requestUrl,
        string $requestMethod = 'GET',
        array $requestOptions = [],
//        int $limit = PHP_INT_MAX,
        int $ttl = (12 * 3600),
        $headers = []
    ) {
        $this->requestUrl     = $requestUrl;
        $this->requestMethod  = $requestMethod;
        $this->requestOptions = $requestOptions;
//        $this->limit          = $limit;
        $this->cacheItemPool = $cacheItemPool;
        $this->consoleOutput = $consoleOutput;
        $this->ttl           = $ttl;
        $this->headers = $headers;
    }

    /**
     * Get the source iterator
     * @return \Generator
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getIterator()
    {
        $this->consoleOutput->write(
            "<info>Streamer\HttpCsv: Getting data from URL {$this->requestUrl}</info>"
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
        $response = $httpClient->request(
            $this->requestMethod,
            $this->requestUrl,
            $this->requestOptions + ['stream' => true]
        );

        $csvReader = \League\Csv\Reader::createFromStream(StreamWrapper::getResource($response->getBody()));
        if (empty($this->headers)) {
            $csvReader->setHeaderOffset(0);
        }
        foreach ($csvReader->getIterator() as $row) {
            yield array_combine($this->headers, $row);
        }
    }
}
