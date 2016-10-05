<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Setup;

use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    /**
     * EAV setup factory
     *
     * @var CategorySetupFactory
     */
    private $categorySetupFactory;

    /**
     * Init
     *
     * @param CategorySetupFactory $categorySetupFactory
     */
    public function __construct(CategorySetupFactory $categorySetupFactory)
    {
        $this->categorySetupFactory = $categorySetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /** @var \Magento\Catalog\Setup\CategorySetup $categorySetup */
        $categorySetup = $this->categorySetupFactory->create(['setup' => $setup]);

        $categorySetup->addAttribute(CategoryAttributeInterface::ENTITY_TYPE_CODE, 'external_id', [
            'type' => 'varchar',
            'label' => 'External ID',
            'input' => 'text',
            'required' => false,
            'sort_order' => 9,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL
        ]);

        $categorySetup->addAttribute(CategoryAttributeInterface::ENTITY_TYPE_CODE, 'product_price_margin', [
            'type' => 'varchar',
            'label' => 'Product Price Margin',
            'input' => 'text',
            'required' => false,
            'sort_order' => 9,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL
        ]);
    }
}
