<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Api\ProductMapperInterface;

/**
 * @package Ho\Import
 */
abstract class ProductMapper extends AbstractRowModifier
    implements ProductMapperInterface
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
                $sku = $this->getSku($item);

                if (($errors = $this->validateItem($item)) !== true) {
                    $this->consoleOutput->writeln($errors);
                }
                $this->items[$sku] = $this->mapItem($item);
            } catch (\Exception $e) {
                $this->consoleOutput->writeln($e->getMessage());
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
