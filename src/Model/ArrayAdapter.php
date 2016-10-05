<?php

/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Model;
use Magento\ImportExport\Model\Import\AbstractSource;

class ArrayAdapter extends AbstractSource
{
    /**
     * Since the importer requires an instance of AbstractSource we need to reimplement ArrayIterator
     *
     * @var \ArrayIterator
     */
    protected $iterator;

    /**
     * ArrayAdapter constructor.
     * @param \[] &$data
     */
    public function __construct(&$data)
    {
        $this->iterator = new \ArrayIterator($data);
        parent::__construct($this->loadColNames());
    }

    /**
     * Get the next row, not used in \ArrayIterator
     * @throws \Exception
     * @return void
     */
    protected function _getNextRow()
    {
        throw new \Exception('Get Next row not implemented, but required by AbstractSource');
    }

    /**
     * Load all column data from the source file.
     *
     * @return \[]
     */
    protected function loadColNames()
    {
        $keys = [];
        foreach ($this->iterator as $item) {
            foreach (array_keys($item) as $colName) {
                $keys[$colName] = $colName;
            }
        }
        $this->rewind();

        return array_values($keys);
    }

    /**
     * Return the current element
     *
     * @link  http://php.net/manual/en/iterator.current.php
     * @return \[]
     */
    public function current()
    {
        return $this->iterator->current();
    }

    /**
     * Move forward to next element
     *
     * @link  http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->iterator->next();
    }

    /**
     * Return the key of the current element
     *
     * @link  http://php.net/manual/en/iterator.key.php
     * @return string|int|null
     */
    public function key()
    {
        return $this->iterator->key();
    }

    /**
     * Checks if current position is valid
     *
     * @link  http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     *        Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->iterator->valid();
    }

    /**
     * Rewind the Iterator to the first element
     *
     * @link  http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->iterator->rewind();
    }

    /**
     * Seeks to a position
     *
     * @param int $position The position to seek to.
     * @link  http://php.net/manual/en/seekableiterator.seek.php
     * @return void
     */
    public function seek($position)
    {
        $this->iterator->seek($position);
    }
}
