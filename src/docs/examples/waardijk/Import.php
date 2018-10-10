<?php

/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\Waardijk\Import;

use GuzzleHttp\Exception\GuzzleException;
use Ho\Import\Helper\ItemMapperTools;
use Ho\Import\Helper\LineFormatterMulti;
use Ho\Import\Logger\Log;
use Ho\Import\RowModifier;
use Ho\Import\Streamer\FileCsvFactory;
use Ho\Import\Streamer\HttpCsvFactory;
use Magento\Catalog\Api\CategoryListInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Eav\Model\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Stopwatch\Stopwatch;
use Ho\Import\Model\ImportProfile;

class ProductImport extends ImportProfile
{
    private const SOURCE_FILE = 'var/import/product/ExportCsvWebshop.txt.ACA';
    private const ATTRIBUTE_SET = 'Migration_Waardijk Schoen';

    /** @var RowModifier\SourceIteratorFactory  */
    private $sourceIteratorFactory;

    /** @var FileCsvFactory  */
    private $fileCsvFactory;

    /** @var RowModifier\ItemMapperFactory  */
    private $itemMapperFactory;

    /** @var ManagerInterface  */
    private $messageManager;

    /** @var array  */
    private $colorMapping = [];

    /** @var LoggerInterface  */
    private $logger;

    /** @var RowModifier\ProductUrlKeyFactory  */
    private $productUrlKeyFactory;

    /** @var Filesystem  */
    private $filesystem;

    /** @var HttpCsvFactory  */
    private $httpCsvFactory;

    /** @var array  */
    private $sizeMapping = [];

    /** @var Config  */
    private $eavConfig;

    /** @var ScopeConfigInterface  */
    private $scopeConfig;

    /** @var array  */
    private $categoryMapping = [];

    private $seasonMapping = [];

    /** @var SearchCriteriaBuilder  */
    private $searchCriteriaBuilder;

    /** @var CategoryRepositoryInterface  */
    private $categoryRepository;

    /** @var CategoryListInterface  */
    private $categoryRepositoryList;

    /** @var RowModifier\ConfigurableBuilderFactory  */
    private $configurableBuilderFactory;

    /** @var RowModifier\AttributeOptionCreatorFactory  */
    private $attributeOptionCreatorFactory;

    /** @var RowModifier\ProductDisablerFactory  */
    private $productDisablerFactory;

    /** @var RowModifier\TemplateFieldParserFactory  */
    private $templateFieldParserFactory;

    /** @var \Magento\Catalog\Model\Product\Url  */
    private $urlKeyFormatter;

    /** @var LineFormatterMulti  */
    private $lineFormatterMulti;

    /** @var SerializerInterface  */
    private $serializer;

    /** @var RowModifier\ExternalCategoryManagementFactory  */
    private $externalCategoryManagementFactory;

    /** @var string */
    private $fileCsvImportSourceFile;

    /** @var string */
    private $attributeSetCode;

