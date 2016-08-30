<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Magento\Catalog\Api\ProductAttributeOptionManagementInterface as OptionManagement;
use Magento\Eav\Model\Entity\Attribute\OptionFactory;
use Symfony\Component\Console\Output\ConsoleOutput;

class AttributeOptionCreator extends AbstractRowModifier
{

    /**
     * Option management interface
     *
     * @var OptionManagement
     */
    protected $optionManagement;

    /**
     * Entity attribute option model factory
     *
     * @var OptionFactory
     */
    protected $optionFactory;

    /**
     * Array of attributes to automatically fill
     *
     * @var array
     */
    protected $attributes;


    /**
     * AttributeOptionCreator constructor.
     *
     * @param OptionManagement $optionManagement
     * @param OptionFactory    $optionFactory
     * @param ConsoleOutput    $consoleOutput
     */
    public function __construct(
        OptionManagement $optionManagement,
        OptionFactory $optionFactory,
        ConsoleOutput $consoleOutput
    ) {
        parent::__construct($consoleOutput);
        $this->optionManagement = $optionManagement;
        $this->optionFactory = $optionFactory;
    }


    /**
     * Automatically create attribute options.
     *
     * @return void
     */
    public function process()
    {
        $attributes = implode(', ', $this->getAttributes());
        $this->consoleOutput->writeln("<info>Creating attribute options for: {$attributes}</info>");
        foreach ($this->getAttributes() as $attribute) {
            $this->createForAttribute($attribute);
        }
    }


    /**
     * Create attribute options
     *
     * @param string $attributeCode
     * @return void
     */
    public function createForAttribute(string $attributeCode)
    {
        $uniqueOptions = $this->getNonExistingAttributes($attributeCode);

        $items = $this->optionManagement->getItems($attributeCode);
        foreach ($items as $item) {
            if (in_array($item->getLabel(), $uniqueOptions)) {
                unset($uniqueOptions[$item->getLabel()]);
            }
        }

        foreach ($uniqueOptions as $optionLabel) {
            $optionModel = $this->optionFactory->create();
            $optionModel->setAttributeId($attributeCode);
            $optionModel->setLabel($optionLabel);

            $this->optionManagement->add($attributeCode, $optionModel);
        }
    }


    /**
     * Get a list of attributes that need to be created.
     *
     * @param string $attribute
     *
     * @return array
     */
    protected function getNonExistingAttributes(string $attribute)
    {
        $uniqueValues = [];
        foreach ($this->items as $identifier => $item) {
            if (empty($item[$attribute])) {
                continue;
            }

            if (! is_string($item[$attribute])) {
                $this->consoleOutput->writeln(
                    "<error>AttributeOptionCreator: Invalid value for {$attribute} {$identifier}</error>"
                );
                continue;
            }
            $uniqueValues[$item[$attribute]] = $item[$attribute];
        }
        return $uniqueValues;
    }


    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }


    /**
     * @param array $attributes
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }
}
