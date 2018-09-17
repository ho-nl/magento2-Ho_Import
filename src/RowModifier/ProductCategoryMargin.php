<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Helper\LineFormatterMulti;
use Ho\Import\Logger\Log;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Convert cost fields to price fields
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
     * @param ConsoleOutput             $consoleOutput
     * @param Log                       $log
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param LineFormatterMulti        $lineFormatterMulti
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        Log $log,
        CategoryCollectionFactory $categoryCollectionFactory,
        LineFormatterMulti $lineFormatterMulti
    ) {
        parent::__construct($consoleOutput, $log);

        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->lineFormatterMulti = $lineFormatterMulti;
    }

    /**
     * Add category margins to product.
     *
     * @return void
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>Calculating product margins</info>");

        $this->initCategoryMapping();
        $scope = 'ProductCategoryMargin';

        foreach ($this->items as $identifier => &$item) {
            $item['price'] = 0;

            //Do some checks if the product is valid for adding a product margin.
            if (!isset($item['cost'])) {
                if ($item['product_online'] > 0) {
                    $this->consoleOutput->writeln(
                        "<comment>{$scope}: No cost field found for product {$identifier}, disabling product.</comment>"
                    );
                    $item['product_online'] = (string) 0;
                }
                continue;
            }

            if (empty($item['categories'])) {
                $this->consoleOutput->writeln(
                    "<comment>{$scope}: No category found for product {$identifier}, disabling product.</comment>"
                );
                unset($item['tier_prices']);
                $item['product_online'] = (string) 0;
                $item['price'] = $item['cost'];
                continue;
            }

            if ($item['cost'] == 0) {
                $this->consoleOutput->writeln(
                    "<comment>{$scope}: Cost field set to 0 for product {$identifier}, wrong prices may be imported.</comment>"
                );
            }

            $categories = $this->extractCategoriesFromString($item['categories']);
            $margins = [];

            foreach ($categories as $category) {
                $marginCategories = array_filter($this->categoryMapping, function ($marginCategory) use ($category) {
                    return strpos($category, $marginCategory) === 0;
                }, ARRAY_FILTER_USE_KEY);

                if (!$marginCategories) {
                    continue;
                }
                $margin = end($marginCategories);
                $margins[key($marginCategories)] = $margin;
            }

            if (count($margins) <= 0) {
                $categoryNames = implode(',', $categories);
                $this->consoleOutput->writeln(
                    "<comment>{$scope}: No margin-category found for product {$identifier}, setting cost as price ".
                    "(categories: {$categoryNames})</comment>"
                );
                $margin = 1;
            } else {
                $margin = end($margins) / 100 + 1;
            }

            $item['price'] = $this->roundPrice($item['cost'] * $margin);

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
     * Apply margin to arbitrary arrays, convert cost fields to price fields with the margin.
     *
     * @param $item
     * @param $margin
     */
    protected function applyMargin(&$item, $margin)
    {
        if (! is_array($item)) {
            return;
        }

        if (isset($item['fee_cost'])) {
            $item['fee'] = $this->roundPrice($item['fee_cost'] * $margin);
            unset($item['fee_cost']);
        }
        if (isset($item['cost'])) {
            $item['price'] = $this->roundPrice($item['cost'] * $margin);
            unset($item['cost']);
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
     * @todo Move to a category helper
     *
     * @return string[] mixed
     */
    private function extractCategoriesFromString($categories)
    {
        return array_filter(array_map('trim', explode(',', trim($categories))));
    }

    /**
     * Round prices, increase precision if the price is very small (small than 0,10)
     * @param float $price
     * @return float
     */
    private function roundPrice($price) {
        $precision = $price < 0.10 ? 3 : 2;
        return round($price, $precision);
    }
}
