<?php
declare(strict_types=1);

/**
 * RAG epiesa.ro pentru clasificare produse vitrină (Ollama).
 * Index keyword-based — fără embeddings obligatorii; funcționează offline.
 */
final class ImportShowcaseEpiesaRag
{
    private const INDEX_FILE = 'showcase_epiesa_rag.json';
    private const TAXONOMY_FILE = 'epiesa_auto_taxonomy.json';

    /** @var array<string, mixed>|null */
    private static ?array $indexCache = null;

    public static function indexPath(): string
    {
        return IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'rag' . DIRECTORY_SEPARATOR . self::INDEX_FILE;
    }

    public static function taxonomyPath(): string
    {
        return IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'rag' . DIRECTORY_SEPARATOR . self::TAXONOMY_FILE;
    }

    public static function isAvailable(): bool
    {
        $index = self::loadIndex();

        return ($index['chunks'] ?? []) !== [];
    }

    /** @return array{available:bool,count:int,built_at:?string,source:?string,target:int} */
    public static function stats(): array
    {
        $index = self::loadIndex();
        $chunks = is_array($index['chunks'] ?? null) ? $index['chunks'] : [];

        return [
            'available' => $chunks !== [],
            'count' => count($chunks),
            'built_at' => isset($index['built_at']) ? (string) $index['built_at'] : null,
            'source' => isset($index['source']) ? (string) $index['source'] : null,
            'target' => (int) ($index['target_count'] ?? 5000),
            'taxonomy' => is_file(self::taxonomyPath()),
        ];
    }

    /**
     * Recuperează chunk-uri relevante pentru un produs + tipuri cerute.
     *
     * @param list<string> $types
     * @return list<array<string, mixed>>
     */
    public static function retrieveForProduct(string $query, array $types, int $limit = 8): array
    {
        $index = self::loadIndex();
        $chunks = is_array($index['chunks'] ?? null) ? $index['chunks'] : [];
        if ($chunks === []) {
            return self::fallbackFromTaxonomy($query, $types, $limit);
        }

        $typesNorm = self::normalizeTypes($types);
        $queryTokens = self::tokenize($query);
        if ($queryTokens === []) {
            return self::topByType($chunks, $typesNorm, $limit);
        }

        $scored = [];
        foreach ($chunks as $chunk) {
            if (!is_array($chunk)) {
                continue;
            }
            $chunkType = mb_strtolower(trim((string) ($chunk['showcase_type'] ?? '')));
            if ($typesNorm !== [] && $chunkType !== '' && !in_array($chunkType, $typesNorm, true)) {
                continue;
            }

            $score = self::scoreChunk($chunk, $queryTokens, $typesNorm);
            if ($score <= 0) {
                continue;
            }
            $scored[] = ['score' => $score, 'chunk' => $chunk];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $out = [];
        $seen = [];
        foreach ($scored as $row) {
            $id = (string) ($row['chunk']['id'] ?? '');
            if ($id !== '' && isset($seen[$id])) {
                continue;
            }
            if ($id !== '') {
                $seen[$id] = true;
            }
            $out[] = $row['chunk'];
            if (count($out) >= $limit) {
                break;
            }
        }

        if ($out === []) {
            return self::fallbackFromTaxonomy($query, $types, $limit);
        }

        return $out;
    }

    /**
     * RAG pentru un batch Ollama — combină tipuri + titluri din chunk.
     *
     * @param list<array<string, mixed>> $items
     * @param list<string> $types
     */
    public static function retrieveForBatch(array $items, array $types, int $limit = 18): array
    {
        $parts = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $card = is_array($item['card'] ?? null) ? $item['card'] : [];
            $parts[] = trim((string) ($item['title'] ?? $card['title'] ?? $item['name'] ?? $card['name'] ?? ''));
        }
        $query = implode(' ', array_filter($parts));

        return self::retrieveForProduct($query, $types, $limit);
    }

