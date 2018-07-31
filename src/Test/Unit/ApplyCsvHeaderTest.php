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
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class ApplyCsvHeaderTest extends TestCase
{
    /** @var ObjectManager */
    private $objectManager;

    /** @var FileCsv */
    private $parser;

    /** @var [] */
    private $header;

    /** @var String */
    private $requestFile;

    /** @var  DirectoryList */
    private $directoryList;

    protected function setUp()
    {
        parent::setUp();
        $this->objectManager = new ObjectManager($this);
        $this->directoryList = $this->createMock(DirectoryList::class);
    }

    /**
     *
     * @test
     * @throws \League\Csv\Exception
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
     * @throws \League\Csv\Exception
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