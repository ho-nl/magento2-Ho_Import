<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Mapper;

use Ho\Import\MapperException;

class ProductMapper implements ProductMapperInterface
{

    protected $numberFormatter;

    /**
     * URL Model
     *
     * @var \Magento\Catalog\Model\Product\Url
     */
    private $url;


    /**
     * ProductMapper constructor.
     *
     * @param \Magento\Catalog\Model\Product\Url $url
     */
    public function __construct(
        \Magento\Catalog\Model\Product\Url $url
    ) {
        $this->url = $url;

        //@todo What is the locale of the file of MOB
        $this->numberFormatter = new \NumberFormatter('de_DE', \NumberFormatter::DECIMAL);
    }


    /**
     * {@inheritdoc}
     */
    public function getSku(array $rawProduct)
    {
        if (empty($rawProduct['PRODUCT_NUMBER'])) {
            throw new MapperException('SKU not found for line: '. $rawProduct['SHORT_DESCRIPTION']);
        }

        return $rawProduct['PRODUCT_NUMBER'];
    }


    /**
     * {@inheritdoc}
     */
    public function validateItem(array $rawProduct)
    {
        $errors = [];
        if (isset($rawProduct['GROSS_WEIGHT_UNIT']) && $rawProduct['GROSS_WEIGHT_UNIT'] != 'KG') {
            $errors['GROSS_WEIGHT_UNIT'] = 'Weight Unit is not KG: '.$rawProduct['GROSS_WEIGHT_UNIT'];
        }

        return count($errors) > 0 ? $errors : true;
    }


    /**
     * Get Mapping Definition
     *
     * @return array
     */
    public function getMappingDefinition()
    {
        $sku = function ($rawProduct) {
            return $this->getSku($rawProduct);
        };

        $name = function ($rawProduct) {
            return $rawProduct['LONG_DESCRIPTION'] ?? $rawProduct['SHORT_DESCRIPTION'] ?? null;
        };
        $configurableSku = function ($rawProduct) {
            return $rawProduct['PRODUCT_BASE_NUMBER'] ?? null;
        };

        return [
            'sku'                => $sku,
            'attribute_set_code' => 'Default',
            'product_type'       => 'simple',
            'product_websites'   => 'base',
            'name'               => $name,
            'price'              => '14.0000',
            'url_key'            => function ($rawProduct) use ($sku, $name) {
                return $this->url->formatUrlKey("{$sku($rawProduct)} {$name($rawProduct)}");
            },
            'weight'             => function ($rawProduct) {
                return $this->numberFormatter->parse($rawProduct['GROSS_WEIGHT']);
            },
            'visibility'         => function ($rawProduct) use ($configurableSku) {
                return $configurableSku ? 'Catalog, Search' : 'Search';
            },
            'tax_class_name'     => 'Taxable Goods',
            'product_online'     => '1',
            'short_description'  => null,
            'description'        => '',
            '_configurable_sku'  => $configurableSku
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function mapItem(array $rawProduct) : array
    {
        $product = [];
        foreach ($this->getMappingDefinition() as $key => $value) {
            if (is_callable($value)) {
                $value = $value($rawProduct);
            }

            $product[$key] = $value;
        }

        return $product;
    }
}
