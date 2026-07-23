<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\OllamaLlmClient;
use Config\Database;
use PDO;
use Throwable;

/**
 * Modul 6 — rezumate log/cron (informativ, fără auto-apply).
 */
final class AiLogSummaryService
{
    private string $root;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    /** @return array<string, mixed> */
    public function collectSources(int $logLines = 120): array
    {
        return [
            'generated_at' => date('c'),
            'import_log' => $this->tailImportLog($logLines),
            'cron_progress' => $this->readJson($this->root . '/app/Import/MatchingPro/state/cron_progress.json'),
            'cron_control' => $this->readJson($this->root . '/app/Import/MatchingPro/state/cron_control.json'),
            'staging_journal' => $this->readJson($this->root . '/app/Import/MatchingPro/state/cron_staging_last.json'),
            'ollama_errors' => $this->tailJsonl($this->root . '/admin/storage/ollama/errors.jsonl', 15),
            'llm_routes' => $this->tailJsonl($this->root . '/app/Backend/storage/llm_router/routes.jsonl', 15),
        ];
    }

    /**
     * @param array<string, mixed> $options user_id, preview_only
     * @return array<string, mixed>
     */
    public function generate(array $options = []): array
    {
        $previewOnly = !empty($options['preview_only']);
        $sources = $this->collectSources((int) ($options['log_lines'] ?? 120));

        if ($previewOnly) {
            return [
                'ok' => true,
                'preview' => true,
                'sources' => $sources,
                'message' => 'Agregare fără apel LLM',
            ];
        }

        $ollama = new OllamaLlmClient($this->root . '/app');
        if (!$ollama->isEnabled()) {
            return $this->finalizeWithFallback($sources, $options, 'Ollama dezactivat — sumar local.');
        }

        $readiness = $ollama->readiness();
        if (empty($readiness['ready'])) {
            return $this->finalizeWithFallback($sources, $options, 'Ollama offline — sumar local fără așteptare.');
        }

        $governance = new AiGovernanceService($this->root);
        $prompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserPayload($sources);

        $result = $governance->propose(
            'mod_06_log_summary',
            $prompt,
            $userMessage,
            ['sources_meta' => [
                'import_lines' => count($sources['import_log'] ?? []),
                'ollama_errors' => count($sources['ollama_errors'] ?? []),
            ]],
            [
                'user_id' => isset($options['user_id']) ? (int) $options['user_id'] : null,
                'action' => 'log_summary',
                'timeout' => 45,
            ]
        );

        if (!empty($result['ok']) && is_array($result['structured'])) {
            $this->persistSummary($result['structured'], $sources, (int) ($result['log_id'] ?? 0));

            return array_merge($result, ['sources' => $sources]);
        }

        return $this->finalizeWithFallback(
            $sources,
            $options,
            trim((string) ($result['error'] ?? '')) ?: 'Sumar generat local (Ollama indisponibil sau timeout).',
            (int) ($result['log_id'] ?? 0),
            $result
        );
    }

    /**
     * @param array<string, mixed> $sources
     * @param array<string, mixed> $options
     * @param array<string, mixed>|null $llmResult
     * @return array<string, mixed>
     */
    private function finalizeWithFallback(
        array $sources,
        array $options,
        string $message,
        int $logId = 0,
        ?array $llmResult = null,
    ): array {
        $heuristic = $this->buildHeuristicSummary($sources);
        $this->persistSummary($heuristic, $sources, $logId);

        if ($logId > 0) {
            (new AiInteractionLogService(null, $this->root))->patchEntry($logId, [
                'status' => 'fallback_ok',
                'output_json' => $heuristic,
                'output_raw' => json_encode($heuristic, JSON_UNESCAPED_UNICODE),
            ]);
        }

        $base = is_array($llmResult) ? $llmResult : [];

        return array_merge($base, [
            'ok' => true,
            'fallback' => true,
            'structured' => $heuristic,
            'sources' => $sources,
            'error' => $message,
            'status' => 'fallback_ok',
            'message' => $message,
        ]);
    }

