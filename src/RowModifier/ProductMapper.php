<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
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
        $this->consoleOutput->writeln("<info>Mapping product information</info>");
        $this->log->addInfo('Mapping product information');

        foreach ($this->getSourceItems() as $item) {
            try {
                $identifier = $this->getSku($item);
                $errors = $this->validateItem($item, $identifier);
                if ($errors === true) {
                    $filteredItem = array_filter($this->mapItem($item), function ($value) {
                        return $value !== null;
                    });

                    $this->items[$identifier] = array_map(function ($item) {
                        if ($item == \Ho\Import\RowModifier\ItemMapper::FIELD_EMPTY) {
                            return null;
                        }
                        return $item;
                    }, $filteredItem);
                } else {
                    $output = sprintf('Error validating, skipping %s: %s', $identifier, implode(',', $errors));

                    $this->consoleOutput->writeln(sprintf('<comment>%s</comment>', $output));
                    $this->log->addInfo($output);
                }

            } catch (\Exception $e) {
                $this->consoleOutput->writeln("<error>{$e->getMessage()}</error>");
                $this->log->addError($e->getMessage());
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
            if (\is_callable($value)) {
                $value = $value($item);
            }

            $product[$key] = $value;
        }

        return $product;
    }
}
