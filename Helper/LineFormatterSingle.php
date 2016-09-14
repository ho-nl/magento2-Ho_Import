<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Helper;

class LineFormatterSingle
{
    const ITEM_DELIMITER = \Magento\ImportExport\Model\Import::DEFAULT_GLOBAL_MULTI_VALUE_SEPARATOR;
    const VALUE_DELIMITER = '=';

    /**
     * Encode a line to be compatible with the importer
     * @param string[] $line
     * @return string
     */
    public function encode($line)
    {
        $values = [];
        foreach ($line as $key => $value) {
            $values[] = implode(self::VALUE_DELIMITER, [$key, $value]);
        }
        return implode(self::ITEM_DELIMITER, $values);
    }

    /**
     * Decode Line for a compatible importer line
     *
     * @param string $line
     * @todo isn't able to handle comma's in values, this will break the explode.
     * @return string[]
     */
    public function decode($line)
    {
        if (! $line) {
            return [];
        }

        $lineItems = explode(self::ITEM_DELIMITER, $line);
        $values = [];
        foreach ($lineItems as $lineItem) {
            list($key, $value) = explode(self::VALUE_DELIMITER, $lineItem);
            $values[$key] = $value;
        }

        return $values;
    }
}
