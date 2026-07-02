<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

final class XlsxReader
{
    public function readRows(string $path, ?string $sheetName = null): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Die XLSX-Datei konnte nicht geöffnet werden.');
        }

        $sharedStrings = $this->sharedStrings($zip);
        $sheetPath = $this->sheetPath($zip, $sheetName);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Das Tabellenblatt konnte nicht gelesen werden.');
        }

        $rows = $this->rowsFromSheetXml($sheetXml, $sharedStrings);
        if ($rows === []) {
            return [];
        }

        $headers = array_map(
            static fn (mixed $value): string => trim((string) $value),
            $rows[0]
        );

        $records = [];
        foreach (array_slice($rows, 1) as $row) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $record = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $record[$header] = trim((string) ($row[$index] ?? ''));
            }

            $records[] = $record;
        }

        return $records;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $xpath = $this->xpath($xml);
        $strings = [];
        foreach ($xpath->query('//main:si') as $node) {
            $parts = [];
            foreach ($xpath->query('.//main:t', $node) as $textNode) {
                $parts[] = $textNode->textContent;
            }

            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    private function sheetPath(ZipArchive $zip, ?string $sheetName): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            throw new RuntimeException('Die XLSX-Struktur ist unvollständig.');
        }

        $workbookXPath = $this->xpath($workbookXml);
        $relsXPath = $this->xpath($relsXml, ['rel' => 'http://schemas.openxmlformats.org/package/2006/relationships']);

        $sheetNodes = $workbookXPath->query('//main:sheets/main:sheet');
        if ($sheetNodes === false || $sheetNodes->length === 0) {
            throw new RuntimeException('Die XLSX-Datei enthält keine Tabellenblätter.');
        }

        $selectedSheet = null;
        foreach ($sheetNodes as $sheetNode) {
            $name = trim((string) $sheetNode->attributes?->getNamedItem('name')?->nodeValue);
            if ($sheetName === null || $name === $sheetName) {
                $selectedSheet = $sheetNode;
                break;
            }
        }

        if (!$selectedSheet instanceof DOMElement) {
            throw new RuntimeException('Das gewünschte Tabellenblatt wurde nicht gefunden.');
        }

        $relationshipId = (string) $selectedSheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
        foreach ($relsXPath->query('//rel:Relationship') as $relationship) {
            if ((string) $relationship->attributes?->getNamedItem('Id')?->nodeValue !== $relationshipId) {
                continue;
            }

            $target = (string) $relationship->attributes?->getNamedItem('Target')?->nodeValue;
            return str_starts_with($target, 'xl/') ? $target : 'xl/' . ltrim($target, '/');
        }

        throw new RuntimeException('Das Tabellenblatt konnte in der XLSX-Datei nicht aufgelöst werden.');
    }

    private function rowsFromSheetXml(string $xml, array $sharedStrings): array
    {
        $xpath = $this->xpath($xml);
        $rows = [];

        foreach ($xpath->query('//main:sheetData/main:row') as $rowNode) {
            $row = [];
            foreach ($xpath->query('./main:c', $rowNode) as $cellNode) {
                $reference = (string) $cellNode->attributes?->getNamedItem('r')?->nodeValue;
                $columnIndex = $this->columnIndex($reference);
                $row[$columnIndex] = $this->cellValue($xpath, $cellNode, $sharedStrings);
            }

            if ($row !== []) {
                ksort($row);
                $maxIndex = max(array_keys($row));
                $rows[] = array_replace(array_fill(0, $maxIndex + 1, ''), $row);
            }
        }

        return $rows;
    }

    private function cellValue(DOMXPath $xpath, DOMElement $cellNode, array $sharedStrings): string
    {
        $type = (string) $cellNode->getAttribute('t');
        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($xpath->query('.//main:is//main:t', $cellNode) as $textNode) {
                $parts[] = $textNode->textContent;
            }

            return implode('', $parts);
        }

        $valueNode = $xpath->query('./main:v', $cellNode)->item(0);
        if ($valueNode === null) {
            return '';
        }

        $value = trim($valueNode->textContent);
        if ($type === 's') {
            return (string) ($sharedStrings[(int) $value] ?? '');
        }

        if ($type === 'b') {
            return $value === '1' ? '1' : '';
        }

        return $value;
    }

    private function xpath(string $xml, array $namespaces = []): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($namespaces as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }

        return $xpath;
    }

    private function columnIndex(string $reference): int
    {
        if (!preg_match('/^[A-Z]+/', strtoupper($reference), $matches)) {
            return 0;
        }

        $column = $matches[0];
        $index = 0;
        foreach (str_split($column) as $character) {
            $index = ($index * 26) + (ord($character) - 64);
        }

        return max(0, $index - 1);
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
