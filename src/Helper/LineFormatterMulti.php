<?php
/**
 * Copyright (c) 2016 H&O E-commerce specialisten B.V. (http://www.h-o.nl/)
 * See LICENSE.txt for license details.
 */
namespace Ho\Import\Helper;



class LineFormatterMulti
{
    const LINE_DELIMITER = \Magento\CatalogImportExport\Model\Import\Product::PSEUDO_MULTI_LINE_SEPARATOR;

    /**
     * @var LineFormatterSingle
     */
    private $lineFormatterSingle;

    /**
     * LineEncoderMulti constructor.
     *
     * @param LineFormatterSingle $lineEncoderSingle
     */
    public function __construct(
        LineFormatterSingle $lineEncoderSingle
    ) {
        $this->lineFormatterSingle = $lineEncoderSingle;
    }

    /**
     * Encode lines
     *
     * @param string[string[]] $lines
     * @return string
     */
    public function encode($lines)
    {
        $encodedLines = array_map(function ($line) {
            $encodedLine = $this->lineFormatterSingle->encode($line);

            //@todo replace pipe characters

            return $encodedLine;
        }, $lines);
        return implode(self::LINE_DELIMITER, $encodedLines);
    }

    /**
     * Decode Lines
     *
     * @param string $lines
     * @return []
     */
    public function decode($lines)
    {
        if (! $lines) {
            return [];
        }

        return array_map(function ($line) {
            return $this->lineFormatterSingle->decode($line);
        }, explode(self::LINE_DELIMITER, $lines));
    }
}
