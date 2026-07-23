<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';

/**
 * Persistență arbore vehicule ePiesa (marca → model → versiune → motor).
 */
final class EpiesaVehicleTreeStore
{
    /** @return array<string, mixed> */
    public static function emptyTree(): array
    {
        return [
            'version' => 1,
            'updated_at' => date('c'),
            'stats' => [
                'brands' => 0,
                'models' => 0,
                'versions' => 0,
                'motors' => 0,
                'combos' => 0,
            ],
            'brands' => [],
        ];
    }

    /** @return array<string, mixed> */
    public static function load(): array
    {
        $path = ScraperPaths::epiesaVehicleTreePath();
        if (!is_file($path)) {
            return self::emptyTree();
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return self::emptyTree();
        }

        if (!isset($data['brands']) || !is_array($data['brands'])) {
            $data['brands'] = [];
        }

        return $data;
    }

    /** @param array<string, mixed> $tree */
    public static function save(array $tree): void
    {
        ScraperPaths::ensureDirs();
        $dir = dirname(ScraperPaths::epiesaVehicleTreePath());
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $tree['updated_at'] = date('c');
        $tree['stats'] = self::computeStats($tree);

        file_put_contents(
            ScraperPaths::epiesaVehicleTreePath(),
            json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    /** @param array<string, mixed> $tree @return array{brands:int,models:int,versions:int,motors:int,combos:int} */
    public static function computeStats(array $tree): array
    {
        $stats = ['brands' => 0, 'models' => 0, 'versions' => 0, 'motors' => 0, 'combos' => 0];
        foreach ($tree['brands'] ?? [] as $brand) {
            if (!is_array($brand)) {
                continue;
            }
            ++$stats['brands'];
            foreach ($brand['models'] ?? [] as $model) {
                if (!is_array($model)) {
                    continue;
                }
                ++$stats['models'];
                foreach ($model['versions'] ?? [] as $version) {
                    if (!is_array($version)) {
                        continue;
                    }
                    ++$stats['versions'];
                    $motors = is_array($version['motors'] ?? null) ? $version['motors'] : [];
                    $stats['motors'] += count($motors);
                    $stats['combos'] += count($motors);
                }
            }
        }

        return $stats;
    }

    /** @return array<string, mixed> */
    public static function loadCrawlState(): array
    {
        $path = ScraperPaths::epiesaVehicleCrawlStatePath();
        if (!is_file($path)) {
            return ['status' => 'idle', 'started_at' => null, 'finished_at' => null, 'last_brand_id' => 0];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : ['status' => 'idle'];
    }

    /** @param array<string, mixed> $state */
    public static function saveCrawlState(array $state): void
    {
        ScraperPaths::ensureDirs();
        file_put_contents(
            ScraperPaths::epiesaVehicleCrawlStatePath(),
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    /**
     * @param array<string, mixed> $tree
     * @return list<array<string, mixed>>
     */
    public static function flattenCombos(array $tree, int $limit = 0): array
    {
        $out = [];
        foreach ($tree['brands'] ?? [] as $brand) {
            if (!is_array($brand)) {
                continue;
            }
            foreach ($brand['models'] ?? [] as $model) {
                if (!is_array($model)) {
                    continue;
                }
                foreach ($model['versions'] ?? [] as $version) {
                    if (!is_array($version)) {
                        continue;
                    }
                    foreach ($version['motors'] ?? [] as $motor) {
                        if (!is_array($motor)) {
                            continue;
                        }
                        $out[] = [
                            'marca_id' => (int) ($brand['id'] ?? 0),
                            'marca' => (string) ($brand['nume'] ?? ''),
                            'model' => (string) ($model['nume'] ?? ''),
                            'versiune_id' => (int) ($version['id'] ?? 0),
                            'versiune' => (string) ($version['label'] ?? ''),
                            'motor_id' => (int) ($motor['id'] ?? 0),
                            'motor' => (string) ($motor['label'] ?? ''),
                            'url' => (string) ($motor['url'] ?? ''),
                        ];
                        if ($limit > 0 && count($out) >= $limit) {
                            return $out;
                        }
                    }
                }
            }
        }

        return $out;
    }

    /** Export listă plată cu toate combinațiile + URL-uri catalog. */
    public static function saveFlatCombos(array $tree): int
    {
        ScraperPaths::ensureDirs();
        $flat = self::flattenCombos($tree, 0);
        $payload = [
            'version' => 1,
            'updated_at' => date('c'),
            'count' => count($flat),
            'combos' => $flat,
        ];
        file_put_contents(
            ScraperPaths::epiesaVehicleCombosFlatPath(),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        return count($flat);
    }

    public static function isCrawlRunning(): bool
    {
        $state = self::loadCrawlState();
        if ((string) ($state['status'] ?? '') !== 'running') {
            return false;
        }
        $last = strtotime((string) ($state['last_progress_at'] ?? $state['started_at'] ?? '')) ?: 0;
        if ($last > 0 && (time() - $last) > 3600) {
            return false;
        }

        return true;
    }
}
