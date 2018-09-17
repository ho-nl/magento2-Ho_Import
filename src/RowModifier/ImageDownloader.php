<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Promise;
use Ho\Import\Logger\Log;
use Magento\Framework\App\Filesystem\DirectoryList;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class AsyncImageDownloader
 *
 * @todo Implement caching strategory. We don't need to download the file each time, but would be nice if it gets
 *       downloaded once in a while.
 * @todo Implement a method to check the image for validity. Currently it just saves the image but it will throw
 *       an error while importing the images.
 * @package Ho\Import
 */
class ImageDownloader extends AbstractRowModifier
{

    /**
     * Console progressbar component.
     *
     * @var ProgressBar
     */
    protected $progressBar;

    /**
     * Gruzzle Http Client
     * @var HttpClient
     */
    private $httpClient;

    /**
     * Get Magento directories to place images.
     *
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * The concurrent target to download
     *
     * @var int
     */
    protected $concurrent = 25;

    /**
     * Array to cache all requests so they don't get downloaded twice
     *
     * @var \GuzzleHttp\Promise\[]
     */
    protected $cachedRequests = [];

    /**
     * @var \Closure[]
     */
    private $cachedReject = [];

    /**
     * Use existing files or redownload alle files
     *
     * @var bool
     */
    protected $useExisting = false;

    /**
     * @param DirectoryList $directoryList
     * @param ConsoleOutput $consoleOutput
     * @param Log           $log
     */
    public function __construct(
        DirectoryList $directoryList,
        ConsoleOutput $consoleOutput,
        Log $log
    ) {
        parent::__construct($consoleOutput, $log);

        $this->directoryList = $directoryList;
        $this->httpClient = new HttpClient();
    }

    /**
     * Actually download the images async
     *
     * @todo Implement the additional fields, those are comma seperated
     * @return void
     */
    public function process()
    {
        $imageFields = ['image', 'small_image', 'thumbnail', 'swatch_image'];
        $imageArrayFields = ['additional_images'];

        $itemCount = count($this->items);
        $this->consoleOutput->writeln("<info>Downloading images for {$itemCount} items</info>");
//        $this->progressBar->start();

        if (! file_exists($this->directoryList->getPath('media') . '/import/')) {
            mkdir($this->directoryList->getPath('media') . '/import/', 0777, true);
        }


        $requestGenerator = function () use ($imageFields, $imageArrayFields) {
            foreach ($this->items as &$item) {
                foreach ($imageFields as $field) {
                    if (!isset($item[$field])) {
                        continue;
                    }

                    if ($promise = $this->downloadAsync($item[$field], $item)) {
                        yield $promise;
                    }
                }
                foreach ($imageArrayFields as $imageArrayField) {
                    if (! isset($item[$imageArrayField])) {
                        continue;
                    }

                    $item[$imageArrayField] = array_unique(explode(',', $item[$imageArrayField]));
                    foreach ($item[$imageArrayField] as &$value) {
                        if ($promise = $this->downloadAsync($value, $item)) {
                            yield $promise;
                        }
                    }
                }
            }
        };

        $pool = new \GuzzleHttp\Pool($this->httpClient, $requestGenerator(), [
            'concurrency' => $this->getConcurrent(),
            'rejected' => function (\GuzzleHttp\Exception\ClientException $reason) {
                $reason->getRequest()->getBody()->close();
                $fileName   = str_replace(' ', '', basename($reason->getRequest()->getUri()));

                $this->cachedReject[$fileName]();
            },
        ]);
        $pool->promise()->wait();

//        $this->progressBar->finish();
        $this->consoleOutput->write("\n");

        //Implode all image array fields
        foreach ($this->items as &$item) {
            foreach ($imageArrayFields as $imageArrayField) {
                if (! isset($item[$imageArrayField])) {
                    continue;
                }

                $item[$imageArrayField] = implode(',', array_filter($item[$imageArrayField]));
            }
        }

        $this->cachedReject = [];
        $this->cachedRequests = [];
    }

    /**
     * Download the actual image async and resolve the to the new value
     *
     * @param string &$value
     * @param array $item
     *
     * @return \Closure|Promise\PromiseInterface
     */
    protected function downloadAsync(&$value, &$item)
    {
        return function () use (&$value, &$item) {
            $fileName   = str_replace(' ', '', basename($value));
            $targetPath = $this->directoryList->getPath('media') . '/import/' . $fileName;

            if (isset($this->cachedRequests[$fileName])) {
                $promise = $this->cachedRequests[$fileName];
            } elseif (file_exists($targetPath)) {
                //@todo honor isUseExisting
                $value = $fileName;
                return null;
            } else {
//                $this->progressBar->advance();
                $promise = $this->httpClient
                    ->getAsync($value, [
                        'sink' => $targetPath,
                        'connect_timeout' => 5
                    ]);
            }

            $promise->then(function (\GuzzleHttp\Psr7\Response $response) use (&$value, $fileName) {
                $response->getBody()->close();
                //@todo check if the image is an actual image, else delete the image.
                $value = $fileName;
            });

            //Save the reject
            $this->cachedReject[$fileName] = function () use (
                &$value,
                &$item,
                $fileName,
                $targetPath
            ) {
                //File already deleted
                if (file_exists($targetPath)) {
                    unlink($targetPath); // clean up any remaining file pointers if the download failed
                    $this->consoleOutput->writeln(
                        "\n<comment>Image can not be downloaded: {$value}</comment>"
                    );
                }

                foreach ($item as &$itemValue) {
                    if ($value == $itemValue) {
                        if (is_array($itemValue)) {
                            foreach ($itemValue as &$itemArrValue) {
                                if ($value == $itemArrValue) {
                                    $itemArrValue = null;
                                }
                            }
                        }
                        $itemValue = null;
                    }
                }
                $value = null;
            };

            return $this->cachedRequests[$fileName] = $promise;
        };
    }

    /**
     * Get the amount of concurrent images downloaded
     *
     * @return int
     */
    public function getConcurrent()
    {
        return $this->concurrent;
    }

    /**
     * Set the amount of concurrent images downloaded
     *
     * @param int $concurrent
     */
    public function setConcurrent(int $concurrent)
    {
        $this->concurrent = $concurrent;
    }

    /**
     * Overwrite existing images or not.
     *
     * @return boolean
     */
    public function isUseExisting()
    {
        return $this->useExisting;
    }

    /**
     * Overwrite existing images or not.
     * @param boolean $useExisting
     * @return void
     */
    public function setUseExisting($useExisting)
    {
        $this->useExisting = (bool) $useExisting;
    }
}
