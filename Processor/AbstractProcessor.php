<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Processor;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * @package Ho\Import
 */
abstract class AbstractProcessor
{
    /**
     * @var array
     */
    protected $data;


    /**
     * Set the data array for fields to import
     *
     * @param array &$data
     */
    public function setData(&$data)
    {
        $this->data =& $data;
    }

    /**
     * @return void
     */
    abstract public function process();
}
