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
     * @todo reduce the Cyclomatic complexity and NPath complexity
     * {@inheritdoc}
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>Creating configurable products... </info>");

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
                $configurables[$configurableSku] = $this->initConfigurable($item, $configurableSku);
            }

            //Add the configurable simple to the configurable
            $attributes = $attrCallback($item);
            $variation = ['sku' => $identifier];
            foreach ($attributes as $attribute) {
                $variation[$attribute] = $item[$attribute];
                unset($configurables[$configurableSku][$attribute]);
            }


            $configurables[$configurableSku]['configurable_variations'][] = $variation;
        }

        $configurables = $this->filterConfigurables($configurables);

        //modify the simples that are in configurables
        foreach ($configurables as $configurable) {
            foreach ($configurable['configurable_variations'] as $simpleData) {
                $item =& $this->items[$simpleData['sku']];

                //@todo implement the simple product mapper by using ItemMapper
                foreach ($this->simpleValues as $key => $value) {
                    if (is_callable($value)) {
                        $value = $value($item);
                    }

                    $item[$key] = $value;
                }
            }
        }

        $configurables = array_map(function ($configurable) {
            $configurable['configurable_variations'] = $this->lineFormatterMulti->encode(
                $configurable['configurable_variations']
            );

            return $configurable;
        }, $configurables);

        $configCount = count($configurables);

        $this->consoleOutput->writeln("<info>Configurable products created: {$configCount}</info>");
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

    /**
     * Init a configurable product
     * @param string[] $item
     * @param string $configurableSku
     *
     * @return string[]
     */
    private function initConfigurable($item, $configurableSku)
    {
        $configurable = $item;
        $configurable['sku'] = $configurableSku;
        $configurable['product_type'] = 'configurable';
        $configurable['configurable_variations'] = [];

        //@todo implement the configurable product mapper by using ItemMapper
        foreach ($this->configurableValues as $key => $value) {
            if (is_callable($value)) {
                $value = $value($configurable);
            }
            $configurable[$key] = $value;
        }
        return $configurable;
    }

    /**
     * Filter all configurables
     * - Remove all configurables that have a price diference.
     * - cleanup all 'empty' configurables.
     *
     * @param array[] $configurables
     * @return array[]
     */
    private function filterConfigurables(array $configurables)
    {
        return array_filter($configurables, function ($item) {
            if (count($item['configurable_variations']) <= 1) {
//                $this->consoleOutput->writeln(
//                    "<comment>Configurable {$item['sku']} not created: Only 1 simple found</comment>"
//                );
                return false;
            }
            return true;
        });
    }
}
