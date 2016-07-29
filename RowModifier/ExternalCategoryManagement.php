<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class ExternalCategoryManagement
 *
 * @todo Keep existing categories.
 * @todo understand external_categories array.
 * @package Ho\Import\RowModifier
 */
class ExternalCategoryManagement extends AbstractRowModifier
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
     * Product Collection Factory
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    private $externalCategoryPathFilter;

    private $productCategoryMapping;


    /**
     * ExternalCategoryManagement constructor.
     *
     * @param ConsoleOutput             $consoleOutput
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ProductCollectionFactory  $productCollectionFactory
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        CategoryCollectionFactory $categoryCollectionFactory,
        ProductCollectionFactory $productCollectionFactory
    ) {
        parent::__construct($consoleOutput);
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
    }


    /**
     * Add additional categories to products
     * @return void
     */
    public function process()
    {
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
    protected function initCategoryMapping()
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

            $structure = explode('/', $category->getPath());
            $pathSize = count($structure);
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
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function initCategoryProductMapping()
    {
        //Get all categories that aren't managed externally.
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->addNameToResult();
        $categoryCollection->addAttributeToFilter('external_id', ['null' => true], 'left');

        $categoryMapping = [];
        foreach ($categoryCollection as $category) {
            /** @var Category $category */

            $structure = explode('/', $category->getPath());
            $pathSize = count($structure);
            if ($pathSize > 2) {
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
     * Ability to always mark certain category paths as externally managed.
     *
     * @param string[] $externalCategoryPathFilter
     * @return void
     */
    public function setExternalCategoryPathFilter(array $externalCategoryPathFilter)
    {
        $this->externalCategoryPathFilter = $externalCategoryPathFilter;
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
