<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Helper\LineFormatterMulti;
use Ho\Import\Logger\Log;
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
     * @var \Closure[]
     */
    protected $configurableValues = [];

    /**
     * Mapping for mapped simples who belong to configurables
     *
     * @var \Closure[]
     */
    protected $simpleValues = [];

    /**
     * @var LineFormatterMulti
     */
    private $lineFormatterMulti;

    /**
     * @var \Closure
     */
    private $splitOnValue;

    /**
     * @var bool
     */
    private $enableFilterConfigurable;

    /**
     * @param ConsoleOutput      $consoleOutput
     * @param LineFormatterMulti $lineFormatterMulti
     * @param \Closure           $configurableSku
     * @param \Closure           $attributes
     * @param \Closure[]         $configurableValues
     * @param \Closure[]         $simpleValues
     * @param Log                $log
     * @param \Closure           $splitOnValue
     * @param bool               $enableFilterConfigurable
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        LineFormatterMulti $lineFormatterMulti,
        $configurableSku,
        $attributes,
        $configurableValues,
        $simpleValues,
        Log $log,
        $splitOnValue = null,
        $enableFilterConfigurable = true
    ) {
        parent::__construct($consoleOutput, $log);
        $this->lineFormatterMulti = $lineFormatterMulti;
        $this->configurableSku = $configurableSku;
        $this->attributes = $attributes;
        $this->configurableValues = $configurableValues;
        $this->simpleValues = $simpleValues;
        $this->splitOnValue = $splitOnValue;
        $this->enableFilterConfigurable = $enableFilterConfigurable;
    }

    /**
     * @todo reduce the Cyclomatic complexity and NPath complexity
     * {@inheritdoc}
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>Creating configurable products...</info>");
        $this->log->addInfo('Creating configurable products...');

        $skuCallback = $this->configurableSku;
        $attrCallback = $this->attributes;
        $configurables = [];

        foreach ($this->items as $identifier => &$item) {
            if (isset($item['product_online']) && $item['product_online'] <= 0) {
                continue;
            }

            $configurableSkus = $skuCallback($item);

            if (! $configurableSkus) {
                continue;
            }

            $configurableSkus = is_array($configurableSkus) ? $configurableSkus : [$configurableSkus];

            foreach ($configurableSkus as $configurableSku) {
                if ($item['product_type'] == 'configurable') {
                    continue 2;
                }

                //Init the configurable
                if (! isset($configurables[$configurableSku])) {
                    $configurables[$configurableSku] = $this->initConfigurable($item, $configurableSku);
                }

                $variation = ['sku' => $identifier];

                //Add the configurable simple to the configurable
                $attributes = $attrCallback($item, $configurableSku);

                foreach ($attributes as $attribute) {
                    $variation[$attribute] = $item[$attribute];
                    unset($configurables[$configurableSku][$attribute]);
                }

                if ($this->splitOnValue) {
                    $split = $this->splitOnValue;
                    $variation['split'] = $split($item);
                }

                $configurables[$configurableSku]['configurable_variations'][] = $variation;
            }
        }
        unset($item);

        $count = count($configurables);
        $this->consoleOutput->writeln("{$count} potential configurables created");
        $this->log->addInfo("{$count} potential configurables created");
        $configurables = $this->splitOnValue($configurables);

        if ($this->enableFilterConfigurable) {
            $configurables = $this->filterConfigurables($configurables);
        }

        $this->setSimpleValues($configurables);

        $configurables = array_map(function ($configurable) {
            $configurable['configurable_variations'] = $this->lineFormatterMulti->encode(
                $configurable['configurable_variations']
            );

            return $configurable;
        }, $configurables);

        $count = count($configurables);
        $this->consoleOutput->writeln("<info>Created {$count} configurables</info>");
        $this->log->addInfo("Created {$count} configurables");

        $this->items = array_replace($this->items, $configurables);
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
        if (isset($this->items[$configurableSku])) {
            return $this->items[$configurableSku];
        }

        $configurable = $item;
        $configurable['sku'] = $configurableSku;
        $configurable['product_type'] = 'configurable';
        $configurable['configurable_variations'] = [];

        //@todo implement the configurable product mapper by using ItemMapper
        foreach ($this->configurableValues as $key => $value) {
            if (\is_callable($value)) {
                $value = $value($configurable);
            }
            $configurable[$key] = $value;
        }
        return $configurable;
    }

    /**
     * Filter all configurables
     * - cleanup all 'empty' configurables.
     *
     * @param array[] $configurables
     * @return array[]
     */
    private function filterConfigurables(array $configurables)
    {
        $count = 0;
        $configurables = array_filter($configurables, function ($item) use (&$count) {
            if (count($item['configurable_variations']) <= 1) {
                $count++;
                return false;
            }
            return true;
        });

        return $configurables;
    }

    /**
     * After creating configurables, split configurables based on this value.
     * This is primarily used to create configurables with a consistent price (split all variations that have
     * a different price).
     *
     * @param array[] $configurables
     * @return array[]
     */
    private function splitOnValue($configurables)
    {
        if (! $this->splitOnValue) {
            return $configurables;
        }

        $newConfigurables = [];
        foreach ($configurables as $identifier => $configurable) {
            $splitConfigurables = [];
            $variations = $configurable['configurable_variations'];
            unset($configurable['configurable_variations']);

            foreach ($variations as $variation) {
                $splitKey = (string) $variation['split'];
                if (!isset($splitConfigurables[$splitKey])) {
                    //Base the new configurable on the first variation
                    $splitConfigurables[$splitKey] =
                        $this->initConfigurable($this->items[$variation['sku']], $identifier);

                    $splitConfigurables[$splitKey]['configurable_variations'] = [];
                }
                unset($variation['split']);
                $splitConfigurables[$splitKey]['configurable_variations'][] = $variation;
            }

            $splitConfigurables = $this->filterConfigurables($splitConfigurables);
            ksort($splitConfigurables);
            $sequence = 0;
            foreach ($splitConfigurables as $splitConfigurable) {
                if (! isset($newConfigurables[$identifier])) {
                    $newConfigurables[$identifier] = $splitConfigurable;
                } else {
                    $newSku = $identifier.'-'.$sequence;
                    $splitConfigurable['sku'] = $newSku;
                    $newConfigurables[$newSku] = $splitConfigurable;
                }
                $sequence++;
            }
        }

        $count = count($newConfigurables) - count($configurables);
        if ($count > 0) {
            $this->consoleOutput->writeln("Created {$count} extra configurables while splitting");
            $this->log->addInfo("Created {$count} extra configurables while splitting");
        }

        return $newConfigurables;
    }

    /**
     * Set the simple product values
     * @param array[] $configurables
     *
     * @return void
     */
    private function setSimpleValues($configurables)
    {
        //modify the simples that are in configurables
        foreach ($configurables as $configurable) {
            foreach ($configurable['configurable_variations'] as $simpleData) {
                $item =& $this->items[$simpleData['sku']];
                //@todo implement the simple product mapper by using ItemMapper
                foreach ($this->simpleValues as $key => $value) {
                    if (\is_callable($value)) {
                        $value = $value($item);
                    }
                    $item[$key] = $value;
                }
            }
        }
    }
}
