<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Helper;



class ItemMapperTools
{

    /**
     * Get the field of an item
     *
     * @param string $fieldName
     * @return \Closure
     */
    public function getField($fieldName)
    {
        return function ($item) use ($fieldName) {
            return $item[$fieldName] ?? null;
        };
    }

    /**
     * Get multiple fields of an item and concatenate on the given string
     *
     * @param string[] $fields
     * @param string $implode
     *
     * @return \Closure
     */
    public function getFieldConcat($fields, $implode = ',')
    {
        return function ($item) use ($fields, $implode) {
            $values = array_filter(array_map(function ($fieldName) use ($item) {
                return $item[$fieldName] ?? null;
            }, $fields));

            return implode($implode, $values);
        };
    }
}
