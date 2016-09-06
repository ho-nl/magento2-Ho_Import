<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

/**
 * @todo Make it work with a factory so that we dont have to explicitly call setMappingDefinition but use a constructor
 *       argument
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
    protected $mappingDefinition;


    /**
     * Check if the product can be imported
     *
     * @param array $item
     * @param string $sku
     *
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

        foreach ($this->items as $identifier => $item) {
            try {
                $errors = $this->validateItem($item, $identifier);
                if ($errors === true) {
                    $filteredItem = array_filter($this->mapItem($item), function ($value) {
                        return $value !== null;
                    });

                    $this->items[$identifier] = array_map(function ($value) use ($identifier, $filteredItem) {
                        if ($value === \Ho\Import\RowModifier\ItemMapper::FIELD_EMPTY) {
                            return null;
                        }

                        if (is_array($value) || is_object($value)) {
                            $val = print_r($filteredItem, true);
                            $this->consoleOutput->writeln(
                                "<comment>Value is a object or array for $identifier: {$val}</comment>"
                            );
                            return null;
                        }

                        return $value;
                    }, $filteredItem);
                } else {
                    $this->consoleOutput->writeln(
                        sprintf("<comment>Error validating, skipping %s: %s</comment>",
                            $identifier, implode(",", $errors))
                    );
                }

            } catch (\Exception $e) {
                $this->consoleOutput->writeln("<error>ItemMapper: {$e->getMessage()} (removing product)</error>");
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
        foreach ($this->mappingDefinition as $key => $value) {
            if (is_callable($value)) {
                $value = $value($item);
            }

            $product[$key] = $value;
        }

        return $product;
    }


    /**
     * Set the mapping definition of the product.
     * @param \Closure[]|string[] $mappingDefinition
     * @return void
     */
    public function setMappingDefinition($mappingDefinition)
    {
        $this->mappingDefinition = $mappingDefinition;
    }
}
