<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Console\Command;

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


    public function __construct(
        ObjectManagerFactory $objectManagerFactory,
        DirectoryList $directoryList,
        ProductMapper $productMapper
    ) {
        parent::__construct($objectManagerFactory);
        $this->directoryList = $directoryList;
        $this->productMapper = $productMapper;
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
            $prodInfo = $this->getSourceStreamer($fileName, 100);
            foreach ($prodInfo as $item) {
                try {
                    $sku = $this->productMapper->getSku($item);
                    $data[$sku] = $this->productMapper->mapItem($item);
                } catch (\Exception $e) {
                    $output->writeln($e->getMessage());
                }
            }
        }

        $timeTaken = microtime(true) - $start;
        $perSecond = count($data) / $timeTaken;
        $output->writeln("Time taken: {$timeTaken}, products per second {$perSecond}");

        return array_values($data);
    }
}
