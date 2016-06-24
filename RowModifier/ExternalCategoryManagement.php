<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Symfony\Component\Console\Output\ConsoleOutput;

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
     * ExternalCategoryManagement constructor.
     *
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ConsoleOutput             $consoleOutput
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        ConsoleOutput $consoleOutput
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
            foreach ($categories as $category) {
                if (isset($this->categoryMapping[$category])) {
                    $categories = array_merge($categories, $this->categoryMapping[$category]);
                }
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

                $importGroups = explode(',', $category->getData('external_id'));
                foreach ($importGroups as $importGroup) {

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
}
