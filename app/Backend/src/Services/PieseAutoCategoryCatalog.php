<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Categorii\CategoriiModel;
use RuntimeException;

/**
 * Catalog taxonomie PieseAuto.ro — parse HTML, JSON unificat, import în tabel categorii.
 */
final class PieseAutoCategoryCatalog
{
    public const SOURCE_URL = 'https://www.pieseauto.ro/catalog-piese-auto/';

    private CategoriiModel $model;

    public function __construct(?CategoriiModel $model = null)
    {
        $this->model = $model ?? new CategoriiModel();
    }

    public static function projectRoot(): string
    {
        return defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 3);
    }

    public static function catalogJsonPath(): string
    {
        $storage = self::projectRoot() . '/app/Backend/storage/catalog/pieseauto-categorii.json';
        if (is_file($storage)) {
            return $storage;
        }

        return self::projectRoot() . '/catalog-pieseauto-categorii.json';
    }

    public static function htmlSnapshotPath(): string
    {
        return self::projectRoot() . '/app/Backend/storage/catalog/pieseauto-catalog.html';
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        $path = self::catalogJsonPath();
        if (!is_file($path)) {
            return [
                'source' => self::SOURCE_URL,
                'title' => 'Catalog piese auto',
                'categories' => [],
                'total_categories' => 0,
                'total_subcategories' => 0,
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $this->normalizePayload($decoded) : [
            'source' => self::SOURCE_URL,
            'title' => 'Catalog piese auto',
            'categories' => [],
            'total_categories' => 0,
            'total_subcategories' => 0,
        ];
    }

    /** @param array<string, mixed> $payload */
    public function save(array $payload): string
    {
        $normalized = $this->normalizePayload($payload);
        $dir = dirname(self::catalogJsonPath());
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = self::projectRoot() . '/app/Backend/storage/catalog/pieseauto-categorii.json';
        file_put_contents(
            $path,
            json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $legacy = self::projectRoot() . '/catalog-pieseauto-categorii.json';
        if ($legacy !== $path) {
            file_put_contents(
                $legacy,
                json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        }

        return $path;
    }

    /** @return array<string, int|string> */
    public function stats(): array
    {
        $catalog = $this->load();
        $categories = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];
        $subCount = 0;
        foreach ($categories as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $subCount += count(is_array($cat['items'] ?? null) ? $cat['items'] : []);
        }

        $dbStats = $this->countImportedRows();

        return [
            'json_path' => self::catalogJsonPath(),
            'json_exists' => is_file(self::catalogJsonPath()),
            'html_snapshot' => self::htmlSnapshotPath(),
            'html_exists' => is_file(self::htmlSnapshotPath()),
            'source' => (string) ($catalog['source'] ?? self::SOURCE_URL),
            'total_categories' => count($categories),
            'total_subcategories' => $subCount,
            'db_categories' => $dbStats['categories'],
            'db_subcategories' => $dbStats['subcategories'],
            'db_pieseauto_categories' => $dbStats['pieseauto_categories'],
            'db_pieseauto_subcategories' => $dbStats['pieseauto_subcategories'],
        ];
    }

    /**
     * Parse HTML catalog PieseAuto (cats-group sau autocomplete).
     *
     * @return list<array<string, mixed>>
     */
    public function parseHtml(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        $categories = $this->parseCatsGroups($html);
        if ($categories === []) {
            $categories = $this->parseAutocomplete($html);
        }

        return $categories;
    }

    /**
     * Parse HTML, salvează JSON unificat + snapshot HTML opțional.
     *
     * @return array{path:string,categories:int,subcategories:int}
     */
    public function parseAndSave(string $html, bool $keepHtmlSnapshot = true): array
    {
        $categories = $this->parseHtml($html);
        if ($categories === []) {
            throw new RuntimeException('Nu s-au găsit categorii în HTML (cats-group sau autocomplete).');
        }

        if ($keepHtmlSnapshot) {
            $htmlDir = dirname(self::htmlSnapshotPath());
            if (!is_dir($htmlDir)) {
                mkdir($htmlDir, 0775, true);
            }
            file_put_contents(self::htmlSnapshotPath(), $html);
        }

        $path = $this->save([
            'source' => self::SOURCE_URL,
            'title' => 'Catalog piese auto',
            'categories' => $categories,
            'parsed_at' => date('c'),
        ]);

        $subCount = 0;
        foreach ($categories as $cat) {
            $subCount += count(is_array($cat['items'] ?? null) ? $cat['items'] : []);
        }

        return [
            'path' => $path,
            'categories' => count($categories),
            'subcategories' => $subCount,
        ];
    }

    /**
     * Importă catalogul JSON în tabel categorii (categorie + subcategorie).
     *
     * @return array{imported_categories:int,imported_subcategories:int,updated:int,skipped:int}
     */
    public function importToDatabase(bool $onlyMissing = true): array
    {
        $catalog = $this->load();
        $categories = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];
        if ($categories === []) {
            throw new RuntimeException('Catalogul PieseAuto este gol — parsează HTML sau verifică JSON.');
        }

        $existingBySlug = [];
        foreach ($this->model->findAll() as $row) {
            $key = ($row['type'] ?? 'categorie') . '::' . ($row['slug'] ?? '');
            $existingBySlug[$key] = $row;
        }

        $importedCategories = 0;
        $importedSubcategories = 0;
        $updated = 0;
        $skipped = 0;
        $sortOrder = 100;

        foreach ($categories as $cat) {
            if (!is_array($cat)) {
                continue;
            }

            $mainLabel = trim((string) ($cat['name'] ?? ''));
            if ($mainLabel === '') {
                continue;
            }

            $mainSlug = trim((string) ($cat['slug'] ?? ''));
            if ($mainSlug === '') {
                $mainSlug = $this->slugify($mainLabel);
            }

            $mainKey = 'categorie::' . $mainSlug;
            $mainMeta = json_encode([
                'source' => 'pieseauto',
                'href' => (string) ($cat['href'] ?? ''),
                'pieseauto_slug' => $mainSlug,
            ], JSON_UNESCAPED_UNICODE);

            $parentId = $this->upsertRow(
                $existingBySlug,
                $mainKey,
                [
                    'label' => $mainLabel,
                    'slug' => $mainSlug,
                    'type' => 'categorie',
                    'parent_id' => null,
                    'sort_order' => $sortOrder,
                    'is_active' => 1,
                    'meta' => $mainMeta,
                ],
                $onlyMissing,
                $importedCategories,
                $updated,
                $skipped
            );
            $sortOrder += 10;

            $subSort = 10;
            foreach (is_array($cat['items'] ?? null) ? $cat['items'] : [] as $sub) {
                if (!is_array($sub)) {
                    continue;
                }
                $subLabel = trim((string) ($sub['name'] ?? ''));
                if ($subLabel === '') {
                    continue;
                }
                $subSlug = trim((string) ($sub['slug'] ?? ''));
                if ($subSlug === '') {
                    $subSlug = $this->slugify($subLabel);
                }

                $subKey = 'subcategorie::' . $subSlug;
                $subMeta = json_encode([
                    'source' => 'pieseauto',
                    'href' => (string) ($sub['href'] ?? ''),
                    'pieseauto_slug' => $subSlug,
                    'parent_slug' => $mainSlug,
                ], JSON_UNESCAPED_UNICODE);

                $this->upsertRow(
                    $existingBySlug,
                    $subKey,
                    [
                        'label' => $subLabel,
                        'slug' => $subSlug,
                        'type' => 'subcategorie',
                        'parent_id' => $parentId,
                        'sort_order' => $subSort,
                        'is_active' => 1,
                        'meta' => $subMeta,
                    ],
                    $onlyMissing,
                    $importedSubcategories,
                    $updated,
                    $skipped
                );
                $subSort += 10;
            }
        }

        return [
            'imported_categories' => $importedCategories,
            'imported_subcategories' => $importedSubcategories,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /** @return array{categories:int,subcategories:int,pieseauto_categories:int,pieseauto_subcategories:int} */
    private function countImportedRows(): array
    {
        $categories = 0;
        $subcategories = 0;
        $paCategories = 0;
        $paSubcategories = 0;

        foreach ($this->model->findAll() as $row) {
            $type = (string) ($row['type'] ?? '');
            $meta = $this->decodeMeta($row['meta'] ?? null);
            $isPa = ($meta['source'] ?? '') === 'pieseauto';

            if ($type === 'subcategorie') {
                $subcategories++;
                if ($isPa) {
                    $paSubcategories++;
                }
            } elseif ($type === 'categorie') {
                $categories++;
                if ($isPa) {
                    $paCategories++;
                }
            }
        }

        return [
            'categories' => $categories,
            'subcategories' => $subcategories,
            'pieseauto_categories' => $paCategories,
            'pieseauto_subcategories' => $paSubcategories,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function parseCatsGroups(string $html): array
    {
        $pattern = '/<div class="cats-group">\s*'
            . '<h2 class="cats-group__main-cat">\s*'
            . '<a href="([^"]+)" title="([^"]*)">([^<]+)<\/a>\s*'
            . '<\/h2>\s*'
            . '<ul class="cats-group__items">(.*?)<\/ul>/si';

        if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $linkPattern = '/<a href="([^"]+)" title="([^"]*)">([^<]+)<\/a>/si';
        $categories = [];

        foreach ($matches as $match) {
            $itemsHtml = (string) ($match[4] ?? '');
            $items = [];
            if (preg_match_all($linkPattern, $itemsHtml, $itemMatches, PREG_SET_ORDER)) {
                foreach ($itemMatches as $itemMatch) {
                    $name = $this->cleanText((string) ($itemMatch[3] ?? ''));
                    $href = (string) ($itemMatch[1] ?? '');
                    $items[] = [
                        'name' => $name,
                        'href' => $href,
                        'title' => $this->cleanText((string) ($itemMatch[2] ?? '')) ?: $name,
                        'slug' => $this->slugFromHref($href),
                    ];
                }
            }

            $mainName = $this->cleanText((string) ($match[3] ?? ''));
            $mainHref = (string) ($match[1] ?? '');
            $categories[] = [
                'name' => $mainName,
                'href' => $mainHref,
                'title' => $this->cleanText((string) ($match[2] ?? '')) ?: $mainName,
                'slug' => $this->slugFromHref($mainHref),
                'count' => count($items),
                'items' => $items,
            ];
        }

        return $categories;
    }

    /** @return list<array<string, mixed>> */
    private function parseAutocomplete(string $html): array
    {
        if (!preg_match('/source:\s*(\[.*?\])\s*,\s*minLength/s', $html, $match)) {
            return [];
        }

        $entries = json_decode($match[1], true);
        if (!is_array($entries)) {
            return [];
        }

        $categories = [];
        /** @var array<string, array<string, mixed>> $index */
        $index = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $label = $this->cleanText((string) ($entry['label'] ?? ''));
            $href = (string) ($entry['value'] ?? '');
            $slug = $this->slugFromHref($href);

            if (str_contains($label, ' -> ')) {
                [$parentName, $childName] = explode(' -> ', $label, 2);
                $parent = $index[$parentName] ?? null;
                if (!is_array($parent)) {
                    continue;
                }
                $parent['items'][] = [
                    'name' => $childName,
                    'href' => $href,
                    'title' => $childName,
                    'slug' => $slug,
                ];
                $parent['count'] = count($parent['items']);
                $index[$parentName] = $parent;
                continue;
            }

            $cat = [
                'name' => $label,
                'href' => $href,
                'title' => $label,
                'slug' => $slug,
                'count' => 0,
                'items' => [],
            ];
            $categories[] = $cat;
            $index[$label] = $cat;
        }

        if ($index !== []) {
            $categories = array_values($index);
        }

        return $categories;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function normalizePayload(array $payload): array
    {
        $categories = is_array($payload['categories'] ?? null) ? $payload['categories'] : [];
        $subCount = 0;
        foreach ($categories as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $subCount += count(is_array($cat['items'] ?? null) ? $cat['items'] : []);
        }

        return [
            'source' => (string) ($payload['source'] ?? self::SOURCE_URL),
            'title' => (string) ($payload['title'] ?? 'Catalog piese auto'),
            'categories' => $categories,
            'total_categories' => count($categories),
            'total_subcategories' => $subCount,
            'parsed_at' => (string) ($payload['parsed_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $existingBySlug
     * @param array<string, mixed> $payload
     */
    private function upsertRow(
        array &$existingBySlug,
        string $key,
        array $payload,
        bool $onlyMissing,
        int &$importedCounter,
        int &$updatedCounter,
        int &$skippedCounter
    ): int {
        if (isset($existingBySlug[$key])) {
            $existing = $existingBySlug[$key];
            $id = (int) ($existing['id'] ?? 0);
            if ($onlyMissing) {
                $skippedCounter++;
                return $id;
            }

            $this->model->update($id, $payload);
            $existingBySlug[$key] = array_merge($existing, $payload, ['id' => $id]);
            $updatedCounter++;

            return $id;
        }

        $this->model->insert($payload);
        $importedCounter++;
        $id = (int) \Config\Database::getDB()->lastInsertId();
        if ($id > 0) {
            $existingBySlug[$key] = array_merge($payload, ['id' => $id]);
            return $id;
        }

        return 0;
    }

    private function slugFromHref(string $href): string
    {
        $path = trim((string) parse_url($href, PHP_URL_PATH), '/');
        if ($path === '') {
            return '';
        }
        $parts = explode('/', $path);

        return end($parts) ?: '';
    }

    private function slugify(string $label): string
    {
        $slug = mb_strtolower($label, 'UTF-8');
        $slug = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'ă', 'â', 'î', 'ş', 'ţ'],
            ['a', 'a', 'i', 's', 't', 'a', 'a', 'i', 's', 't'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? $slug;

        return trim($slug, '-');
    }

    private function cleanText(string $value): string
    {
        return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return array<string, mixed> */
    private function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }
        if (!is_string($meta) || trim($meta) === '') {
            return [];
        }
        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }
}