    /**
     * @param ObjectManagerFactory                          $objectManagerFactory
     * @param Stopwatch                                     $stopwatch
     * @param ConsoleOutput                                 $consoleOutput
     * @param Log                                           $log
     * @param RowModifier\SourceIteratorFactory             $sourceIteratorFactory
     * @param FileCsvFactory                                $fileCsvFactory
     * @param RowModifier\ItemMapperFactory                 $itemMapperFactory
     * @param ManagerInterface                              $messageManager
     * @param RowModifier\ProductUrlKeyFactory              $productUrlKeyFactory
     * @param LoggerInterface                               $logger
     * @param Filesystem                                    $filesystem
     * @param HttpCsvFactory                                $httpCsvfactory
     * @param Config                                        $eavConfig
     * @param ScopeConfigInterface                          $scopeConfig
     * @param CategoryListInterface                         $categoryRepositoryList
     * @param SearchCriteriaBuilder                         $searchCriteriaBuilder
     * @param CategoryRepositoryInterface                   $categoryRepository
     * @param LineFormatterMulti                            $lineFormatterMulti
     * @param \Magento\Catalog\Model\Product\Url            $urlKeyFormatter
     * @param RowModifier\ConfigurableBuilderFactory        $configurableBuilderFactory
     * @param RowModifier\AttributeOptionCreatorFactory     $attributeOptionCreatorFactory
     * @param RowModifier\ProductDisablerFactory            $productDisablerFactory
     * @param RowModifier\TemplateFieldParserFactory        $templateFieldParserFactory
     * @param SerializerInterface                           $serializer
     * @param RowModifier\ExternalCategoryManagementFactory $externalCategoryManagementFactory
     * @param string                                        $fileCsvImportSourceFile
     * @param string                                        $attributeSetCode
     */
    public function __construct(
        ObjectManagerFactory $objectManagerFactory,
        Stopwatch $stopwatch,
        ConsoleOutput $consoleOutput,
        Log $log,
        RowModifier\SourceIteratorFactory $sourceIteratorFactory,
        FileCsvFactory $fileCsvFactory,
        RowModifier\ItemMapperFactory $itemMapperFactory,
        ManagerInterface $messageManager,
        RowModifier\ProductUrlKeyFactory $productUrlKeyFactory,
        LoggerInterface $logger,
        Filesystem $filesystem,
        HttpCsvFactory $httpCsvfactory,
        Config $eavConfig,
        ScopeConfigInterface $scopeConfig,
        CategoryListInterface $categoryRepositoryList,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CategoryRepositoryInterface $categoryRepository,
        LineFormatterMulti $lineFormatterMulti,
        \Magento\Catalog\Model\Product\Url $urlKeyFormatter,
        RowModifier\ConfigurableBuilderFactory $configurableBuilderFactory,
        RowModifier\AttributeOptionCreatorFactory $attributeOptionCreatorFactory,
        RowModifier\ProductDisablerFactory $productDisablerFactory,
        RowModifier\TemplateFieldParserFactory $templateFieldParserFactory,
        SerializerInterface $serializer,
        RowModifier\ExternalCategoryManagementFactory $externalCategoryManagementFactory,
        string $fileCsvImportSourceFile = self::SOURCE_FILE,
        string $attributeSetCode = self::ATTRIBUTE_SET
    ) {
        parent::__construct($objectManagerFactory, $stopwatch, $consoleOutput, $log);

        $this->sourceIteratorFactory = $sourceIteratorFactory;
        $this->fileCsvFactory = $fileCsvFactory;
        $this->itemMapperFactory = $itemMapperFactory;
        $this->messageManager = $messageManager;
        $this->productUrlKeyFactory = $productUrlKeyFactory;
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->httpCsvFactory = $httpCsvfactory;
        $this->eavConfig = $eavConfig;
        $this->scopeConfig = $scopeConfig;
        $this->categoryRepositoryList = $categoryRepositoryList;
        $this->categoryRepository = $categoryRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->urlKeyFormatter = $urlKeyFormatter;
        $this->configurableBuilderFactory = $configurableBuilderFactory;
        $this->attributeOptionCreatorFactory = $attributeOptionCreatorFactory;
        $this->productDisablerFactory = $productDisablerFactory;
        $this->templateFieldParserFactory = $templateFieldParserFactory;
        $this->serializer = $serializer;
        $this->lineFormatterMulti = $lineFormatterMulti;
        $this->externalCategoryManagementFactory = $externalCategoryManagementFactory;
        $this->fileCsvImportSourceFile = $fileCsvImportSourceFile;
        $this->attributeSetCode = $attributeSetCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return [
            'behavior' => \Magento\ImportExport\Model\Import::BEHAVIOR_REPLACE,
            'entity' => 'catalog_product',
            'validation_strategy' => ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_SKIP_ERRORS,
            'allowed_error_count' => 100,
        ];
    }

