<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\RowModifier;

use Magento\Catalog\Api\ProductAttributeOptionManagementInterface as OptionManagement;
use Magento\Eav\Model\Entity\Attribute\OptionFactory;

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
     * @param OptionManagement    $optionManagement
     * @param OptionFactory       $optionFactory
     */
    public function __construct(
        OptionManagement $optionManagement,
        OptionFactory $optionFactory
    ) {
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
        foreach ($this->items as $item) {
            if (!isset($item[$attribute]) || empty($item[$attribute])) {
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
