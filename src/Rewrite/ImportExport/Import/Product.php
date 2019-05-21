<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Rewrite\ImportExport\Import;

use Magento\Catalog\Helper\Catalog;
use Magento\Catalog\Model\Config as CatalogConfig;
use Ho\Import\Helper\LineFormatterMulti;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogImportExport\Model\Import\Product\RowValidatorInterface as ValidatorInterface;
use Magento\Framework\Model\ResourceModel\Db\ObjectRelationProcessor;
use Magento\Framework\Model\ResourceModel\Db\TransactionManagerInterface;
use Magento\Framework\Stdlib\DateTime;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\Store\Model\Store;

class Product extends \Magento\CatalogImportExport\Model\Import\Product
{
    const SKIP_ATTRIBUTES_WHEN_UPDATING = '_import_skip_attributes_when_updating';

    /** @var LineFormatterMulti $lineFormatterMulti */
    private $lineFormatterMulti;

    /** @var CatalogConfig $catalogConfig */
    private $catalogConfig;

    /** @var string $productEntityLinkField */
    private $productEntityLinkField;

    /**
     * @param \Magento\Framework\Json\Helper\Data                                          $jsonHelper
     * @param \Magento\ImportExport\Helper\Data                                            $importExportData
     * @param \Magento\ImportExport\Model\ResourceModel\Import\Data                        $importData
     * @param \Magento\Eav\Model\Config                                                    $config
     * @param \Magento\Framework\App\ResourceConnection                                    $resource
     * @param \Magento\ImportExport\Model\ResourceModel\Helper                             $resourceHelper
     * @param \Magento\Framework\Stdlib\StringUtils                                        $string
     * @param ProcessingErrorAggregatorInterface                                           $errorAggregator
     * @param \Magento\Framework\Event\ManagerInterface                                    $eventManager
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface                         $stockRegistry
     * @param \Magento\CatalogInventory\Api\StockConfigurationInterface                    $stockConfiguration
     * @param \Magento\CatalogInventory\Model\Spi\StockStateProviderInterface              $stockStateProvider
     * @param \Magento\Catalog\Helper\Data                                                 $catalogData
     * @param Import\Config                                                                $importConfig
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory
     * @param \Magento\CatalogImportExport\Model\Import\Product\OptionFactory              $optionFactory
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory      $setColFactory
     * @param \Magento\CatalogImportExport\Model\Import\Product\Type\Factory               $productTypeFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product\LinkFactory                     $linkFactory
     * @param \Magento\CatalogImportExport\Model\Import\Proxy\ProductFactory               $proxyProdFactory
     * @param \Magento\CatalogImportExport\Model\Import\UploaderFactory                    $uploaderFactory
     * @param \Magento\Framework\Filesystem                                                $filesystem
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory              $stockResItemFac
     * @param DateTime\TimezoneInterface                                                   $localeDate
     * @param DateTime                                                                     $dateTime
     * @param \Psr\Log\LoggerInterface                                                     $logger
     * @param \Magento\Framework\Indexer\IndexerRegistry                                   $indexerRegistry
     * @param \Magento\CatalogImportExport\Model\Import\Product\StoreResolver              $storeResolver
     * @param \Magento\CatalogImportExport\Model\Import\Product\SkuProcessor               $skuProcessor
     * @param \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor          $categoryProcessor
     * @param \Magento\CatalogImportExport\Model\Import\Product\Validator                  $validator
     * @param ObjectRelationProcessor                                                      $objectRelationProcessor
     * @param TransactionManagerInterface                                                  $transactionManager
     * @param \Magento\CatalogImportExport\Model\Import\Product\TaxClassProcessor          $taxClassProcessor
     * @param \Magento\Framework\App\Config\ScopeConfigInterface                           $scopeConfig
     * @param \Magento\Catalog\Model\Product\Url                                           $productUrl
     * @param LineFormatterMulti                                                           $lineFormatterMulti
     * @param CatalogConfig                                                                $catalogConfig
     * @param array                                                                        $data
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\Eav\Model\Config $config,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Stdlib\StringUtils $string,
        ProcessingErrorAggregatorInterface $errorAggregator,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration,
        \Magento\CatalogInventory\Model\Spi\StockStateProviderInterface $stockStateProvider,
        \Magento\Catalog\Helper\Data $catalogData,
        \Magento\ImportExport\Model\Import\Config $importConfig,
        \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory,
        \Magento\CatalogImportExport\Model\Import\Product\OptionFactory $optionFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setColFactory,
        \Magento\CatalogImportExport\Model\Import\Product\Type\Factory $productTypeFactory,
        \Magento\Catalog\Model\ResourceModel\Product\LinkFactory $linkFactory,
        \Magento\CatalogImportExport\Model\Import\Proxy\ProductFactory $proxyProdFactory,
        \Magento\CatalogImportExport\Model\Import\UploaderFactory $uploaderFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory $stockResItemFac,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        DateTime $dateTime,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        \Magento\CatalogImportExport\Model\Import\Product\StoreResolver $storeResolver,
        \Magento\CatalogImportExport\Model\Import\Product\SkuProcessor $skuProcessor,
        \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor $categoryProcessor,
        \Magento\CatalogImportExport\Model\Import\Product\Validator $validator,
        ObjectRelationProcessor $objectRelationProcessor,
        TransactionManagerInterface $transactionManager,
        \Magento\CatalogImportExport\Model\Import\Product\TaxClassProcessor $taxClassProcessor,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\Url $productUrl,
        LineFormatterMulti $lineFormatterMulti,
        CatalogConfig $catalogConfig,
        array $data = []
    ) {
        \Magento\CatalogImportExport\Model\Import\Product::__construct($jsonHelper, $importExportData, $importData,
            $config, $resource, $resourceHelper, $string, $errorAggregator, $eventManager, $stockRegistry,
            $stockConfiguration, $stockStateProvider, $catalogData, $importConfig, $resourceFactory, $optionFactory,
            $setColFactory, $productTypeFactory, $linkFactory, $proxyProdFactory, $uploaderFactory, $filesystem,
            $stockResItemFac, $localeDate, $dateTime, $logger, $indexerRegistry, $storeResolver, $skuProcessor,
            $categoryProcessor, $validator, $objectRelationProcessor, $transactionManager, $taxClassProcessor,
            $scopeConfig, $productUrl, $data);
        $this->lineFormatterMulti = $lineFormatterMulti;
        $this->catalogConfig = $catalogConfig;
    }

    /**
     * Create Product entity from raw data.
     * @fixme https://github.com/magento/magento2/issues/5993
     *
     * @throws \Exception
     * @return bool Result of operation.
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _importData()
    {
        $this->_validatedRows = null;
        if (Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            $this->_deleteProducts();
        } elseif (Import::BEHAVIOR_REPLACE == $this->getBehavior()) {
            $this->_replaceFlag = true;
            $this->_saveProductsData();
        } else {
            $this->_saveProductsData();
        }

        $this->_eventManager->dispatch('catalog_product_import_finish_before', ['adapter' => $this]);
        return true;
    }

    /**
     * Gather and save information about product entities.
     * @fixme https://github.com/magento/magento2/issues/5160
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @throws LocalizedException
     */
    protected function _saveProducts()
    {
        $priceIsGlobal = $this->_catalogData->isPriceGlobal();
        $productLimit = null;
        $productsQty = null;
        $entityLinkField = $this->getProductEntityLinkField();

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityRowsIn = [];
            $entityRowsUp = [];
            $attributes = [];
            $this->websitesCache = [];
            $this->categoriesCache = [];
            $tierPrices = [];
            $mediaGallery = [];
            $labelsForUpdate = [];
            $uploadedImages = [];
            $previousType = null;
            $prevAttributeSet = null;
            $existingImages = $this->getExistingImages($bunch);

            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }
                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                }
                $rowScope = $this->getRowScope($rowData);
                $rowSku = $rowData[self::COL_SKU];

                if (null === $rowSku) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                } elseif (self::SCOPE_STORE == $rowScope) {
                    // set necessary data from SCOPE_DEFAULT row
                    $rowData[self::COL_TYPE] = $this->skuProcessor->getNewSku($rowSku)['type_id'];
                    $rowData['attribute_set_id'] = $this->skuProcessor->getNewSku($rowSku)['attr_set_id'];
                    $rowData[self::COL_ATTR_SET] = $this->skuProcessor->getNewSku($rowSku)['attr_set_code'];
                }

                // 1. Entity phase
                if (isset($this->_oldSku[$rowSku])) {
                    // existing row
                    if (isset($rowData['attribute_set_code'])) {
                        $attributeSetId = $this->catalogConfig->getAttributeSetId(
                            $this->getEntityTypeId(),
                            $rowData['attribute_set_code']
                        );

                        // wrong attribute_set_code was received
                        if (!$attributeSetId) {
                            throw new \Magento\Framework\Exception\LocalizedException(
                                __(
                                    'Wrong attribute set code "%1", please correct it and try again.',
                                    $rowData['attribute_set_code']
                                )
                            );
                        }
                    } else {
                        $attributeSetId = $this->skuProcessor->getNewSku($rowSku)['attr_set_id'];
                    }
                    // existing row
                    $entityRowsUp[] = [
                        'updated_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                        'attribute_set_id' => $attributeSetId,
                        $this->getProductEntityLinkField()
                        => $this->_oldSku[$rowSku][$this->getProductEntityLinkField()],
                    ];
                } else {
                    if (!$productLimit || $productsQty < $productLimit) {
                        $entityRowsIn[strtolower($rowSku)] = [
                            'attribute_set_id' => $this->skuProcessor->getNewSku($rowSku)['attr_set_id'],
                            'type_id' => $this->skuProcessor->getNewSku($rowSku)['type_id'],
                            'sku' => $rowSku,
                            'has_options' => isset($rowData['has_options']) ? $rowData['has_options'] : 0,
                            'created_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                            'updated_at' => (new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT),
                        ];
                        $productsQty++;
                    } else {
                        $rowSku = null;
                        // sign for child rows to be skipped
                        $this->getErrorAggregator()->addRowToSkip($rowNum);
                        continue;
                    }
                }

                if (!array_key_exists($rowSku, $this->websitesCache)) {
                    $this->websitesCache[$rowSku] = [];
                }
                // 2. Product-to-Website phase
                if (!empty($rowData[self::COL_PRODUCT_WEBSITES])) {
                    $websiteCodes = explode($this->getMultipleValueSeparator(), $rowData[self::COL_PRODUCT_WEBSITES]);
                    foreach ($websiteCodes as $websiteCode) {
                        $websiteId = $this->storeResolver->getWebsiteCodeToId($websiteCode);
                        $this->websitesCache[$rowSku][$websiteId] = true;
                    }
                }

                // 3. Categories phase
                if (!array_key_exists($rowSku, $this->categoriesCache)) {
                    $this->categoriesCache[$rowSku] = [];
                }
                $rowData['rowNum'] = $rowNum;
                $categoryIds = $this->processRowCategories($rowData);
                foreach ($categoryIds as $id) {
                    $this->categoriesCache[$rowSku][$id] = true;
                }
                unset($rowData['rowNum']);

                // 4.1. Tier prices phase
                //_getMultiRowFormat
                if (!empty($rowData['tier_prices'])) {
                    $tierPriceData = $this->lineFormatterMulti->decode($rowData['tier_prices']);
                    foreach ($tierPriceData as $tierPrice) {
                        $tierPrices[$rowSku][] = [
                            'all_groups' => $tierPrice['website'] == self::VALUE_ALL,
                            'customer_group_id' => $tierPrice['customer_group'] ==
                            self::VALUE_ALL ? 0 : $tierPrice['customer_group'],
                            'qty' => $tierPrice['qty'],
                            'value' => $tierPrice['price'],
                            'website_id' => self::VALUE_ALL == $tierPrice['website']
                            || $priceIsGlobal ? 0 : $this->storeResolver->getWebsiteCodeToId($tierPrice['website']),
                        ];
                    }
                }

                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                // 5. Media gallery phase
                $disabledImages = [];
                list($rowImages, $rowLabels) = $this->getImagesFromRow($rowData);
                $storeId = !empty($rowData[self::COL_STORE])
                    ? $this->getStoreIdByCode($rowData[self::COL_STORE])
                    : Store::DEFAULT_STORE_ID;
                if (isset($rowData['_media_is_disabled']) && strlen(trim($rowData['_media_is_disabled']))) {
                    $disabledImages = array_flip(
                        explode($this->getMultipleValueSeparator(), $rowData['_media_is_disabled'])
                    );
                    if (empty($rowImages)) {
                        foreach (array_keys($disabledImages) as $disabledImage) {
                            $rowImages[self::COL_MEDIA_IMAGE][] = $disabledImage;
                        }
                    }
                }
                $rowData[self::COL_MEDIA_IMAGE] = [];

                /*
                 * Note: to avoid problems with undefined sorting, the value of media gallery items positions
                 * must be unique in scope of one product.
                 */
                $position = 0;
                foreach ($rowImages as $column => $columnImages) {
                    foreach ($columnImages as $columnImageKey => $columnImage) {
                        if (!isset($uploadedImages[$columnImage])) {
                            $uploadedFile = $this->uploadMediaFiles(trim($columnImage), true);
                            if ($uploadedFile) {
                                $uploadedImages[$columnImage] = $uploadedFile;
                            } else {
                                $this->addRowError(
                                    ValidatorInterface::ERROR_MEDIA_URL_NOT_ACCESSIBLE,
                                    $rowNum,
                                    null,
                                    null,
                                    ProcessingError::ERROR_LEVEL_NOT_CRITICAL
                                );
                            }
                        } else {
                            $uploadedFile = $uploadedImages[$columnImage];
                        }

                        if ($uploadedFile && $column !== self::COL_MEDIA_IMAGE) {
                            $rowData[$column] = $uploadedFile;
                        }
                        $imageNotAssigned = !isset($existingImages[$rowSku][$uploadedFile]);
                        if ($uploadedFile && $imageNotAssigned) {
                            if ($column == self::COL_MEDIA_IMAGE) {
                                $rowData[$column][] = $uploadedFile;
                            }
                            $mediaGallery[$storeId][$rowSku][$uploadedFile] = [
                                'attribute_id' => $this->getMediaGalleryAttributeId(),
                                'label' => isset($rowLabels[$column][$position]) ? $rowLabels[$column][$position] : '',
                                'position' => ++$position,
                                'disabled' => isset($disabledImages[$columnImage]) ? '1' : '0',
                                'value' => $uploadedFile,
                            ];
                            $existingImages[$rowSku][$uploadedFile] = true;
                        }
                    }
                }

                // 6. Attributes phase
                $rowStore = (self::SCOPE_STORE == $rowScope)
                    ? $this->storeResolver->getStoreCodeToId($rowData[self::COL_STORE])
                    : 0;
                $productType = isset($rowData[self::COL_TYPE]) ? $rowData[self::COL_TYPE] : null;
                if (!is_null($productType)) {
                    $previousType = $productType;
                }
                if (isset($rowData[self::COL_ATTR_SET])) {
                    $prevAttributeSet = $rowData[self::COL_ATTR_SET];
                }
                if (self::SCOPE_NULL == $rowScope) {
                    // for multiselect attributes only
                    if (!is_null($prevAttributeSet)) {
                        $rowData[self::COL_ATTR_SET] = $prevAttributeSet;
                    }
                    if (is_null($productType) && !is_null($previousType)) {
                        $productType = $previousType;
                    }
                    if (is_null($productType)) {
                        continue;
                    }
                }

                $productTypeModel = $this->_productTypeModels[$productType];
                if (!empty($rowData['tax_class_name'])) {
                    $rowData['tax_class_id'] =
                        $this->taxClassProcessor->upsertTaxClass($rowData['tax_class_name'], $productTypeModel);
                }

                if ($this->getBehavior() == Import::BEHAVIOR_APPEND ||
                    empty($rowData[self::COL_SKU])
                ) {
                    $rowData = $productTypeModel->clearEmptyData($rowData);
                }

                $rowData = $productTypeModel->prepareAttributesWithDefaultValueForSave(
                    $rowData,
                    !isset($this->_oldSku[$rowSku])
                );
                $product = $this->_proxyProdFactory->create(['data' => $rowData]);

                foreach ($rowData as $attrCode => $attrValue) {
                    $attribute = $this->retrieveAttributeByCode($attrCode);

                    if ($this->skipUpdatingAttribute($rowSku, $attrCode)) {
                        continue;
                    }

                    if ('multiselect' != $attribute->getFrontendInput() && self::SCOPE_NULL == $rowScope) {
                        // skip attribute processing for SCOPE_NULL rows
                        continue;
                    }
                    $attrId = $attribute->getId();
                    $backModel = $attribute->getBackendModel();
                    $attrTable = $attribute->getBackend()->getTable();
                    $storeIds = [0];

                    if (
                        'datetime' == $attribute->getBackendType()
                        && (
                            in_array($attribute->getAttributeCode(), $this->dateAttrCodes)
                            || $attribute->getIsUserDefined()
                        )
                    ) {
                        $attrValue = $this->dateTime->formatDate($attrValue, false);
                    } elseif ('datetime' == $attribute->getBackendType() && strtotime($attrValue)) {
                        $attrValue = $this->dateTime->gmDate(
                            'Y-m-d H:i:s',
                            $this->_localeDate->date($attrValue)->getTimestamp()
                        );
                    } elseif ($backModel) {
                        $attribute->getBackend()->beforeSave($product);
                        $attrValue = $product->getData($attribute->getAttributeCode());
                    }
                    if (self::SCOPE_STORE == $rowScope) {
                        if (self::SCOPE_WEBSITE == $attribute->getIsGlobal()) {
                            // check website defaults already set
                            if (!isset($attributes[$attrTable][$rowSku][$attrId][$rowStore])) {
                                $storeIds = $this->storeResolver->getStoreIdToWebsiteStoreIds($rowStore);
                            }
                        } elseif (self::SCOPE_STORE == $attribute->getIsGlobal()) {
                            $storeIds = [$rowStore];
                        }
                        if (!isset($this->_oldSku[$rowSku])) {
                            $storeIds[] = 0;
                        }
                    }
                    foreach ($storeIds as $storeId) {
                        if (!isset($attributes[$attrTable][$rowSku][$attrId][$storeId])) {
                            $attributes[$attrTable][$rowSku][$attrId][$storeId] = $attrValue;
                        }
                    }
                    // restore 'backend_model' to avoid 'default' setting
                    $attribute->setBackendModel($backModel);
                }
            }

            foreach ($bunch as $rowNum => $rowData) {
                if ($this->getErrorAggregator()->isRowInvalid($rowNum)) {
                    unset($bunch[$rowNum]);
                }
            }

            $this->saveProductEntity(
                $entityRowsIn,
                $entityRowsUp
            )->_saveProductWebsites(
                $this->websitesCache
            )->_saveProductCategories(
                $this->categoriesCache
            )->_saveProductTierPrices(
                $tierPrices
            )->_saveMediaGallery(
                $mediaGallery
            )->_saveProductAttributes(
                $attributes
            );

            $this->_eventManager->dispatch(
                'catalog_product_import_bunch_save_after',
                ['adapter' => $this, 'bunch' => $bunch]
            );
        }

        return $this;
    }

    public function skipUpdatingAttribute($rowSku, $attrCode)
    {
        return isset($this->_oldSku[$rowSku]) && \array_key_exists(self::SKIP_ATTRIBUTES_WHEN_UPDATING, $this->_parameters) && \array_key_exists($attrCode, $this->_parameters[self::SKIP_ATTRIBUTES_WHEN_UPDATING]);
    }

    /**
     * Required for self::_saveProducts
     * Get product entity link field
     *
     * @return string
     */
    private function getProductEntityLinkField()
    {
        if (!$this->productEntityLinkField) {
            $this->productEntityLinkField = $this->getMetadataPool()
                ->getMetadata(\Magento\Catalog\Api\Data\ProductInterface::class)
                ->getLinkField();
        }
        return $this->productEntityLinkField;
    }

    /**
     * Stock item saving.
     * @fixme https://github.com/magento/magento2/issues/5887
     *
     * @return $this
     */
    protected function _saveStockItem()
    {
        /** @var $stockResource \Magento\CatalogInventory\Model\ResourceModel\Stock\Item */
        $stockResource = $this->_stockResItemFac->create();
        $entityTable = $stockResource->getMainTable();
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $stockData = [];
            $productIdsToReindex = [];
            // Format bunch to stock data rows
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->isRowAllowedToImport($rowData, $rowNum)) {
                    continue;
                }
                $row = [];
                $row['product_id']     = $this->skuProcessor->getNewSku($rowData[self::COL_SKU])['entity_id'];
                $productIdsToReindex[] = $row['product_id'];
                $row['website_id'] = $this->stockConfiguration->getDefaultScopeId();
                $row['stock_id']   = $this->stockRegistry->getStock($row['website_id'])->getStockId();
                $stockItemDo    = $this->stockRegistry->getStockItem($row['product_id'], $row['website_id']);
                $existStockData = $stockItemDo->getData();
                $row = array_merge(
                    $this->defaultStockData,
                    array_intersect_key($existStockData, $this->defaultStockData),
                    array_intersect_key($rowData, $this->defaultStockData),
                    $row
                );
                if ($this->stockConfiguration->isQty(
                    $this->skuProcessor->getNewSku($rowData[self::COL_SKU])['type_id']
                )
                ) {
                    $stockItemDo->setData($row);
                    $row['is_in_stock'] = $this->stockStateProvider->verifyStock($stockItemDo);
                    if ($this->stockStateProvider->verifyNotification($stockItemDo)) {
                        $row['low_stock_date'] = $this->dateTime->gmDate(
                            'Y-m-d H:i:s',
                            (new \DateTime())->getTimestamp()
                        );
                    }
                    $row['stock_status_changed_auto']
                        = (int)!$this->stockStateProvider->verifyStock($stockItemDo);
                } else {
                    $row['qty'] = 0;
                }
                if (!isset($stockData[$rowData[self::COL_SKU]])) {
                    $stockData[$rowData[self::COL_SKU]] = $row;
                }
            }
            // Insert rows
            if (!empty($stockData)) {
                $this->_connection->insertOnDuplicate($entityTable, array_values($stockData));
            }

            $this->reindexProducts($productIdsToReindex);
        }

        return $this;
    }

    /**
     * Initiate product reindex by product ids
     *
     * @param array $productIdsToReindex
     * @return void
     */
    private function reindexProducts($productIdsToReindex = [])
    {
        $indexer = $this->indexerRegistry->get('catalog_product_category');
        if (\is_array($productIdsToReindex) && count($productIdsToReindex) > 0 && !$indexer->isScheduled()) {
            $indexer->reindexList($productIdsToReindex);
        }
    }

    /**
     * Validate data row.
     *
     * @fixme https://github.com/magento/magento2/issues/5993
     * @param array $rowData
     * @param int $rowNum
     * @return boolean
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function validateRow(array $rowData, $rowNum)
    {
        if (isset($this->_validatedRows[$rowNum])) {
            // check that row is already validated
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }
        $this->_validatedRows[$rowNum] = true;
        $rowScope = $this->getRowScope($rowData);
        // BEHAVIOR_DELETE use specific validation logic
        if (Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            if (self::SCOPE_DEFAULT == $rowScope && !isset($this->_oldSku[$rowData[self::COL_SKU]])) {
                $this->addRowError(ValidatorInterface::ERROR_SKU_NOT_FOUND_FOR_DELETE, $rowNum);
                return false;
            }
            return true;
        }
        if (!$this->validator->isValid($rowData)) {
            foreach ($this->validator->getMessages() as $message) {
                $this->addRowError($message, $rowNum, $this->validator->getInvalidAttribute());
            }
        }
        $sku = $rowData[self::COL_SKU];
        if (null === $sku) {
            $this->addRowError(ValidatorInterface::ERROR_SKU_IS_EMPTY, $rowNum);
        } elseif (false === $sku) {
            $this->addRowError(ValidatorInterface::ERROR_ROW_IS_ORPHAN, $rowNum);
        } elseif (self::SCOPE_STORE == $rowScope
            && !$this->storeResolver->getStoreCodeToId($rowData[self::COL_STORE])
        ) {
            $this->addRowError(ValidatorInterface::ERROR_INVALID_STORE, $rowNum);
        }
        // SKU is specified, row is SCOPE_DEFAULT, new product block begins
        $this->_processedEntitiesCount++;
        $sku = $rowData[self::COL_SKU];
        if (isset($this->_oldSku[$sku])) {
            // can we get all necessary data from existent DB product?
            // check for supported type of existing product
            if (isset($this->_productTypeModels[$this->_oldSku[$sku]['type_id']])) {
                $this->skuProcessor->addNewSku(
                    $sku,
                    $this->prepareNewSkuData($sku)
                );
            } else {
                $this->addRowError(ValidatorInterface::ERROR_TYPE_UNSUPPORTED, $rowNum);
                // child rows of legacy products with unsupported types are orphans
                $sku = false;
            }
        } else {
            // validate new product type and attribute set
            if (!isset($rowData[self::COL_TYPE]) || !isset($this->_productTypeModels[$rowData[self::COL_TYPE]])) {
                $this->addRowError(ValidatorInterface::ERROR_INVALID_TYPE, $rowNum);
            } elseif (!isset(
                    $rowData[self::COL_ATTR_SET]
                ) || !isset(
                    $this->_attrSetNameToId[$rowData[self::COL_ATTR_SET]]
                )
            ) {
                $this->addRowError(ValidatorInterface::ERROR_INVALID_ATTR_SET, $rowNum);
            } elseif (is_null($this->skuProcessor->getNewSku($sku))) {
                $this->skuProcessor->addNewSku(
                    $sku,
                    [
                        'row_id' => null,
                        'entity_id' => null,
                        'type_id' => $rowData[self::COL_TYPE],
                        'attr_set_id' => $this->_attrSetNameToId[$rowData[self::COL_ATTR_SET]],
                        'attr_set_code' => $rowData[self::COL_ATTR_SET],
                    ]
                );
            }
            if ($this->getErrorAggregator()->isRowInvalid($rowNum)) {
                // mark SCOPE_DEFAULT row as invalid for future child rows if product not in DB already
                $sku = false;
            }
        }
        if (!$this->getErrorAggregator()->isRowInvalid($rowNum)) {
            $newSku = $this->skuProcessor->getNewSku($sku);
            // set attribute set code into row data for followed attribute validation in type model
            $rowData[self::COL_ATTR_SET] = $newSku['attr_set_code'];
            $rowAttributesValid = $this->_productTypeModels[$newSku['type_id']]->isRowValid(
                $rowData,
                $rowNum,
                !isset($this->_oldSku[$sku])
            );
            if (!$rowAttributesValid && self::SCOPE_DEFAULT == $rowScope) {
                // mark SCOPE_DEFAULT row as invalid for future child rows if product not in DB already
                $sku = false;
            }
        }
        // validate custom options
        $this->getOptionEntity()->validateRow($rowData, $rowNum);
        if ($this->isNeedToValidateUrlKey($rowData)) {
            $urlKey = $this->getUrlKey($rowData);
            $storeCodes = empty($rowData[self::COL_STORE_VIEW_CODE])
                ? array_flip($this->storeResolver->getStoreCodeToId())
                : explode($this->getMultipleValueSeparator(), $rowData[self::COL_STORE_VIEW_CODE]);
            foreach ($storeCodes as $storeCode) {
                $storeId = $this->storeResolver->getStoreCodeToId($storeCode);
                $productUrlSuffix = $this->getProductUrlSuffix($storeId);
                $urlPath = $urlKey . $productUrlSuffix;
                if (empty($this->urlKeys[$storeId][$urlPath])
                    || ($this->urlKeys[$storeId][$urlPath] == $rowData[self::COL_SKU])
                ) {
                    $this->urlKeys[$storeId][$urlPath] = $rowData[self::COL_SKU];
                    $this->rowNumbers[$storeId][$urlPath] = $rowNum;
                } else {
                    $this->addRowError(ValidatorInterface::ERROR_DUPLICATE_URL_KEY, $rowNum);
                }
            }
        }
        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    /**
     * @param array $rowData
     * @return bool
     */
    private function isNeedToValidateUrlKey($rowData)
    {
        return (!empty($rowData[self::URL_KEY]) || !empty($rowData[self::COL_NAME]))
            && (empty($rowData[self::COL_VISIBILITY])
            || $rowData[self::COL_VISIBILITY]
            !== (string)Visibility::getOptionArray()[Visibility::VISIBILITY_NOT_VISIBLE]);
    }

    /**
     * Required for self::validateRow
     * Prepare new SKU data
     *
     * @param string $sku
     * @return array
     */
    private function prepareNewSkuData($sku)
    {
        $data = [];
        foreach ($this->_oldSku[$sku] as $key => $value) {
            $data[$key] = $value;
        }
        $data['attr_set_code'] = $this->_attrSetIdToName[$this->_oldSku[$sku]['attr_set_id']];
        return $data;
    }

}