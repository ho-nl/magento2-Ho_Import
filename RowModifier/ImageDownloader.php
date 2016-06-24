<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Promise;
use Magento\Framework\App\Filesystem\DirectoryList;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class AsyncImageDownloader
 *
 * @todo implement Guzzle request Pool http://docs.guzzlephp.org/en/latest/quickstart.html#concurrent-requests
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
    protected $concurrent = 10;

    /**
     * Array to cache all requests so they don't get downloaded twice
     *
     * @var \[]
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
     * @param HttpClient    $httpClient
     * @param ConsoleOutput $consoleOutput
     */
    public function __construct(
        DirectoryList $directoryList,
        HttpClient $httpClient,
        ConsoleOutput $consoleOutput
    ) {
        parent::__construct($consoleOutput);
        $this->directoryList = $directoryList;
        $this->httpClient    = $httpClient;
        $this->progressBar   = new ProgressBar($this->consoleOutput);
    }

    /**
     * Actually download the images async
     *
     * @todo Implement the additional fields, those are comma seperated
     * @return void
     */
    public function process()
    {
        $imageFields = ['swatch_image','image', 'small_image', 'thumbnail'];
        //$additionalFields = 'additional_images';

        $itemCount = count($this->items);
        $this->consoleOutput->writeln("<info>Downloading images for {$itemCount} items</info>");
        $this->progressBar->start();

        $promises = [];

        $count = 0;
        $totalCount = 0;
        foreach ($this->items as &$item) {
            foreach ($imageFields as $field) {
                if (!isset($item[$field])) {
                    continue;
                }

                if ($promise = $this->downloadAsync($item, $field)) {
                    $promises[] = $promise;
                }
                $count++;
                $totalCount++;
            }

            if ($count >= $this->getConcurrent()) {
                Promise\settle($promises)->wait();
                $promises = [];
                $count = 0;
            }
        }

        $this->progressBar->finish();
        $this->consoleOutput->write("\n");
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

        if (isset($this->cachedRequests[$fileName])) {
            $promise = $this->cachedRequests[$fileName];
        } elseif (file_exists($targetPath)) { //@todo honor isUseExisting
            $item[$field] = $fileName;
            return null;
        } else {
            $this->progressBar->advance();
            $promise = $this->httpClient
                ->getAsync($item[$field], [
                    'sink' => $targetPath,
                    'connect_timeout' => 5
                ]);
        }

        $promise
            ->then(function () use (&$item, $field, $fileName) {
                $item[$field] = $fileName;
            })
            ->otherwise(function () use (&$item, $field, $fileName, $targetPath) {
                unlink($targetPath); // clean up any remaining file pointers if the download failed

                $this->consoleOutput->writeln(
                    "\n<comment>Image can not be downloaded: {$fileName} for {$item['sku']}</comment>"
                );

                foreach ($item as $keyField => $value) {
                    if ($value == $item[$field]) {
                        $item[$keyField] = null;
                    }
                }
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
