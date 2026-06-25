<?php

declare(strict_types=1);

/**
 * Validare imagini pipeline — reguli AI (heuristic + opțional OpenAI Vision la test).
 */
final class PipelineImageAiGate
{
    /** @param array<string, mixed> $product @param array<string, mixed> $hit @param array<string, mixed> $opts */
    public static function filterHit(array $product, array $hit, array $opts = []): array
    {
        $pipelinePath = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
        if (is_file($pipelinePath)) {
            require_once $pipelinePath;
        }

        $rules = function_exists('besoiu_scraper_image_ai_rules')
            ? besoiu_scraper_image_ai_rules()
            : ['enabled' => false, 'min_score_keep' => 70];

        if (empty($rules['enabled'])) {
            return self::accept(100, 'match', 'Validare AI dezactivată în pipeline.');
        }

        $heuristic = self::heuristicValidate($product, $hit, $rules);
        if (!$heuristic['accepted']) {
            return $heuristic;
        }

        $useVision = !empty($opts['test_mode'])
            && empty($opts['skip_vision'])
            && !empty($rules['accept_product_match']);
        if ($useVision && self::openAiKey() !== '') {
            $vision = self::visionValidate($product, $hit, $rules);
            if (!$vision['accepted']) {
                return $vision;
            }

            return $vision;
        }

        return $heuristic;
    }

    /** @param array<string, mixed> $product @param array<string, mixed> $hit @param array<string, mixed> $rules */
    private static function heuristicValidate(array $product, array $hit, array $rules): array
    {
        $minKeep = max(0, min(100, (int) ($rules['min_score_keep'] ?? 70)));
        $url = strtolower(trim((string) ($hit['url'] ?? '')));
        $hitTitle = trim((string) ($hit['title'] ?? ''));
        $name = trim((string) ($product['pName'] ?? ''));
        $category = trim((string) ($product['pCategory'] ?? ''));
        $subcategory = trim((string) ($product['pSubcategory'] ?? ''));

        $score = 72;
        $issues = [];

        if ($url === '') {
            return self::reject(0, 'mismatch', 'Lipsește URL imagine.');
        }

        if (!empty($rules['reject_placeholder'])) {
            foreach (['brands/thumbs', '360-icon', '/assets/', '.svg', 'lazyload.php', 'placeholder'] as $bad) {
                if (str_contains($url, $bad)) {
                    return self::reject(5, 'mismatch', 'Imagine respinsă: logo / placeholder (' . $bad . ').');
                }
            }
        }

        $wantBrand = trim((string) ($product['pBrand'] ?? ''));
        if ($wantBrand === '' && function_exists('besoiu_image_detect_brand_from_text')) {
            $wantBrand = besoiu_image_detect_brand_from_text($name);
        }

        if ($wantBrand !== '' && $hitTitle !== '') {
            $wantLower = strtolower($wantBrand);
            $titleLower = strtolower($hitTitle);
            if (!str_contains($titleLower, $wantLower)) {
                $wrongBrand = self::detectForeignBrand($titleLower, $wantLower);
                if ($wrongBrand !== '') {
                    $score -= 55;
                    $issues[] = 'Brand greșit în sursă: ' . $wrongBrand . ' (așteptat ' . $wantBrand . ')';
                } else {
                    $score -= 25;
                    $issues[] = 'Titlul sursei nu menționează brandul «' . $wantBrand . '»';
                }
            } else {
                $score += 12;
            }
        }

        if (!empty($rules['reject_wrong_category'])) {
            $catAdjust = self::categoryMatchScore($name, $category, $subcategory, $hitTitle);
            $score += $catAdjust;
            if ($catAdjust <= -40) {
                $issues[] = 'Categorie/familie piesă nepotrivită față de titlul produsului';
            }
        }

        $wantCode = preg_replace('/\D+/', '', (string) ($product['pCode'] ?? '')) ?? '';
        $hitSku = preg_replace('/\D+/', '', (string) ($hit['sku'] ?? '')) ?? '';
        if ($wantCode !== '' && strlen($wantCode) >= 5) {
            if ($hitSku === $wantCode || str_contains(preg_replace('/\D+/', '', strtolower($hitTitle)) ?? '', $wantCode)) {
                $score += 15;
            }
        }

        $score = max(0, min(100, $score));
        if ($score < $minKeep) {
            $msg = 'AI heuristic: scor ' . $score . '/100 (min ' . $minKeep . ')';
            if ($issues !== []) {
                $msg .= ' — ' . implode('; ', $issues);
            }

            return self::reject($score, 'mismatch', $msg);
        }

        $promptPenalty = self::promptExtraAdjust($rules, $hitTitle, $name, $category);
        $score += $promptPenalty;
        if ($promptPenalty < 0) {
            $issues[] = 'Reguli operator: imagine nepotrivită';
        }
        $score = max(0, min(100, $score));
        if ($score < $minKeep) {
            return self::reject($score, 'mismatch', 'AI heuristic (reguli operator): scor ' . $score . '/100');
        }

        $msg = 'AI heuristic: OK (' . $score . '/100)';
        if ($issues !== []) {
            $msg .= ' — ' . implode('; ', $issues);
        }

        return self::accept($score, $score >= 85 ? 'match' : 'partial', $msg);
    }