    /**
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \League\Csv\Exception
     *
     * @return array
     */
    public function getItems()
    {
        $items = [];

        $this->initColorMapping();
        $this->initSizeMapping();
        $this->initSeasonMapping();
        $this->initCategoryMapping();

        $waardijkSizeEu = function ($item) {
            $sizeGroup = (int) $item['maatbalk'];
            $size = $item['maat'];

            foreach ($this->sizeMapping as $sizeMap) {
                if ($sizeMap['group'] === $sizeGroup && $sizeMap['group_size'] === $size) {
                    return str_replace(['.66', '.33'], [' 2/3', ' 1/3'], $sizeMap['eu_size']);
                }
            }

            $this->logger->info(__('Could not map the size to the EU size. Group %s, Size %s', $sizeGroup, $size));

            return str_replace(['.66', '.33'], [' 2/3', ' 1/3'], $size);
        };

        $sku = function ($item) use ($waardijkSizeEu) {
            return strtolower($item['own_sku'] .'.'. $waardijkSizeEu($item));
        };

        try {
            $sourceIterator = $this->sourceIteratorFactory->create([
                'identifier' => $sku,
                'iterator' => $this->fileCsvFactory->create([
                    'headers' => AcaDataMappingStorage::ACA_FILE_HEADER_COLUMNS,
                    'requestFile' => $this->fileCsvImportSourceFile
                ])->getIterator()
            ]);

            $sourceIterator->setItems($items);
            $sourceIterator->process();
        } catch (FileSystemException $e) {
            $this->messageManager->addExceptionMessage($e);
        }

        $urlKey = function ($item) use ($sku) {
            return implode(' ', [$item['merk'], $item['model'], $sku($item)]);
        };

        $qty = ItemMapperTools::getField('voorraad_totaal');

        $supplierSizeGroup = function ($item) {
            $sizeGroup = (int) ItemMapperTools::getField('maatbalk')($item);
            foreach ($this->sizeMapping as $sizeMap) {
                if ($sizeMap['group'] === $sizeGroup) {
                    return $sizeMap['group_label'];
                }
            }

            return null;
        };

        // Map all magento attributes.
        $itemMapper = $this->itemMapperFactory->create([
            'mapping' => [
                'sku' => $sku,
                'configurable_sku' => ItemMapperTools::getField('own_sku'),
                'configurable_url_key' => function ($item) {
                    return implode(' ', [$item['merk'], $item['model'], ItemMapperTools::getField('own_sku')($item)]);
                },
                'attribute_set_code' => $this->attributeSetCode,
                'product_type' => 'simple',
                'categories' => function ($item) {
                    $categories = [];
                    $groupNumber = substr($item['own_sku'], 0, 3);
                    $groupMatches = [
                        $groupNumber[0].'__',
                        substr($groupNumber, 0, 2).'_',
                        $groupNumber
                    ];

                    foreach ($groupMatches as $groupMatch) {
                        if (isset($this->categoryMapping[$groupMatch])) {
                            $categories[] = $this->categoryMapping[$groupMatch];
                        }
                    }

                    return implode(',', $categories);
                },
                'product_websites' => 'base',
                'format_artikelgroepomschr_kort' => function ($item) {
                    return mb_strtolower(str_replace(['.', ','], '', $item['artikelgroepomschr_kort']));
                },
                'format_kleuromschrijving' => function ($item) {
                    return mb_strtolower(str_replace(['.', ','], '', $item['kleuromschrijving']));
                },
                'format_omschrijving_voetbed' => function ($item) {
                    return mb_strtolower(str_replace(['.', ','], '', $item['omschrijving_voetbed']));
                },
                'format_zool_omschr' => function ($item) {
                    return mb_strtolower(str_replace(['.', ','], '', $item['zool_omschr']));
                },
                'format_materiaalomschrijving' => function ($item) {
                    return mb_strtolower(str_replace(['.', ','], '', $item['materiaalomschrijving']));
                },
                'name' => function ($item) use ($sku) {
                    return implode(' ', [$item['merk'], $item['model'], $sku($item)]);
                },
                'short_name' => function ($item) {
                    return implode(' ', [$item['merk'], $item['model']]);
                },
                'merk' => ItemMapperTools::getField('merk'),
                'model' => ItemMapperTools::getField('model'),
                'configurable_name' => function ($item) {
                    return implode(' ', [$item['merk'], $item['model'], $item['own_sku']]);
                },
                'configurable_short_name' => function ($item) {
                    return implode(' ', [$item['merk'], $item['model']]);
                },
                'description' => '<!--empty-->',
                'product_online' => '1',
                'url_key' => $urlKey,
                'visibility' => 'Not Visible Individually',
                'price' => ItemMapperTools::getField('price'),
                'special_price_from_date' => function ($item) {
                    $price = $item['price'];
                    $specialPrice = $item['special_price'];
                    if (! $specialPrice) {
                        return '';
                    }

                    return $specialPrice < $price ? (new \DateTime())->format('Y-m-d') : '';
                },
                'special_price' => function ($item) {
                    $price = $item['price'];
                    $specialPrice = $item['special_price'];
                    if (! $specialPrice) {
                        return '';
                    }

                    return $specialPrice < $price ? $specialPrice : '';
                },
                'qty' => $qty,
                'use_config_min_qty' => '1',
                'use_config_backorders' => '1',
                'use_config_min_sale_qty' => '1',
                'use_config_max_sale_qty' => '1',
                'is_in_stock' => function ($item) use ($qty) {
                    return $qty($item) > 0;
                },
                'use_config_notify_stock_qty' => '1',
                'use_config_manage_stock' => '1',
                'use_config_qty_increments' => '1',
                'use_config_enable_qty_inc' => '1',
                'base_image' => function ($item) {
                    return $this->getMediaImages($item)[0] ?? null;
                },
                'small_image' => function ($item) {
                    return $this->getMediaImages($item)[0] ?? null;
                },
                'thumbnail_image' => function ($item) {
                    return $this->getMediaImages($item)[0] ?? null;
                },
                'swatch_image' => function ($item) {
                    return $this->getMediaImages($item)[0] ?? null;
                },
                'additional_images' => function ($item) {
                    return implode(',', $this->getMediaImages($item) ?? null);
                },
                'gender' => ItemMapperTools::getField('type_omschr'),
                'color' => function ($item) {
                    $sku = strtolower($item['own_sku']);
                    $color = explode('.', $sku)[1]; // Center part of SKU (e.g. 101.00.002).

                    if (isset(AcaDataMappingStorage::COLOR_CODE_MAPPING[$color])) {
                        $colorString = AcaDataMappingStorage::COLOR_CODE_MAPPING[$color];

                        return $this->colorMapping[$colorString] ?? $colorString;
                    }

                    $this->logger->info(__('No color found with code %s in color mapping (SKU: %s)', $color, $sku));

                    return null;
                },
                'waardijk_shoe_color_manu' => ItemMapperTools::getField('kleuromschrijving'),
                'waardijk_heel_height' => function ($item) {
                    foreach (range(0, 12) as $number) {
                        if (strpos($item['artikelgroepomschrijving'], sprintf('ca. %s cm', $number)) !== false) {
                            return $number;
                        }
                    }

                    return null;
                },
                'waardijk_wijdtemaat' => ItemMapperTools::getField('wijdte'),
                'wijdte' => ItemMapperTools::getField('wijdte'),
                'waardijk_modelname' => ItemMapperTools::getField('model'),
                'waardijk_brandname' => ItemMapperTools::getField('merk'),
                'waardijk_size_eu' => $waardijkSizeEu,
                'waardijk_size_supplier' => function ($item) use ($waardijkSizeEu, $supplierSizeGroup) {
                    $euSize = $waardijkSizeEu($item);
                    $sizeGroup = $supplierSizeGroup($item);
                    foreach ($this->sizeMapping as $sizeMap) {
                        if ($sizeMap['group_label'] === $sizeGroup && $sizeMap['eu_size'] === $euSize) {
                            return $sizeMap['group_size'];
                        }
                    }

                    return '';
                },
                'waardijk_size_supplier_group' => $supplierSizeGroup,
                'waardijk_season' => function ($item) {
                    return $this->seasonMapping[$item['seizoensomschrijving']] ?? 'A - Bovenaan';
                },
                'waardijk_zool_materiaal' => ItemMapperTools::getField('zool_omschr'),
                'waardijk_mat_top' => ItemMapperTools::getField('materiaalomschrijving'),
                'waardijk_shoe_footbed_2' => function ($item) {
                    switch ($item['voetbed']) {
                        case 'VV':
                            return 'Nee';
                        case 'UV':
                            return 'Ja';
                    }
                },
                'waardijk_mat_voering' => ItemMapperTools::getField('voerings'),
                'waardijk_maat_label' => function ($item) use ($supplierSizeGroup) {
                    $sizeGroup = $supplierSizeGroup($item);
                    $supplierSizeLabel = 'Brand';
                    if (strpos((string) $sizeGroup, 'e.u.') !== false) {
                        $supplierSizeLabel = null;
                    } elseif (strpos((string) $sizeGroup, 'uk') !== false) {
                        $supplierSizeLabel = 'UK';
                    }

                    return $supplierSizeLabel;
                },
                'ean' => ItemMapperTools::getField('ean'),
                'subproducts_in_stock' => function ($item) {
                    return $item['voorraad_totaal'] ? 1000 : 0;
                }
            ]
        ]);
        $itemMapper->setItems($items);
        $itemMapper->process();

        $templateFieldParser = $this->templateFieldParserFactory->create([
            'templateFields' => [
                'short_description' => "Dit artikel {{if format_artikelgroepomschr_kort}}betreft een {{var format_artikelgroepomschr_kort}}{{else}}is{{/if}} van het merk {{var merk}}. De modelnaam is {{var model}} en de kleur is {{var format_kleuromschrijving}}. {{depend wijdte}}Het product heeft een wijdte {{var wijdte}}{{if format_omschrijving_voetbed}} en een {{var format_omschrijving_voetbed}}.{{else}}.{{/if}}{{/depend}} {{depend format_zool_omschr}}De zool is van {{var format_zool_omschr}}{{if format_materiaalomschrijving}} en het materiaal bovenkant is van {{var format_materiaalomschrijving}}.{{else}}.{{/if}}{{/depend}}",
                'meta_description' => "Dit artikel van het merk {{var merk}} met de modelnaam {{var model}} in de kleur {{var format_kleuromschrijving}} koopt u nu online bij Waardijk.nl. Inclusief gratis verzending!"
            ]
        ]);
        $templateFieldParser->setItems($items);
        $templateFieldParser->process();

        //attributeOptionCreator
        $attributeOptionCreator = $this->attributeOptionCreatorFactory->create([
            'attributes' => [
                'waardijk_mat_voering',
                'waardijk_brandname',
                'waardijk_size_eu',
                'color',
                'waardijk_size_supplier_group',
                'waardijk_wijdtemaat',
                'waardijk_maat_label',
                'waardijk_product_size',
                'waardijk_shoe_class_type',
                'waardijk_shoe_footbed_2',
                'waardijk_season'
            ]
        ]);
        $attributeOptionCreator->setItems($items);
        $attributeOptionCreator->process();

        $this->buildConfigurables($items);

        $externalCategoryManagement = $this->externalCategoryManagementFactory->create();
        $externalCategoryManagement->setItems($items);
        $externalCategoryManagement->process();

        $items = array_map(function ($item) use ($items) {
            if (isset($item['configurable_variations'])) {
                $inStock = [];
                $variations = $this->lineFormatterMulti->decode($item['configurable_variations']);
                foreach ($variations as $variation) {
                    if ($items[$variation['sku']]['qty']) {
                        $inStock[$variation['sku']] = 1;
                    }
                }
                $item[AcaDataMappingStorage::ATTRIBUTE_CODE_EXCLUDE_FROM_FEED] = (int) (count($inStock) < 3);
            }
            return $item;
        }, $items);

        $productUrlKey = $this->productUrlKeyFactory->create();
        $productUrlKey->setItems($items);
        $productUrlKey->process();

        $productStockDisabler = $this->productDisablerFactory->create([
            'profile' => 'product',
            'force' => true
        ]);
        $productStockDisabler->setItems($items);
        $productStockDisabler->process();

        return $items;
    }

