<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Comunicare\ShopChatKnowledgeModel;
use Config\Database;
use PDO;
use Throwable;

/**
 * Export FAQ + politici → finetuning/date_antrenare.jsonl (format messages Llama).
 */
final class FinetuningFaqExportService
{
    private const SYSTEM_PROMPT = 'Ești consultant informativ Besoiu Piese Auto (România). Răspunde în română, prețuri RON, fără invenții de stoc. Nu confirmi comenzi comerciale automat.';

    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /**
     * @return array{written: int, total: int, path: string, sources: array<string, int>}
     */
    public function exportToJsonl(?string $targetPath = null, int $limit = 500, bool $mergeExisting = true): array
    {
        $targetPath = $targetPath ?? $this->projectRoot . '/finetuning/date_antrenare.jsonl';
        $examples = $this->collectExamples($limit);

        $existing = [];
        if ($mergeExisting && is_file($targetPath)) {
            foreach (file($targetPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                $hash = $this->exampleHash($line);
                $existing[$hash] = $line;
            }
        }

        $sources = ['knowledge' => 0, 'policies' => 0, 'manual' => 0];
        foreach ($examples as $ex) {
            $source = (string) ($ex['_source'] ?? 'manual');
            unset($ex['_source']);
            $line = json_encode($ex, JSON_UNESCAPED_UNICODE);
            if (!is_string($line)) {
                continue;
            }
            $hash = $this->exampleHash($line);
            if (!isset($existing[$hash])) {
                $existing[$hash] = $line;
                if (isset($sources[$source])) {
                    $sources[$source]++;
                }
            }
        }

        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $lines = array_values($existing);
        file_put_contents($targetPath, implode("\n", $lines) . (count($lines) ? "\n" : ''));

        return [
            'written' => count($sources) ? array_sum($sources) : 0,
            'total' => count($lines),
            'path' => $targetPath,
            'sources' => $sources,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function collectExamples(int $limit = 500): array
    {
        $out = [];
        $out = array_merge($out, $this->fromShopChatKnowledge($limit));
        $out = array_merge($out, $this->fromPolicies());

        return array_slice($out, 0, $limit);
    }

    /** @return list<array<string, mixed>> */
    private function fromShopChatKnowledge(int $limit): array
    {
        if (!ShopChatKnowledgeModel::tableExists()) {
            return [];
        }

        try {
            $svc = ShopChatKnowledgeService::create();
            $rows = $svc->list(['channel' => 'all']);
        } catch (Throwable) {
            return [];
        }

        $examples = [];
        foreach ($rows as $row) {
            if (count($examples) >= $limit) {
                break;
            }
            $response = trim((string) ($row['expected_response'] ?? ''));
            if ($response === '') {
                continue;
            }
            $questions = $this->splitQuestions($row['question_examples'] ?? '', (string) ($row['title'] ?? ''));
            foreach ($questions as $q) {
                if (count($examples) >= $limit) {
                    break;
                }
                $examples[] = $this->messageExample($q, $response, 'knowledge');
            }
        }

        return $examples;
    }

    /** @return list<array<string, mixed>> */
    private function fromPolicies(): array
    {
        $ctx = new ShopChatClientContextService($this->projectRoot);
        $text = $ctx->policiesContext();
        $examples = [];

        $pairs = [
            ['Cum livrați piesele?', 'livrare'],
            ['Care e politica de retur?', 'retur'],
            ['Cum pot plăti?', 'plată'],
            ['Ce garanție oferiți?', 'garanție'],
        ];

        foreach ($pairs as [$question, $needle]) {
            $answer = $this->extractPolicyLine($text, $needle);
            if ($answer !== '') {
                $examples[] = $this->messageExample($question, $answer, 'policies');
            }
        }

        return $examples;
    }

    /** @param string|list<string>|mixed $raw */
    private function splitQuestions(mixed $raw, string $title): array
    {
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $p) {
                $p = trim((string) $p);
                if (mb_strlen($p, 'UTF-8') >= 3) {
                    $out[] = $p;
                }
            }
            if ($out !== []) {
                return $out;
            }
        }

        $text = trim((string) $raw);
        $parts = $text !== '' ? (preg_split('/[\n|;]+/', $text) ?: []) : [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (mb_strlen($p, 'UTF-8') >= 5) {
                $out[] = $p;
            }
        }
        if ($out === [] && trim($title) !== '') {
            $out[] = trim($title);
        }

        return $out;
    }

    private function extractPolicyLine(string $block, string $needle): string
    {
        foreach (explode("\n", $block) as $line) {
            if (mb_stripos($line, $needle, 0, 'UTF-8') !== false) {
                $line = preg_replace('/^-\s*/', '', trim($line)) ?? trim($line);
                $pos = mb_strpos($line, ':', 0, 'UTF-8');
                if ($pos !== false) {
                    return trim(mb_substr($line, $pos + 1, null, 'UTF-8'));
                }

                return $line;
            }
        }

        return '';
    }

    /** @return array<string, mixed> */
    private function messageExample(string $user, string $assistant, string $source): array
    {
        return [
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $user],
                ['role' => 'assistant', 'content' => $assistant],
            ],
            '_source' => $source,
        ];
    }

    private function exampleHash(string $jsonLine): string
    {
        $decoded = json_decode($jsonLine, true);
        if (!is_array($decoded)) {
            return md5($jsonLine);
        }
        $messages = $decoded['messages'] ?? [];
        if (is_array($messages)) {
            foreach ($messages as $m) {
                if (is_array($m) && ($m['role'] ?? '') === 'user') {
                    return md5((string) ($m['content'] ?? ''));
                }
            }
        }
        $user = (string) ($decoded['instruction'] ?? $decoded['input'] ?? $jsonLine);

        return md5($user);
    }
}
