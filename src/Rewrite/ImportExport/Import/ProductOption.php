<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Rewrite\ImportExport\Import;

class ProductOption extends \Magento\CatalogImportExport\Model\Import\Product\Option
{
    /**
     * List of specific custom option types
     *
     * @var array
     */
    protected $_specificTypes = [
        'date' => ['price', 'sku'],
        'date_time' => ['price', 'sku'],
        'time' => ['price', 'sku'],
        'field' => ['price', 'sku', 'max_characters'],
        'area' => ['price', 'sku', 'max_characters'],
        'drop_down' => true,
        'radio' => true,
        'checkbox' => true,
        'multiple' => true,
        'file' => ['price', 'sku', 'file_extension', 'image_size_x', 'image_size_y'],
    ];


    /**
     * Retrieve specific type data
     *
     * @param array $rowData
     * @param int $optionTypeId
     * @param bool $defaultStore
     * @return array|false
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _getSpecificTypeData(array $rowData, $optionTypeId, $defaultStore = true)
    {
        if (!empty($rowData[self::COLUMN_ROW_TITLE]) && $defaultStore && empty($rowData[self::COLUMN_STORE])) {
            $valueData = [
                'option_type_id' => $optionTypeId,
                'sort_order' => empty($rowData[self::COLUMN_ROW_SORT]) ? 0 : abs($rowData[self::COLUMN_ROW_SORT]),
                'sku' => !empty($rowData[self::COLUMN_ROW_SKU]) ? $rowData[self::COLUMN_ROW_SKU] : '',
            ];

            $priceData = false;
            if (isset($rowData[self::COLUMN_ROW_PRICE])) {
                $priceData = [
                    'price' => (double)rtrim($rowData[self::COLUMN_ROW_PRICE], '%'),
                    'price_type' => 'fixed',
                ];
                if ('%' == substr($rowData[self::COLUMN_ROW_PRICE], -1)) {
                    $priceData['price_type'] = 'percent';
                }
            }
            return ['value' => $valueData, 'title' => $rowData[self::COLUMN_ROW_TITLE], 'price' => $priceData];
        } elseif (!empty($rowData[self::COLUMN_ROW_TITLE]) && !$defaultStore && !empty($rowData[self::COLUMN_STORE])) {
            return ['title' => $rowData[self::COLUMN_ROW_TITLE]];
        }
        return false;
    }
}
