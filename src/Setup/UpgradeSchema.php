<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        if (version_compare($context->getVersion(), '0.3.0', '<')) {
            $connection = $setup->getConnection();


            $table = $connection->newTable($connection->getTableName('ho_import_link'))
                ->setComment('Ho Import Link Table')
                ->addColumn('profile', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, [
                    'nullable' => false,
                ])
                ->addColumn('identifier', \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, 255, [
                    'nullable' => false,
                ])->addIndex(
                    $connection->getIndexName('ho_import_link', ['profile', 'identifier']),
                    ['profile', 'identifier'],
                    ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
                );

            $connection->createTable($table);
        }
    }
}
