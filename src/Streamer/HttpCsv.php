<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Streamer;

use Bakame\Psr7\Adapter\StreamWrapper;
use Ho\Import\Logger\Log;
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
     * @var array
     */
    private $requestOptions;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var array
     */
    public $headers;

    /**
     * @var Log
     */
    private $log;

    /**
     * @param CachePool     $cacheItemPool
     * @param ConsoleOutput $consoleOutput
     * @param string        $requestUrl
     * @param Log           $log
     * @param string        $requestMethod
     * @param array         $requestOptions
     * @param int           $ttl
     * @param array         $headers
     */
    public function __construct(
        CachePool $cacheItemPool,
        ConsoleOutput $consoleOutput,
        string $requestUrl,
        Log $log,
        string $requestMethod = 'GET',
        array $requestOptions = [],
        int $ttl = 12 * 3600,
        array $headers = []
    ) {
        $this->cacheItemPool = $cacheItemPool;
        $this->consoleOutput = $consoleOutput;
        $this->requestUrl = $requestUrl;
        $this->log = $log;
        $this->requestMethod = $requestMethod;
        $this->requestOptions = $requestOptions;
        $this->ttl = $ttl;
        $this->headers = $headers;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \League\Csv\Exception
     *
     * @return \Generator
     */
    public function getIterator()
    {
        $this->consoleOutput->write(
            "<info>Streamer\HttpCsv: Getting data from URL {$this->requestUrl}</info>"
        );
        $this->log->addInfo('Streamer\HttpCsv: Getting data from URL '.$this->requestUrl);

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

        if ($result->getHeader('X-Kevinrob-Cache') && $result->getHeader('X-Kevinrob-Cache')[0] === 'HIT') {
            $this->consoleOutput->write(" <info>[Cache {$result->getHeader('X-Kevinrob-Cache')[0]}]</info>");
        } else {
            $this->consoleOutput->write(" <comment>[Cache {$result->getHeader('X-Kevinrob-Cache')[0]}]</comment>");
        }
        $this->consoleOutput->write("\n");

        $csvReader = \League\Csv\Reader::createFromStream(StreamWrapper::getResource($result->getBody(), 'r'));
        if (empty($this->headers)) {
            $csvReader->setHeaderOffset(0);
        }

        foreach ($csvReader->getIterator() as $row) {
            yield (empty($this->headers) ? $row : array_combine($this->headers, $row));
        }
    }
}
