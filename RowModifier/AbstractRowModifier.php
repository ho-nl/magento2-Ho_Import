<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;
/**
 * @package Ho\Import
 */
abstract class AbstractRowModifier
{
    /**
     * Item array with all items to import
     *
     * @var array
     */
    protected $items;


    /**
     * Set the data array for fields to import
     *
     * @param array &$items
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
