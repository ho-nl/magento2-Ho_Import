<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Mapper;

use Ho\Import\MapperException;
use Magento\Catalog\Model\Product\Url;

class ProductMapper implements ProductMapperInterface
{

    protected $numberFormatter;

    /**
     * URL Model
     *
     * @var Url
     */
    private $url;


    /**
     * ProductMapper constructor.
     *
     * @param Url $url
     */
    public function __construct(
        Url $url
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
            return preg_replace('/\s+/', ' ', $rawProduct['SHORT_DESCRIPTION']);
        };
        $configurableSku = function ($rawProduct) {
            return $rawProduct['PRODUCT_BASE_NUMBER'] ?? null;
        };

        return [
            'sku'                => $sku,
            'attribute_set_code' => 'Default',
            'categories'         => function ($rawProduct) {
                $category = implode('/', array_filter([
                    isset($rawProduct['CATEGORY_LEVEL_3'])
                        ? mb_convert_case($rawProduct['CATEGORY_LEVEL_1'], MB_CASE_TITLE)
                        : null,
                    isset($rawProduct['CATEGORY_LEVEL_3'])
                        ? mb_convert_case($rawProduct['CATEGORY_LEVEL_2'], MB_CASE_TITLE)
                        : null,
                    isset($rawProduct['CATEGORY_LEVEL_3'])
                        ? mb_convert_case($rawProduct['CATEGORY_LEVEL_3'], MB_CASE_TITLE)
                        : null,
                ]));
                return $category;
            },
            'product_type'       => 'simple',
            'product_websites'   => 'base',
            'name'               => $name,
            'price'              => '14.0000',
            'qty'                => 10,
            'is_in_stock'        => 1,
            'url_key'            => function ($rawProduct) use ($sku, $name) {
                $name = $sku($rawProduct) . ' ' . trim(str_replace($sku($rawProduct), '', $name($rawProduct)));
                return $this->url->formatUrlKey($name);
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
            'image'              => function ($rawProduct) {
                return $rawProduct['IMAGE_URL'];
            },
            'small_image'        => function ($rawProduct) {
                return $rawProduct['IMAGE_URL'];
            },
            'thumbnail'          => function ($rawProduct) {
                return $rawProduct['IMAGE_URL'];
            },
            'swatch_image'          => function ($rawProduct) {
                return $rawProduct['IMAGE_URL'];
            },
            'configurable_sku'   => $configurableSku,
            'color'              => function ($rawProduct) {
                if (!isset($rawProduct['COLOR_DESCRIPTION'])) {
                    return null;
                }
                $color = is_array($rawProduct['COLOR_DESCRIPTION'])
                    ? reset($rawProduct['COLOR_DESCRIPTION'])
                    : $rawProduct['COLOR_DESCRIPTION'];
                return mb_convert_case(mb_strtolower($color), MB_CASE_TITLE);
            },
            'size'               => function ($rawProduct) use ($sku) {
                $skuParts = explode('-', $sku($rawProduct));
                return end($skuParts) != $rawProduct['COLOR_CODE'] ? end($skuParts) : null;
            }
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
