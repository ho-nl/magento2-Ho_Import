<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

/**
 * Created by PhpStorm.
 * User: simonprins
 * Date: 17/05/2019
 * Time: 14:11
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Logger\Log;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class AssignAllWebsites extends AbstractRowModifier
{

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        ConsoleOutput $consoleOutput,
        Log $log,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($consoleOutput, $log);
        $this->consoleOutput = $consoleOutput;
        $this->log = $log;
        $this->storeManager = $storeManager;
    }

    /**
     * Method to process the row data
     *
     * @return void
     */
    public function process()
    {
        $this->consoleOutput->writeln("<info>Assigning all products to all websites...</info>");
        $this->log->addInfo('Assigning all products to all websites...');

        $allWebsites = $this->allWebsites();

        foreach($this->items as $identifier => &$item) {
            $item['product_websites'] = $allWebsites;
        }
    }

    private function allWebsites()
    {
        return implode(',', array_map(function (\Magento\Store\Api\Data\WebsiteInterface $website) {
            return $website->getCode();
        }, $this->storeManager->getWebsites()));
    }
}
