<?php

declare(strict_types=1);

/**
 * Bridge Scraper → Metro LLM Orchestra (LlmRouterService).
 */
final class ScraperMetroLlmBridge
{
    /**
     * @param list<string> $fieldsNeeded
     * @return array<string, mixed>
     */
    public static function analyzeHtml(
        string $projectRoot,
        string $html,
        string $sourceId,
        string $userGoals,
        array $fieldsNeeded = ['title', 'image', 'url', 'price'],
    ): array {
        $autoload = rtrim($projectRoot, '/\\') . '/admin/vendor/autoload.php';
        if (!is_file($autoload)) {
            return ['ok' => false, 'error' => 'Composer autoload lipsă.'];
        }
        require_once $autoload;

        if (!defined('BESOIU_API_MANUAL_CALL')) {
            define('BESOIU_API_MANUAL_CALL', true);
        }

        require_once __DIR__ . '/ScraperHtmlSample.php';

        $context = ScraperHtmlSample::buildContext(trim($html));
        if ($context === []) {
            return ['ok' => false, 'error' => 'HTML gol sau invalid.'];
        }

        $compassLines = [];
        foreach ((array) ($context['compass'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $compassLines[] = ($row['token'] ?? '') . ' (' . ($row['count'] ?? 0) . '×)';
        }

        $fieldsJson = json_encode($fieldsNeeded, JSON_UNESCAPED_UNICODE);
        $prompt = "Sursă: {$sourceId}\nPagină: " . ($context['title'] ?? '') . "\nCerință: {$userGoals}\nCâmpuri: {$fieldsJson}\n";
        $prompt .= 'Busolă: ' . implode(', ', array_slice($compassLines, 0, 12)) . "\n";
        $prompt .= "Răspunde DOAR JSON: {\"selectors\":{\"block\":\"...\"},\"items\":[],\"explanation_ro\":\"...\"}\n\nHTML:\n";
        $prompt .= (string) ($context['snippet'] ?? '');

        $orch = \Besoiu\Services\MetroAiOrchestrator::create($projectRoot);
        $res = $orch->dispatchComplete(
            'scraper_analyze',
            'Agent scraping e-commerce. Răspunde doar JSON valid pentru selectori CSS.',
            $prompt,
            ['temperature' => 0.1, 'timeout_sec' => 60, 'module_id' => 'scraper_analyze']
        );

        if (empty($res['ok'])) {
            return $res;
        }

        $parsed = self::parseJsonResponse((string) ($res['content'] ?? ''));
        if ($parsed === null) {
            return [
                'ok' => false,
                'error' => 'Răspuns LLM nu e JSON valid.',
                'routed_via' => (string) ($res['routed_via'] ?? ''),
                'raw' => mb_substr((string) ($res['content'] ?? ''), 0, 200),
            ];
        }

        return [
            'ok' => true,
            'mode' => 'metro-orchestra',
            'llm_used' => true,
            'provider' => (string) ($res['routed_via'] ?? 'metro'),
            'model' => (string) ($res['model'] ?? ''),
            'routed_via' => (string) ($res['routed_via'] ?? ''),
            'selectors' => is_array($parsed['selectors'] ?? null) ? $parsed['selectors'] : [],
            'items' => is_array($parsed['items'] ?? null) ? $parsed['items'] : [],
            'explanation_ro' => (string) ($parsed['explanation_ro'] ?? ''),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function parseJsonResponse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $raw = $m[0];
        }
        $json = json_decode($raw, true);

        return is_array($json) ? $json : null;
    }
}
