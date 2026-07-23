<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Categorii\CategoriiModel;
use RuntimeException;

/**
 * Sincronizează taxonomia din DB (sursă canonică) către fișierele JSON folosite
 * de catalog, fitment vehicul și clasificare import.
 */
final class TaxonomySyncService
{
    public const UNIFIED_JSON = 'taxonomie-unificata.json';
    public const VEHICLE_JSON = 'vehicule-fitment.json';

    private CategoriiModel $model;
    private PieseAutoCategoryCatalog $pieseAutoCatalog;

    public function __construct(?CategoriiModel $model = null, ?PieseAutoCategoryCatalog $catalog = null)
    {
        $this->model = $model ?? new CategoriiModel();
        $this->pieseAutoCatalog = $catalog ?? new PieseAutoCategoryCatalog($this->model);
    }

    public static function storageDir(): string
    {
        return PieseAutoCategoryCatalog::projectRoot() . '/app/Backend/storage/catalog';
    }

    public static function unifiedJsonPath(): string
    {
        return self::storageDir() . '/' . self::UNIFIED_JSON;
    }

    public static function vehicleJsonPath(): string
    {
        return self::storageDir() . '/' . self::VEHICLE_JSON;
    }

    /**
     * Rebuild complet: PieseAuto JSON + fitment vehicule + index unificat.
     *
     * @return array<string, mixed>
     */
    public function syncAll(?int $triggerId = null): array
    {
        $piese = $this->syncPieseAutoJson();
        $vehicles = $this->syncVehicleFitmentJson();
        $unified = $this->syncUnifiedIndex($triggerId);

        return [
            'ok' => true,
            'trigger_id' => $triggerId,
            'synced_at' => date('c'),
            'pieseauto' => $piese,
            'vehicule' => $vehicles,
            'unified' => $unified,
        ];
    }

