<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

/**
 * Corpus RAG central — JSONL structurat (500+ elemente, keyword retrieval).
 */
final class AiRagCorpusService
{
    private string $root;
    private string $corpusPath;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $dir = $this->root . '/admin/storage/ai_rag';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->corpusPath = $dir . '/corpus.jsonl';
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $entries = $this->readAll();
        $byType = [];
        foreach ($entries as $e) {
            $t = (string) ($e['source_type'] ?? 'other');
            $byType[$t] = ($byType[$t] ?? 0) + 1;
        }

        return [
            'path' => 'admin/storage/ai_rag/corpus.jsonl',
            'total' => count($entries),
            'target' => 500,
            'by_source_type' => $byType,
            'last_at' => $entries !== [] ? (string) ($entries[array_key_last($entries)]['at'] ?? '') : '',
        ];
    }

    /** @param array<string, mixed> $entry @return array<string, mixed>|null */
    public function append(array $entry): ?array
    {
        $text = trim((string) ($entry['text'] ?? ''));
        if ($text === '') {
            return null;
        }

        $normalized = $this->normalize($entry);
        $hash = $this->textHash($text);
        foreach ($this->readAll() as $existing) {
            if (($existing['text_hash'] ?? '') === $hash) {
                return null;
            }
        }

        @file_put_contents(
            $this->corpusPath,
            json_encode($normalized, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );

        return $normalized;
    }

    /** @param list<array<string, mixed>> $entries @return array{added:int,skipped:int} */
    public function appendMany(array $entries): array
    {
        $added = 0;
        $skipped = 0;
        foreach ($entries as $entry) {
            if ($this->append($entry) !== null) {
                ++$added;
            } else {
                ++$skipped;
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    /** @param list<array<string, mixed>> $entries @return array{replaced:int,added:int} */
    public function replaceManyBySource(array $entries): array
    {
        if ($entries === []) {
            return ['replaced' => 0, 'added' => 0];
        }

        $replaceKeys = [];
        $normalizedNew = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $text = trim((string) ($entry['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $normalized = $this->normalize($entry);
            $sourceType = (string) ($normalized['source_type'] ?? '');
            $sourceId = (string) ($normalized['source_id'] ?? '');
            if ($sourceType !== '' && $sourceId !== '') {
                $replaceKeys[$sourceType . "\0" . $sourceId] = true;
            }
            $normalizedNew[] = $normalized;
        }

        if ($normalizedNew === []) {
            return ['replaced' => 0, 'added' => 0];
        }

        $existing = $this->readAll();
        $replaced = 0;
        $kept = [];
        foreach ($existing as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sourceType = (string) ($row['source_type'] ?? '');
            $sourceId = (string) ($row['source_id'] ?? '');
            $key = $sourceType . "\0" . $sourceId;
            if ($sourceType !== '' && $sourceId !== '' && isset($replaceKeys[$key])) {
                ++$replaced;
                continue;
            }
            $kept[] = $row;
        }

        $kept = array_merge($kept, $normalizedNew);
        $lines = array_map(
            static fn (array $row): string => json_encode($row, JSON_UNESCAPED_UNICODE),
            $kept
        );
        @file_put_contents(
            $this->corpusPath,
            $lines !== [] ? implode("\n", $lines) . "\n" : '',
            LOCK_EX
        );

        return ['replaced' => $replaced, 'added' => count($normalizedNew)];
    }

    public function clear(): void
    {
        @file_put_contents($this->corpusPath, '');
    }

    /** @return list<array<string, mixed>> */
    public function list(int $limit = 100, ?string $sourceType = null): array
    {
        $entries = $this->readAll();
        if ($sourceType !== null && $sourceType !== '') {
            $entries = array_values(array_filter(
                $entries,
                static fn (array $e): bool => (string) ($e['source_type'] ?? '') === $sourceType
            ));
        }

        return array_slice(array_reverse($entries), 0, max(1, min(500, $limit)));
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, int $limit = 10): array
    {
        $query = trim(mb_strtolower($query, 'UTF-8'));
        if ($query === '') {
            return $this->list($limit);
        }

        $tokens = preg_split('/\s+/u', $query) ?: [];
        $scored = [];
        foreach ($this->readAll() as $entry) {
            $score = $this->score($entry, $tokens, $query);
            if ($score <= 0) {
                continue;
            }
            $entry['rag_score'] = $score;
            $scored[] = $entry;
        }
        usort($scored, static fn (array $a, array $b): int => ($b['rag_score'] <=> $a['rag_score']));

        return array_slice($scored, 0, max(1, min(50, $limit)));
    }

    /** @return list<string> */
    public function formatForPrompt(array $entries): array
    {
        $lines = [];
        foreach ($entries as $e) {
            $title = trim((string) ($e['title'] ?? ''));
            $text = trim((string) ($e['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $prefix = $title !== '' ? '[' . $title . '] ' : '';
            $lines[] = '- ' . $prefix . mb_substr($text, 0, 420);
        }

        return $lines;
    }

    /** @param array<string, mixed> $entry @return array<string, mixed> */
    private function normalize(array $entry): array
    {
        $text = trim((string) ($entry['text'] ?? ''));

        return [
            'id' => (string) ($entry['id'] ?? bin2hex(random_bytes(8))),
            'at' => (string) ($entry['at'] ?? date('c')),
            'source_type' => (string) ($entry['source_type'] ?? 'manual'),
            'source_id' => (string) ($entry['source_id'] ?? ''),
            'title' => mb_substr(trim((string) ($entry['title'] ?? '')), 0, 200),
            'text' => $text,
            'text_hash' => $this->textHash($text),
            'keywords' => is_array($entry['keywords'] ?? null) ? array_values($entry['keywords']) : $this->keywords($text),
            'tags' => is_array($entry['tags'] ?? null) ? array_values($entry['tags']) : [],
            'url' => (string) ($entry['url'] ?? ''),
            'image_url' => (string) ($entry['image_url'] ?? ''),
            'seo' => is_array($entry['seo'] ?? null) ? $entry['seo'] : [],
            'links' => is_array($entry['links'] ?? null) ? array_slice($entry['links'], 0, 20) : [],
            'html_chars' => (int) ($entry['html_chars'] ?? 0),
            'pinned' => !empty($entry['pinned']),
        ];
    }

    private function textHash(string $text): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }

    /** @return list<string> */
    private function keywords(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['si', 'sau', 'cu', 'din', 'pentru', 'the', 'and', 'auto', 'piese'];
        $out = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < 3 || in_array($w, $stop, true)) {
                continue;
            }
            $out[] = $w;
            if (count($out) >= 24) {
                break;
            }
        }

        return array_values(array_unique($out));
    }

    /** @param list<string> $tokens */
    private function score(array $entry, array $tokens, string $query): int
    {
        $hay = mb_strtolower(
            (string) ($entry['title'] ?? '') . ' '
            . (string) ($entry['text'] ?? '') . ' '
            . implode(' ', (array) ($entry['keywords'] ?? [])),
            'UTF-8'
        );
        $score = 0;
        if (str_contains($hay, $query)) {
            $score += 8;
        }
        foreach ($tokens as $t) {
            if ($t !== '' && str_contains($hay, $t)) {
                $score += 2;
            }
        }
        if (!empty($entry['pinned'])) {
            $score += 5;
        }

        return $score;
    }

    /** @return list<array<string, mixed>> */
    private function readAll(): array
    {
        if (!is_file($this->corpusPath)) {
            return [];
        }
        $out = [];
        foreach (@file($this->corpusPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $json = json_decode($line, true);
            if (is_array($json) && trim((string) ($json['text'] ?? '')) !== '') {
                $out[] = $json;
            }
        }

        return $out;
    }
}
