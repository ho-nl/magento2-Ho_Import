<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\RowModifier;

use Closure;
use Iterator;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class SourceIterator
 * 
 * @package Ho\Import\RowModifier
 */
class SourceIterator extends AbstractRowModifier
{
    const MODE_CREATE = 'create';
    const MODE_ADD = 'add';

    /**
     * The row Identifier
     *
     * @var Closure
     */
    protected $identifier;

    /**
     * The fow Iterator
     *
     * @var Iterator
     */
    protected $iterator;

    /**
     * @var string
     */
    protected $mode;

    /**
     * SourceIterator constructor.
     *
     * @param ConsoleOutput $consoleOutput
     * @param Closure       $identifier
     * @param Iterator      $iterator
     * @param string        $mode
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        Closure $identifier,
        Iterator $iterator,
        $mode = self::MODE_CREATE
    ) {
        parent::__construct($consoleOutput);
        $this->identifier = $identifier;
        $this->iterator = $iterator;
        $this->mode = $mode;
    }

    /**
     * Load the source into the array
     *
     * @return void
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>SourceIterator: Adding information from stream</info>");

        $identifier = $this->identifier;
        foreach ($this->iterator as $item) {
            $id = $identifier($item);

            switch ($this->mode) {
                case self::MODE_CREATE:
                    if (!isset($this->items[$id])) {
                        $this->items[$id] = [];
                    }
                    $this->items[$id] += $item;

                    break;
                case self::MODE_ADD:
                    if (!isset($this->items[$id])) {
                        $this->consoleOutput->writeln("<comment>SourceIterator: Item not found for {$id}</comment>");
                        continue;
                    }
                    $this->items[$id] += $item;
                    break;
            }
        }
    }

    /**
     * Returns a generator of the data instead of processing the items
     * @return \Generator
     */
    public function generator()
    {
        $identifier = $this->identifier;
        foreach ($this->iterator as $item) {
            yield [$identifier($item), $item];
        }
    }
}
