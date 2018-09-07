<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Rewrite;

class CatalogProductAttributeOptionManagement extends \Magento\Catalog\Model\Product\Attribute\OptionManagement
{
    /**
     * {@inheritdoc}
     */
    public function add($attributeCode, $option)
    {
        /** @var \Magento\Eav\Api\Data\AttributeOptionInterface[] $currentOptions */
        $currentOptions = $this->getItems($attributeCode);
        if (\is_array($currentOptions)) {
            array_walk($currentOptions, function (&$attributeOption) {
                /** @var \Magento\Eav\Api\Data\AttributeOptionInterface $attributeOption */
                $attributeOption = $attributeOption->getLabel();
            });
            if (\in_array($option->getLabel(), $currentOptions, true)) {
                return false;
            }
        }
        return $this->eavOptionManagement->add(
            \Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE,
            $attributeCode,
            $option
        );
    }
}
