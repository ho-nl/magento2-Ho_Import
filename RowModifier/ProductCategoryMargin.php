<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class ProductCategoryMargin extends AbstractRowModifier
{
    /**
     * Mapping from category to internal mapping.
     * @var null
     */
    protected $categoryMapping = null;

    /**
     * Category Collection Factory
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;


    /**
     * ExternalCategoryManagement constructor.
     *
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Add additional categories to products
     * @return void
     */
    public function process()
    {
        $this->initCategoryMapping();

//        var_dump($this->categoryMapping);exit;

        foreach ($this->items as &$item) {
            $categories = explode(',', $item['categories']);
            $margins = [];

            foreach ($categories as $category) {
                $marginCategories = array_filter($this->categoryMapping, function ($marginCategory) use ($category) {
                    return strpos($category, $marginCategory) === 0;
                }, ARRAY_FILTER_USE_KEY);

                if ( ! $marginCategories) {
                    continue;
                }

                $margins[] = end($marginCategories);
            }
            
            $margin = max($margins);
            $item['price'] = round($item['cost'] * $margin, 2);
            var_dump($item['tier_prices']);exit;
        }
    }


    /**
     * Load Magento's categories with the import_group
     * @return \[]
     */
    protected function initCategoryMapping()
    {
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addNameToResult();
        $categoryCollection->addAttributeToSelect('product_price_margin');
        $categoryCollection->addAttributeToSort('level', 'asc');
        $categoryCollection->addAttributeToSort('position', 'asc');

        foreach ($categoryCollection as $category) {
            /** @var Category $category */

            if (! $category->getData('product_price_margin')) {
                continue;
            }

            $structure = explode('/', $category->getPath());
            $pathSize = count($structure);
            if ($pathSize > 1) {
                $path = [];
                for ($i = 1; $i < $pathSize; $i++) {
                    $item = $categoryCollection->getItemById($structure[$i]);
                    if ($item instanceof Category) {
                        $path[] = $item->getName();
                    }
                }

                $this->categoryMapping[implode('/', $path)] = $category->getData('product_price_margin');
            }
        }

        return $this->categoryMapping;
    }
}
