<?php

declare(strict_types=1);

namespace Besoiu\Services;

use RuntimeException;
use ZipArchive;

/**
 * Citește categorii_tree_final.xlsx (sheet-uri: categorii, categorii_secundare, sinonime_cautare).
 * Fără PhpSpreadsheet — ZipArchive + XML, același pattern ca read_xlsx_rows().
 */
final class BesoiuCategoryTreeExcelReader
{
    public static function defaultExcelPath(): string
    {
        return BesoiuCategoryTreeParser::projectRoot() . '/categorie/categorii_tree_final.xlsx';
    }

    public static function isAvailable(?string $path = null): bool
    {
        $path = $path ?? self::defaultExcelPath();

        return is_file($path) && is_readable($path);
    }

    /**
     * @return array{
     *   path:string,
     *   sheets:list<string>,
     *   nodes:list<array<string,mixed>>,
     *   secondary:list<array<string,mixed>>,
     *   synonyms:list<array<string,mixed>>,
     *   stats:array<string,mixed>
     * }
     */
    public function load(?string $path = null): array
    {
        $path = $path ?? self::defaultExcelPath();
        if (!self::isAvailable($path)) {
            throw new RuntimeException(
                'Lipsește fișierul Excel. Copiază categorii_tree_final.xlsx în categorie/'
            );
        }

        $workbook = $this->readWorkbook($path);
        $categoryRows = $this->rowsBySheet($workbook, ['categorii', 'categorie', 'categories']);
        $secondaryRows = $this->rowsBySheet($workbook, ['categorii_secundare', 'categorii secundare']);
        $synonymRows = $this->rowsBySheet($workbook, ['sinonime_cautare', 'sinonime cautare', 'sinonime']);

        $nodes = $this->parseCategoryNodes($categoryRows);
        $secondary = $this->parseSecondaryRows($secondaryRows);
        $synonyms = $this->parseSynonymRows($synonymRows);
        $validation = (new BesoiuCategoryTreeParser())->validate($nodes);

        return [
            'path' => $path,
            'sheets' => array_keys($workbook['sheets']),
            'nodes' => $nodes,
            'secondary' => $secondary,
            'synonyms' => $synonyms,
            'stats' => $validation,
        ];
    }

    /**
     * @param list<array<string, mixed>> $secondary
     * @param list<array<string, mixed>> $synonyms
     */
    public function exportJsonSidecars(array $secondary, array $synonyms): array
    {
        $secondaryPath = BesoiuCategoryTreeImportService::secondaryJsonPath();
        $synonymsPath = BesoiuCategoryTreeImportService::synonymsJsonPath();

        $this->writeJson($secondaryPath, [
            'title' => 'Categorii secundare Besoiu',
            'source' => 'categorii_tree_final.xlsx',
            'exported_at' => date('c'),
            'items' => $secondary,
        ]);

        $this->writeJson($synonymsPath, [
            'title' => 'Sinonime căutare Besoiu',
            'source' => 'categorii_tree_final.xlsx',
            'exported_at' => date('c'),
            'items' => $synonyms,
        ]);

        return [
            'secondary_path' => $secondaryPath,
            'synonyms_path' => $synonymsPath,
            'secondary_count' => count($secondary),
            'synonyms_count' => count($synonyms),
        ];
    }

    /**
     * @return array{sheets:array<string,list<array<string,string>>>}
     */
    private function readWorkbook(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Nu pot deschide Excel: ' . $path);
        }

        $shared = $this->readSharedStrings($zip);
        $sheetMap = $this->readSheetMap($zip);
        $sheets = [];

        foreach ($sheetMap as $sheetName => $sheetFile) {
            $sheetXml = $zip->getFromName($sheetFile);
            if ($sheetXml === false) {
                continue;
            }
            $sheets[$sheetName] = $this->parseSheetRows($sheetXml, $shared);
        }

        $zip->close();

        if ($sheets === []) {
            throw new RuntimeException('Excel fără sheet-uri citibile: ' . $path);
        }

