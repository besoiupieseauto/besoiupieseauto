<?php

declare(strict_types=1);

namespace Besoiu\Services;

/** Bibliotecă context + RAG-lite (fragmente indexate) per agent — robot/data/ai_agents/{slug}/context.library.jsonl */
final class AiAgentContextLibraryService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot
            ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    private function resolveAgentDir(string $slug): string
    {
        $slug = $this->normalizeSlug($slug);
        $candidates = [
            $this->projectRoot . '/robot/data/ai_agents/' . $slug,
            $this->projectRoot . '/app/robot/data/ai_agents/' . $slug,
        ];
        foreach ($candidates as $dir) {
            if (is_file($dir . '/context.library.jsonl')) {
                return $dir;
            }
        }
        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return $candidates[0];
    }

    /** @return array{total:int,manual:int,learned:int,event:int,operator:int,rag_file:string} */
    public function summary(string $slug): array
    {
        $entries = $this->listEntries($slug, 500);
        $counts = ['manual' => 0, 'learned' => 0, 'event' => 0, 'operator' => 0, 'composer' => 0, 'rag' => 0];
        foreach ($entries as $entry) {
            $src = (string) ($entry['source'] ?? 'learned');
            if (!isset($counts[$src])) {
                $counts[$src] = 0;
            }
            ++$counts[$src];
        }

        return [
            'total' => count($entries),
            'manual' => $counts['manual'] + $counts['operator'],
            'learned' => $counts['learned'] + $counts['event'],
            'event' => $counts['event'],
            'operator' => $counts['operator'] + $counts['manual'],
            'composer' => $counts['composer'],
            'rag_file' => $this->libraryRelPath($slug),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listEntries(string $slug, int $limit = 80): array
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            return [];
        }

        $this->migrateFromLearnedIfEmpty($slug);
        $limit = max(1, min(300, $limit));
        $path = $this->libraryPath($slug);
        if (!is_file($path)) {
            return [];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $out = [];
        foreach (array_reverse($lines) as $line) {
            $json = json_decode($line, true);
            if (!is_array($json) || trim((string) ($json['text'] ?? '')) === '') {
                continue;
            }
            $out[] = $this->normalizeEntry($json);
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $entry @return array<string, mixed>|null */
    public function appendEntry(string $slug, array $entry): ?array
    {
        $slug = $this->normalizeSlug($slug);
        $text = trim((string) ($entry['text'] ?? ''));
        if ($slug === '' || $text === '') {
            return null;
        }

        $parser = new AiLibraryPageParserService();
        if ($parser->isScrapeNoise($text)) {
            return null;
        }
        $structured = $parser->parseStructuredText($text);
        if ($structured === null && preg_match('/^produs:/iu', $text)) {
            return null;
        }

        $normalized = $this->normalizeEntry(array_merge($entry, [
            'id' => bin2hex(random_bytes(8)),
            'at' => date('c'),
            'text' => $text,
            'source' => (string) ($entry['source'] ?? 'operator'),
            'tags' => is_array($entry['tags'] ?? null) ? array_values($entry['tags']) : [],
            'keywords' => $this->extractKeywords($text),
        ]));

        $dir = $this->agentDir($slug);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(
            $this->libraryPath($slug),
            json_encode($normalized, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );

        return $normalized;
    }

    /** @return array<string, mixed>|null */
    public function promoteEntry(string $slug, string $id, bool $pinned = true): ?array
    {
        $slug = $this->normalizeSlug($slug);
        $id = trim($id);
        if ($slug === '' || $id === '') {
            return null;
        }

        $entries = $this->readAllEntriesRaw($slug);
        $updated = null;
        foreach ($entries as &$entry) {
            if (!is_array($entry) || (string) ($entry['id'] ?? '') !== $id) {
                continue;
            }
            $entry['source'] = 'operator';
            $entry['pinned'] = $pinned;
            $entry['promoted_at'] = date('c');
            if (!is_array($entry['tags'] ?? null)) {
                $entry['tags'] = [];
            }
            if (!in_array('operator', $entry['tags'], true)) {
                $entry['tags'][] = 'operator';
            }
            $updated = $this->normalizeEntry($entry);
            break;
        }
        unset($entry);

        if ($updated === null) {
            return null;
        }

        $this->saveAllEntries($slug, $entries);

        return $updated;
    }

    public function deleteEntry(string $slug, string $id): bool
    {
        $slug = $this->normalizeSlug($slug);
        $id = trim($id);
        if ($slug === '' || $id === '') {
            return false;
        }

        $entries = $this->readAllEntriesRaw($slug);
        $before = count($entries);
        $entries = array_values(array_filter(
            $entries,
            static fn ($entry) => is_array($entry) && (string) ($entry['id'] ?? '') !== $id
        ));
        if (count($entries) === $before) {
            return false;
        }

        $this->saveAllEntries($slug, $entries);

        return true;
    }

    /** @return list<array<string, mixed>> */
    private function readAllEntriesRaw(string $slug): array
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            return [];
        }

        $path = $this->libraryPath($slug);
        if (!is_file($path)) {
            return [];
        }

        $out = [];
        foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $json = json_decode($line, true);
            if (!is_array($json) || trim((string) ($json['text'] ?? '')) === '') {
                continue;
            }
            $out[] = $json;
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $entries */
    private function saveAllEntries(string $slug, array $entries): void
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '') {
            return;
        }

        $dir = $this->agentDir($slug);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $lines = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalized = $this->normalizeEntry($entry);
            $lines[] = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        }

        @file_put_contents(
            $this->libraryPath($slug),
            $lines === [] ? '' : implode("\n", $lines) . "\n",
            LOCK_EX
        );
    }

    /**
     * RAG-lite: potrivire keyword pe fragmente (fără embedding extern).
     *
     * @return list<array<string, mixed>>
     */
    public function retrieve(string $slug, string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $keywords = $this->extractKeywords($query);
        $entries = $this->listEntries($slug, 200);
        $scored = [];
        foreach ($entries as $entry) {
            if ($this->isNoiseFragment((string) ($entry['text'] ?? ''))) {
                continue;
            }
            $score = $this->scoreEntry($entry, $keywords, $query);
            if ($score > 0) {
                $entry['rag_score'] = $score;
                $scored[] = $entry;
            }
        }

        usort($scored, static fn ($a, $b) => ((int) ($b['rag_score'] ?? 0)) <=> ((int) ($a['rag_score'] ?? 0)));

        return array_slice($scored, 0, max(1, min(20, $limit)));
    }

    /**
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     */
    public function retrieveForEvents(string $slug, array $events, int $limit = 10): array
    {
        $parts = [];
        foreach (array_slice(array_reverse($events), 0, 12) as $event) {
            if (!is_array($event) || $this->isNoiseEvent($event)) {
                continue;
            }
            $parts[] = (string) ($event['action'] ?? '');
            $parts[] = (string) ($event['subject'] ?? '');
        }

        if ($parts === []) {
            return $this->listOperatorEntries($slug, $limit);
        }

        return $this->retrieve($slug, implode(' ', $parts), $limit);
    }

    /** Alias clar pentru Composer / LLM — RAG pe întrebarea utilizatorului. */
    public function retrieveForUserQuery(string $slug, string $query, int $limit = 8): array
    {
        $chunks = $this->retrieve($slug, $query, $limit);
        if ($chunks !== []) {
            return $chunks;
        }

        return $this->listOperatorEntries($slug, min($limit, 6));
    }

    /** @return list<array<string, mixed>> */
    public function listOperatorEntries(string $slug, int $limit = 6): array
    {
        $out = [];
        foreach ($this->listEntries($slug, 120) as $entry) {
            if ($this->isNoiseFragment((string) ($entry['text'] ?? ''))) {
                continue;
            }
            $src = (string) ($entry['source'] ?? '');
            if (in_array($src, ['operator', 'manual'], true) || !empty($entry['pinned'])) {
                $out[] = $entry;
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $event */
    public function isNoiseEvent(array $event): bool
    {
        $action = mb_strtolower(trim((string) ($event['action'] ?? '')), 'UTF-8');
        $subject = mb_strtolower(trim((string) ($event['subject'] ?? '')), 'UTF-8');
        $noiseActions = ['time_on_page', 'scroll', 'page_view', 'mousemove', 'heartbeat', 'ping', 'visibility'];
        foreach ($noiseActions as $na) {
            if ($action === $na || str_contains($action, $na)) {
                return true;
            }
        }

        return str_contains($subject, 'time_on_page')
            || str_contains($subject, 'scroll')
            || preg_match('/\bpe\s+\/(catalog|product|home)\b/u', $subject) === 1;
    }

    public function isNoiseFragment(string $text): bool
    {
        $lower = mb_strtolower(trim($text), 'UTF-8');
        if ($lower === '' || mb_strlen($lower) < 8) {
            return true;
        }
        if (preg_match('/\b(time_on_page|scroll|mousemove|heartbeat|visibility)\b/u', $lower)) {
            return true;
        }
        if (preg_match('/\bpe\s+\/(catalog|product|home|admin)\b/u', $lower) && !preg_match('/\b(factur|comenz|awb|furnizor|produs|oem)\b/u', $lower)) {
            return true;
        }
        $parser = new AiLibraryPageParserService();

        return $parser->isScrapeNoise($text);
    }

    /**
     * Bibliotecă grupată pe secțiuni pentru UI admin.
     *
     * @return array{
     *   summary: array<string, mixed>,
     *   sections: list<array{id:string,label:string,icon:string,count:int,entries:list<array<string,mixed>>}>,
     *   total_visible: int,
     *   total_noise: int
     * }
     */
    public function browseGrouped(string $slug, int $limitPerSection = 30): array
    {
        $parser = new AiLibraryPageParserService();
        $entries = $this->listEntries($slug, 500);
        $sections = [];
        $noise = 0;

        foreach ($entries as $entry) {
            $enriched = $parser->enrichEntry($this->normalizeEntry($entry));
            if (!empty($enriched['is_noise'])) {
                ++$noise;
                continue;
            }
            $sec = (string) ($enriched['section'] ?? 'other');
            if (!isset($sections[$sec])) {
                $sections[$sec] = [
                    'id' => $sec,
                    'label' => (string) ($enriched['section_label'] ?? $sec),
                    'icon' => (string) ($enriched['section_icon'] ?? '📄'),
                    'count' => 0,
                    'entries' => [],
                    'sort' => $parser->classifySection($entry)['sort'] ?? 99,
                ];
            }
            ++$sections[$sec]['count'];
            if (count($sections[$sec]['entries']) < max(1, min(50, $limitPerSection))) {
                $sections[$sec]['entries'][] = $enriched;
            }
        }

        $list = array_values($sections);
        usort($list, static fn ($a, $b) => ($a['sort'] ?? 99) <=> ($b['sort'] ?? 99));

        $visible = 0;
        foreach ($list as $sec) {
            $visible += (int) ($sec['count'] ?? 0);
        }

        return [
            'summary' => $this->summary($slug),
            'sections' => $list,
            'total_visible' => $visible,
            'total_noise' => $noise,
        ];
    }

    /** @return list<string> */
    public function formatForRuntime(array $chunks): array
    {
        $lines = [];
        foreach ($chunks as $chunk) {
            if (!is_array($chunk)) {
                continue;
            }
            $text = trim((string) ($chunk['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $src = (string) ($chunk['source'] ?? 'learned');
            $at = isset($chunk['at']) ? date('d.m.Y', (int) strtotime((string) $chunk['at'])) : '';
            $lines[] = '- [' . $src . ($at !== '' ? ' · ' . $at : '') . '] ' . $text;
        }

        return $lines;
    }

    public function migrateFromLearnedIfEmpty(string $slug): int
    {
        $slug = $this->normalizeSlug($slug);
        if ($slug === '' || is_file($this->libraryPath($slug))) {
            return 0;
        }

        $learnedPath = $this->agentDir($slug) . '/context.learned.mdc';
        if (!is_file($learnedPath)) {
            return 0;
        }

        $parsed = AiMdcParser::parse((string) file_get_contents($learnedPath));
        $body = trim((string) ($parsed['body'] ?? ''));
        if ($body === '') {
            return 0;
        }

        $count = 0;
        $sections = preg_split('/\n(?=##\s)/u', $body) ?: [];
        foreach ($sections as $section) {
            $section = trim($section);
            if ($section === '') {
                continue;
            }
            if (str_starts_with($section, '# Context învățat')) {
                continue;
            }
            $title = 'Fragment';
            $content = $section;
            if (preg_match('/^##\s+(.+?)\s*\n/u', $section, $m)) {
                $title = trim($m[1]);
                $content = trim((string) preg_replace('/^##\s+.+?\n/u', '', $section));
            }
            foreach (preg_split('/\n\s*-\s+/u', $content) as $bullet) {
                $bullet = trim(ltrim($bullet, '- '));
                if ($bullet === '' || mb_strlen($bullet) < 8 || $this->isNoiseFragment($bullet)) {
                    continue;
                }
                if ($this->appendEntry($slug, [
                    'text' => $bullet,
                    'source' => 'learned',
                    'tags' => [$title],
                    'migrated' => true,
                ]) !== null) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /** @param array<string, mixed> $entry */
    private function scoreEntry(array $entry, array $queryKeywords, string $queryRaw): int
    {
        $text = mb_strtolower((string) ($entry['text'] ?? ''), 'UTF-8');
        $score = 0;
        foreach ($queryKeywords as $kw) {
            if ($kw !== '' && str_contains($text, $kw)) {
                $score += 3;
            }
        }
        foreach ((array) ($entry['keywords'] ?? []) as $kw) {
            $kw = mb_strtolower((string) $kw, 'UTF-8');
            foreach ($queryKeywords as $q) {
                if ($q !== '' && ($q === $kw || str_contains($q, $kw) || str_contains($kw, $q))) {
                    $score += 2;
                }
            }
        }
        if (str_contains($text, mb_strtolower($queryRaw, 'UTF-8'))) {
            $score += 5;
        }
        if (!empty($entry['pinned'])) {
            $score += 4;
        }

        return $score;
    }

    /** @return list<string> */
    private function extractKeywords(string $text): array
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $lower = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower) ?? $lower;
        $stop = ['pentru', 'care', 'este', 'sunt', 'acest', 'aceasta', 'agent', 'context', 'din', 'cu', 'si', 'the', 'and'];
        $out = [];
        foreach (preg_split('/\s+/u', $lower) ?: [] as $word) {
            $word = trim($word);
            if (strlen($word) < 3 || in_array($word, $stop, true)) {
                continue;
            }
            $out[] = $word;
        }

        return array_values(array_unique(array_slice($out, 0, 24)));
    }

    /** @param array<string, mixed> $entry @return array<string, mixed> */
    private function normalizeEntry(array $entry): array
    {
        return [
            'id' => (string) ($entry['id'] ?? bin2hex(random_bytes(6))),
            'at' => (string) ($entry['at'] ?? date('c')),
            'source' => (string) ($entry['source'] ?? 'learned'),
            'text' => trim((string) ($entry['text'] ?? '')),
            'tags' => is_array($entry['tags'] ?? null) ? array_values($entry['tags']) : [],
            'keywords' => is_array($entry['keywords'] ?? null) ? array_values($entry['keywords']) : $this->extractKeywords((string) ($entry['text'] ?? '')),
            'pinned' => !empty($entry['pinned']),
            'rag_score' => isset($entry['rag_score']) ? (int) $entry['rag_score'] : null,
        ];
    }

    private function libraryPath(string $slug): string
    {
        return $this->agentDir($slug) . '/context.library.jsonl';
    }

    private function libraryRelPath(string $slug): string
    {
        return 'robot/data/ai_agents/' . $slug . '/context.library.jsonl';
    }

    private function agentDir(string $slug): string
    {
        return $this->resolveAgentDir($slug);
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9\-_]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
