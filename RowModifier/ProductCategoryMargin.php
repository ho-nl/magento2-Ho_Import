<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Symfony\Component\Console\Output\ConsoleOutput;

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
     * @param ConsoleOutput             $consoleOutput
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        parent::__construct($consoleOutput);
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Add additional categories to products
     * @return void
     */
    public function process()
    {
        $this->initCategoryMapping();

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


            if (! $margins) {
                $this->consoleOutput->writeln(
                    "<comment>No margin found for product {$item['sku']}, with categories:</comment>"
                );
                $this->consoleOutput->writeln($categories);
            }

            $margin = (max($margins)) / 100 + 1;
            $item['price'] = round($item['cost'] * $margin, 2);
            if (isset($item['tier_prices'])) {
                $tierPrices = \GuzzleHttp\json_decode($item['tier_prices'], true);

                foreach ($tierPrices as &$tierPrice) {
                    $tierPrice['price'] = round($tierPrice['cost'] * $margin, 2);
                }
                $item['tier_prices'] = json_encode($tierPrices);
            }
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
