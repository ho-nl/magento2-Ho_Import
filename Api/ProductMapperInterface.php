<?php

namespace Ho\Import\Api;

interface ProductMapperInterface
{

    /**
     * Check if the product can be imported
     *
     * @param array $rawProduct
     *
     * @return bool|array return true if validated, return an array of errors when not valid.
     */
    public function validateItem(array $rawProduct);


    /**
     * Method to parse incomming data to outgoing data
     *
     * @param array $rawProduct
     * @return array
     */
    public function mapItem(array $rawProduct) : array;


    /**
     * Get the SKU for the current line
     *
     * @param array $rawProduct
     *
     * @return string
     */
    public function getSku(array $rawProduct);


    /**
     * Get an array or \Generator with all the source items
     *
     * @return array|\Generator
     */
    public function getSourceItems();

    /**
     * Get the mapping definition
     *
     * @return array
     */
    public function getMappingDefinition();
}
