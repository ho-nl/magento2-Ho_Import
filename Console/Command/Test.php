<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Console\Command;

use Ho\Import\RowModifier\ImageDownloader;
use Ho\Import\RowModifier\AttributeOptionCreator;
use Ho\Import\RowModifier\ConfigurableBuilder;
use Ho\Promopost\Import\RowModifier\PriceData;
use Ho\Promopost\Import\RowModifier\StockData;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\ImportExport\Model\Import;
use Magento\Framework\App\Filesystem\DirectoryList;
use Prewk\XmlStringStreamer;
use Ho\Import\Mapper\ProductMapper;
use Symfony\Component\Console\Output\OutputInterface;

class Test extends AbstractCommand
{

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var ProductMapper
     */
    private $productMapper;

    /**
     * @var ImageDownloader
     */
    private $imageDownloader;

    /**
     * @var AttributeOptionCreator
     */
    private $attributeOptionCreator;

    /**
     * @var ConfigurableBuilder
     */
    private $configurableBuilder;

    /**
     * @var PriceData
     */
    private $priceData;

    /**
     * @var StockData
     */
    private $stockData;


    /**
     * Test constructor.
     *
     * @param ObjectManagerFactory   $objectManagerFactory
     * @param DirectoryList          $directoryList
     * @param ProductMapper          $productMapper
     * @param ImageDownloader        $imageDownloader
     * @param AttributeOptionCreator $attributeOptionCreator
     * @param ConfigurableBuilder    $configurableBuilder
     * @param PriceData              $priceData
     * @param StockData              $stockData
     */
    public function __construct(
        ObjectManagerFactory $objectManagerFactory,
        DirectoryList $directoryList,
        ProductMapper $productMapper,
        ImageDownloader $imageDownloader,
        AttributeOptionCreator $attributeOptionCreator,
        ConfigurableBuilder $configurableBuilder,
        PriceData $priceData,
        StockData $stockData
    ) {
        parent::__construct($objectManagerFactory);
        $this->directoryList = $directoryList;
        $this->productMapper = $productMapper;
        $this->imageDownloader = $imageDownloader;
        $this->attributeOptionCreator = $attributeOptionCreator;
        $this->configurableBuilder = $configurableBuilder;
        $this->priceData = $priceData;
        $this->stockData = $stockData;
    }


    /**
     * Set up the test
     */
    protected function configure()
    {
        $this->setName('ho:import:test')
            ->setDescription('Import Simple Products ');
        $this->setBehavior(Import::BEHAVIOR_REPLACE);
        $this->setEntityCode('catalog_product');
        parent::configure();
    }


    /**
     * Implement a generator to yield al items form the XML file.
     *
     * @param string $file
     * @param int $limit
     *
     * @return \Generator
     */
    protected function getSourceStreamer($file, $limit = PHP_INT_MAX)
    {
        $file = $this->directoryList->getRoot().$file;

        $streamer = XmlStringStreamer::createUniqueNodeParser($file, ['uniqueNode' => 'PRODUCT']);
        while (($node = $streamer->getNode()) && $limit > 0) {
            $limit--;
            yield (array) new \SimpleXMLElement($node);
        }
    }


    /**
     * {@inheritdoc}
     */
    protected function getEntities(OutputInterface $output)
    {
        $data = [];

        $start = microtime(true);

        foreach (['/docs/mob/prodinfo_NL.xml', '/docs/mob/prodinfo_TEXTILE_NL.xml'] as $fileName) {
            $prodInfo = $this->getSourceStreamer($fileName);
            foreach ($prodInfo as $item) {
                try {
                    $sku = $this->productMapper->getSku($item);
                    $data[$sku] = $this->productMapper->mapItem($item);
                } catch (\Exception $e) {
                    $output->writeln($e->getMessage());
                }
            }
        }

        //stockData
        $this->stockData->setItems($data);
        $this->stockData->process();

        //priceData
        $this->priceData->setItems($data);
        $this->priceData->process();

        //imageDownloader
        $this->imageDownloader->setItems($data);
        $this->imageDownloader->setUseExisting(true);
        $this->imageDownloader->setConcurrent(10);
        $this->imageDownloader->process();

        //attributeOptionCreator
        $this->attributeOptionCreator->setItems($data);
        $this->attributeOptionCreator->setAttributes(['color', 'size']);
        $this->attributeOptionCreator->process();

        $this->buildConfigurables($data);

        $timeTaken = round(microtime(true) - $start, 2);
        $perSecond = round(count($data) / $timeTaken, 2);
        $output->writeln("Processed Items: {$timeTaken}sec, {$perSecond} products / sec");

        return $data;
    }


    /**
     * Build configurable products from the imported simples.
     *
     * @param array &$data
     * @return void
     */
    public function buildConfigurables(array &$data)
    {
        $this->configurableBuilder->setItems($data);
        $this->configurableBuilder->setAttributes(function (&$item) {
            $attributes = [];
            if (!empty($item['color'])) {
                $attributes[] = 'color';
            }
            if (!empty($item['size'])) {
                $attributes[] = 'size';
            }
            return $attributes;
        });
        $this->configurableBuilder->setConfigurableSku(function (&$item) {
            return $item['configurable_sku'];
        });
        $this->configurableBuilder->setConfigurableValues([
            'name'    => function ($item) {
                return substr($item['name'], 0, strpos($item['name'], $item['sku']));
            },
            'url_key' => function ($item) {
                return $item['sku'];
            },
            'qty' => null,
            
        ]);
        $this->configurableBuilder->setSimpleValues([
            'visibility' => 'Not Visible Individually',
            'categories' => null
        ]);

        $this->configurableBuilder->process();
    }
}