        return ['sheets' => $sheets];
    }

    /** @return list<string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml === false) {
            return $shared;
        }

        $sx = simplexml_load_string($sharedXml);
        if (!$sx) {
            return $shared;
        }

        foreach ($sx->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string) $si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
            }
            $shared[] = $text;
        }

        return $shared;
    }

    /**
     * @return array<string, string> sheet_name => xl/worksheets/sheetN.xml
     */
    private function readSheetMap(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            throw new RuntimeException('Structură Excel invalidă (workbook.xml).');
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);
        if (!$workbook || !$rels) {
            throw new RuntimeException('XML Excel invalid.');
        }

        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relTargets = [];
        foreach ($rels->Relationship as $rel) {
            $attrs = $rel->attributes();
            $id = (string) ($attrs['Id'] ?? '');
            $target = ltrim((string) ($attrs['Target'] ?? ''), '/');
            if ($id === '' || $target === '') {
                continue;
            }
            if (str_starts_with($target, 'xl/')) {
                $relTargets[$id] = $target;
            } elseif (str_starts_with($target, 'worksheets/')) {
                $relTargets[$id] = 'xl/' . $target;
            } else {
                $relTargets[$id] = 'xl/' . $target;
            }
        }

        $map = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $attrs = $sheet->attributes();
            $name = trim((string) ($attrs['name'] ?? ''));
            $relId = (string) ($sheet->attributes('r', true)['id'] ?? '');
            if ($name === '' || $relId === '' || !isset($relTargets[$relId])) {
                continue;
            }
            $map[$this->normalizeSheetName($name)] = $relTargets[$relId];
        }

        return $map;
    }

    /**
     * @param list<string> $shared
     * @return list<array<string, string>>
     */
    private function parseSheetRows(string $sheetXml, array $shared): array
    {
        $xml = simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData)) {
            return [];
        }

        $rawRows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $row = [];
            foreach ($rowNode->c as $cell) {
                $attrs = $cell->attributes();
                $index = $this->columnIndex((string) ($attrs['r'] ?? 'A1'));
                $type = (string) ($attrs['t'] ?? '');
                $value = isset($cell->v) ? (string) $cell->v : '';
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif (($type === 'str' || $type === 'inlineStr') && isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                }
                $row[$index] = trim($value);
            }
            if ($row !== []) {
                ksort($row);
                $rawRows[] = array_values($row);
            }
        }

        if ($rawRows === []) {
            return [];
        }

        $headers = array_map([$this, 'normalizeHeader'], $rawRows[0]);
        $rows = [];
        for ($i = 1, $count = count($rawRows); $i < $count; $i++) {
            $assoc = [];
            foreach ($headers as $col => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = (string) ($rawRows[$i][$col] ?? '');
            }
            if ($this->rowHasData($assoc)) {
                $rows[] = $assoc;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, list<array<string, string>>> $workbook
     * @param list<string> $candidates
     * @return list<array<string, string>>
     */
    private function rowsBySheet(array $workbook, array $candidates): array
    {
        $sheets = $workbook['sheets'] ?? [];
        foreach ($candidates as $candidate) {
            $key = $this->normalizeSheetName($candidate);
            if (isset($sheets[$key])) {
                return $sheets[$key];
            }
        }

        foreach ($sheets as $name => $rows) {
            foreach ($candidates as $candidate) {
                if (str_contains($name, $this->normalizeSheetName($candidate))) {
                    return $rows;
                }
            }
        }

        return [];
    }

    /**
     * @param list<array<string, string>> $rows
     * @return list<array<string, mixed>>
     */
    private function parseCategoryNodes(array $rows): array
    {
        if ($rows === []) {
            throw new RuntimeException('Sheet-ul „categorii” este gol sau lipsește.');
        }

        $nodes = [];
        $childrenCount = [];

        foreach ($rows as $row) {
            $treeId = (int) $this->cell($row, ['id', 'tree_id', 'id categorie']);
            $name = $this->cell($row, ['nume', 'name', 'art_name']);
            if ($treeId <= 0 || $name === '') {
                continue;
            }

            $parentTreeId = (int) $this->cell($row, ['parinte_id', 'parent_id', 'parinte id', 'parent id']);
            $level = (int) $this->cell($row, ['nivel', 'level']);
            $sortOrder = (int) $this->cell($row, ['ordine', 'sort_order', 'order']);
            if ($sortOrder <= 0) {
                $sortOrder = $treeId;
            }

            $displayLabel = $this->cell($row, ['nume_afisare', 'nume afisare', 'display_label', 'label_ui']);
            if ($displayLabel === '') {
                $displayLabel = $name;
            }

            $nodes[] = [
                'tree_id' => $treeId,
                'name' => $name,
                'display_label' => $displayLabel,
                'parent_tree_id' => max(0, $parentTreeId),
                'level' => $level > 0 ? $level : 1,
                'depth' => max(0, ($level > 0 ? $level : 1) - 1),
                'sort_order' => $sortOrder,
                'slug' => $this->cell($row, ['slug']),
                'path' => $this->cell($row, ['cale', 'path']),
                'is_leaf' => true,
            ];
        }

        foreach ($nodes as $node) {
            $parentId = (int) $node['parent_tree_id'];
            if ($parentId > 0) {
                $childrenCount[$parentId] = (int) ($childrenCount[$parentId] ?? 0) + 1;
            }
        }

        foreach ($nodes as &$node) {
            $node['is_leaf'] = !isset($childrenCount[(int) $node['tree_id']]);
        }
        unset($node);

        usort($nodes, static function (array $a, array $b): int {
            return ((int) $a['level'] <=> (int) $b['level'])
                ?: ((int) $a['sort_order'] <=> (int) $b['sort_order'])
                ?: ((int) $a['tree_id'] <=> (int) $b['tree_id']);
        });

        return $nodes;
    }

    /**
     * @param list<array<string, string>> $rows
     * @return list<array<string, mixed>>
     */
    private function parseSecondaryRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $artName = $this->cell($row, ['art_name', 'nume', 'nume produs', 'produs']);
            $treeId = (int) $this->cell($row, [
                'id_categorie_secundara',
                'id categorie secundara',
                'category_tree_id',
                'tree_id',
                'id_categorie',
                'categorie_id',
            ]);
            $categoryName = $this->cell($row, [
                'cale_secundara',
                'cale secundara',
                'nume_categorie',
                'categorie',
                'category_name',
            ]);

            if ($artName === '') {
                continue;
            }

            $items[] = array_filter([
                'art_name' => $artName,
                'category_tree_id' => $treeId > 0 ? $treeId : null,
                'category_name' => $categoryName !== '' ? $categoryName : null,
            ], static fn ($v) => $v !== null && $v !== '');
        }

        return $items;
    }

    /**
     * @param list<array<string, string>> $rows
     * @return list<array<string, string>>
     */
    private function parseSynonymRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $term = $this->cell($row, ['termen_cautare', 'termen cautare', 'term', 'termen', 'sinonim', 'cautare']);
            $target = $this->cell($row, [
                'art_name_tinta',
                'art name tinta',
                'target_art_name',
                'art_name',
                'target',
                'produs',
                'nume produs',
                'categorie tinta',
                'categorie țintă',
            ]);

            if ($term === '' || $target === '') {
                continue;
            }

            $items[] = [
                'term' => $term,
                'target_art_name' => $target,
            ];
        }

        return $items;
    }

    /** @param array<string, string> $row @param list<string> $keys */
    private function cell(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader($key);
            if (isset($row[$normalized]) && trim((string) $row[$normalized]) !== '') {
                return trim((string) $row[$normalized]);
            }
        }

        return '';
    }

    /** @param array<string, string> $row */
    private function rowHasData(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function normalizeSheetName(string $value): string
    {
        return $this->normalizeHeader($value);
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ'], ['a', 'a', 'i', 's', 't', 's', 't'], $value);
        $value = preg_replace('/[^a-z0-9\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function columnIndex(string $cellRef): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellRef)) ?? '';
        $num = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $num = $num * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $num - 1);
    }

    /** @param array<string, mixed> $payload */
    private function writeJson(string $path, array $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}
