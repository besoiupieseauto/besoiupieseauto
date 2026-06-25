<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperIntegrationSchema.php';
require_once __DIR__ . '/ScraperImageSourcesSync.php';

/**
 * Config global integrare scraper — planuri imagini, reguli AI, sync pipeline.
 */
final class ScraperIntegrationStore
{
    private const FILE = 'integration_config.json';

    public static function filePath(): string
    {
        ScraperPaths::ensureDirs();

        return ScraperPaths::storageDir() . '/' . self::FILE;
    }

    /** @return array<string, mixed> */
    public static function load(): array
    {
        $path = self::filePath();
        if (!is_file($path)) {
            $cfg = self::seedFromImageSearchConfig();
            self::save($cfg);

            return $cfg;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return ScraperIntegrationSchema::normalizeGlobal(is_array($data) ? $data : []);
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public static function save(array $config): array
    {
        ScraperPaths::ensureDirs();
        $normalized = ScraperIntegrationSchema::normalizeGlobal($config);
        $normalized['updated_at'] = date('c');

        if (!empty($normalized['sync_to_env'])) {
            self::syncEnvOrder($normalized);
        }

        file_put_contents(
            self::filePath(),
            json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        ScraperImageSourcesSync::rebuild();

        return $normalized;
    }

    /** @return array<string, mixed> */
    private static function seedFromImageSearchConfig(): array
    {
        $cfg = ScraperIntegrationSchema::defaultGlobalConfig();
        $imgPath = ScraperPaths::projectRoot() . '/config/image-search-sources.php';
        if (!is_file($imgPath)) {
            return $cfg;
        }

        $img = require $imgPath;
        $sources = is_array($img['sources'] ?? null) ? $img['sources'] : [];
        $plans = [];
        $tier = 1;
        $rows = [];
        foreach ($sources as $id => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $rows[] = [
                'id' => (string) $id,
                'priority' => (int) ($meta['priority'] ?? 999),
                'enabled' => !empty($meta['enabled']),
                'label' => (string) ($meta['label'] ?? $id),
                'roles' => is_array($meta['roles'] ?? null) ? $meta['roles'] : ['image'],
            ];
        }
        usort($rows, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);
        foreach ($rows as $row) {
            if (!$row['enabled']) {
                continue;
            }
            $plans[] = [
                'tier' => $tier,
                'label' => 'Plan ' . $tier . ' — ' . $row['label'],
                'source_id' => $row['id'],
                'enabled' => true,
                'roles' => $row['roles'],
            ];
            $tier++;
        }
        if ($plans !== []) {
            $cfg['image_plans'] = $plans;
        }

        $audit = is_array($img['audit'] ?? null) ? $img['audit'] : [];
        $cfg['image_ai'] = array_replace_recursive($cfg['image_ai'], [
            'on_import_review' => !empty($audit['on_import_review']),
            'on_import_cron' => !empty($audit['on_import_cron']),
            'auto_retry_on_mismatch' => !empty($audit['auto_retry_on_mismatch']),
            'min_score_keep' => (int) ($audit['min_score_keep'] ?? 70),
            'verdicts_retry' => is_array($audit['verdicts_retry'] ?? null) ? $audit['verdicts_retry'] : ['mismatch'],
        ]);

        return $cfg;
    }

    /** @param array<string, mixed> $config */
    private static function syncEnvOrder(array $config): void
    {
        $ids = [];
        foreach ((array) ($config['image_plans'] ?? []) as $plan) {
            if (!is_array($plan) || empty($plan['enabled'])) {
                continue;
            }
            $ids[] = (string) ($plan['source_id'] ?? '');
        }
        $ids = array_values(array_filter($ids));
        if ($ids === []) {
            return;
        }

        $envPath = ScraperPaths::projectRoot() . '/admin/.env';
        if (!is_file($envPath)) {
            return;
        }

        $line = 'IMAGE_SEARCH_SOURCES=' . implode(',', $ids);
        $content = (string) file_get_contents($envPath);
        if (preg_match('/^IMAGE_SEARCH_SOURCES=.*$/m', $content)) {
            $content = preg_replace('/^IMAGE_SEARCH_SOURCES=.*$/m', $line, $content) ?? $content;
        } else {
            $content = rtrim($content) . "\n" . $line . "\n";
        }
        file_put_contents($envPath, $content);
    }

    /** Scrie ordinea planurilor în overlay JSON citit de pipeline. */
    /** @param array<string, mixed> $config */
    private static function syncImageSearchPhp(array $config): void
    {
        ScraperImageSourcesSync::rebuild();
    }

    /**
     * Planuri imagini active, ordonate.
     *
     * @return list<array<string, mixed>>
     */
    public static function activeImagePlans(): array
    {
        $cfg = self::load();
        $plans = [];
        foreach ((array) ($cfg['image_plans'] ?? []) as $plan) {
            if (!is_array($plan) || empty($plan['enabled'])) {
                continue;
            }
            $sid = trim((string) ($plan['source_id'] ?? ''));
            if ($sid === '') {
                continue;
            }
            $plans[] = $plan;
        }

        usort($plans, static fn (array $a, array $b): int => ((int) ($a['tier'] ?? 0)) <=> ((int) ($b['tier'] ?? 0)));

        return $plans;
    }

    /** @return array<string, mixed> */
    public static function imageAiConfig(): array
    {
        $cfg = self::load();

        return is_array($cfg['image_ai'] ?? null) ? $cfg['image_ai'] : ScraperIntegrationSchema::defaultImageAi();
    }

    /** Adaugă sursă nouă la finalul planurilor imagini. */
    public static function appendSourcePlan(string $sourceId, string $label): void
    {
        $cfg = self::load();
        foreach ((array) ($cfg['image_plans'] ?? []) as $plan) {
            if (is_array($plan) && ($plan['source_id'] ?? '') === $sourceId) {
                return;
            }
        }
        $tier = count((array) ($cfg['image_plans'] ?? [])) + 1;
        $cfg['image_plans'][] = [
            'tier' => $tier,
            'label' => 'Plan ' . $tier . ' — ' . $label,
            'source_id' => $sourceId,
            'enabled' => true,
            'roles' => ['image'],
        ];
        ScraperPaths::ensureDirs();
        file_put_contents(
            self::filePath(),
            json_encode(
                ScraperIntegrationSchema::normalizeGlobal($cfg),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    /** Elimină sursa din planurile imagini (fără rebuild — folosește ScraperImageSourcesSync::onSourceDeleted). */
    public static function removeSourcePlan(string $sourceId): void
    {
        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            return;
        }

        $cfg = self::load();
        $before = count((array) ($cfg['image_plans'] ?? []));
        $cfg['image_plans'] = array_values(array_filter(
            (array) ($cfg['image_plans'] ?? []),
            static fn ($plan): bool => is_array($plan) && (string) ($plan['source_id'] ?? '') !== $sourceId
        ));

        if (count($cfg['image_plans']) === $before) {
            return;
        }

        foreach ($cfg['image_plans'] as $i => &$plan) {
            if (is_array($plan)) {
                $plan['tier'] = $i + 1;
            }
        }
        unset($plan);

        ScraperPaths::ensureDirs();
        file_put_contents(
            self::filePath(),
            json_encode(
                ScraperIntegrationSchema::normalizeGlobal($cfg),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }
}