    /** @return array<string, mixed> */
    public function syncPieseAutoJson(): array
    {
        $categories = $this->buildPieseAutoCategoriesFromDb();
        $path = $this->pieseAutoCatalog->save([
            'source' => PieseAutoCategoryCatalog::SOURCE_URL,
            'title' => 'Catalog piese auto',
            'categories' => $categories,
            'synced_from_db_at' => date('c'),
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

    /** @return array<string, mixed> */
    public function syncVehicleFitmentJson(): array
    {
        $payload = $this->buildVehicleFitmentFromDb();
        $path = $this->writeJson(self::vehicleJsonPath(), $payload);

        return [
            'path' => $path,
            'brands' => count(is_array($payload['brands'] ?? null) ? $payload['brands'] : []),
            'models' => (int) ($payload['stats']['models'] ?? 0),
            'motorizations' => (int) ($payload['stats']['motorizations'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public function syncUnifiedIndex(?int $triggerId = null): array
    {
        $payload = $this->buildUnifiedIndex($triggerId);
        $path = $this->writeJson(self::unifiedJsonPath(), $payload);

        return [
            'path' => $path,
            'stats' => $payload['stats'] ?? [],
        ];
    }

    /** @return array<string, int|string|bool> */
    public function syncStats(): array
    {
        $base = $this->pieseAutoCatalog->stats();
        $all = $this->model->findAll();
        $byType = ['categorie' => 0, 'subcategorie' => 0, 'marca' => 0, 'model' => 0, 'motorizare' => 0];
        $tecdocLinked = 0;

        foreach ($all as $row) {
            $type = (string) ($row['type'] ?? 'categorie');
            if (isset($byType[$type])) {
                $byType[$type]++;
            }
            if ((int) ($row['tecdoc_id'] ?? 0) > 0) {
                $tecdocLinked++;
            }
        }

        $unified = $this->readJson(self::unifiedJsonPath());
        $vehicle = $this->readJson(self::vehicleJsonPath());

        return array_merge($base, [
            'db_by_type' => $byType,
            'db_tecdoc_linked' => $tecdocLinked,
            'vehicle_json_path' => self::vehicleJsonPath(),
            'vehicle_json_exists' => is_file(self::vehicleJsonPath()),
            'vehicle_json_brands' => count(is_array($vehicle['brands'] ?? null) ? $vehicle['brands'] : []),
            'unified_json_path' => self::unifiedJsonPath(),
            'unified_json_exists' => is_file(self::unifiedJsonPath()),
            'last_synced_at' => (string) ($unified['synced_at'] ?? ''),
            'db_marca' => $byType['marca'],
            'db_model' => $byType['model'],
            'db_motorizare' => $byType['motorizare'],
        ]);
    }

    /**
     * Normalizează meta + legături părinte înainte de insert/update.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function enrichPayload(array $data, ?array $existing = null): array
    {
        $type = (string) ($data['type'] ?? $existing['type'] ?? 'categorie');
        $meta = $this->decodeMeta($data['meta'] ?? $existing['meta'] ?? null);

        if (!isset($meta['source']) || trim((string) $meta['source']) === '') {
            $tecdocId = (int) ($data['tecdoc_id'] ?? $existing['tecdoc_id'] ?? 0);
            if ($tecdocId > 0) {
                $meta['source'] = 'tecdoc';
            } elseif (in_array($type, ['categorie', 'subcategorie', 'familie'], true)) {
                $meta['source'] = 'manual';
            } else {
                $meta['source'] = 'local';
            }
        }

        $tecdocId = (int) ($data['tecdoc_id'] ?? $existing['tecdoc_id'] ?? 0);
        if ($tecdocId > 0) {
            $meta['tecdoc_id'] = $tecdocId;
        }

        $parentId = $data['parent_id'] ?? $existing['parent_id'] ?? null;
        if ($parentId !== null && $parentId !== '') {
            $parentId = (int) $parentId;
            if ($parentId > 0) {
                $parent = $this->model->findById($parentId);
                if (is_array($parent)) {
                    $meta['parent_id'] = $parentId;
                    $meta['parent_slug'] = (string) ($parent['slug'] ?? '');
                    $meta['parent_label'] = (string) ($parent['label'] ?? '');
                    if ((int) ($parent['tecdoc_id'] ?? 0) > 0) {
                        $meta['parent_tecdoc_id'] = (int) $parent['tecdoc_id'];
                    }
                }
            }
        }

        if ($type === 'subcategorie' && empty($data['slug'] ?? '') && !empty($data['label'] ?? '')) {
            $data['slug'] = $this->slugify((string) $data['label']);
        }

        $meta['updated_at'] = date('c');
        $data['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function buildPieseAutoCategoriesFromDb(): array
    {
        $all = $this->model->findAll();
        $byId = [];
        foreach ($all as $row) {
            $byId[(int) ($row['id'] ?? 0)] = $row;
        }

        $categories = [];
        foreach ($all as $row) {
            if ((string) ($row['type'] ?? '') !== 'categorie') {
                continue;
            }
            if ((int) ($row['parent_id'] ?? 0) !== 0) {
                continue;
            }

            $mainSlug = trim((string) ($row['slug'] ?? ''));
            $mainLabel = trim((string) ($row['label'] ?? ''));
            if ($mainLabel === '') {
                continue;
            }

            $mainMeta = $this->decodeMeta($row['meta'] ?? null);
            $items = [];

            foreach ($all as $subRow) {
                if ((string) ($subRow['type'] ?? '') !== 'subcategorie') {
                    continue;
                }
                if ((int) ($subRow['parent_id'] ?? 0) !== (int) ($row['id'] ?? 0)) {
                    continue;
                }

                $subLabel = trim((string) ($subRow['label'] ?? ''));
                if ($subLabel === '') {
                    continue;
                }

                $subSlug = trim((string) ($subRow['slug'] ?? ''));
                if ($subSlug === '') {
                    $subSlug = $this->slugify($subLabel);
                }

                $subMeta = $this->decodeMeta($subRow['meta'] ?? null);
                $items[] = [
                    'name' => $subLabel,
                    'href' => (string) ($subMeta['href'] ?? $this->pieseAutoHref($subSlug)),
                    'title' => $subLabel,
                    'slug' => $subSlug,
                    'db_id' => (int) ($subRow['id'] ?? 0),
                    'source' => (string) ($subMeta['source'] ?? 'besoiu'),
                    'tecdoc_id' => (int) ($subRow['tecdoc_id'] ?? 0) ?: null,
                ];
            }

            usort($items, static function (array $a, array $b): int {
                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });

            $categories[] = [
                'name' => $mainLabel,
                'href' => (string) ($mainMeta['href'] ?? $this->pieseAutoHref($mainSlug)),
                'title' => $mainLabel,
                'slug' => $mainSlug !== '' ? $mainSlug : $this->slugify($mainLabel),
                'count' => count($items),
                'items' => $items,
                'db_id' => (int) ($row['id'] ?? 0),
                'source' => (string) ($mainMeta['source'] ?? 'besoiu'),
                'tecdoc_id' => (int) ($row['tecdoc_id'] ?? 0) ?: null,
            ];
        }

        usort($categories, static function (array $a, array $b): int {
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $categories;
    }

    /** @return array<string, mixed> */
    private function buildVehicleFitmentFromDb(): array
    {
        $all = $this->model->findAll();
        $modelsByMarca = [];
        $motorsByModel = [];

        foreach ($all as $row) {
            $type = (string) ($row['type'] ?? '');
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            if ($type === 'model') {
                $parentId = (int) ($row['parent_id'] ?? 0);
                if ($parentId > 0) {
                    $modelsByMarca[$parentId][] = $row;
                }
            } elseif ($type === 'motorizare') {
                $parentId = (int) ($row['parent_id'] ?? 0);
                if ($parentId > 0) {
                    $motorsByModel[$parentId][] = $row;
                }
            }
        }

        $brands = [];
        $modelCount = 0;
        $motorCount = 0;
        $linkedModelIds = [];

        foreach ($all as $row) {
            if ((string) ($row['type'] ?? '') === 'model') {
                $modelCount++;
            } elseif ((string) ($row['type'] ?? '') === 'motorizare') {
                $motorCount++;
            }
        }

        foreach ($all as $row) {
            if ((string) ($row['type'] ?? '') !== 'marca') {
                continue;
            }

            $marcaId = (int) ($row['id'] ?? 0);
            $meta = $this->decodeMeta($row['meta'] ?? null);
            $models = [];

            foreach ($modelsByMarca[$marcaId] ?? [] as $modelRow) {
                $modelId = (int) ($modelRow['id'] ?? 0);
                $linkedModelIds[$modelId] = true;
                $modelMeta = $this->decodeMeta($modelRow['meta'] ?? null);
                $motorizations = [];

                foreach ($motorsByModel[$modelId] ?? [] as $motorRow) {
                    $motorMeta = $this->decodeMeta($motorRow['meta'] ?? null);
                    $motorizations[] = [
                        'id' => (int) ($motorRow['id'] ?? 0),
                        'label' => (string) ($motorRow['label'] ?? ''),
                        'slug' => (string) ($motorRow['slug'] ?? ''),
                        'tecdoc_id' => (int) ($motorRow['tecdoc_id'] ?? 0) ?: null,
                        'source' => (string) ($motorMeta['source'] ?? 'besoiu'),
                        'is_active' => (int) ($motorRow['is_active'] ?? 0) === 1,
                    ];
                }

                usort($motorizations, static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']));

                $models[] = [
                    'id' => $modelId,
                    'label' => (string) ($modelRow['label'] ?? ''),
                    'slug' => (string) ($modelRow['slug'] ?? ''),
                    'tecdoc_id' => (int) ($modelRow['tecdoc_id'] ?? 0) ?: null,
                    'source' => (string) ($modelMeta['source'] ?? 'besoiu'),
                    'is_active' => (int) ($modelRow['is_active'] ?? 0) === 1,
                    'motorizations' => $motorizations,
                ];
            }

            usort($models, static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']));

            $brands[] = [
                'id' => $marcaId,
                'label' => (string) ($row['label'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'tecdoc_id' => (int) ($row['tecdoc_id'] ?? 0) ?: null,
                'source' => (string) ($meta['source'] ?? 'besoiu'),
                'is_active' => (int) ($row['is_active'] ?? 0) === 1,
                'models' => $models,
            ];
        }

        usort($brands, static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']));

        $orphanModels = [];
        foreach ($all as $row) {
            if ((string) ($row['type'] ?? '') !== 'model') {
                continue;
            }
            $modelId = (int) ($row['id'] ?? 0);
            if ($modelId <= 0 || isset($linkedModelIds[$modelId])) {
                continue;
            }
            $modelMeta = $this->decodeMeta($row['meta'] ?? null);
            $orphanModels[] = [
                'id' => $modelId,
                'label' => (string) ($row['label'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                'tecdoc_id' => (int) ($row['tecdoc_id'] ?? 0) ?: null,
                'source' => (string) ($modelMeta['source'] ?? 'besoiu'),
            ];
        }

        return [
            'title' => 'Fitment vehicul — marcă, model, motorizare',
            'source' => 'categorii_db',
            'synced_at' => date('c'),
            'stats' => [
                'brands' => count($brands),
                'models' => $modelCount,
                'motorizations' => $motorCount,
                'orphan_models' => count($orphanModels),
            ],
            'brands' => $brands,
            'orphan_models' => $orphanModels,
        ];
    }

    /** @return array<string, mixed> */
    private function buildUnifiedIndex(?int $triggerId = null): array
    {
        $all = $this->model->findAll();
        $piese = ['categories' => [], 'subcategories' => []];
        $vehicule = ['marci' => [], 'modele' => [], 'motorizari' => []];
        $tecdoc = [];

        foreach ($all as $row) {
            $type = (string) ($row['type'] ?? 'categorie');
            $meta = $this->decodeMeta($row['meta'] ?? null);
            $entry = [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                'tecdoc_id' => (int) ($row['tecdoc_id'] ?? 0) ?: null,
                'source' => (string) ($meta['source'] ?? 'besoiu'),
                'is_active' => (int) ($row['is_active'] ?? 0) === 1,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];

            match ($type) {
                'categorie' => $piese['categories'][] = $entry,
                'subcategorie' => $piese['subcategories'][] = $entry,
                'marca' => $vehicule['marci'][] = $entry,
                'model' => $vehicule['modele'][] = $entry,
                'motorizare' => $vehicule['motorizari'][] = $entry,
                default => null,
            };

            if ($entry['tecdoc_id']) {
                $tecdoc[] = array_merge($entry, ['type' => $type]);
            }
        }

        return [
            'title' => 'Taxonomie unificată Besoiu',
            'synced_at' => date('c'),
            'trigger_id' => $triggerId,
            'stats' => [
                'total' => count($all),
                'categories' => count($piese['categories']),
                'subcategories' => count($piese['subcategories']),
                'marci' => count($vehicule['marci']),
                'modele' => count($vehicule['modele']),
                'motorizari' => count($vehicule['motorizari']),
                'tecdoc_linked' => count($tecdoc),
            ],
            'piese' => $piese,
            'vehicule' => $vehicule,
            'tecdoc_index' => $tecdoc,
            'files' => [
                'pieseauto' => PieseAutoCategoryCatalog::catalogJsonPath(),
                'vehicule' => self::vehicleJsonPath(),
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function writeJson(string $path, array $payload): string
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $written = file_put_contents(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        if ($written === false) {
            throw new RuntimeException('Nu s-a putut scrie fișierul JSON: ' . $path);
        }

        return $path;
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function pieseAutoHref(string $slug): string
    {
        if ($slug === '') {
            return '';
        }

        return 'https://www.pieseauto.ro/' . $slug . '/';
    }

    private function slugify(string $label): string
    {
        $slug = mb_strtolower($label, 'UTF-8');
        $slug = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ'],
            ['a', 'a', 'i', 's', 't', 's', 't'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? $slug;

        return trim($slug, '-');
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
