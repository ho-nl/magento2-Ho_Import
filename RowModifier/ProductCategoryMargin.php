<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Convert cost fields to price fields
 * @todo Add support for options_pricing
 * @package Ho\Import\RowModifier
 */
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

        foreach ($this->items as $identifier => &$item) {
            if (! isset($item['categories'])) {
                $this->consoleOutput->writeln(
                    "<comment>No category found for product {$identifier}, setting cost as price.</comment>"
                );
                $item['price'] = $item['cost'];
                continue;
            }

            $categories = $this->extractCategoriesFromString($item['categories']);
            $margins = [];

            if (! isset($item['cost'])) {
                $this->consoleOutput->writeln(
                    "<comment>Cost not set for product {$identifier}, skipping row.</comment>"
                );
                continue;
            }

            foreach ($categories as $category) {
                $marginCategories = array_filter($this->categoryMapping, function ($marginCategory) use ($category) {
                    return strpos($category, $marginCategory) === 0;
                }, ARRAY_FILTER_USE_KEY);

                if ( ! $marginCategories) {
                    continue;
                }

                $margins[] = end($marginCategories);
            }


            if (count($margins) <= 0) {
                $categoryNames = implode(',', $categories);
                $this->consoleOutput->writeln(
                    "<comment>No margin-category found for product {$identifier}, setting cost as price (categories: " .
                    "{$categoryNames})</comment>"
                );
                $margin = 1;
            } else {
                $margin = (max($margins)) / 100 + 1;
            }

            $item['price'] = round($item['cost'] * $margin, 2);
            if (isset($item['tier_prices'])) {
                $tierPrices = \GuzzleHttp\json_decode($item['tier_prices'], true);

                foreach ($tierPrices as &$tierPrice) {
                    if (! isset($tierPrice['cost'])) {
                        $this->consoleOutput->writeln(
                            "<comment>Cost not set for product {$identifier} tier price, skipping row." .
                            "</comment>"
                        );
                        continue;
                    }
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
        $categoryCollection->setStoreId(0);
        $categoryCollection->addNameToResult();
        $categoryCollection->addAttributeToSelect('product_price_margin');
        $categoryCollection->addAttributeToFilter('product_price_margin', ['gt' => 0]);
        $categoryCollection->addAttributeToSort('level', 'asc');
        $categoryCollection->addAttributeToSort('position', 'asc');

        $this->categoryMapping = [];
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

    /**
     * Remove any surrounding whitespaces
     * Remove empty array values
     *
     * @param string $categories
     * @todo move to helper
     *
     * @return string[] mixed
     */
    protected function extractCategoriesFromString($categories)
    {
        return array_filter(array_map('trim', explode(',', trim($categories))));
    }
}
