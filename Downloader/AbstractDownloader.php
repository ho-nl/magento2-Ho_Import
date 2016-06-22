<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Downloader;

use Magento\Framework\App\Filesystem\DirectoryList;

class AbstractDownloader
{

    /**
     * @var DirectoryList
     */
    private $directoryList;


    /**
     * AbstractDownloader constructor.
     *
     * @param DirectoryList $directoryList
     */
    public function __construct(
        DirectoryList $directoryList
    ) {
        $this->directoryList = $directoryList;
    }


    /**
     * @param string $folder
     * @param string $filename
     *
     * @return string
     */
    protected function getTargetPath(string $folder, string $filename = '')
    {
        return $this->directoryList->getRoot() . '/' . trim($folder, '/') . '/' . trim($filename, '/');
    }
}
