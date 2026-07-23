<?php

declare(strict_types=1);

namespace Besoiu\Services;

/** Pattern-uri invatate + adaptare la cereri necunoscute (buton Invata). */
final class SectionAssistantLearnService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /** @param array<string, mixed> $context @return array<string, mixed>|null */
    public function learnAndAnswer(
        string $message,
        string $section,
        array $context,
        SectionAssistantModuleService $modules
    ): ?array {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        $message = SectionAssistantQueryHelper::normalizeComposerTypos($message);
        $message = trim((string) preg_replace('/^\[invata\]\s*/iu', '', $message));

        $intentRouter = new SectionAssistantIntentRouterService($this->projectRoot);
        $plan = $intentRouter->resolve($message, $section, $context, $modules);
        if ($plan === null) {
            $plan = $modules->tryAnswerRelaxed($message, $section, $context);
        }
        if ($plan === null) {
            $plan = $this->tryCatalogHintPlan($message, $section);
        }

        if ($plan === null) {
            return null;
        }

        $handlerKey = trim((string) ($plan['source'] ?? ''));
        if (str_contains($handlerKey, '+')) {
            $parts = explode('+', $handlerKey);
            $handlerKey = trim((string) end($parts));
        }
        if ($handlerKey === '' && is_array($plan['intent_decoded'] ?? null)) {
            $handlerKey = trim((string) ($plan['intent_decoded']['handler'] ?? ''));
        }
        if ($handlerKey !== '' && $handlerKey !== 'learn_failed' && $this->isPersistableHandler($handlerKey)) {
            $this->remember($message, $handlerKey);
        }

        $plan['learned'] = $handlerKey !== '' && $handlerKey !== 'learn_failed';
        $plan['learned_at'] = $plan['learned'] ? date('c') : null;
        $plan['learned_handler'] = $handlerKey;
        if ($handlerKey !== '' && $handlerKey !== 'learn_failed') {
            $plan['source'] = 'learned_adapter';
        }

        return $plan;
    }

    private function isPersistableHandler(string $handler): bool
    {
        return in_array($handler, [
            'admin_full_catalog',
            'adaos_comercial',
            'supplier_list',
            'supplier_compare',
            'invoice_list',
            'awb_list',
            'cart_list',
            'vitrina_homepage',
            'import_queue',
            'product_filter',
            'orders_summary',
            'client_orders',
            'category_list',
            'client_list',
            'product_inventory',
            'comunicare_summary',
            'supplier_search',
        ], true);
    }

    public function matchLearned(string $message): ?string
    {
        $norm = $this->normalizePhrase($message);
        if ($norm === '') {
            return null;
        }

        foreach ($this->loadPatterns() as $pattern) {
            if (!is_array($pattern)) {
                continue;
            }
            $handler = trim((string) ($pattern['handler'] ?? ''));
            if ($handler === '') {
                continue;
            }
            $stored = trim((string) ($pattern['phrase_norm'] ?? ''));
            if ($stored !== '' && ($norm === $stored || str_contains($norm, $stored) || str_contains($stored, $norm))) {
                $this->touchPattern($pattern);

                return $handler;
            }
            $keywords = is_array($pattern['keywords'] ?? null) ? $pattern['keywords'] : [];
            if ($keywords !== [] && $this->containsAllKeywords($norm, $keywords)) {
                $this->touchPattern($pattern);

                return $handler;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function buildUnknownLearnablePlan(string $message, string $section): array
    {
        $hint = $this->guessHint($message);

        return [
            'source' => 'unknown_learnable',
            'can_learn' => true,
            'reply_ro' => 'Nu am inca o rutare invatata pentru intrebarea asta.'
                . ($hint !== '' ? ' Pare legat de: ' . $hint . '.' : '')
                . ' Apasa Invata — caut in modulele admin si ma adaptez.',
            'intent' => 'learn',
            'cheat_sheet' => [
                'kind' => 'learn_prompt',
                'title' => 'Necunoscut — pot invata',
                'subtitle' => 'Apasa Invata pentru adaptare automata',
                'updated_at' => date('d.m.Y H:i'),
                'can_learn' => true,
                'hints' => [
                    'Invata = scanare module admin + interogari live (fara LLM).',
                    'Dupa invatare, aceeasi intrebare va merge direct data viitoare.',
                ],
                'shortcuts' => [
                    ['label' => 'Jurnal AI Section', 'url' => '/admin/ai-agent'],
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed>|null */
    private function tryCatalogHintPlan(string $message, string $section): ?array
    {
        $lower = mb_strtolower($message, 'UTF-8');
        $modules = SectionAssistantAdminCatalog::all();
        $matches = [];
        foreach ($modules as $mod) {
            if (!is_array($mod)) {
                continue;
            }
            foreach ($mod['features'] ?? [] as $feature) {
                $line = mb_strtolower((string) $feature, 'UTF-8');
                if ($line === '') {
                    continue;
                }
                $score = 0;
                foreach ($this->tokenize($lower) as $token) {
                    if (strlen($token) >= 4 && str_contains($line, $token)) {
                        ++$score;
                    }
                }
                if ($score >= 1) {
                    $url = '/admin/product';
                    if (preg_match('#(/admin/[a-z0-9\-/?=&]+)#i', (string) $feature, $m)) {
                        $url = $m[1];
                    }
                    $label = (string) $feature;
                    if (preg_match('/^(.+?)\s*[—–-]\s*/u', (string) $feature, $m)) {
                        $label = trim($m[1]);
                    }
                    $matches[] = ['label' => $label, 'url' => $url, 'score' => $score];
                }
            }
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        $top = array_slice($matches, 0, 5);

        return [
            'source' => 'learn_failed',
            'can_learn' => false,
            'reply_ro' => 'Am cautat in modulele admin dar nu am inca o interogare live pentru asta. Incearca o formulare mai specifica sau deschide modulul relevant.',
            'intent' => 'navigate',
            'cheat_sheet' => [
                'kind' => 'learn_failed',
                'title' => 'Invatare partiala',
                'subtitle' => 'Module posibil relevante',
                'updated_at' => date('d.m.Y H:i'),
                'capabilities' => array_map(static fn ($m) => [
                    'label' => (string) ($m['label'] ?? ''),
                    'url' => (string) ($m['url'] ?? '#'),
                ], $top),
                'hints' => [
                    'Exemple care merg acum: cate clienti sunt in sistem, comenzi pentru Radu Galac, ce facturi avem generate, ce AWB avem.',
                ],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    private function remember(string $message, string $handler): void
    {
        $norm = $this->normalizePhrase($message);
        if ($norm === '' || $handler === '') {
            return;
        }

        $patterns = $this->loadPatterns();
        foreach ($patterns as &$pattern) {
            if (!is_array($pattern)) {
                continue;
            }
            if ((string) ($pattern['phrase_norm'] ?? '') === $norm) {
                $pattern['handler'] = $handler;
                $pattern['updated_at'] = date('c');
                $pattern['hits'] = (int) ($pattern['hits'] ?? 0) + 1;
                $this->savePatterns($patterns);

                return;
            }
        }
        unset($pattern);

        $patterns[] = [
            'id' => bin2hex(random_bytes(6)),
            'phrase_norm' => $norm,
            'keywords' => $this->extractKeywords($norm),
            'handler' => $handler,
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'hits' => 1,
        ];

        $this->savePatterns(array_slice($patterns, -200));
    }

    /** @param array<string, mixed> $pattern */
    private function touchPattern(array $pattern): void
    {
        $patterns = $this->loadPatterns();
        $id = (string) ($pattern['id'] ?? '');
        $changed = false;
        foreach ($patterns as &$row) {
            if (!is_array($row)) {
                continue;
            }
            if ($id !== '' && (string) ($row['id'] ?? '') === $id) {
                $row['hits'] = (int) ($row['hits'] ?? 0) + 1;
                $row['last_hit_at'] = date('c');
                $changed = true;
                break;
            }
        }
        unset($row);
        if ($changed) {
            $this->savePatterns($patterns);
        }
    }

    /** @return list<array<string, mixed>> */
    private function loadPatterns(): array
    {
        $file = $this->storageFile();
        if (!is_file($file)) {
            return [];
        }
        $json = json_decode((string) @file_get_contents($file), true);

        return is_array($json['patterns'] ?? null) ? $json['patterns'] : [];
    }

    /** @param list<array<string, mixed>> $patterns */
    private function savePatterns(array $patterns): void
    {
        $dir = dirname($this->storageFile());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            $this->storageFile(),
            json_encode(['patterns' => $patterns, 'updated_at' => date('c')], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    private function storageFile(): string
    {
        return $this->projectRoot . '/admin/storage/section_assistant_learned.json';
    }

    private function normalizePhrase(string $message): string
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        $lower = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower) ?? $lower;
        $lower = preg_replace('/\s+/u', ' ', $lower) ?? $lower;

        return trim($lower);
    }

    /** @return list<string> */
    private function extractKeywords(string $norm): array
    {
        $stop = ['ce', 'care', 'cate', 'sunt', 'este', 'avem', 'acum', 'acuma', 'generate', 'generat', 'in', 'la', 'de', 'cu', 'si', 'sau', 'dar', 'pentru'];
        $out = [];
        foreach (explode(' ', $norm) as $word) {
            $word = trim($word);
            if (strlen($word) < 3 || in_array($word, $stop, true)) {
                continue;
            }
            $out[] = $word;
        }

        return array_values(array_unique(array_slice($out, 0, 8)));
    }

    /** @param list<string> $keywords */
    private function containsAllKeywords(string $norm, array $keywords): bool
    {
        if ($keywords === []) {
            return false;
        }
        $matched = 0;
        foreach ($keywords as $kw) {
            $kw = trim((string) $kw);
            if ($kw !== '' && str_contains($norm, $kw)) {
                ++$matched;
            }
        }

        return $matched >= min(count($keywords), max(2, (int) ceil(count($keywords) * 0.6)));
    }

    /** @return list<string> */
    private function tokenize(string $lower): array
    {
        $parts = preg_split('/\s+/u', $lower) ?: [];

        return array_values(array_filter($parts, static fn ($p) => is_string($p) && strlen($p) >= 3));
    }

    private function guessHint(string $message): string
    {
        $lower = mb_strtolower($message, 'UTF-8');
        if (preg_match('/\b(factur\w*)\b/u', $lower)) {
            return 'facturi';
        }
        if (preg_match('/\b(awb|livr\w*|curier)\b/u', $lower)) {
            return 'AWB / livrare';
        }
        if (preg_match('/\b(furnizor\w*)\b/u', $lower)) {
            return 'furnizori';
        }
        if (preg_match('/\b(produs\w*|catalog)\b/u', $lower)) {
            return 'produse / catalog';
        }
        if (preg_match('/\b(cos|cart)\b/u', $lower)) {
            return 'cos / comenzi';
        }
        if (preg_match('/\b(comenz\w*)\b/u', $lower)) {
            return 'comenzi';
        }
        if (preg_match('/\b(client\w*|clienti)\b/u', $lower)) {
            return 'clienti';
        }

        return '';
    }
}
