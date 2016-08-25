<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Helper\LineFormatterMulti;
use Magento\CatalogImportExport\Model\Import\Product as ImportProduct;
use Magento\ImportExport\Model\Import;
use Symfony\Component\Console\Output\ConsoleOutput;

class ConfigurableBuilder extends AbstractRowModifier
{

    /**
     * Method to retrieve the configurable SKU
     *
     * @var \Closure
     */
    protected $configurableSku;

    /**
     * Method to retrieve
     *
     * @var \Closure
     */
    protected $attributes;


    /**
     * Mapping for additional configurable values
     *
     * @var array
     */
    protected $configurableValues = [];


    /**
     * Mapping for mapped simples who belong to configurables
     *
     * @var array
     */
    protected $simpleValues = [];

    /**
     * @var LineFormatterMulti
     */
    private $lineFormatterMulti;

    /**
     * ConfigurableBuilder constructor.
     *
     * @param ConsoleOutput      $consoleOutput
     * @param LineFormatterMulti $lineFormatterMulti
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        LineFormatterMulti $lineFormatterMulti
    ) {
        parent::__construct($consoleOutput);
        $this->lineFormatterMulti = $lineFormatterMulti;
    }

    /**
     * {@inheritdoc}
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>Creating configurable products</info>");

        $skuCallback = $this->configurableSku;
        $attrCallback = $this->attributes;
        $configurables = [];

        foreach ($this->items as $identifier => &$item) {
            $configurableSku = $skuCallback($item);

            if (! $configurableSku) {
                continue;
            }

            if (isset($item['product_online']) && $item['product_online'] <= 0) {
                continue;
            }

            //Init the configurable
            if (! isset($configurables[$configurableSku])) {
                $configurables[$configurableSku] = $item;
                $configurables[$configurableSku]['sku'] = $configurableSku;
                $configurables[$configurableSku]['product_type'] = 'configurable';
                $configurables[$configurableSku]['configurable_variations'] = [];

                foreach ($this->configurableValues as $key => $value) {
                    if (is_callable($value)) {
                        $value = $value($configurables[$configurableSku]);
                    }

                    $configurables[$configurableSku][$key] = $value;
                }
            }

            //Add the configurable simple to the configurable
            $attributes = $attrCallback($item);
            $variation = ['sku' => $identifier];
            foreach ($attributes as $attribute) {
                $variation[$attribute] = $item[$attribute];
                unset($configurables[$configurableSku][$attribute]);
            }

            if (! isset($configurables[$configurableSku]['_is_in_stock'])) {
                $configurables[$configurableSku]['_is_in_stock'] = 0;
            }
            if (isset($item['is_in_stock']) && $item['is_in_stock'] > 0) {
                $configurables[$configurableSku]['_is_in_stock'] = $item['is_in_stock'];
            }

            $configurables[$configurableSku]['configurable_variations'][] = $variation;
        }

        //cleanup all 'empty' configurables.
        $configurables = array_filter($configurables, function ($item) {
            return count($item['configurable_variations']) > 1;
        });

        //modify the simples that are in configurables
        foreach ($configurables as $configurable) {
            foreach ($configurable['configurable_variations'] as $simpleData) {
                $item =& $this->items[$simpleData['sku']];

                foreach ($this->simpleValues as $key => $value) {
                    if (is_callable($value)) {
                        $value = $value($item);
                    }

                    $item[$key] = $value;
                }
            }
        }

        $configurables = array_map(function ($configurable) {
            $configurable['is_in_stock'] = $configurable['_is_in_stock'];
            unset($configurable['_is_in_stock']);

            $configurable['configurable_variations'] = $this->lineFormatterMulti->encode(
                $configurable['configurable_variations']
            );

            return $configurable;
        }, $configurables);

        $this->items += $configurables;
    }


    /**
     * Set the method to retrieve the configurable SKU
     *
     * @param \Closure $configurableSku
     */
    public function setConfigurableSku(\Closure $configurableSku)
    {
        $this->configurableSku = $configurableSku;
    }


    /**
     * Set the method to retrieve the new attributes
     *
     * @param \Closure $attributes
     */
    public function setAttributes(\Closure $attributes)
    {
        $this->attributes = $attributes;
    }


    /**
     * Set mapping for additional configurable values
     *
     * @param array $configurableValues
     */
    public function setConfigurableValues(array $configurableValues)
    {
        $this->configurableValues = $configurableValues;
    }


    /**
     * Mapping for mapped simples who belong to configurables
     *
     * @param array $simpleValues
     */
    public function setSimpleValues(array $simpleValues)
    {
        $this->simpleValues = $simpleValues;
    }
}
