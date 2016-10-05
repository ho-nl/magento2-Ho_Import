<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\RowModifier;

use Closure;
use Iterator;
use Symfony\Component\Console\Output\ConsoleOutput;

class NonUniqueSourceIterator extends SourceIterator
{

    /**
     * Node name where the item will be added
     * @var string
     */
    private $nodeName;

    /**
     * NonUniqueSourceIterator constructor.
     *
     * @param ConsoleOutput $consoleOutput
     * @param Closure       $identifier
     * @param Iterator      $iterator
     * @param string        $nodeName
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        Closure $identifier,
        Iterator $iterator,
        $nodeName
    ) {
        parent::__construct($consoleOutput, $identifier, $iterator);
        $this->nodeName = $nodeName;
    }

    /**
     * Load the source into the array
     *
     * @return void
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>NonUniqueSourceIterator: Adding information from stream</info>");

        $identifier = $this->identifier;
        foreach ($this->iterator as $item) {
            $id = $identifier($item);
            if (!isset($this->items[$id])) {
                $this->consoleOutput->writeln("<comment>NonUniqueSourceIterator: Item not found for {$id}</comment>");
                continue;
            }
            $this->items[$id][$this->nodeName][] = $item;
        }
    }
}
