<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

/**
 * @deprecated Please use ItemMapper instead of extending ProductMapper
 * @package Ho\Import
 */
abstract class ProductMapper extends AbstractRowModifier
{
    /**
     * Check if the product can be imported
     *
     * @param array $item
     *
     * @return bool|\[] return true if validated, return an array of errors when not valid.
     */
    public function validateItem(array $item)
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
        foreach ($this->getSourceItems() as $item) {
            try {
                $identifier = $this->getSku($item);
                $errors = $this->validateItem($item, $identifier);
                if ($errors === true) {
                    $this->items[$identifier] = array_map(function ($item) {
                        if ($item == \Ho\Import\RowModifier\ItemMapper::FIELD_EMPTY) {
                            return null;
                        }
                        return $item;
                    }, array_filter($this->mapItem($item)));
                } else {
                    $this->consoleOutput->writeln(
                        sprintf("<comment>Error validating, skipping %s: %s</comment>",
                            $identifier, implode(",", $errors))
                    );
                }

            } catch (\Exception $e) {
                $this->consoleOutput->writeln("<error>{$e->getMessage()}</error>");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapItem(array $item) : array
    {
        $product = [];
        foreach ($this->getMappingDefinition() as $key => $value) {
            if (is_callable($value)) {
                $value = $value($item);
            }

            $product[$key] = $value;
        }

        return $product;
    }
}