    /** @param array<string, mixed> $product @param array<string, mixed> $hit @param array<string, mixed> $rules */
    private static function visionValidate(array $product, array $hit, array $rules): array
    {
        $minKeep = max(0, min(100, (int) ($rules['min_score_keep'] ?? 70)));
        $apiKey = self::openAiKey();
        if ($apiKey === '') {
            return self::accept(70, 'partial', 'OpenAI indisponibil — doar validare heuristică.');
        }

        $auditPath = dirname(__DIR__, 2) . '/admin/src/Services/ProductImageAuditService.php';
        if (!is_file($auditPath)) {
            return self::accept(70, 'partial', 'Serviciu audit indisponibil.');
        }

        require_once $auditPath;

        $name = trim((string) ($product['pName'] ?? ''));
        $brand = trim((string) ($product['pBrand'] ?? ''));
        if ($brand === '' && function_exists('besoiu_image_detect_brand_from_text')) {
            $brand = besoiu_image_detect_brand_from_text($name);
        }

        $auditProduct = [
            'randomn_id' => 'pipeline_test',
            'title' => $name,
            'code' => trim((string) ($product['pCode'] ?? '')),
            'brand' => $brand,
            'category' => trim((string) ($product['pCategory'] ?? '')),
            'subcategory' => trim((string) ($product['pSubcategory'] ?? '')),
            'description_excerpt' => trim((string) ($hit['title'] ?? '')),
            'image_url' => trim((string) ($hit['url'] ?? '')),
        ];

        $model = 'gpt-4o-mini';
        if (function_exists('besoiu_env_model_value')) {
            $model = besoiu_env_model_value('OPENAI_MODEL', 'gpt-4o-mini');
        }

        try {
            $service = new \Evasystem\Services\ProductImageAuditService(dirname(__DIR__, 2));
            $result = $service->analyzeProductWithVision($auditProduct, $apiKey, $model);
            $verdict = strtolower((string) ($result['verdict'] ?? 'uncertain'));
            $score = (int) ($result['match_score'] ?? 0);
            $summary = trim((string) ($result['summary_ro'] ?? ''));

            if (in_array($verdict, ['mismatch', 'no_image', 'error'], true) || $score < $minKeep) {
                $msg = 'AI Vision: ' . ($summary !== '' ? $summary : ('verdict ' . $verdict . ', scor ' . $score));

                return self::reject($score, $verdict !== '' ? $verdict : 'mismatch', $msg);
            }

            return self::accept($score, $verdict !== '' ? $verdict : 'match', 'AI Vision: ' . ($summary !== '' ? $summary : ('scor ' . $score)));
        } catch (Throwable $e) {
            return self::accept(65, 'partial', 'Vision indisponibil: ' . $e->getMessage());
        }
    }

