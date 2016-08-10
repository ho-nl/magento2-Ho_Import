<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Helper\LineFormatterMulti;
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
    private $categoryMapping = null;

    /**
     * Category Collection Factory
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var LineFormatterMulti
     */
    private $lineFormatterMulti;

    /**
     * ExternalCategoryManagement constructor.
     *
     * @param ConsoleOutput             $consoleOutput
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param LineFormatterMulti        $lineFormatterMulti
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        CategoryCollectionFactory $categoryCollectionFactory,
        LineFormatterMulti $lineFormatterMulti
    ) {
        parent::__construct($consoleOutput);
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->lineFormatterMulti = $lineFormatterMulti;
    }

    /**
     * Add additional categories to products
     * @return void
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>Calculating product margins</info>");

        $this->initCategoryMapping();
        $scope = 'ProductCategoryMargin';

        foreach ($this->items as $identifier => &$item) {
            if (empty($item['cost'])) {
                $this->consoleOutput->writeln(
                    "<comment>{$scope}: No cost field found for product {$identifier}, disabling product.</comment>"
                );
                $item['status'] = 0;
                $item['product_online'] = 0;
                continue;
            }

            if (empty($item['categories'])) {
                $this->consoleOutput->writeln(
                    "<comment>{$scope}: No category found for product {$identifier}, setting cost as price.</comment>"
                );
                $item['price'] = $item['cost'];
                continue;
            }

            $categories = $this->extractCategoriesFromString($item['categories']);
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

            if (count($margins) <= 0) {
                $categoryNames = implode(',', $categories);
                $this->consoleOutput->writeln(
                    "<comment>{$scope}: No margin-category found for product {$identifier}, setting cost as price (categories: " .
                    "{$categoryNames})</comment>"
                );
                $margin = 1;
            } else {
                $margin = (max($margins)) / 100 + 1;
            }

            $item['price'] = round($item['cost'] * $margin, 2);

            foreach (['tier_prices', 'options_pricing', 'custom_options'] as $option) {
                if (empty($item[$option])) {
                    continue;
                }

                $decodedOptions = $this->lineFormatterMulti->decode($item[$option]);
                $this->applyMargin($decodedOptions, $margin);
                $item[$option] = $this->lineFormatterMulti->encode($decodedOptions);
            }
        }
    }

    /**
     * @param $item
     * @param $margin
     */
    protected function applyMargin(&$item, $margin)
    {
        if (isset($item['cost'])) {
            $item['price'] = round($item['cost'] * $margin, 2);
            unset($item['cost']);
            return;
        }

        if (! is_array($item)) {
            return;
        }

        foreach ($item as &$subItem) {
            $this->applyMargin($subItem, $margin);
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
