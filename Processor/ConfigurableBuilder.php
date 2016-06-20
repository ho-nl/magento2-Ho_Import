<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Processor;

use Magento\CatalogImportExport\Model\Import\Product as ImportProduct;
use Magento\ImportExport\Model\Import;

class ConfigurableBuilder extends AbstractProcessor
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
     * {@inheritdoc}
     */
    public function process()
    {
        $skuCallback = $this->configurableSku;
        $attrCallback = $this->attributes;
        $configurables = [];

        foreach ($this->data as &$item) {
            $configurableSku = $skuCallback($item);

            if (! $configurableSku) {
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
            $variation = ['sku' => $item['sku']];
            foreach ($attributes as $attribute) {
                $variation[$attribute] = $item[$attribute];
                unset($configurables[$configurableSku][$attribute]);
            }
            $configurables[$configurableSku]['configurable_variations'][] = $variation;
        }

        //cleanup all 'empty' configurables.
        $configurables = array_filter($configurables, function($item){
            return count($item['configurable_variations']) > 1;
        });

        //modify the simples that are in configurables
        foreach ($configurables as $configurable) {
            foreach ($configurable['configurable_variations'] as $simpleData) {
                $item =& $this->data[$simpleData['sku']];

                foreach ($this->simpleValues as $key => $value) {
                    if (is_callable($value)) {
                        $value = $value($item);
                    }

                    $item[$key] = $value;
                }
            }
        }

        $configurables = array_map(function ($configurable) {
            foreach (['configurable_variations'] as $field) {
                $configurable[$field] = array_map(function ($options) {
                    $newOptions = [];
                    foreach ($options as $key => $value) {
                        $newOptions[] = sprintf('%s=%s', $key, $value);
                    }
                    return implode(Import::DEFAULT_GLOBAL_MULTI_VALUE_SEPARATOR, $newOptions);
                }, $configurable[$field]);
                $configurable[$field] = implode(ImportProduct::PSEUDO_MULTI_LINE_SEPARATOR, $configurable[$field]);
                return $configurable;
            }
        }, $configurables);

        $this->data += $configurables;
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