    /**
     * @throws GuzzleException
     *
     * @return void
     */
    private function initColorMapping(): void
    {
        $data = $this->httpCsvFactory->create([
            'requestUrl' => 'https://docs.google.com/spreadsheets/d/1YkCmTlKfjFQURJRIUxV7xXmyomxfjHgUZZfYWSVvpJg/pub?gid=1879578340&single=true&output=csv'
        ])->getIterator();

        foreach ($data as $row) {
            if ($row['Nieuwe kleur']) {
                $this->colorMapping[$row['Huidige kleur']] = ucfirst($row['Nieuwe kleur']);
            }
        }

        $this->logger->info('Loaded color mapping');
    }

    /**
     * @throws GuzzleException
     *
     * @return void
     */
    private function initSizeMapping(): void
    {
        $groupLabels = [];
        $groups = [];
        $sizes = [];
        $data = $this->httpCsvFactory->create([
            'requestUrl' => 'https://docs.google.com/spreadsheets/d/1m63C0GS99g2HIli-3zqx158y3veRSGbx5aurJQnebdQ/pub?gid=2114743663&single=true&output=csv'
        ])->getIterator();

        foreach ($data as $row) {
            if (empty($groupLabels)) {
                foreach ($row as $brand => $value) {
                    $groupLabels[$brand] = $brand;
                }
            }
            if (empty($groups)) {
                foreach ($row as $key => $value) {
                    $groups[$key] = (int) $value;
                }
            }

            $rowKey = $row['E.U. standaard'];
            foreach ($row as $key => $value) {
                if (empty($rowKey) || empty($value) || ! isset($groups[$key]) || empty($groups[$key])) {
                    continue;
                }

                $sizes[$rowKey][$groups[$key]] = [$groupLabels[$key], $value];
            }
        }

        foreach ($sizes as $euSize => $groupSizes) {
            foreach ($groupSizes as $group => [$groupLabel, $groupSize]) {
                $this->sizeMapping[] = [
                    'eu_size' => str_replace(',', '.', $euSize),
                    'group' => $group,
                    'group_label' => $groupLabel,
                    'group_size' => str_replace(',', '.', $groupSize),
                ];
            }
        }
    }