    /**
     * @param list<array<string, mixed>> $chunks
     */
    public static function formatForPrompt(array $chunks): string
    {
        if ($chunks === []) {
            return '';
        }

        $lines = [
            'REFERINȚĂ epiesa.ro — Produse auto universale (RAG, consumabile vitrină):',
            'Folosește aceste exemple ca standard; match=true DOAR dacă produsul e similar cu INCLUDE, NU cu EXCLUDE.',
        ];

        foreach (array_slice($chunks, 0, 20) as $i => $chunk) {
            $verdict = strtoupper((string) ($chunk['verdict'] ?? 'info'));
            $type = (string) ($chunk['showcase_type'] ?? '?');
            $sub = (string) ($chunk['epiesa_subcategory'] ?? '');
            $example = trim((string) ($chunk['example_product'] ?? ''));
            $text = trim((string) ($chunk['text'] ?? ''));
            $line = ($i + 1) . '. [' . $verdict . ' | ' . $type . ']';
            if ($sub !== '') {
                $line .= ' epiesa: ' . $sub;
            }
            if ($example !== '') {
                $line .= ' — ex: «' . $example . '»';
            } elseif ($text !== '') {
                $line .= ' — ' . mb_substr($text, 0, 120);
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    private static function loadIndex(): array
    {
        if (self::$indexCache !== null) {
            return self::$indexCache;
        }

        $path = self::indexPath();
        if (!is_file($path)) {
            self::$indexCache = [];

            return self::$indexCache;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        self::$indexCache = is_array($decoded) ? $decoded : [];

        return self::$indexCache;
    }

    /**
     * @param list<string> $types
     * @return list<string>
     */
    private static function normalizeTypes(array $types): array
    {
        $out = [];
        foreach ($types as $type) {
            $t = mb_strtolower(trim((string) $type));
            if ($t !== '' && !in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function tokenize(string $text): array
    {
        $text = mb_strtolower(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/[^a-z0-9ăâîșțáéíóúü+\-\s]/u', ' ', $text) ?? '';
        $parts = preg_split('/\s+/u', trim($text)) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) >= 2) {
                $tokens[] = $part;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param array<string, mixed> $chunk
     * @param list<string> $queryTokens
     * @param list<string> $types
     */
    private static function scoreChunk(array $chunk, array $queryTokens, array $types): float
    {
        $score = 0.0;
        $chunkType = mb_strtolower(trim((string) ($chunk['showcase_type'] ?? '')));
        if ($types !== [] && $chunkType !== '' && in_array($chunkType, $types, true)) {
            $score += 4.0;
        }

        $keywords = is_array($chunk['keywords'] ?? null) ? $chunk['keywords'] : [];
        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) ($chunk['text'] ?? ''),
            (string) ($chunk['example_product'] ?? ''),
            implode(' ', $keywords),
        ])));

        foreach ($queryTokens as $token) {
            if (str_contains($haystack, $token)) {
                $score += 2.0;
            }
            foreach ($keywords as $kw) {
                if (is_string($kw) && str_contains(mb_strtolower($kw), $token)) {
                    $score += 3.0;
                }
            }
        }

        if (($chunk['verdict'] ?? '') === 'include') {
            $score += 0.5;
        }

        return $score;
    }

    /**
     * @param list<array<string, mixed>> $chunks
     * @param list<string> $types
     * @return list<array<string, mixed>>
     */
    private static function topByType(array $chunks, array $types, int $limit): array
    {
        $out = [];
        foreach ($chunks as $chunk) {
            if (!is_array($chunk)) {
                continue;
            }
            $chunkType = mb_strtolower(trim((string) ($chunk['showcase_type'] ?? '')));
            if ($types === [] || ($chunkType !== '' && in_array($chunkType, $types, true))) {
                $out[] = $chunk;
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $types
     * @return list<array<string, mixed>>
     */
    private static function fallbackFromTaxonomy(string $query, array $types, int $limit): array
    {
        $path = self::taxonomyPath();
        if (!is_file($path)) {
            return [];
        }

        $tax = json_decode((string) file_get_contents($path), true);
        if (!is_array($tax)) {
            return [];
        }

        $map = is_array($tax['showcase_type_map'] ?? null) ? $tax['showcase_type_map'] : [];
        $typesNorm = self::normalizeTypes($types);
        $out = [];

        foreach ($typesNorm as $type) {
            $def = is_array($map[$type] ?? null) ? $map[$type] : null;
            if ($def === null) {
                continue;
            }
            $subs = is_array($def['subcategories'] ?? null) ? $def['subcategories'] : [];
            foreach (array_slice($subs, 0, 2) as $sub) {
                $out[] = [
                    'id' => 'tax_' . $type . '_' . md5((string) $sub),
                    'showcase_type' => $type,
                    'epiesa_subcategory' => (string) $sub,
                    'verdict' => 'include',
                    'text' => 'Categorie epiesa «' . $sub . '» — consumabil vitrină tip ' . $type,
                    'keywords' => is_array($def['include_signals'] ?? null) ? $def['include_signals'] : [],
                ];
            }
            $excludes = is_array($def['exclude_signals'] ?? null) ? array_slice($def['exclude_signals'], 0, 3) : [];
            foreach ($excludes as $ex) {
                $out[] = [
                    'id' => 'tax_ex_' . $type . '_' . md5((string) $ex),
                    'showcase_type' => $type,
                    'verdict' => 'exclude',
                    'text' => 'NU vitrină ' . $type . ': «' . $ex . '» — piesă/accesoriu, nu consumabil raft',
                    'keywords' => [(string) $ex],
                ];
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return array_slice($out, 0, $limit);
    }
}
