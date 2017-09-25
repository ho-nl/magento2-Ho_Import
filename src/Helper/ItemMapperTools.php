<?php
/**
 * Copyright © 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
/**
 * @todo investigate the possibility to move to a completely classless helper file.
 *       Like used here: https://github.com/guzzle/guzzle/blob/master/src/functions.php
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
    public static function getField($fieldName)
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
    public static function getFieldConcat($fields, $implode = ',')
    {
        return function ($item) use ($fields, $implode) {
            $values = array_filter(array_map(function ($fieldName) use ($item) {
                return $item[$fieldName] ?? null;
            }, $fields));

            return implode($implode, $values);
        };
    }
}
