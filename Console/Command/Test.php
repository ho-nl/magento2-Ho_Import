<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Console\Command;

use Ho\Import\AsyncImageDownloader;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\ImportExport\Model\Import;
use Magento\Framework\App\Filesystem\DirectoryList;
use Prewk\XmlStringStreamer;
use Ho\Import\Mapper\ProductMapper;
use Symfony\Component\Console\Input\InputInterface;
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
     * Test constructor.
     *
     * @param ObjectManagerFactory $objectManagerFactory
     * @param DirectoryList        $directoryList
     * @param ProductMapper        $productMapper
     * @param AsyncImageDownloader $asyncImageDownloader
     */
    public function __construct(
        ObjectManagerFactory $objectManagerFactory,
        DirectoryList $directoryList,
        ProductMapper $productMapper,
        AsyncImageDownloader $asyncImageDownloader
    ) {
        parent::__construct($objectManagerFactory);
        $this->directoryList = $directoryList;
        $this->productMapper = $productMapper;
        $this->asyncImageDownloader = $asyncImageDownloader;
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

        $timeTaken = round(microtime(true) - $start, 2);
        $perSecond = round(count($data) / $timeTaken, 2);
        $output->writeln("Mapping Products: {$timeTaken}sec, {$perSecond} products / sec ");


        $start = microtime(true);
        $this->downloadImages($data);


        $timeTaken = round(microtime(true) - $start, 2);
        $perSecond = round(count($data) / $timeTaken, 2);
        $output->writeln("Downloading Images: {$timeTaken}sec, {$perSecond} products / sec");


        var_dump($data['MO8750-48']);exit;
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
        $this->asyncImageDownloader->download();
    }
}
