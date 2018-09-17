<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\RowModifier;

use Closure;
use Ho\Import\Logger\Log;
use Iterator;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * SourceIterator
 *
 * Create a new object with SourceIteratorFactory see constructor for options. Example:
 *
 * ```PHP
 * $sourceIterator = $this->sourceIteratorFactory->create([
 *     'identifier' => function ($printInfo) {
 *         return $printInfo['PRODUCT_KEY'];
 *     },
 *     'mode' => 'create',
 *     'iterator' => $myIterator
 * ]);
 * ```
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
    private $identifier;

    /**
     * The fow Iterator
     *
     * @var Iterator
     */
    private $iterator;

    /**
     * @var string
     */
    private $mode;

    /**
     * SourceIterator constructor.
     *
     * @param ConsoleOutput $consoleOutput
     * @param Closure       $identifier  Anonymous function (http://php.net/manual/en/functions.anonymous.php)
     *                                   to identify the parent. Can return a String or Array.
     *                                   First argument of the \Closure will be the item
     * @param Iterator      $iterator    \Ho\Import\Streamer\FileXml or \Ho\Import\Streamer\HttpXml
     * @param Log           $log
     * @param string        $mode        self::MODE_ADD or self::MODE_CREATE
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        Closure $identifier,
        Iterator $iterator,
        Log $log,
        $mode = self::MODE_CREATE
    ) {
        parent::__construct($consoleOutput, $log);

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
        $this->consoleOutput->writeln("SourceIterator: <info>Adding information from stream</info>");
        $this->log->addInfo('SourceIterator: Adding information from stream');

        $identifier = $this->identifier;
        foreach ($this->iterator as $item) {
            $ids = $identifier($item);
            $ids = \is_array($ids) ? $ids : [$ids];

            foreach ($ids as $id) {
                switch ($this->mode) {
                    case self::MODE_CREATE:
                        if (!isset($this->items[$id])) {
                            $this->items[$id] = [];
                        }
                        $this->items[$id] += $item;

                        break;
                    case self::MODE_ADD:
                        if (!isset($this->items[$id])) {
                            $this->consoleOutput->writeln(
                                "SourceIterator: <comment>Item not found for {$id}</comment>"
                            );
                            $this->log->addInfo("SourceIterator: Item not found for {$id}");

                            continue;
                        }
                        $this->items[$id] += $item;
                        break;
                }
            }
        }
    }
}
