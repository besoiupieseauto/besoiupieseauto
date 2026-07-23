<?php

declare(strict_types=1);

require_once __DIR__ . '/EpiesaVehicleSelectorClient.php';
require_once __DIR__ . '/EpiesaVehicleTreeStore.php';
require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperLogger.php';

/**
 * Parcurge toate combinațiile marca → model → generație → motorizare (API ePiesa).
 */
final class EpiesaVehicleTreeCrawler
{
    /**
     * @param array<string, mixed> $options
     *   - delay_ms (int) pauză între apeluri API
     *   - resume (bool) sare mărcile deja salvate
     *   - max_brands (int) 0 = toate
     *   - max_models_per_brand (int) 0 = toate
     *   - brand_ids (list<int>) filtru opțional
     * @return array<string, mixed>
     */
    public static function crawl(array $options = []): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        $delayMs = max(0, min(2000, (int) ($options['delay_ms'] ?? 120)));
        $resume = !array_key_exists('resume', $options) || !empty($options['resume']);
        $maxBrands = max(0, (int) ($options['max_brands'] ?? 0));
        $maxModels = max(0, (int) ($options['max_models_per_brand'] ?? 0));
        $brandFilter = [];
        if (isset($options['brand_ids']) && is_array($options['brand_ids'])) {
            foreach ($options['brand_ids'] as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $brandFilter[$id] = true;
                }
            }
        }

        $tree = $resume ? EpiesaVehicleTreeStore::load() : EpiesaVehicleTreeStore::emptyTree();
        $existingBrandIds = [];
        foreach ($tree['brands'] as $b) {
            if (is_array($b) && isset($b['id'])) {
                $existingBrandIds[(int) $b['id']] = true;
            }
        }

        $state = [
            'status' => 'running',
            'started_at' => date('c'),
            'finished_at' => null,
            'delay_ms' => $delayMs,
            'processed_brands' => 0,
            'skipped_brands' => 0,
            'total_brands_estimated' => 0,
            'last_progress_at' => date('c'),
            'last_brand_id' => 0,
            'last_brand' => '',
            'stats_snapshot' => EpiesaVehicleTreeStore::computeStats($tree),
            'errors' => [],
            'cancel_requested' => false,
        ];
        EpiesaVehicleTreeStore::saveCrawlState($state);

        ScraperLogger::log('info', 'Start crawl arbore vehicule ePiesa');

        $marci = EpiesaVehicleSelectorClient::marci();
        $state['total_brands_estimated'] = count($marci);
        $state['last_progress_at'] = date('c');
        EpiesaVehicleTreeStore::saveCrawlState($state);
        EpiesaVehicleSelectorClient::sleepMs($delayMs);

        $brandCount = 0;
        $newBrands = [];

        foreach ($marci as $marca) {
            $liveState = EpiesaVehicleTreeStore::loadCrawlState();
            if (!empty($liveState['cancel_requested'])) {
                $state['status'] = 'cancelled';
                $state['finished_at'] = date('c');
                $state['message'] = 'Oprit de utilizator.';
                EpiesaVehicleTreeStore::saveCrawlState($state);
                break;
            }

            $mfaId = (int) ($marca['id'] ?? 0);
            $marcaNume = (string) ($marca['nume'] ?? '');
            if ($mfaId <= 0 || $marcaNume === '') {
                continue;
            }

            if ($brandFilter !== [] && !isset($brandFilter[$mfaId])) {
                continue;
            }

            if ($resume && isset($existingBrandIds[$mfaId])) {
                ++$state['skipped_brands'];
                continue;
            }

            if ($maxBrands > 0 && $brandCount >= $maxBrands) {
                break;
            }

            $brandNode = [
                'id' => $mfaId,
                'nume' => $marcaNume,
                'top' => !empty($marca['top']),
                'models' => [],
            ];

            try {
                $modele = EpiesaVehicleSelectorClient::modele($mfaId);
            } catch (Throwable $e) {
                $state['errors'][] = 'modele mfa=' . $mfaId . ': ' . $e->getMessage();
                ScraperLogger::log('warn', 'ePiesa modele eșuat | mfa=' . $mfaId);
                continue;
            }
            EpiesaVehicleSelectorClient::sleepMs($delayMs);

            $modelCount = 0;
            foreach ($modele as $modelRow) {
                $modelNume = (string) ($modelRow['nume'] ?? '');
                if ($modelNume === '') {
                    continue;
                }
                if ($maxModels > 0 && $modelCount >= $maxModels) {
                    break;
                }

                $modelNode = [
                    'nume' => $modelNume,
                    'versions' => [],
                ];

                try {
                    $versiuni = EpiesaVehicleSelectorClient::versiuni($mfaId, $modelNume);
                } catch (Throwable $e) {
                    $state['errors'][] = 'versiuni ' . $marcaNume . '/' . $modelNume . ': ' . $e->getMessage();
                    continue;
                }
                EpiesaVehicleSelectorClient::sleepMs($delayMs);

                foreach ($versiuni as $vers) {
                    $modId = (int) ($vers['id'] ?? 0);
                    $versLabel = (string) ($vers['label'] ?? '');
                    if ($modId <= 0) {
                        continue;
                    }

                    $versionNode = [
                        'id' => $modId,
                        'label' => $versLabel,
                        'motors' => [],
                    ];

                    try {
                        $motors = EpiesaVehicleSelectorClient::motorizari($modId);
                    } catch (Throwable $e) {
                        $state['errors'][] = 'motor mod=' . $modId . ': ' . $e->getMessage();
                        continue;
                    }
                    EpiesaVehicleSelectorClient::sleepMs($delayMs);

                    foreach ($motors as $motor) {
                        $typId = (int) ($motor['id'] ?? 0);
                        $motorLabel = (string) ($motor['label'] ?? '');
                        if ($typId <= 0) {
                            continue;
                        }

                        try {
                            $catalogUrl = EpiesaVehicleSelectorClient::resolveCatalogUrl(
                                $marcaNume,
                                $modelNume,
                                $modId,
                                $typId
                            );
                        } catch (Throwable $e) {
                            $state['errors'][] = 'url typ=' . $typId . ': ' . $e->getMessage();
                            continue;
                        }
                        EpiesaVehicleSelectorClient::sleepMs($delayMs);

                        $versionNode['motors'][] = [
                            'id' => $typId,
                            'label' => $motorLabel,
                            'url' => $catalogUrl,
                        ];
                    }

                    if ($versionNode['motors'] !== []) {
                        $modelNode['versions'][] = $versionNode;
                    }
                }

                if ($modelNode['versions'] !== []) {
                    $brandNode['models'][] = $modelNode;
                    ++$modelCount;
                }
            }

            if ($brandNode['models'] !== []) {
                $newBrands[] = $brandNode;
                $tree['brands'][] = $brandNode;
                EpiesaVehicleTreeStore::save($tree);
                ++$brandCount;
                ++$state['processed_brands'];
                $state['last_brand_id'] = $mfaId;
                $state['last_brand'] = $marcaNume;
                $state['last_progress_at'] = date('c');
                $state['stats_snapshot'] = EpiesaVehicleTreeStore::computeStats($tree);
                EpiesaVehicleTreeStore::saveCrawlState($state);
                ScraperLogger::log('info', 'ePiesa arbore | marcă salvată: ' . $marcaNume . ' | combos=' . (EpiesaVehicleTreeStore::computeStats($tree)['combos'] ?? 0));
            }
        }

        if (($state['status'] ?? '') === 'running') {
            $state['status'] = 'completed';
        }
        $state['finished_at'] = date('c');
        $tree['stats'] = EpiesaVehicleTreeStore::computeStats($tree);
        EpiesaVehicleTreeStore::save($tree);
        $flatCount = EpiesaVehicleTreeStore::saveFlatCombos($tree);
        $state['flat_combos_path'] = ScraperPaths::epiesaVehicleCombosFlatPath();
        $state['flat_combos_count'] = $flatCount;
        EpiesaVehicleTreeStore::saveCrawlState($state);

        $sample = EpiesaVehicleTreeStore::flattenCombos($tree, 5);

        return [
            'ok' => true,
            'message' => sprintf(
                'Arbore vehicule: %d mărci noi, %d sărite (resume), %d combinații motor totale, %d linkuri exportate.',
                $brandCount,
                (int) ($state['skipped_brands'] ?? 0),
                (int) ($tree['stats']['combos'] ?? 0),
                (int) ($state['flat_combos_count'] ?? 0)
            ),
            'stats' => $tree['stats'],
            'crawl_state' => $state,
            'sample_combos' => $sample,
            'tree_path' => ScraperPaths::epiesaVehicleTreePath(),
            'flat_combos_path' => ScraperPaths::epiesaVehicleCombosFlatPath(),
            'flat_combos_count' => (int) ($state['flat_combos_count'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public static function status(): array
    {
        $tree = EpiesaVehicleTreeStore::load();
        $state = EpiesaVehicleTreeStore::loadCrawlState();

        return [
            'tree' => [
                'updated_at' => $tree['updated_at'] ?? null,
                'stats' => EpiesaVehicleTreeStore::computeStats($tree),
                'path' => ScraperPaths::epiesaVehicleTreePath(),
            ],
            'crawl_state' => $state,
            'running' => EpiesaVehicleTreeStore::isCrawlRunning(),
            'flat_combos' => [
                'path' => ScraperPaths::epiesaVehicleCombosFlatPath(),
                'exists' => is_file(ScraperPaths::epiesaVehicleCombosFlatPath()),
            ],
            'sample_combos' => EpiesaVehicleTreeStore::flattenCombos($tree, 3),
            'progress_pct' => self::progressPercent($state),
        ];
    }

    /** @param array<string, mixed> $state */
    private static function progressPercent(array $state): int
    {
        $total = max(1, (int) ($state['total_brands_estimated'] ?? 0));
        $done = (int) ($state['processed_brands'] ?? 0) + (int) ($state['skipped_brands'] ?? 0);
        if (($state['status'] ?? '') === 'completed') {
            return 100;
        }
        if (($state['status'] ?? '') !== 'running') {
            return 0;
        }

        return max(0, min(99, (int) round(($done / $total) * 100)));
    }

    public static function requestCancel(): void
    {
        $state = EpiesaVehicleTreeStore::loadCrawlState();
        $state['cancel_requested'] = true;
        $state['cancelled_at'] = date('c');
        EpiesaVehicleTreeStore::saveCrawlState($state);
    }

    /** Preview un singur lanț (test rapid). @return array<string, mixed> */
    public static function previewFirstChain(?int $mfaId = null): array
    {
        $marci = EpiesaVehicleSelectorClient::marci();
        if ($marci === []) {
            throw new RuntimeException('Lista mărci goală.');
        }

        $marca = null;
        if ($mfaId !== null && $mfaId > 0) {
            foreach ($marci as $row) {
                if ((int) ($row['id'] ?? 0) === $mfaId) {
                    $marca = $row;
                    break;
                }
            }
        }
        $marca ??= $marci[0];

        $mfa = (int) $marca['id'];
        $modele = EpiesaVehicleSelectorClient::modele($mfa);
        if ($modele === []) {
            throw new RuntimeException('Fără modele pentru marca ' . ($marca['nume'] ?? ''));
        }
        $model = (string) $modele[0]['nume'];

        $versiuni = EpiesaVehicleSelectorClient::versiuni($mfa, $model);
        if ($versiuni === []) {
            throw new RuntimeException('Fără versiuni pentru ' . $model);
        }
        $vers = $versiuni[0];
        $modId = (int) $vers['id'];

        $motors = EpiesaVehicleSelectorClient::motorizari($modId);
        if ($motors === []) {
            throw new RuntimeException('Fără motorizări pentru mod=' . $modId);
        }
        $motor = $motors[0];
        $url = EpiesaVehicleSelectorClient::resolveCatalogUrl(
            (string) $marca['nume'],
            $model,
            $modId,
            (int) $motor['id']
        );

        return [
            'marca' => $marca,
            'model' => $model,
            'versiune' => $vers,
            'motor' => $motor,
            'catalog_url' => $url,
        ];
    }
}
