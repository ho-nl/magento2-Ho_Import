<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Ho\Import\Logger\Log;
use Symfony\Component\Console\Output\ConsoleOutput;

class ProductDisabler extends AbstractRowModifier
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * Unique key in the ho_import_link table.
     * @var string
     */
    private $profile;

    /**
     * Database default connection
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;

    /**
     * @var bool
     */
    private $force;

    /**
     * @param ConsoleOutput                             $consoleOutput
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param string                                    $profile
     * @param Log                                       $log
     * @param bool                                      $force
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        \Magento\Framework\App\ResourceConnection $resource,
        string $profile,
        Log $log,
        bool $force = false
    ) {
        parent::__construct($consoleOutput, $log);

        $this->profile = $profile;
        $this->connection  = $resource->getConnection();
        $this->consoleOutput = $consoleOutput;
        $this->force = $force;
    }

    /**
     * Process the data, append items that need to be disabled.
     *
     * @return void
     */
    public function process()
    {
        if (count($this->items)  <= 100 && $this->force === false) {
            $this->consoleOutput->writeln(
                "<info>ProductDisabler: Skipping because less then 100 products are imported, use force option to disable anyway</info>"
            );
            return;
        }

        $identifiers = array_map(function ($identifier) {
            return (string) $identifier;
        }, array_keys($this->items));

        $this->appendNewItemsToDb($identifiers);
        $itemsToDisable = $this->getItemsToDisable($identifiers);

        $itemsToDisable = array_map(function ($identifier) {
            return [
                'sku' => $identifier,
                'product_online' => (string) 0,
            ];
        }, $itemsToDisable);

        $count = count($itemsToDisable);
        $this->consoleOutput->writeln("<info>ProductDisabler: Disabling {$count} products</info>");
        $this->log->addInfo("ProductDisabler: Disabling {$count} products");

        $this->items += $itemsToDisable;
    }

    /**
     * Append new items to the ho_import_link table.
     *
     * @param string[] $identifiers
     * @return void
     */
    public function appendNewItemsToDb($identifiers)
    {
        $insertData = array_map(function ($identifier) {
            return ['profile' => $this->profile, 'identifier' => $identifier];
        }, $identifiers);
        $table      = $this->connection->getTableName('ho_import_link');
        $this->connection->insertOnDuplicate($table, $insertData);
    }

    /**
     * Get all the items from ho_import_link table that need to be disabled
     *
     * @param string[] $identifiers
     *
     * @todo Only find non-disabled products
     * @return string[]
     */
    private function getItemsToDisable($identifiers)
    {
        $table  = $this->connection->getTableName('ho_import_link');
        $select = $this->connection->select()
            ->from($table, ['ho_import_link.identifier'])
            ->where('profile = ?', $this->profile)
            ->where('identifier NOT IN(?)', $identifiers)
            ->join(
                $this->connection->getTableName('catalog_product_entity'),
                'ho_import_link.identifier = catalog_product_entity.sku'
            );

        return $this->connection->fetchCol($select);
    }
}
