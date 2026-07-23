<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Phase4;

use Besoiu\Services\AiSupervisor\Store\AiSupervisorStore;

/**
 * Faza 4c — raport zilnic consolidat pentru operator și agenți.
 */
final class DailyReportBuilder
{
    public function __construct(
        private ?AiSupervisorStore $store = null,
    ) {
        $this->store = $store ?? new AiSupervisorStore();
    }

    /** @param array<string, mixed> $reports */
    public function run(array $reports): array
    {
        $day = date('Y-m-d');
        $previous = $this->store->read('daily_report');
        $prevDay = (string) ($previous['day'] ?? '');

        $sections = [
            'catalog' => $this->sectionCatalog($reports['catalog_audit'] ?? []),
            'pipeline' => $this->sectionPipeline($reports['pipeline_health'] ?? []),
            'tokens' => $this->sectionTokens($reports['token_budget'] ?? []),
            'suppliers' => $this->sectionSuppliers($reports['supplier_watch'] ?? []),
            'diagnostics' => $this->sectionDiagnostics($reports['diagnostics'] ?? []),
            'conversations' => $this->sectionConversations($reports['conversations'] ?? []),
        ];

        $highlights = $this->buildHighlights($sections);
        $markdown = $this->buildMarkdown($day, $sections, $highlights);

        $report = [
            'ok' => true,
            'http' => 200,
            'day' => $day,
            'regenerated' => $prevDay !== $day,
            'generated_at' => date('c'),
            'highlights' => $highlights,
            'sections' => $sections,
            'markdown' => $markdown,
        ];

        $this->store->write('daily_report', $report);

        $ctxDir = dirname($this->store->dir()) . '/ai_context';
        if (!is_dir($ctxDir)) {
            @mkdir($ctxDir, 0775, true);
        }
        @file_put_contents($ctxDir . '/supervisor_daily.md', $markdown);

        return $report;
    }

    /** @return array<string, mixed> */
    public function latest(): array
    {
        return $this->store->read('daily_report');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function sectionCatalog(array $data): array
    {
        return [
            'score' => (int) ($data['score'] ?? 0),
            'label' => (string) ($data['score_label'] ?? '—'),
            'issues' => count(is_array($data['issues'] ?? null) ? $data['issues'] : []),
            'no_image' => (int) (($data['counts']['no_image'] ?? 0)),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function sectionPipeline(array $data): array
    {
        return [
            'passed' => (int) ($data['passed'] ?? 0),
            'failed' => (int) ($data['failed'] ?? 0),
            'ok' => !empty($data['ok']),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function sectionTokens(array $data): array
    {
        return [
            'tokens_today' => (int) ($data['tokens_today'] ?? 0),
            'used_pct' => (float) ($data['used_pct'] ?? 0),
            'status' => (string) ($data['status'] ?? 'ok'),
            'tasks_left' => (int) ($data['estimated_llm_tasks_left'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function sectionSuppliers(array $data): array
    {
        return [
            'files_new' => count(is_array($data['files_new'] ?? null) ? $data['files_new'] : []),
            'needs_attention' => count(is_array($data['needs_attention'] ?? null) ? $data['needs_attention'] : []),
            'last_scan' => (string) ($data['last_scan_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function sectionDiagnostics(array $data): array
    {
        $items = is_array($data['diagnostics'] ?? null) ? $data['diagnostics'] : [];

        return [
            'count' => count($items),
            'llm_used' => !empty($data['llm_used']),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function sectionConversations(array $data): array
    {
        return [
            'issues' => (int) ($data['issues_detected'] ?? 0),
            'positive' => (int) ($data['positive_signals'] ?? 0),
            'sessions' => (int) ($data['sessions_count'] ?? 0),
        ];
    }

    /** @param array<string, array<string, mixed>> $sections @return list<string> */
    private function buildHighlights(array $sections): array
    {
        $h = [];
        $cat = $sections['catalog'];
        $h[] = sprintf('Catalog scor %d/100 (%s) — %d probleme.', $cat['score'], $cat['label'], $cat['issues']);
        $pipe = $sections['pipeline'];
        $h[] = sprintf('Pipeline imagini: %d OK, %d eșuate.', $pipe['passed'], $pipe['failed']);
        $tok = $sections['tokens'];
        $h[] = sprintf('Tokeni AI: %d (%.1f%% limită), ~%d task-uri rămase.', $tok['tokens_today'], $tok['used_pct'], $tok['tasks_left']);
        $sup = $sections['suppliers'];
        if ($sup['files_new'] > 0 || $sup['needs_attention'] > 0) {
            $h[] = sprintf('Furnizori: %d fișiere noi, %d necesită atenție.', $sup['files_new'], $sup['needs_attention']);
        }
        $conv = $sections['conversations'];
        if ($conv['issues'] > 0) {
            $h[] = sprintf('Clienți: %d mesaje cu probleme detectate.', $conv['issues']);
        }

        return $h;
    }

    /** @param array<string, array<string, mixed>> $sections @param list<string> $highlights */
    private function buildMarkdown(string $day, array $sections, array $highlights): string
    {
        $lines = [];
        $lines[] = '# Raport supervizor Besoiu — ' . $day;
        $lines[] = 'Generat: ' . date('Y-m-d H:i:s');
        $lines[] = '';
        $lines[] = '## Rezumat';
        foreach ($highlights as $item) {
            $lines[] = '- ' . $item;
        }
        $lines[] = '';
        $lines[] = '## Detalii';
        foreach ($sections as $key => $sec) {
            $lines[] = '### ' . ucfirst($key);
            foreach ($sec as $k => $v) {
                $lines[] = '- ' . $k . ': ' . (is_scalar($v) ? (string) $v : json_encode($v));
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
