<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Logger\Log;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class ProductUrlKey
 *
 * @todo high/low priority items, visible products should get more priority than invisible products (relevant)?
 * @todo Implement stopwatch to check the performance of the item.
 *
 * @package Ho\Import\RowModifier
 */
class ProductUrlKey extends AbstractRowModifier
{
    /**
     * URL Key Formatter
     *
     * @var \Magento\Catalog\Model\Product\Url
     */
    private $urlKeyFormatter;

    /**
     * URL Finder
     *
     * @var UrlFinderInterface
     */
    private $urlFinderInterface;

    /**
     * Product Collection
     *
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * Id to SKU matching
     *
     * @var string[]
     */
    private $idToSku = [];

    /**
     * Matched URL's
     *
     * @var string[]
     */
    private $urls = [];

    /**
     * Product URL Suffix per store view
     *
     * @var string[]
     */
    private $productUrlSuffix = [];

    /**
     * Scope Config
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ConsoleOutput                      $consoleOutput
     * @param Log                                $log
     * @param \Magento\Catalog\Model\Product\Url $urlKeyFormatter
     * @param UrlFinderInterface                 $urlFinderInterface
     * @param ScopeConfigInterface               $scopeConfig
     * @param CollectionFactory                  $productCollectionFactory
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        Log $log,
        \Magento\Catalog\Model\Product\Url $urlKeyFormatter,
        UrlFinderInterface $urlFinderInterface,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $productCollectionFactory
    ) {
        parent::__construct($consoleOutput, $log);

        $this->urlKeyFormatter = $urlKeyFormatter;
        $this->urlFinderInterface = $urlFinderInterface;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->consoleOutput = $consoleOutput;
    }

    /**
     * Get the proper URL keys
     *
     * @return void
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>Checking url_keys and make sure they are unique</info>");
        $this->log->addInfo('Checking url_keys and make sure they are unique');

        $this->initProductToSku();
        foreach ($this->items as $identifier => &$item) {
            //check for existence.
            if (empty($item['url_key'])) {
                $this->consoleOutput->writeln("<comment>ProductUrlKey: url_key not found {$identifier}</comment>");
                $this->log->addInfo('ProductUrlKey: url_key not found '.$identifier);
            }
            $item['url_key'] = $this->getUrlKey($item['url_key'], $identifier);
        }

        $this->reset();
    }

    /**
     * Get the product URL-key and check for availability
     *
     * @param string $urlKey
     * @param string $identifier
     *
     * @return mixed
     */
    public function getUrlKey($urlKey, $identifier)
    {
        $urlKey = mb_strtolower($urlKey);
        $options = [
            $this->urlKeyFormatter->formatUrlKey($urlKey),
            $this->urlKeyFormatter->formatUrlKey($identifier . '-' . $urlKey),
            $this->urlKeyFormatter->formatUrlKey($identifier . '-' . $urlKey . '-1'),
            $this->urlKeyFormatter->formatUrlKey($identifier . '-' . $urlKey . '-2'),
            $this->urlKeyFormatter->formatUrlKey($identifier . '-' . $urlKey . '-3'),
            $this->urlKeyFormatter->formatUrlKey($identifier . '-' . $urlKey . '-4'),
        ];
        $options = array_unique($options);

        foreach ($options as $option) {
            if (isset($this->urls[$option])) {
                continue;
            }

            $foundKeys = $this->urlFinderInterface->findAllByData([
                'request_path' => $option . $this->getProductUrlSuffix(0)
            ]);

            //The URL Key doesn't exist
            if (count($foundKeys) <= 0) {
                $this->urls[$option] = true;
                return $option;
            }
            $isKeyCurrent = array_reduce($foundKeys, function ($carry, UrlRewrite $item) use ($identifier) {
                if ($carry) {
                    return $carry;
                }


                if (isset($this->idToSku[$item->getEntityId()])
                    && $this->idToSku[$item->getEntityId()] == $identifier) {
                    return true;
                }
                return false;
            });

            if ($isKeyCurrent) {
                $this->urls[$option] = true;
                return $option;
            }
            continue;
        }

        $this->consoleOutput->writeln(
            "<error>Can not find available URL-key for {$identifier}, you might run into trouble</error>"
        );
        $this->log->addError("Can not find available URL-key for {$identifier}, you might run into trouble");
    }

    /**
     * Init mapping product_id to SKU
     * @return void
     */
    protected function initProductToSku()
    {
        $productCollection = $this->productCollectionFactory->create();
        $select = $productCollection->getSelect()->reset('columns')->columns(['entity_id', 'sku']);
        $this->idToSku = $productCollection->getConnection()->fetchPairs($select);
    }

    /**
     * Retrieve product rewrite suffix for store
     * @param int $storeId
     * @return string
     */
    protected function getProductUrlSuffix($storeId = null)
    {
        if (!isset($this->productUrlSuffix[$storeId])) {
            $this->productUrlSuffix[$storeId] = $this->scopeConfig->getValue(
                \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator::XML_PATH_PRODUCT_URL_SUFFIX,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        return $this->productUrlSuffix[$storeId];
    }

    /**
     * Reset the object to clear memory
     * @return void
     */
    protected function reset()
    {
        $this->idToSku = [];
        $this->urls    = [];
    }
}