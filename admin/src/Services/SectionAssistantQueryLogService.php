<?php

declare(strict_types=1);

namespace Besoiu\Services;

/** Jurnal query-uri Composer widget — context + evaluare pentru AI Section / training. */
final class SectionAssistantQueryLogService
{
    private string $projectRoot;

    private SectionAssistantOutcomeEvaluator $evaluator;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->evaluator = new SectionAssistantOutcomeEvaluator();
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $context
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public function recordTurn(string $message, array $input, array $plan, array $context, array $response): array
    {
        $feedback = $this->evaluator->evaluate($message, $plan, $input, $context);
        if (!empty($plan['can_learn'])) {
            $feedback['can_learn'] = true;
        }
        $queryId = bin2hex(random_bytes(8));

        $history = is_array($input['conversation_history'] ?? null) ? $input['conversation_history'] : [];
        $entry = [
            'id' => $queryId,
            'at' => date('c'),
            'session_id' => trim((string) ($input['session_id'] ?? '')),
            'user' => (string) ($_SESSION['user_name'] ?? $_SESSION['user_login'] ?? 'admin'),
            'section' => (string) ($response['section'] ?? $context['section'] ?? ''),
            'path' => (string) ($input['path'] ?? ''),
            'message' => $message,
            'conversation_turns' => count($history),
            'conversation_thread' => array_slice($history, -12),
            'context_summary' => $this->summarizeContext($context),
            'response_source' => (string) ($plan['source'] ?? ''),
            'response_intent' => (string) ($plan['intent'] ?? $response['intent'] ?? ''),
            'reply_excerpt' => mb_substr(trim((string) ($response['reply'] ?? '')), 0, 280, 'UTF-8'),
            'action_type' => is_array($plan['action'] ?? null) ? (string) ($plan['action']['type'] ?? '') : '',
            'pending_action' => is_array($plan['pending_action'] ?? null) ? $plan['pending_action'] : null,
            'feedback' => $feedback,
            'operator_rating' => null,
        ];

        $this->append($entry);
        $this->mirrorToAiWork($entry);

        return array_merge($feedback, ['query_id' => $queryId]);
    }

    /** @param array<string, mixed> $entry */
    private function mirrorToAiWork(array $entry): void
    {
        try {
            $source = (string) ($entry['response_source'] ?? '');
            $fb = is_array($entry['feedback'] ?? null) ? $entry['feedback'] : [];
            $fbStatus = (string) ($fb['status'] ?? '');
            $ollamaUsed = stripos($source, 'ollama') !== false;

            (new \Besoiu\Services\AiRag\AiWorkFeedService($this->projectRoot))->append([
                'id' => (string) ($entry['id'] ?? ''),
                'source_type' => 'section_assist',
                'section' => (string) ($entry['section'] ?? ''),
                'action' => (string) ($entry['response_intent'] ?? 'chat'),
                'summary' => mb_substr((string) ($entry['message'] ?? ''), 0, 120)
                    . ' → ' . mb_substr((string) ($entry['reply_excerpt'] ?? ''), 0, 160),
                'status' => $fbStatus !== '' ? $fbStatus : 'unknown',
                'ollama_actuated' => $ollamaUsed,
                'model' => $ollamaUsed ? 'ollama' : '',
                'success' => $fbStatus === 'ok' ? true : ($fbStatus === 'fail' ? false : null),
                'meta' => [
                    'path' => (string) ($entry['path'] ?? ''),
                    'source' => $source,
                ],
            ]);
        } catch (\Throwable) {
            // non-blocking mirror
        }
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function summarizeContext(array $context): array
    {
        $counts = is_array($context['counts'] ?? null) ? $context['counts'] : [];
        $thread = is_array($context['conversation_thread'] ?? null) ? $context['conversation_thread'] : [];

        return [
            'section' => (string) ($context['section'] ?? ''),
            'counts' => $counts,
            'conversation_messages' => count($thread),
            'has_catalog_snapshot' => !empty($context['catalog_snapshot']),
        ];
    }

    /** @param array<string, mixed> $entry */
    private function append(array $entry): void
    {
        $dir = $this->storageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(
            $dir . '/queries_' . date('Ymd') . '.jsonl',
            json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));
        $files = glob($this->storageDir() . '/queries_*.jsonl') ?: [];
        rsort($files);
        $out = [];

        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $lines = array_reverse($lines);
            foreach ($lines as $line) {
                $json = json_decode($line, true);
                if (!is_array($json)) {
                    continue;
                }
                $out[] = $json;
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function summary(int $days = 3): array
    {
        $items = $this->recent(500);
        $cutoff = strtotime('-' . max(1, $days) . ' days');
        $ok = 0;
        $partial = 0;
        $fail = 0;
        $missing = [];

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $at = strtotime((string) ($row['at'] ?? ''));
            if ($at !== false && $at < $cutoff) {
                continue;
            }
            $fb = is_array($row['feedback'] ?? null) ? $row['feedback'] : [];
            $st = (string) ($fb['status'] ?? '');
            if ($st === 'ok') {
                ++$ok;
            } elseif ($st === 'partial') {
                ++$partial;
            } else {
                ++$fail;
            }
            foreach ((array) ($fb['missing_context'] ?? []) as $m) {
                $missing[(string) $m] = ((int) ($missing[(string) $m] ?? 0)) + 1;
            }
        }

        arsort($missing);

        return [
            'total' => $ok + $partial + $fail,
            'ok' => $ok,
            'partial' => $partial,
            'fail' => $fail,
            'top_missing_context' => array_slice($missing, 0, 8, true),
            'recent' => array_slice($items, 0, 12),
        ];
    }

    /** @return array<string, mixed>|null */
    public function setOperatorRating(string $queryId, string $rating, string $note = ''): ?array
    {
        $queryId = trim($queryId);
        if ($queryId === '') {
            return null;
        }

        $rating = in_array($rating, ['ok', 'bad', 'partial'], true) ? $rating : 'bad';
        $updatedEntry = null;

        foreach (glob($this->storageDir() . '/queries_*.jsonl') ?: [] as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES) ?: [];
            $changed = false;
            foreach ($lines as $i => $line) {
                $json = json_decode($line, true);
                if (!is_array($json) || (string) ($json['id'] ?? '') !== $queryId) {
                    continue;
                }
                $json['operator_rating'] = $rating;
                $json['operator_note'] = trim($note);
                $json['operator_rated_at'] = date('c');
                if (is_array($json['feedback'] ?? null)) {
                    $json['feedback']['operator_overridden'] = true;
                    $json['feedback']['operator_rating'] = $rating;
                    $json['feedback']['operator_rated_at'] = $json['operator_rated_at'];
                    $json['feedback']['badge_ok'] = $rating === 'ok';
                    $json['feedback']['status'] = match ($rating) {
                        'ok' => 'ok',
                        'partial' => 'partial',
                        default => 'fail',
                    };
                    $json['feedback']['outcome_label'] = match ($rating) {
                        'ok' => 'OK — confirmat de operator',
                        'partial' => 'Partial — confirmat de operator',
                        default => 'Respins — raspuns incorect (operator)',
                    };
                    $json['feedback'] = $this->appendOperatorCheck($json['feedback'], $rating, trim($note));
                }
                $lines[$i] = json_encode($json, JSON_UNESCAPED_UNICODE);
                $changed = true;
                $updatedEntry = $json;
                break;
            }
            if ($changed) {
                @file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX);
                break;
            }
        }

