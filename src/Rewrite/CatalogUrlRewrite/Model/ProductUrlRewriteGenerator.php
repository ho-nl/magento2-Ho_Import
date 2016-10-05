<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Rewrite\CatalogUrlRewrite\Model;

use Magento\CatalogUrlRewrite\Model\ObjectRegistryFactory;
use Magento\CatalogUrlRewrite\Model\Product\CanonicalUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\Product\CategoriesUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\Product\CurrentUrlRewritesRegenerator;
use Magento\CatalogUrlRewrite\Service\V1\StoreViewService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ProductUrlRewriteGenerator extends \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator
{

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * ProductUrlRewriteGenerator constructor.
     *
     * @param CanonicalUrlRewriteGenerator               $canonicalUrlRewriteGenerator
     * @param CurrentUrlRewritesRegenerator              $currentUrlRewritesRegenerator
     * @param CategoriesUrlRewriteGenerator              $categoriesUrlRewriteGenerator
     * @param ObjectRegistryFactory                      $objectRegistryFactory
     * @param StoreViewService                           $storeViewService
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param ScopeConfigInterface                       $scopeConfig
     */
    public function __construct(
        CanonicalUrlRewriteGenerator $canonicalUrlRewriteGenerator,
        CurrentUrlRewritesRegenerator $currentUrlRewritesRegenerator,
        CategoriesUrlRewriteGenerator $categoriesUrlRewriteGenerator,
        ObjectRegistryFactory $objectRegistryFactory,
        StoreViewService $storeViewService,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($canonicalUrlRewriteGenerator, $currentUrlRewritesRegenerator,
            $categoriesUrlRewriteGenerator, $objectRegistryFactory, $storeViewService, $storeManager);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Generate list of urls for specific store view
     *
     * @param int                                $storeId
     * @param \Magento\Framework\Data\Collection $productCategories
     *
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    protected function generateForSpecificStoreView($storeId, $productCategories)
    {
        if ($this->isProductUseCategoriesEnabled($storeId)) {
            return parent::generateForSpecificStoreView($storeId, $productCategories);
        }
        $categories = [];
        foreach ($productCategories as $category) {
            if ($this->isCategoryProperForGenerating($category, $storeId)) {
                $categories[] = $category;
            }
        }
        $this->productCategories = $this->objectRegistryFactory->create(['entities' => $categories]);
        /**
         * @var $urls \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
         */
        $urls = array_merge(
            $this->canonicalUrlRewriteGenerator->generate($storeId, $this->product),
            $this->currentUrlRewritesRegenerator->generate($storeId, $this->product, $this->productCategories)
        );
        /* Reduce duplicates. Last wins */
        $result = [];
        foreach ($urls as $url) {
            $result[$url->getTargetPath() . '-' . $url->getStoreId()] = $url;
        }
        $this->productCategories = null;
        return $result;
    }

    /**
     * @param $storeId
     *
     * @return bool
     */
    protected function isProductUseCategoriesEnabled($storeId)
    {
        return $this->scopeConfig->isSetFlag(
            \Magento\Catalog\Helper\Product::XML_PATH_PRODUCT_URL_USE_CATEGORY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
