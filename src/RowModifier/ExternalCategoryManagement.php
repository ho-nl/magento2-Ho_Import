<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Logger\Log;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Symfony\Component\Console\Output\ConsoleOutput;

class ExternalCategoryManagement extends AbstractRowModifier
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
     * Product Collection Factory
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var string[]
     */
    private $externalCategoryPathFilter;

    /**
     * @var int[]
     */
    private $productCategoryMapping;

    /**
     * @param ConsoleOutput             $consoleOutput
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ProductCollectionFactory  $productCollectionFactory
     * @param Log                       $log
     * @param string[]                  $externalCategoryPathFilter
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        CategoryCollectionFactory $categoryCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        Log $log,
        $externalCategoryPathFilter = []
    ) {
        parent::__construct($consoleOutput, $log);

        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->externalCategoryPathFilter = $externalCategoryPathFilter;
    }

    /**
     * Add additional categories to products
     * @return void
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>Adding existing product category associations</info>");
        $this->log->addInfo('Adding existing product category associations');

        $this->initCategoryMapping();
        $this->initCategoryProductMapping();
        foreach ($this->items as $identifier => &$item) {
            $categories = $this->extractCategoriesFromString($item['categories']);

            foreach ($categories as $category) {
                if (isset($this->categoryMapping[$category])) {
                    $categories = array_merge($categories, $this->categoryMapping[$category]);
                }
            }
            
            if (isset($this->productCategoryMapping[$identifier])) {
                $categories = array_merge($categories, $this->productCategoryMapping[$identifier]);
            }
            $item['categories'] = implode(',', $categories);
        }
    }

    /**
     * Load Magento's categories with the import_group
     * @return \[]
     */
    private function initCategoryMapping()
    {
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addNameToResult();
        $categoryCollection->setStoreId(0);
        $categoryCollection->addAttributeToSelect('external_id');

        foreach ($categoryCollection as $category) {
            /** @var Category $category */

            if (! $category->getData('external_id')) {
                continue;
            }

            $structure = $category->getPathIds();
            $pathSize = $category->getLevel() + 1;
            if ($pathSize > 2) {
                $path = [];
                for ($i = 1; $i < $pathSize; $i++) {
                    $item = $categoryCollection->getItemById($structure[$i]);
                    if ($item instanceof Category) {
                        $path[] = $item->getName();
                    }
                }

                foreach ($this->extractCategoriesFromString($category->getData('external_id')) as $importGroup) {
                    $importGroup = trim($importGroup);

                    if (! isset($this->categoryMapping[trim($importGroup)])) {
                        $this->categoryMapping[trim($importGroup)] = [];
                    }
                    // additional options for category referencing: name starting from base category, or category id
                    $this->categoryMapping[trim($importGroup)][] = implode('/', $path);
                }
            }
        }

        if (! $this->categoryMapping) {
            return [];
        }

        return $this->categoryMapping;
    }

    /**
     * @todo Add filter on current products
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function initCategoryProductMapping()
    {
        //Get all categories that aren't managed externally.
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addNameToResult();

        $categoryMapping = [];
        foreach ($categoryCollection as $category) {
            /** @var Category $category */
            $categories = $this->extractCategoriesFromString($category->getExternalId());
            if (count($categories) > 0) {
                continue;
            }

            $structure = $category->getPathIds();
            $pathSize = $category->getLevel() + 1;
            if ($pathSize > 1) {
                $path = [];
                for ($i = 1; $i < $pathSize; $i++) {
                    $item = $categoryCollection->getItemById($structure[$i]);
                    if ($item instanceof Category) {
                        $path[] = $item->getName();
                    }
                }

                $categoryMapping[$category->getId()] = implode('/', $path);
            }
        }

        if ($this->externalCategoryPathFilter) {
            $categoryMapping = array_filter($categoryMapping, function ($category) {
                foreach ($this->externalCategoryPathFilter as $external) {
                    if (strpos($category, $external) === 0) {
                        return false;
                    }
                }
                return true;
            });
        }

        $productCollection = $this->productCollectionFactory->create();

        $categorySelect = $productCollection->getConnection()->select()->from(
            ['cat' => $productCollection->getTable('catalog_category_product')],
            ['cat.category_id']
        )->where($productCollection->getConnection()->prepareSqlCondition(
            'cat.category_id',
            ['in' => $categoryCollection->getAllIds()]
        ));

        $categorySelect->join(
            ['products' => $productCollection->getMainTable()],
            'cat.product_id = products.entity_id',
            ['products.sku']
        );
        $categorySelect->where('products.sku IN(?)', array_keys($this->items));

        $results = $productCollection->getConnection()->fetchAll($categorySelect);

        $this->productCategoryMapping =  [];
        foreach ($results as $result) {
            if (! isset($categoryMapping[$result['category_id']])) {
                continue;
            }

            if (! isset($this->productCategoryMapping[$result['sku']])) {
                $this->productCategoryMapping[$result['sku']] = [];
            }

            $this->productCategoryMapping[$result['sku']][] = $categoryMapping[$result['category_id']];
        }
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
    private function extractCategoriesFromString($categories)
    {
        return array_filter(array_map(function ($category) {
            return trim($category, " \t\n\r\0\x0B/");
        }, explode(',', trim($categories, " \t\n\r\0\x0B/"))));
    }
}