        return $updatedEntry;
    }

    /** @param array<string, mixed> $feedback @return array<string, mixed> */
    private function appendOperatorCheck(array $feedback, string $rating, string $note): array
    {
        $checks = is_array($feedback['checks'] ?? null) ? $feedback['checks'] : [];
        $checks = array_values(array_filter($checks, static fn ($row) => is_array($row) && (string) ($row['id'] ?? '') !== 'operator_verdict'));
        $checks[] = [
            'id' => 'operator_verdict',
            'label' => 'Verdict operator',
            'status' => $rating === 'ok' ? 'pass' : ($rating === 'partial' ? 'warn' : 'fail'),
            'detail' => match ($rating) {
                'ok' => 'Confirmat corect de operator',
                'partial' => 'Partial — confirmat de operator',
                default => 'Marcat incorect de operator' . ($note !== '' ? ': ' . $note : ''),
            },
        ];
        $feedback['checks'] = $checks;

        $evaluationLog = is_array($feedback['evaluation_log'] ?? null) ? $feedback['evaluation_log'] : [];
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        foreach ($checks as $check) {
            if (!is_array($check)) {
                continue;
            }
            $st = (string) ($check['status'] ?? '');
            if ($st === 'pass') {
                ++$passed;
            } elseif ($st === 'warn') {
                ++$warnings;
            } else {
                ++$failed;
            }
        }
        $evaluationLog['operator_rated_at'] = date('c');
        $evaluationLog['operator_status'] = $rating;
        $evaluationLog['summary'] = [
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            'total' => count($checks),
        ];
        $feedback['evaluation_log'] = $evaluationLog;

        return $feedback;
    }

    /** @return array<string, mixed>|null */
    public function findById(string $queryId): ?array
    {
        $queryId = trim($queryId);
        if ($queryId === '') {
            return null;
        }

        foreach (glob($this->storageDir() . '/queries_*.jsonl') ?: [] as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES) ?: [];
            foreach (array_reverse($lines) as $line) {
                $json = json_decode($line, true);
                if (!is_array($json) || (string) ($json['id'] ?? '') !== $queryId) {
                    continue;
                }

                return $json;
            }
        }

        return null;
    }

    public function markLearnedFrom(string $queryId, string $learnSource): bool
    {
        $queryId = trim($queryId);
        if ($queryId === '') {
            return false;
        }

        foreach (glob($this->storageDir() . '/queries_*.jsonl') ?: [] as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES) ?: [];
            $changed = false;
            foreach ($lines as $i => $line) {
                $json = json_decode($line, true);
                if (!is_array($json) || (string) ($json['id'] ?? '') !== $queryId) {
                    continue;
                }
                $json['learned_from'] = true;
                $json['learned_source'] = $learnSource;
                $json['learned_at'] = date('c');
                if (is_array($json['feedback'] ?? null)) {
                    $json['feedback']['learn_triggered'] = true;
                }
                $lines[$i] = json_encode($json, JSON_UNESCAPED_UNICODE);
                $changed = true;
                break;
            }
            if ($changed) {
                @file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX);

                return true;
            }
        }

        return false;
    }

    private function storageDir(): string
    {
        return $this->projectRoot . '/admin/storage/section_assistant_queries';
    }
}
