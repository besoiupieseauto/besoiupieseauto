<?php

declare(strict_types=1);

namespace Besoiu\Services;

use RuntimeException;

/**
 * Parsează categorii_tree_vizual.txt — arbore Besoiu cu [ID] Nume.
 * Frunza = ART_NAME exact (fără normalizare).
 */
final class BesoiuCategoryTreeParser
{
    public static function defaultVisualTreePath(): string
    {
        return self::projectRoot() . '/categorie/categorii_tree_vizual.txt';
    }

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * @return list<array{
     *   tree_id:int,
     *   name:string,
     *   depth:int,
     *   parent_tree_id:int,
     *   is_leaf:bool,
     *   level:int,
     *   sort_order:int
     * }>
     */
    public function parseFile(?string $path = null): array
    {
        $path = $path ?? self::defaultVisualTreePath();
        if (!is_file($path)) {
            throw new RuntimeException('Fișier arbore categorii lipsă: ' . $path);
        }

        return $this->parseText((string) file_get_contents($path));
    }

    /**
     * @return list<array{
     *   tree_id:int,
     *   name:string,
     *   depth:int,
     *   parent_tree_id:int,
     *   is_leaf:bool,
     *   level:int,
     *   sort_order:int
     * }>
     */
    public function parseText(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $nodes = [];
        $stack = [];
        $inTreeSection = false;
        $sortByParent = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (!$inTreeSection) {
                if (str_starts_with($trimmed, 'B. Subgrupe')) {
                    $inTreeSection = true;
                }
                continue;
            }

            if (!preg_match('/^(.*?)\[(\d+)\]\s*(.+)$/u', $line, $match)) {
                continue;
            }

            $prefix = (string) $match[1];
            $treeId = (int) $match[2];
            $name = trim((string) $match[3]);
            if ($treeId <= 0 || $name === '') {
                continue;
            }

            $depth = $this->depthFromPrefix($prefix);
            while ($stack !== [] && (int) $stack[count($stack) - 1]['depth'] >= $depth) {
                array_pop($stack);
            }

            $parentTreeId = 0;
            if ($stack !== []) {
                $parentTreeId = (int) $stack[count($stack) - 1]['tree_id'];
            }

            $sortByParent[$parentTreeId] = (int) ($sortByParent[$parentTreeId] ?? 0) + 1;

            $node = [
                'tree_id' => $treeId,
                'name' => $name,
                'depth' => $depth,
                'parent_tree_id' => $parentTreeId,
                'is_leaf' => true,
                'level' => $depth + 1,
                'sort_order' => $sortByParent[$parentTreeId],
            ];

            $nodes[] = $node;
            $stack[] = ['tree_id' => $treeId, 'depth' => $depth];
        }

        if ($nodes === []) {
            throw new RuntimeException('Nu s-au găsit noduri în arborele de categorii.');
        }

        $childrenCount = [];
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

        return $nodes;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return array{
     *   total:int,
     *   roots:int,
     *   leaves:int,
     *   max_depth:int,
     *   duplicate_tree_ids:list<int>
     * }
     */
    public function validate(array $nodes): array
    {
        $seen = [];
        $duplicateTreeIds = [];
        $roots = 0;
        $leaves = 0;
        $maxDepth = 0;

        foreach ($nodes as $node) {
            $treeId = (int) ($node['tree_id'] ?? 0);
            if (isset($seen[$treeId])) {
                $duplicateTreeIds[] = $treeId;
            }
            $seen[$treeId] = true;

            if ((int) ($node['parent_tree_id'] ?? 0) === 0) {
                $roots++;
            }
            if (!empty($node['is_leaf'])) {
                $leaves++;
            }
            $maxDepth = max($maxDepth, (int) ($node['depth'] ?? 0));
        }

        return [
            'total' => count($nodes),
            'roots' => $roots,
            'leaves' => $leaves,
            'max_depth' => $maxDepth,
            'duplicate_tree_ids' => array_values(array_unique($duplicateTreeIds)),
        ];
    }

    private function depthFromPrefix(string $prefix): int
    {
        if ($prefix === '') {
            return 0;
        }

        $bars = substr_count($prefix, '│');
        $hasBranch = str_contains($prefix, '├──') || str_contains($prefix, '└──');

        return $bars + ($hasBranch ? 1 : 0);
    }
}
