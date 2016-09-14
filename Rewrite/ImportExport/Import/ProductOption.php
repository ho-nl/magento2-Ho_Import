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
}