    /**
     * @throws LocalizedException
     *
     * @return void
     */
    private function initSeasonMapping(): void
    {
        $attribute = $this->eavConfig->getAttribute('catalog_product', 'waardijk_season');
        $attributeValues = $attribute->getSource()->getAllOptions();

        if ($mapping = $this->scopeConfig->getValue('waardijk_aca_import/season/map_season')) {
            $mapping = $this->serializer->unserialize($mapping);
            foreach ($mapping as $map) {
                foreach ($attributeValues as $attributeValue) {
                    if ($attributeValue['value'] === $map['attribute_value']) {
                        $this->seasonMapping[$map['value']] = $attributeValue['label'];
                    }
                }
            }
        }
    }

    /**
     * @throws NoSuchEntityException
     *
     * @return void
     */
    private function initCategoryMapping(): void
    {
        $categories = $this->categoryRepositoryList->getList($this->searchCriteriaBuilder->create());
        foreach ($categories->getItems() as $category) {
            if (! $category->getData('import_group')) {
                continue;
            }

            $structure = explode('/', $category->getPath());
            $pathSize = count($structure);
            if ($pathSize > 2) {
                $path = [];
                for ($i = 1; $i < $pathSize; $i++) {
                    $item = $this->categoryRepository->get($structure[$i]);
                    if ($item instanceof CategoryInterface) {
                        $path[] = $item->getName();
                    }
                }

                $importGroups = explode(',', $category->getData('import_group'));
                foreach ($importGroups as $importGroup) {
                    // additional options for category referencing: name starting from base category, or category id
                    $this->categoryMapping[$importGroup] = implode('/', $path);
                }
            }
        }
    }

