<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Throwable;

/**
 * Rulează misiunile celor 10 site-uri — scrape, procesare Ollama, stocare bibliotecă.
 */
final class AiSiteMissionRunnerService
{
    private string $root;
    private string $progressPath;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $dir = $this->root . '/admin/storage/ai_rag';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->progressPath = $dir . '/site_missions_progress.json';
    }

    /** @return array<string, mixed> */
    public function progress(): array
    {
        if (!is_file($this->progressPath)) {
            return ['status' => 'idle', 'log' => []];
        }
        $json = json_decode((string) file_get_contents($this->progressPath), true);

        return is_array($json) ? $json : ['status' => 'idle'];
    }

    /**
     * @param array<string, mixed> $options slots_only (array of slot ints), dry_run
     * @return array<string, mixed>
     */
    public function run(array $options = []): array
    {
        $configSvc = new AiUserSitesConfigService($this->root);
        $sourceSvc = new AiMarketResearchSourceService($this->root);
        $scraper = new AiRagIntelligentScrapeService($this->root);
        $processSvc = new AiMarketResearchProcessService($this->root);
        $store = new AiMarketResearchStore(null, $this->root);
        $corpus = new AiRagCorpusService($this->root);

        $allSites = $configSvc->activeSites();
        $slotsOnly = is_array($options['slots'] ?? null) ? array_map('intval', $options['slots']) : [];
        if ($slotsOnly !== []) {
            $allSites = array_values(array_filter(
                $allSites,
                static fn (array $s): bool => in_array((int) ($s['slot'] ?? 0), $slotsOnly, true)
            ));
        }

        if ($allSites === []) {
            return ['ok' => false, 'error' => 'Niciun site activ cu URL — bifează Activ și completează URL-ul'];
        }

        $progress = [
            'status' => 'running',
            'started_at' => date('c'),
            'total' => count($allSites),
            'done' => 0,
            'ok_count' => 0,
            'fail_count' => 0,
            'fragments_total' => 0,
            'current' => 'Pornire job…',
            'current_step' => 'queued',
            'log' => [],
            'results' => [],
        ];
        $this->appendLog($progress, 0, null, 'Job pornit — ' . count($allSites) . ' site-uri active în coadă');
        $this->writeProgress($progress);

        foreach ($allSites as $idx => $site) {
            $slot = (int) ($site['slot'] ?? 0);
            $url = trim((string) ($site['url'] ?? ''));
            $name = trim((string) ($site['name'] ?? ('Site ' . $slot)));
            $mission = trim((string) ($site['mission'] ?? ''));
            $category = trim((string) ($site['category'] ?? ''));
            $tip = trim((string) ($site['tip_sursa'] ?? 'catalog_produse'));
            $tipLabel = (string) (AiUserSitesConfigService::TIP_SURSA_OPTIONS[$tip] ?? $tip);

            $progress['current'] = ($idx + 1) . '/' . count($allSites) . ' — ' . $name;
            $progress['current_step'] = 'start';
            $this->appendLog($progress, $slot, null, 'Pornesc site #' . $slot . ' · ' . $name);
            $this->writeProgress($progress);

            if ($idx > 0) {
                $sourceSvc->humanDelay();
            }

            $this->appendLog($progress, $slot, null, 'Verific robots.txt pentru ' . parse_url($url, PHP_URL_HOST));
            $progress['current_step'] = 'robots';
            $this->writeProgress($progress);

            $robots = $sourceSvc->checkRobots($url);
            if (!$robots['allowed']) {
                $this->failSlot($configSvc, $slot, $progress, 'Blocat robots.txt: ' . $robots['reason'], $name, $url);
                continue;
            }

            $topic = trim($category . ($mission !== '' ? ' | Misiune: ' . $mission : ''));

            $this->appendLog($progress, $slot, null, 'Scrape URL — tip: ' . $tipLabel);
            $progress['current_step'] = 'scrape';
            $this->writeProgress($progress);

            try {
                $res = $scraper->scrapeToCorpus($url, [
                    'source_id' => 'site_' . $slot,
                    'topic' => $topic,
                    'follow_links' => !empty($site['follow_links']),
                    'summarize' => true,
                    'max_follow' => (int) ($site['max_follow'] ?? 3),
                    'use_source_pipeline' => false,
                ]);
            } catch (Throwable $e) {
                $this->failSlot($configSvc, $slot, $progress, $e->getMessage(), $name, $url);
                continue;
            }

            if (empty($res['ok'])) {
                $this->failSlot($configSvc, $slot, $progress, (string) ($res['error'] ?? 'Scrape eșuat'), $name, $url);
                continue;
            }

            $fragments = (int) ($res['fragments_added'] ?? 0);
            $this->appendLog($progress, $slot, null, 'Scrape OK — ' . $fragments . ' fragmente extrase');
            $progress['current_step'] = 'process';
            $this->writeProgress($progress);

            $plainForProcess = $this->buildProcessText($res, $topic);

            $processed = null;
            if ($plainForProcess !== '') {
                $this->appendLog($progress, $slot, null, 'Procesare AI / extragere structurată…');
                $this->writeProgress($progress);

                $extract = $processSvc->extract($tip, $plainForProcess, $url);
                if (!empty($extract['ok'])) {
                    $processed = $extract;
                    $store->insert([
                        'tip_sursa' => $tip,
                        'source_id' => 'site_' . $slot,
                        'url_sursa' => $url,
                        'data_scraping' => date('Y-m-d H:i:s'),
                        'continut_brut' => mb_substr($plainForProcess, 0, 50000),
                        'continut_procesat' => $extract['continut_procesat'],
                        'confidence_extractie' => $extract['confidence'],
                        'status_validare' => 'auto',
                        'seo' => $this->extractSeoFromRes($res),
                    ]);
                    $this->appendLog($progress, $slot, null, 'Extragere AI OK — confidence ' . round((float) ($extract['confidence'] ?? 0) * 100) . '%');
                } else {
                    $this->appendLog($progress, $slot, null, 'Extragere AI omisă — ' . (string) ($extract['error'] ?? 'fără date'));
                }
            }

            $corpus->append([
                'source_type' => 'scrape',
                'source_id' => 'site_' . $slot,
                'title' => $name . ($category !== '' ? ' — ' . $category : ''),
                'text' => 'Misiune site #' . $slot . ': ' . ($mission !== '' ? $mission : $topic)
                    . "\nURL: {$url}\nFragmente: {$fragments}"
                    . ($processed ? "\nProcesat: " . json_encode($processed['continut_procesat'], JSON_UNESCAPED_UNICODE) : ''),
                'url' => $url,
                'tags' => ['user_site', 'slot_' . $slot, $tip, $category !== '' ? $category : 'general'],
                'keywords' => $this->keywordsFromMission($mission, $category),
                'seo' => $this->extractSeoFromRes($res),
            ]);

            $configSvc->patchSlot($slot, [
                'last_run_at' => date('c'),
                'last_run_ok' => true,
                'last_fragments' => $fragments,
            ]);

            ++$progress['ok_count'];
            $progress['fragments_total'] += $fragments;
            $progress['results'][] = [
                'slot' => $slot,
                'name' => $name,
                'url' => $url,
                'ok' => true,
                'fragments' => $fragments,
                'mission' => $mission,
            ];
            $progress['log'][] = [
                'at' => date('c'),
                'slot' => $slot,
                'ok' => true,
                'level' => 'ok',
                'message' => "✓ {$name}: +{$fragments} fragmente RAG · salvat în corpus",
            ];
            $progress['done'] = $idx + 1;
            $progress['current_step'] = 'done_slot';
            $this->writeProgress($progress);
        }

        $progress['status'] = 'done';
        $progress['finished_at'] = date('c');
        $progress['current'] = 'Finalizat';
        $progress['current_step'] = 'finished';
        $this->appendLog($progress, 0, true, 'Job finalizat — ' . $progress['ok_count'] . ' OK, ' . $progress['fail_count'] . ' eșecuri, ' . $progress['fragments_total'] . ' fragmente');
        unset($progress['current']);
        $this->writeProgress($progress);

        return [
            'ok' => $progress['ok_count'] > 0,
            'ok_count' => $progress['ok_count'],
            'fail_count' => $progress['fail_count'],
            'fragments_total' => $progress['fragments_total'],
            'progress' => $progress,
            'corpus' => $corpus->status(),
        ];
    }

    /** @param array<string, mixed> $res */
    private function buildProcessText(array $res, string $topic): string
    {
        $parts = [$topic];
        foreach ((array) ($res['entries'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $text = trim((string) ($entry['text'] ?? ''));
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        if (!empty($res['trace']) && is_array($res['trace'])) {
            $parts[] = json_encode($res['trace'], JSON_UNESCAPED_UNICODE);
        }

        return mb_substr(implode("\n\n", $parts), 0, 12000);
    }

    /** @param array<string, mixed> $res @return array<string, string> */
    private function extractSeoFromRes(array $res): array
    {
        $seo = is_array($res['seo'] ?? null) ? $res['seo'] : [];

        return [
            'title' => (string) ($seo['title'] ?? ''),
            'description' => (string) ($seo['description'] ?? ''),
            'h1' => (string) ($seo['h1'] ?? ''),
        ];
    }

    /** @return list<string> */
    private function keywordsFromMission(string $mission, string $category): array
    {
        $text = mb_strtolower($mission . ' ' . $category, 'UTF-8');
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($words as $w) {
            if (mb_strlen($w) >= 3) {
                $out[] = $w;
            }
            if (count($out) >= 12) {
                break;
            }
        }

        return array_values(array_unique($out));
    }

    /** @param array<string, mixed> $progress */
    private function failSlot(
        AiUserSitesConfigService $configSvc,
        int $slot,
        array &$progress,
        string $error,
        string $name,
        string $url,
    ): void {
        ++$progress['fail_count'];
        $progress['results'][] = ['slot' => $slot, 'name' => $name, 'url' => $url, 'ok' => false, 'error' => $error];
        $this->appendLog($progress, $slot, false, '✗ ' . $name . ': ' . $error);
        $configSvc->patchSlot($slot, ['last_run_at' => date('c'), 'last_run_ok' => false, 'last_fragments' => 0]);
        ++$progress['done'];
        $this->writeProgress($progress);
    }

    /** @param array<string, mixed> $progress */
    private function appendLog(array &$progress, int $slot, ?bool $ok, string $message): void
    {
        $level = 'info';
        if ($ok === true) {
            $level = 'ok';
        } elseif ($ok === false) {
            $level = 'fail';
        }
        $progress['log'][] = [
            'at' => date('c'),
            'slot' => $slot,
            'ok' => $ok,
            'level' => $level,
            'message' => $message,
        ];
    }

    /** @param array<string, mixed> $progress */
    private function writeProgress(array $progress): void
    {
        @file_put_contents($this->progressPath, json_encode($progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
