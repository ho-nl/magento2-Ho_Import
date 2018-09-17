<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\RowModifier;

use Ho\Import\Logger\Log;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * NonUniqueSourceIterator
 *
 * Create a new object with NonUniqueSourceIteratorFactory see constructor for options. Example:
 *
 * ```PHP
 * $sourceIterator = $this->nonUniqueSourceIteratorFactory->create([
 *     'nodeName' => 'printing',
 *     'identifier' => function ($printInfo) {
 *         return $printInfo['PRODUCT_KEY'];
 *     },
 *     'iterator' => $myIterator
 * ]);
 * ```
 *
 * @package Ho\Import\RowModifier
 */
class NonUniqueSourceIterator extends AbstractRowModifier
{

    /**
     * Node name where the item will be added
     * @var string
     */
    private $nodeName;

    /**
     * @var \Closure
     */
    private $identifier;

    /**
     * @var \Iterator
     */
    private $iterator;

    /**
     * NonUniqueSourceIterator
     *
     * @param ConsoleOutput $consoleOutput
     * @param \Closure      $identifier  Anonymous function (http://php.net/manual/en/functions.anonymous.php)
     *                                   to identify the parent. Can return a String or Array.
     *                                   First argument of the \Closure will be the $item
     * @param \Iterator     $iterator    \Ho\Import\Streamer\FileXml or \Ho\Import\Streamer\HttpXml
     * @param string        $nodeName    Array key where the data will be stored in the parent
     * @param Log           $log
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        \Closure $identifier,
        \Iterator $iterator,
        $nodeName,
        Log $log
    ) {
        parent::__construct($consoleOutput, $log);

        $this->identifier = $identifier;
        $this->iterator = $iterator;
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
        $this->log->addInfo('NonUniqueSourceIterator: Adding information from stream');

        $identifier = $this->identifier;
        foreach ($this->iterator as $item) {
            $ids = $identifier($item);
            $ids = is_array($ids) ? $ids : [$ids];

            foreach ($ids as $id) {
                if (!isset($this->items[$id])) {
                    $this->consoleOutput->writeln(
                        "NonUniqueSourceIterator: <comment>Item not found for {$id}</comment>"
                    );
                    $this->log->addInfo("NonUniqueSourceIterator: Item not found for {$id}");

                    continue;
                }
                $this->items[$id][$this->nodeName][] = $item;
            }
        }
    }
}
