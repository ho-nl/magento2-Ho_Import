<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Processor;

use Magento\Catalog\Api\ProductAttributeOptionManagementInterface as OptionManagement;
use Magento\Eav\Model\Entity\Attribute\OptionFactory;

class AttributeOptionCreator
{

    /**
     * @var array
     */
    protected $data;

    /**
     * @var OptionManagement
     */
    private $optionManagement;

    /**
     * @var OptionFactory
     */
    private $optionFactory;


    /**
     * AttributeOptionCreator constructor.
     *
     * @param OptionManagement $optionManagement
     * @param OptionFactory $optionFactory
     */
    public function __construct(
        OptionManagement $optionManagement,
        OptionFactory $optionFactory
    ) {
        $this->optionManagement = $optionManagement;
        $this->optionFactory = $optionFactory;
    }

    /**
     * Set the data array for fields to import
     *
     * @param array &$data
     */
    public function setData(array &$data)
    {
        $this->data =& $data;
    }


    /**
     * Automatically create attribute options.
     *
     * @param array $attributes
     */
    public function process(array $attributes)
    {
        foreach ($attributes as $attribute) {
            $this->createForAttribute($attribute);
        }
    }


    /**
     * Create attribute options
     *
     * @param string $attribute
     */
    public function createForAttribute(string $attribute)
    {
        $uniqueOptions = $this->getWantedAttributeOptions($attribute);

        $items = $this->optionManagement->getItems($attribute);
        foreach ($items as $item) {
            if (in_array($item->getLabel(), $uniqueOptions)) {
                unset($uniqueOptions[$item->getLabel()]);
            }
        }

        foreach ($uniqueOptions as $optionLabel) {
            $optionModel = $this->optionFactory->create();
            $optionModel->setAttributeId($attribute);
            $optionModel->setLabel($optionLabel);
            $this->optionManagement->add($attribute, $optionModel);
        }
    }


    /**
     * @param string $attribute
     */
    protected function getWantedAttributeOptions(string $attribute)
    {
        $uniqueValues = [];
        foreach ($this->data as $item) {
            if (!isset($item[$attribute]) || empty($item[$attribute])) {
                continue;
            }
            $uniqueValues[$item[$attribute]] = $item[$attribute];
        }
        return $uniqueValues;
    }
}