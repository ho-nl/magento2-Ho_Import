<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Console\Command;

use Ho\Import\Processor\AsyncImageDownloader;
use Ho\Import\Processor\AttributeOptionCreator;
use Ho\Import\Processor\ConfigurableBuilder;
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
     * @var AsyncImageDownloader
     */
    private $asyncImageDownloader;

    /**
     * @var AttributeOptionCreator
     */
    private $attributeOptionCreator;

    /**
     * @var ConfigurableBuilder
     */
    private $configurableBuilder;


    /**
     * Test constructor.
     *
     * @param ObjectManagerFactory   $objectManagerFactory
     * @param DirectoryList          $directoryList
     * @param ProductMapper          $productMapper
     * @param AsyncImageDownloader   $asyncImageDownloader
     * @param AttributeOptionCreator $attributeOptionCreator
     * @param ConfigurableBuilder    $configurableBuilder
     */
    public function __construct(
        ObjectManagerFactory $objectManagerFactory,
        DirectoryList $directoryList,
        ProductMapper $productMapper,
        AsyncImageDownloader $asyncImageDownloader,
        AttributeOptionCreator $attributeOptionCreator,
        ConfigurableBuilder $configurableBuilder
    ) {
        parent::__construct($objectManagerFactory);
        $this->directoryList = $directoryList;
        $this->productMapper = $productMapper;
        $this->asyncImageDownloader = $asyncImageDownloader;
        $this->attributeOptionCreator = $attributeOptionCreator;
        $this->configurableBuilder = $configurableBuilder;
    }


    /**
     * Set up the test
     */
    protected function configure()
    {
        $this->setName('ho:import:test')
            ->setDescription('Import Simple Products ');
        $this->setBehavior(Import::BEHAVIOR_APPEND);
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
            $prodInfo = $this->getSourceStreamer($fileName, 10);
            foreach ($prodInfo as $item) {
                try {
                    $sku = $this->productMapper->getSku($item);
                    $data[$sku] = $this->productMapper->mapItem($item);
                } catch (\Exception $e) {
                    $output->writeln($e->getMessage());
                }
            }
        }


        $this->downloadImages($data);
        $this->createAttributeOptions($data);
        $this->buildConfiruables($data);

        $timeTaken = round(microtime(true) - $start, 2);
        $perSecond = round(count($data) / $timeTaken, 2);
        $output->writeln("Processed Items: {$timeTaken}sec, {$perSecond} products / sec");

        return $data;
    }


    /**
     * Download images async
     *
     * @param array &$data
     *
     * @return void
     */
    protected function downloadImages(&$data)
    {
        $this->asyncImageDownloader->setData($data);
        $this->asyncImageDownloader->setUseExisting(true);
        $this->asyncImageDownloader->setConcurrent(10);
        $this->asyncImageDownloader->process();
    }


    /**
     * Create attributes from the source data.
     *
     * @param array &$data
     * @param array $attributes
     * @return void
     */
    protected function createAttributeOptions(array &$data)
    {
        $this->attributeOptionCreator->setData($data);
        $this->attributeOptionCreator->process(['color']);
    }


    /**
     * Build configurable products from the imported simples.
     *
     * @param array &$data
     * @return void
     */
    public function buildConfiruables(array &$data)
    {
        $this->configurableBuilder->setData($data);
        $this->configurableBuilder->setAttributes(function (&$item) {
            return ['color'];
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
        ]);
        $this->configurableBuilder->setSimpleValues([
            'visibility' => 'Not Visible Individually'
        ]);

        $this->configurableBuilder->process();
    }
}
