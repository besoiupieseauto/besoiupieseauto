<?php

declare(strict_types=1);

/**
 * Schema integrare scraper ↔ pipeline imagini / import / audit AI.
 */
final class ScraperIntegrationSchema
{
    /** @return array<string, mixed> */
    public static function defaultGlobalConfig(): array
    {
        return [
            'version' => 1,
            'image_plans' => [
                ['tier' => 1, 'label' => 'Plan principal', 'source_id' => 'caietcomenzi', 'enabled' => true, 'roles' => ['image']],
                ['tier' => 2, 'label' => 'Plan secundar', 'source_id' => 'tecdoc_csv', 'enabled' => true, 'roles' => ['image', 'description']],
                ['tier' => 3, 'label' => 'Plan 3', 'source_id' => 'epiesa', 'enabled' => true, 'roles' => ['image', 'title']],
                ['tier' => 4, 'label' => 'Plan 4', 'source_id' => 'autodoc', 'enabled' => true, 'roles' => ['image', 'title']],
                ['tier' => 5, 'label' => 'Plan 5', 'source_id' => 'tecdoc_api', 'enabled' => true, 'roles' => ['image', 'oem', 'description']],
            ],
            'image_ai' => self::defaultImageAi(),
            'sync_to_env' => false,
            'updated_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultImageAi(): array
    {
        return [
            'enabled' => true,
            'engine' => 'cursor',
            'prompt_extra' => '',
            'accept_white_background' => true,
            'accept_product_match' => true,
            'reject_placeholder' => true,
            'reject_wrong_category' => true,
            'min_score_keep' => 70,
            'on_import_review' => true,
            'on_import_cron' => false,
            'auto_retry_on_mismatch' => true,
            'verdicts_retry' => ['mismatch', 'error', 'no_image'],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultSourceIntegration(): array
    {
        return [
            'use_in_image_pipeline' => true,
            'pipeline_tier' => null,
            'extraction_goals' => [],
            'image_ai' => [
                'enabled' => false,
                'prompt_extra' => '',
            ],
            'rapidapi' => [
                'validate_on_import' => false,
                'fields' => ['oem', 'image', 'description'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function extractionGoalCatalog(): array
    {
        return [
            [
                'type' => 'oem_codes',
                'label' => 'Coduri OEM',
                'hint' => 'Extrage coduri OEM din bloc text / listă compatibilitate.',
                'map_to' => 'pOem',
            ],
            [
                'type' => 'description',
                'label' => 'Descriere detaliată produs',
                'hint' => 'Text descriptiv complet pentru pagina produs.',
                'map_to' => 'pNote',
            ],
            [
                'type' => 'gap_fill',
                'label' => 'Completează ce lipsește la noi',
                'hint' => 'Adaugă câmpuri goale în BD (titlu, specs, OEM) dacă sursa le are.',
                'map_to' => 'auto',
            ],
            [
                'type' => 'rapidapi_validate',
                'label' => 'Validează cu TecDoc RapidAPI',
                'hint' => 'Verifică cod/OEM cu API și completează imagine + descriere oficială.',
                'map_to' => 'raw_json.scraper_validation',
            ],
            [
                'type' => 'sku',
                'label' => 'SKU / cod articol',
                'hint' => 'Cod furnizor sau articol din pagină.',
                'map_to' => 'pCode',
            ],
            [
                'type' => 'custom_text',
                'label' => 'Câmp text custom',
                'hint' => 'Extrage orice bloc text — denumești tu ce cauți.',
                'map_to' => 'custom',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function normalizeGlobal(array $config): array
    {
        $base = self::defaultGlobalConfig();
        $merged = array_replace_recursive($base, $config);
        $merged['version'] = 1;

        $plans = [];
        foreach ((array) ($merged['image_plans'] ?? []) as $i => $plan) {
            if (!is_array($plan)) {
                continue;
            }
            $sourceId = trim((string) ($plan['source_id'] ?? ''));
            if ($sourceId === '') {
                continue;
            }
            $plans[] = [
                'tier' => (int) ($plan['tier'] ?? ($i + 1)),
                'label' => trim((string) ($plan['label'] ?? 'Plan ' . ($i + 1))),
                'source_id' => $sourceId,
                'enabled' => !isset($plan['enabled']) || !empty($plan['enabled']),
                'roles' => is_array($plan['roles'] ?? null) ? array_values($plan['roles']) : ['image'],
            ];
        }
        usort($plans, static fn (array $a, array $b): int => $a['tier'] <=> $b['tier']);
        foreach ($plans as $i => &$p) {
            $p['tier'] = $i + 1;
        }
        unset($p);
        $merged['image_plans'] = $plans;

        return $merged;
    }

    /**
     * @param array<string, mixed> $integration
     * @return array<string, mixed>
     */
    public static function normalizeSourceIntegration(array $integration): array
    {
        $base = self::defaultSourceIntegration();
        $merged = array_replace_recursive($base, $integration);

        $goals = [];
        foreach ((array) ($merged['extraction_goals'] ?? []) as $goal) {
            if (!is_array($goal)) {
                continue;
            }
            $type = trim((string) ($goal['type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $goals[] = [
                'id' => trim((string) ($goal['id'] ?? 'goal_' . substr(md5($type . json_encode($goal)), 0, 8))),
                'type' => $type,
                'label' => trim((string) ($goal['label'] ?? $type)),
                'enabled' => !isset($goal['enabled']) || !empty($goal['enabled']),
                'selector' => trim((string) ($goal['selector'] ?? '')),
                'step_element_id' => trim((string) ($goal['step_element_id'] ?? '')),
                'rapidapi_validate' => !empty($goal['rapidapi_validate']) || $type === 'rapidapi_validate',
                'map_to' => trim((string) ($goal['map_to'] ?? '')),
                'notes' => trim((string) ($goal['notes'] ?? '')),
            ];
        }
        $merged['extraction_goals'] = $goals;

        return $merged;
    }

    /**
     * Construiește fragment prompt AI din config global + sursă.
     *
     * @param array<string, mixed>|null $globalAi
     * @param array<string, mixed>|null $sourceAi
     */
    public static function buildImageAiPromptExtra(?array $globalAi, ?array $sourceAi, ?string $sourceId = null): string
    {
        $lines = [];
        $g = is_array($globalAi) ? $globalAi : self::defaultImageAi();
        $s = is_array($sourceAi) ? $sourceAi : [];

        if ($sourceId !== null && $sourceId !== '') {
            $lines[] = 'Sursă imagine: ' . $sourceId;
        }

        if (!empty($g['accept_white_background'])) {
            $lines[] = '- Acceptă imagine cu fundal alb / studio dacă produsul e clar vizibil.';
        }
        if (!empty($g['accept_product_match'])) {
            $lines[] = '- Acceptă dacă imaginea se potrivește clar cu titlul și categoria produsului.';
        }
        if (!empty($g['reject_placeholder'])) {
            $lines[] = '- Respinge placeholder, stele, logo generic fără produs.';
        }
        if (!empty($g['reject_wrong_category'])) {
            $lines[] = '- Respinge dacă e altă categorie (ex: ulei la filtru, mașină întreagă la piesă mică).';
        }

        $extra = trim((string) ($g['prompt_extra'] ?? ''));
        if ($extra !== '') {
            $lines[] = 'Reguli operator (global): ' . $extra;
        }
        $extraS = trim((string) ($s['prompt_extra'] ?? ''));
        if ($extraS !== '') {
            $lines[] = 'Reguli operator (sursă): ' . $extraS;
        }

        return implode("\n", $lines);
    }
}
