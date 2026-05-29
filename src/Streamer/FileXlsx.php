<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace Ho\Import\Streamer;

use Generator;
use Ho\Import\Logger\Log;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Sheet;
use RuntimeException;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Streams rows out of an XLSX file as associative arrays keyed by the
 * first row's headers. Drops into the same SourceIterator chain as
 * FileCsv / FileXml.
 *
 * Uses openspout's streaming XLSX reader (constant memory regardless of
 * file size; ~10k rows/sec). openspout is a soft dependency — install
 * it explicitly via `composer require openspout/openspout` when this
 * streamer is used. The library is not pulled in by Ho_Import's own
 * composer.json so projects that only consume FileCsv / FileXml don't
 * grow their `vendor/` for nothing.
 */
class FileXlsx
{
    /** @var string */
    private $requestFile;

    /** @var ConsoleOutput */
    private $consoleOutput;

    /** @var DirectoryList */
    private $directoryList;

    /** @var Log */
    private $log;

    /** @var ?string  Name of the sheet to read; null = first/active sheet. */
    private $sheet;

    /** @var int  1-indexed row number of the header. */
    private $headerRow;

    /**
     * @param ConsoleOutput $consoleOutput
     * @param DirectoryList $directoryList
     * @param string        $requestFile  Relative or absolute path to filename.
     * @param Log           $log
     * @param string|null   $sheet        Name of the sheet to read (default = first / active sheet).
     * @param int           $headerRow    Row number containing the headers (1-indexed, default 1).
     */
    public function __construct(
        ConsoleOutput $consoleOutput,
        DirectoryList $directoryList,
        string $requestFile,
        Log $log,
        ?string $sheet = null,
        int $headerRow = 1
    ) {
        $this->consoleOutput = $consoleOutput;
        $this->directoryList = $directoryList;
        $this->requestFile = $requestFile;
        $this->log = $log;
        $this->sheet = $sheet;
        $this->headerRow = $headerRow;
    }

    /**
     * @throws FileSystemException
     *
     * @return Generator<int, array<string, string>>
     */
    public function getIterator(): Generator
    {
        $absolute = $this->getRequestFile();
        if (!file_exists($absolute)) {
            $this->log->critical(sprintf('requestFile %s not found', $absolute));
            throw new FileSystemException(__('requestFile %1 not found', $absolute));
        }

        $sheetLabel = $this->sheet !== null ? sprintf(' (sheet: %s)', $this->sheet) : '';
        $this->consoleOutput->writeln(
            "<info>Streamer\\FileXlsx: Getting data from requestFile {$this->requestFile}{$sheetLabel}</info>"
        );
        $this->log->info('Streamer\FileXlsx: Getting data from requestFile ' . $this->requestFile);

        $reader = new Reader();
        $reader->open($absolute);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($this->sheet !== null && $sheet->getName() !== $this->sheet) {
                    continue;
                }
                yield from $this->iterateSheet($sheet);
                return;
            }
            throw new RuntimeException(sprintf(
                'Sheet "%s" not found in %s',
                $this->sheet ?? '<active>',
                $this->requestFile
            ));
        } finally {
            $reader->close();
        }
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    private function iterateSheet(Sheet $sheet): Generator
    {
        $headers = [];
        foreach ($sheet->getRowIterator() as $rowIndex => $row) {
            $cells = array_map(
                static function ($cell) {
                    return (string) $cell->getValue();
                },
                $row->getCells()
            );
            if ($rowIndex < $this->headerRow) {
                continue;
            }
            if ($rowIndex === $this->headerRow) {
                $headers = $cells;
                continue;
            }
            $assoc = [];
            foreach ($headers as $i => $header) {
                $assoc[$header] = $cells[$i] ?? '';
            }
            // Skip fully empty rows (Excel trailing blank rows are common).
            if (array_filter($assoc, static function ($v) { return $v !== ''; }) === []) {
                continue;
            }
            yield $assoc;
        }
    }

    /**
     * @return string
     */
    private function getRequestFile(): string
    {
        return $this->requestFile[0] === '/'
            ? $this->requestFile
            : $this->directoryList->getRoot() . '/' . trim($this->requestFile, '/');
    }
}
