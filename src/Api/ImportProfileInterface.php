<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Api;

interface ImportProfileInterface
{
    /**
     * Array of all items
     *
     * @return \[]
     */
    public function getItems();

    public function getProcessedItems(): array;

    /**
     * Array of the config values
     *
     * @return \[]
     */
    public function getConfig();

    /**
     * Run the import
     *
     * @return mixed
     */
    public function run();

    /**
     * @return \Exception
     */
    public function getException();

    /**
     * @return string
     */
    public function getErrors();
}