    /**
     * @param array $item
     *
     * @return array
     */
    private function getMediaImages(array $item): array
    {
        $ownSku = $this->urlKeyFormatter->formatUrlKey($item['own_sku'] ?? $item['configurable_sku']);
        $dir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath().'import'.'/';
        $images = [];
        $possibleFileNames = [
            $ownSku,
            $ownSku.'-1',
            $ownSku.'-2',
            $ownSku.'-3',
            $ownSku.'-4',
        ];

        foreach ($possibleFileNames as $fileName) {
            if (file_exists($dir.$fileName.'.png')) {
                $images[] = '/'.$fileName.'.png';
            } elseif (file_exists($dir.$fileName.'.jpg')) {
                $images[] = '/'.$fileName.'.jpg';
            }
        }

        return $images;
    }

    /**
     * Build configurable products from the imported simples.
     *
     * @param array $items
     *
     * @return void
     */
    private function buildConfigurables(array &$items): void
    {
        $configurableBuilder = $this->configurableBuilderFactory->create([
            'attributes' => function () {
                return ['waardijk_size_eu'];
            },
            'configurableSku' => function ($item) {
                return $item['is_in_stock'] === false ? null : $item['configurable_sku'];
            },
            'configurableValues' => [
                'name' => function ($item) {
                    return $item['configurable_name'];
                },
                'short_name' => function ($item) {
                    return $item['configurable_short_name'];
                },
                'qty' => 0,
                'url_key' => function ($item) {
                    return $item['configurable_url_key'];
                },
                'is_in_stock' => function ($item) {
                    return isset($this->getMediaImages($item)[0]) ? 1 : 0;
                },
                'visibility' => 'Catalog, Search',
                'use_config_backorders' => 0,
                'backorders' => 1,
                'base_image' => function ($item) {
                    return $this->getMediaImages($item)[0] ?? null;
                },
                'small_image' => function ($item) {
                    return $this->getMediaImages($item)[0] ?? null;
                },
                'thumbnail_image' => function ($item) {
                    return $this->getMediaImages($item)[0] ?? null;
                },
                'additional_images' => function ($item) {
                    return implode(',', $this->getMediaImages($item) ?? null);
                },
            ],
            'simpleValues' => [
                'visibility' => 'Not Visible Individually'
            ],
            $splitOnValue = null,
            'enableFilterConfigurable' => false
        ]);

        $configurableBuilder->setItems($items);
        $configurableBuilder->process();
    }
}
