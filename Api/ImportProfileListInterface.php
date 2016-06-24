<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Api;

interface ImportProfileListInterface
{
    /**
     * Constructor
     *
     * @param \Ho\Import\Api\ImportProfileInterface[]|\[] $profiles
     */
    public function __construct(array $profiles = []);

    /**
     * Array of all items
     *
     * @return ImportProfileInterface[]
     */
    public function getProfiles();
}
