<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Base implementation of the RowModifier
 *
 * @package Ho\Import
 */
abstract class AbstractRowModifier
{

    /**
     * Log items to the console.
     *
     * @var ConsoleOutput
     */
    protected $consoleOutput;



    /**
     * Item array with all items to import
     *
     * @var \[]
     */
    protected $items;

    /**
     * AbstractRowModifier constructor.
     *
     * @param ConsoleOutput $consoleOutput
     */
    public function __construct(
        ConsoleOutput $consoleOutput
    ) {
        $this->consoleOutput = $consoleOutput;
    }


    /**
     * Set the data array for fields to import
     *
     * @param \[] &$items
     * @return void
     */
    public function setItems(&$items)
    {
        $this->items =& $items;
    }

    /**
     * Method to process the row data
     *
     * @return void
     */
    abstract public function process();
}
