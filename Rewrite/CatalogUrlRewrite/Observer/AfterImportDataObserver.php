<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Rewrite\CatalogUrlRewrite\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewriteFactory;

class AfterImportDataObserver extends \Magento\CatalogUrlRewrite\Observer\AfterImportDataObserver
{

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * AfterImportDataObserver constructor.
     *
     * @param \Magento\Catalog\Model\ProductFactory                    $catalogProductFactory
     * @param \Magento\CatalogUrlRewrite\Model\ObjectRegistryFactory   $objectRegistryFactory
     * @param \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator $productUrlPathGenerator
     * @param \Magento\CatalogUrlRewrite\Service\V1\StoreViewService   $storeViewService
     * @param \Magento\Store\Model\StoreManagerInterface               $storeManager
     * @param UrlPersistInterface                                      $urlPersist
     * @param UrlRewriteFactory                                        $urlRewriteFactory
     * @param UrlFinderInterface                                       $urlFinder
     * @param ScopeConfigInterface                                     $scopeConfig
     */
    public function __construct(
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Magento\CatalogUrlRewrite\Model\ObjectRegistryFactory $objectRegistryFactory,
        \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator $productUrlPathGenerator,
        \Magento\CatalogUrlRewrite\Service\V1\StoreViewService $storeViewService,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        UrlPersistInterface $urlPersist,
        UrlRewriteFactory $urlRewriteFactory,
        UrlFinderInterface $urlFinder,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($catalogProductFactory, $objectRegistryFactory, $productUrlPathGenerator, $storeViewService,
            $storeManager, $urlPersist, $urlRewriteFactory, $urlFinder);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Generate product url rewrites
     *
     * @return \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
     */
    protected function generateUrls()
    {
        if ($this->isProductUseCategoriesEnabled()) {
            return parent::generateUrls();
        }        /**
         * @var $urls \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[]
         */
        $urls = array_merge(
            $this->canonicalUrlRewriteGenerate(),
            $this->currentUrlRewritesRegenerate()
        );
        /* Reduce duplicates. Last wins */
        $result = [];
        foreach ($urls as $url) {
            $result[$url->getTargetPath() . '-' . $url->getStoreId()] = $url;
        }
        $this->productCategories = null;
        $this->products = [];
        return $result;
    }

    /**
     * Is catalog/seo/product_use_categories enabled
     * @param $storeId
     *
     * @return bool
     */
    protected function isProductUseCategoriesEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            \Magento\Catalog\Helper\Product::XML_PATH_PRODUCT_URL_USE_CATEGORY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