    /** @param array<string, mixed> $sources @return array<string, mixed> */
    private function buildHeuristicSummary(array $sources): array
    {
        $importLines = (array) ($sources['import_log'] ?? []);
        $ollamaErrors = (array) ($sources['ollama_errors'] ?? []);
        $progress = is_array($sources['cron_progress'] ?? null) ? $sources['cron_progress'] : [];
        $staging = is_array($sources['staging_journal'] ?? null) ? $sources['staging_journal'] : [];

        $highlights = [];
        if ($importLines !== []) {
            $highlights[] = 'Ultimele ' . count($importLines) . ' linii din log import (' . date('Y-m-d') . ').';
            $last = trim((string) $importLines[array_key_last($importLines)]);
            if ($last !== '') {
                $highlights[] = 'Ultima linie: ' . mb_substr($last, 0, 120);
            }
        } else {
            $highlights[] = 'Nu există linii în log-ul de import pentru ziua curentă.';
        }
        if ($ollamaErrors !== []) {
            $highlights[] = count($ollamaErrors) . ' erori Ollama recente în errors.jsonl.';
        }
        if ($progress !== []) {
            $highlights[] = 'Progres cron: ' . mb_substr(json_encode($progress, JSON_UNESCAPED_UNICODE), 0, 140);
        }
        if ($staging !== []) {
            $highlights[] = 'Jurnal staging disponibil.';
        }

        $summary = 'Rezumat operațional generat local. ';
        if ($ollamaErrors !== []) {
            $summary .= 'Au fost detectate erori Ollama — verificați serviciul pe 127.0.0.1:11434. ';
        }
        if ($importLines !== []) {
            $summary .= 'Import activ — consultați log-ul pentru detalii cron.';
        } else {
            $summary .= 'Fără activitate import în log-ul de azi.';
        }

        return [
            'summary_ro' => trim($summary),
            'highlights' => $highlights,
            'stats' => [
                'errors' => count($ollamaErrors),
                'warnings' => 0,
                'cron_status' => (string) ($progress['status'] ?? $progress['phase'] ?? 'necunoscut'),
                'import_lines' => count($importLines),
            ],
            'recommendations' => array_values(array_filter([
                $ollamaErrors !== [] ? 'Reporniți Ollama sau reduceți concurența apelurilor LLM.' : null,
                $importLines === [] ? 'Verificați dacă cron import rulează și calea app/Import/MatchingPro/logs/.' : null,
                'Reîncercați sumarul AI când Ollama răspunde sub 30s.',
            ])),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listSummaries(int $limit = 20): array
    {
        try {
            $pdo = Database::getDB();
            $this->ensureSummaryTable($pdo);
            $limit = max(1, min(50, $limit));
            $stmt = $pdo->query(
                'SELECT id, summary_text, stats_json, log_id, created_at FROM ai_log_summaries ORDER BY id DESC LIMIT ' . $limit
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            return array_map(static function (array $row): array {
                if (!empty($row['stats_json']) && is_string($row['stats_json'])) {
                    $row['stats_json'] = json_decode($row['stats_json'], true);
                }

                return $row;
            }, $rows ?: []);
        } catch (Throwable) {
            return [];
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Ești analist operațional Besoiu Piese Auto. Primești log-uri cron import, progres, erori Ollama și rute LLM.

Răspunde DOAR JSON valid (fără markdown), schema:
{
  "summary_ro": "2-4 propoziții în română",
  "highlights": ["bullet 1", "bullet 2"],
  "stats": {"errors": 0, "warnings": 0, "cron_status": "string"},
  "recommendations": ["acțiune recomandată"]
}

Reguli: nu inventa cifre — folosește doar datele din input. Dacă log-ul e gol, spune clar.
PROMPT;
    }

    /** @param array<string, mixed> $sources */
    private function buildUserPayload(array $sources): string
    {
        $chunks = [];
        foreach ((array) ($sources['import_log'] ?? []) as $line) {
            $chunks[] = (string) $line;
        }
        $progress = $sources['cron_progress'] ?? [];
        if (is_array($progress) && $progress !== []) {
            $chunks[] = 'CRON_PROGRESS: ' . json_encode($progress, JSON_UNESCAPED_UNICODE);
        }
        $staging = $sources['staging_journal'] ?? [];
        if (is_array($staging) && $staging !== []) {
            $chunks[] = 'STAGING: ' . json_encode($staging, JSON_UNESCAPED_UNICODE);
        }
        foreach ((array) ($sources['ollama_errors'] ?? []) as $err) {
            if (is_array($err)) {
                $chunks[] = 'OLLAMA_ERR: ' . json_encode($err, JSON_UNESCAPED_UNICODE);
            }
        }

        return mb_substr(implode("\n", $chunks), 0, 12000);
    }

    /** @param array<string, mixed> $structured @param array<string, mixed> $sources */
    private function persistSummary(array $structured, array $sources, int $logId): void
    {
        try {
            $pdo = Database::getDB();
            $this->ensureSummaryTable($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO ai_log_summaries (summary_text, stats_json, sources_hash, log_id) VALUES (:text, :stats, :hash, :log_id)'
            );
            $stmt->execute([
                ':text' => (string) ($structured['summary_ro'] ?? ''),
                ':stats' => json_encode($structured, JSON_UNESCAPED_UNICODE),
                ':hash' => substr(sha1(json_encode($sources)), 0, 40),
                ':log_id' => $logId > 0 ? $logId : null,
            ]);
        } catch (Throwable) {
            // non-blocking
        }
    }

    private function ensureSummaryTable(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS ai_log_summaries (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                summary_text TEXT NOT NULL,
                stats_json LONGTEXT NULL,
                sources_hash VARCHAR(64) NULL,
                log_id BIGINT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ai_summary_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return list<string> */
    private function tailImportLog(int $maxLines): array
    {
        $logDir = $this->root . '/app/Import/MatchingPro/logs';
        $today = $logDir . '/import_' . date('Y-m-d') . '.log';
        if (!is_file($today)) {
            return [];
        }
        $lines = file($today, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }

        return array_slice(array_values(array_filter($lines, static fn (string $l): bool => trim($l) !== '')), -$maxLines);
    }

    /** @return list<array<string, mixed>> */
    private function tailJsonl(string $path, int $limit): array
    {
        if (!is_file($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }
        $lines = array_slice($lines, -$limit);
        $out = [];
        foreach ($lines as $line) {
            $json = json_decode((string) $line, true);
            if (is_array($json)) {
                $out[] = $json;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }
}
