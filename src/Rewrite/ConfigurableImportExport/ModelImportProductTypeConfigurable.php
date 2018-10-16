<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Rewrite\ConfigurableImportExport;

class ModelImportProductTypeConfigurable extends \Magento\ConfigurableImportExport\Model\Import\Product\Type\Configurable
{
    /**
     * Collect super data labels.
     * @fixme https://github.com/magento/magento2/issues/5993
     *
     * @param array $data
     * @param integer|string $productSuperAttrId
     * @param integer|string $productId
     * @param array $variationLabels
     * @return $this
     */
    protected function _collectSuperDataLabels($data, $productSuperAttrId, $productId, $variationLabels)
    {
        $attrParams = $this->_superAttributes[$data['_super_attribute_code']];
        $this->_superAttributesData['attributes'][$productId][$attrParams['id']] = [
            'product_super_attribute_id' => $productSuperAttrId,
            'position' => 0,
        ];

        return $this;
    }

    /**
     * Fixes issue with not properly deleting link
     *
     * @param array $rowData
     *
     * @return ModelImportProductTypeConfigurable
     */
    protected function _collectSuperData($rowData)
    {
        parent::_collectSuperData($rowData);
        return $this->_deleteData();
    }

    /**
     * Delete unnecessary links.
     * @fixes PROMO-589
     *
     * @return $this
     */
    protected function _deleteData()
    {
        $linkTable = $this->_resource->getTableName('catalog_product_super_link');
        $relationTable = $this->_resource->getTableName('catalog_product_relation');

        parent::_deleteData();

        if ($this->_entityModel->getBehavior() == \Magento\ImportExport\Model\Import::BEHAVIOR_REPLACE
            && !empty($this->_productSuperData['assoc_entity_ids'])
            && !empty($this->_productSuperData['product_id'])
        ) {
            $quoted = $this->_connection->quoteInto('IN (?)', [$this->_productSuperData['product_id']]);
            $quotedChildren = $this->_connection->quoteInto('NOT IN (?)', $this->_productSuperData['assoc_entity_ids']);

            $this->_connection->delete($linkTable, "parent_id {$quoted} AND product_id {$quotedChildren}");
            $this->_connection->delete($relationTable, "parent_id {$quoted} AND child_id {$quotedChildren}");
        }

        return $this;
    }
}