    private static function categoryMatchScore(string $name, string $category, string $subcategory, string $hitTitle): int
    {
        $blob = strtolower(trim(implode(' ', array_filter([$name, $category, $subcategory]))));
        $titleLower = strtolower($hitTitle);
        if ($blob === '' || $titleLower === '') {
            return 0;
        }

        $groups = [
            'filter' => ['filtru', 'filter', 'mann-filter'],
            'fluid' => ['lichid', 'ulei', 'oil', 'antigel', 'brake fluid', 'lichid frana', 'lichid frână'],
            'brake' => ['frana', 'frână', 'brake', 'disc', 'placute', 'plăcuțe', 'etrier'],
            'suspension' => ['suspensie', 'amortizor', 'arc', 'rulment', 'bieleta', 'cap bara', 'bara stabilizatoare'],
            'engine' => ['motor', 'piston', 'cuzinet', 'segment', 'chiulas'],
        ];

        $want = [];
        foreach ($groups as $key => $words) {
            foreach ($words as $word) {
                if (str_contains($blob, $word)) {
                    $want[$key] = true;
                    break;
                }
            }
        }

        if ($want === []) {
            return 0;
        }

        $adjust = 0;
        foreach ($groups as $key => $words) {
            $inTitle = false;
            foreach ($words as $word) {
                if (str_contains($titleLower, $word)) {
                    $inTitle = true;
                    break;
                }
            }
            if (!$inTitle) {
                continue;
            }
            if (!empty($want[$key])) {
                $adjust += 15;
            } elseif (count($want) === 1) {
                $adjust -= 50;
            }
        }

        return $adjust;
    }

    private static function detectForeignBrand(string $titleLower, string $wantLower): string
    {
        $brands = [
            'trw', 'mann', 'bosch', 'meyle', 'nk', 'sachs', 'valeo', 'gates', 'ridex',
            'febi', 'blue print', 'blueprint', 'skf', 'fag', 'ina', 'lemförder', 'lemforder',
            'continental', 'ate', 'brembo', 'mahle', 'knecht', 'purflux', 'hengst', 'filtron',
        ];

        foreach ($brands as $brand) {
            if ($brand === $wantLower) {
                continue;
            }
            if (str_contains($titleLower, $brand)) {
                return strtoupper($brand);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $rules */
    private static function promptExtraAdjust(array $rules, string $hitTitle, string $productName, string $category): int
    {
        $extra = strtolower(trim((string) ($rules['prompt_extra'] ?? '')));
        if ($extra === '') {
            return 0;
        }

        $titleLower = strtolower($hitTitle);
        $adjust = 0;

        if (str_contains($extra, 'mașini întregi') || str_contains($extra, 'masini intregi')) {
            foreach (['autoturism', 'masina intreaga', 'vehicul complet', 'whole car', 'full car'] as $bad) {
                if (str_contains($titleLower, $bad)) {
                    $adjust -= 60;
                }
            }
        }

        if (str_contains($extra, 'logo')) {
            foreach (['logo', 'brand banner', 'manufacturer logo'] as $bad) {
                if (str_contains($titleLower, $bad) && strlen($titleLower) < 40) {
                    $adjust -= 50;
                }
            }
        }

        if (str_contains($extra, 'fără piesă') || str_contains($extra, 'fara piesa')) {
            if ($titleLower === '' || strlen($titleLower) < 8) {
                $adjust -= 30;
            }
        }

        return $adjust;
    }

    private static function openAiKey(): string
    {
        $key = trim((string) ($_ENV['OPENAI_KEY'] ?? getenv('OPENAI_KEY') ?: ''));

        return $key;
    }

    /** @return array{accepted: bool, score: int, verdict: string, message: string} */
    private static function accept(int $score, string $verdict, string $message): array
    {
        return ['accepted' => true, 'score' => $score, 'verdict' => $verdict, 'message' => $message];
    }

    /** @return array{accepted: bool, score: int, verdict: string, message: string} */
    private static function reject(int $score, string $verdict, string $message): array
    {
        return ['accepted' => false, 'score' => $score, 'verdict' => $verdict, 'message' => $message];
    }
}
