<?php

namespace Ho\Import\Mapper;

interface ProductMapperInterface
{

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
     * Check if the product can be imported
     *
     * @param array $rawProduct
     *
     * @return bool|array return true if validated, return an array of errors when not valid.
     */
    public function validateItem(array $rawProduct);
}
