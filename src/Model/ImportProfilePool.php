<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Model;

use Ho\Import\Api\ImportProfilePoolInterface;

/**
 * Class CommandList has a list of commands, which can be extended via DI configuration.
 */
class ImportProfilePool implements ImportProfilePoolInterface
{
    /**
     * List of available import profiles
     *
     * @var \Ho\Import\Api\ImportProfileInterface[]
     */
    protected $profiles;

    /**
     * Constructor
     *
     * @param \Ho\Import\Api\ImportProfileInterface[]|\[] $profiles
     */
    public function __construct(array $profiles = [])
    {
        $this->profiles = $profiles;
    }

    /**
     * {@inheritdoc}
     */
    public function getProfiles()
    {
        return $this->profiles;
    }
}
