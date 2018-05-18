<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace Ho\Import\Test\Unit;

use Ho\Import\Streamer\FileCsv;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use PHPUnit\Framework\TestCase;

class ApplyCsvHeaderTest extends TestCase
{

    private $objectManager;

    /**
     * @var \Ho\Import\Streamer\FileCsv
     */
    private $parser;

    private $header;

    private $requestFile;

    private $directoryList;

    protected function setUp()
    {
        parent::setUp();
        $this->objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->directoryList = $this->createMock(DirectoryList::class);
    }

    /**
     *
     * @test
     */
    public function should_have_headers_as_keys_for_csv_file_without_header()
    {
        // @todo remove /data/web/magento2/
        $this->requestFile = '/data/web/magento2/var/import/product/sampleNoHeader.csv';
        $this->header = [
            'firstName',
            'lastName',
            'email',
            'phoneNumber'];
        try {
          $this->parser = $this->objectManager->getObject(FileCsv::class, [
              'header' => $this->header,
              'requestFile' => $this->requestFile,
              'directoryList' => $this->directoryList
          ]);
          $csvRows = $this->parser->getIterator();
          foreach ($csvRows as $csvRow) {
              array_map(function($csvRowKey){
                  self::assertContains($csvRowKey, $this->header);
              }, array_keys($csvRow));
          }
      } catch(FileSystemException $e) {
          echo $e->getMessage();
      }
    }

    /**
     *
     * @test
     */
    public function should_have_headers_as_keys_for_csv_file_with_header()
    {
        // @todo remove /data/web/magento2/
        $this->requestFile = '/data/web/magento2/var/import/product/sampleWithHeader.csv';
        $this->header = [
            'firstName',
            'lastName',
            'email',
            'phoneNumber'];
        try {
            $this->parser = $this->objectManager->getObject(FileCsv::class, [
                'requestFile' => $this->requestFile,
                'directoryList' => $this->directoryList
            ]);
            $csvRows = $this->parser->getIterator();
            foreach ($csvRows as $csvRow) {
                array_map(function($csvRowKey){
                    self::assertContains($csvRowKey, $this->header);
                }, array_keys($csvRow));
            }
        } catch(FileSystemException $e) {
            echo $e->getMessage();
        }
    }

}