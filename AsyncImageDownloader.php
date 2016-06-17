<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Class AsyncImageDownloader
 *
 * @todo implement Guzzle request Pool http://docs.guzzlephp.org/en/latest/quickstart.html#concurrent-requests
 * @package Ho\Import
 */
class AsyncImageDownloader
{
    /**
     * @var array
     */
    protected $data;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * The concurrent target to download
     *
     * @var int
     */
    protected $concurrent = 10;

    /**
     * Array to cache all requests so they don't get downloaded twice
     *
     * @var array
     */
    protected $cachedRequests = [];

    /**
     * Use existing files or redownload alle files
     *
     * @var bool
     */
    protected $useExisting = false;


    /**
     * AsyncImageDownloader constructor.
     *
     * @param DirectoryList $directoryList
     * @param Client        $client
     */
    public function __construct(
        DirectoryList $directoryList,
        Client $client
    ) {
        $this->directoryList = $directoryList;
        $this->client = $client;
    }


    /**
     * Set the data array for fields to import
     *
     * @param $data
     */
    public function setData(&$data)
    {
        $this->data =& $data;
    }

    /**
     * Actually download the images async
     *
     * @todo Implement the additional fields, those are comma seperated
     * @return void
     */
    public function download()
    {
        $imageFields = ['swatch_image','image', 'small_image', 'thumbnail'];
        //$additionalFields = 'additional_images';

        $promises = [];

        $count = 0;
        foreach ($this->data as &$item) {
            foreach ($imageFields as $field) {
                if (!isset($item[$field])) {
                    continue;
                }
                if ($promise = $this->downloadAsync($item, $field)) {
                    $promises[] = $promise;
                }
                $count++;
            }

            if ($count >= $this->getConcurrent()) {
                Promise\settle($promises)->wait();
                $promises = [];
                $count = 0;
            }
        }
    }


    /**
     * Download the actual image async and resolve the to the new value
     *
     * @param array &$item
     * @param string $field
     *
     * @return Promise\PromiseInterface|null
     */
    protected function downloadAsync(&$item, $field)
    {
        $fileName   = basename($item[$field]);
        $targetPath = $this->directoryList->getPath('media') . '/import/' . $fileName;

        if (file_exists($targetPath)) {
            $item[$field] = $fileName;
            return null;
        } elseif (isset($this->cachedRequests[$fileName])) {
            $promise = $this->cachedRequests[$fileName];
        } else {
            $promise = $this->client
                ->getAsync($item[$field], [
                    'sink' => $targetPath,
                    'connect_timeout' => 5
                ]);
        }

        $promise
            ->then(function () use (&$item, $field, $fileName) {
                $item[$field] = $fileName;
            })
            ->otherwise(function () use (&$item, $field, $targetPath) {
                unlink($targetPath); // clean up any remaining file pointers if the download failed
                $item[$field] = null;
            });

        return $this->cachedRequests[$fileName] = $promise;
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
     * @return boolean
     */
    public function isUseExisting()
    {
        return $this->useExisting;
    }


    /**
     * @param boolean $useExisting
     */
    public function setUseExisting($useExisting)
    {
        $this->useExisting = (bool) $useExisting;
    }
}
