<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Logger\Log;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * @todo Make it work with a factory so that we dont have to explicitly call setMappingDefinition but use a constructor
 *       argument
 *
 * @todo Support new way to declare values. This allows us to
 * new Value($bla, [
 *     'store_1' => 1,
 *     'store_2' => 2,
 *     'store_3' => 3
 * ]);
 *
 * @package Ho\Import
 */
class ItemMapper extends AbstractRowModifier
{
    const FIELD_EMPTY = 'field-empty';

    /**
     * Mapping Definition
     * @var \Closure[]|string[]
     */
    private $mapping;

    /**
     * @param ConsoleOutput $consoleOutput
     * @param Log           $log
     * @param array         $mapping
     */
    public function __construct(ConsoleOutput $consoleOutput, Log $log, array $mapping = [])
    {
        parent::__construct($consoleOutput, $log);

        $this->mapping = $mapping;
    }

    /**
     * Check if the product can be imported
     *
     * @param array $item
     * @param string $sku
     *
     * @todo Remove or implement from the constructor, serves no puprose right now.
     * @return bool|\string[] return true if validated, return an array of errors when not valid.
     */
    public function validateItem(array $item, $sku)
    {
        return true;
    }

    /**
     * Retrieve the data.
     *
     * @return void
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>Mapping item information</info>");
        $this->log->addInfo('Mapping item information');

        foreach ($this->items as $identifier => $item) {
            try {
                $itemsValidated = $this->validateItem($item, $identifier);
                if ($itemsValidated === true) {
                    $filteredItem = array_filter($this->mapItem($item), function ($value) {
                        return $value !== null;
                    });

                    $this->items[$identifier] = array_map(function ($value) use ($identifier, $filteredItem) {
                        if ($value === \Ho\Import\RowModifier\ItemMapper::FIELD_EMPTY) {
                            return null;
                        }

                        if (\is_array($value) || \is_object($value)) {
                            $val = print_r($filteredItem, true);
                            $this->consoleOutput->writeln(
                                "<comment>Value is a object or array for $identifier: {$val}</comment>"
                            );
                            $this->log->addInfo("Value is a object or array for $identifier: {$val}");

                            return null;
                        }

                        return $value;
                    }, $filteredItem);
                } else {
                    $output = sprintf('Error validating, skipping %s: %s', $identifier, implode(',', $itemsValidated));

                    $this->consoleOutput->writeln(sprintf('<comment>%s</comment>', $output));
                    $this->log->addError($output);
                }

            } catch (\Exception $e) {
                $this->consoleOutput->writeln(
                    "<error>ItemMapper: {$e->getMessage()} (removing product {$identifier})</error>"
                );
                $this->log->addError("ItemMapper: {$e->getMessage()} (removing product {$identifier})");

                unset($this->items[$identifier]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapItem(array $item) : array
    {
        $product = [];
        foreach ($this->mapping as $key => $value) {
            if (\is_callable($value)) {
                $value = $value($item);
            }

            $product[$key] = $value;
        }

        return $product;
    }

    /**
     * Set the mapping definition of the product.
     *
     * @param \Closure[]|string[] $mapping
     *
     * @deprecated Please set the mapping defintion with the ItemMapperFactory
     * @return void
     */
    public function setMappingDefinition($mapping)
    {
        $this->mapping = $mapping;
    }
}
