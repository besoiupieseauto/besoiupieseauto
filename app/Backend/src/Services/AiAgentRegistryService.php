<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Throwable;

/**
 * Registry agenți AI — prompt .mdc, context manual, auto-învățare (.mdc generat).
 */
final class AiAgentRegistryService
{
    private string $agentsDir;
    private string $importDir;

    public function __construct(?string $agentsDir = null)
    {
        $root = defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4);
        $this->agentsDir = $agentsDir ?? $this->resolveAgentsDir($root);
        $this->importDir = $root . '/bes/AI.mdc';
    }

    private function resolveAgentsDir(string $root): string
    {
        $candidates = [
            $root . '/app/robot/data/ai_agents',
            $root . '/robot/data/ai_agents',
        ];
        $best = $candidates[0];
        $bestScore = -1;
        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $score = 0;
            foreach (AiOllamaAgentRunnerService::CORE_SLUGS as $slug) {
                if (is_file($dir . '/' . $slug . '/agent.mdc')) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $dir;
            }
        }

        return $best;
    }

    /** @return list<array<string, mixed>> */
    public function listAgents(): array
    {
        $this->ensureDefaults();
        $out = [];
        foreach (glob($this->agentsDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            if (str_starts_with($slug, '_')) {
                continue;
            }
            $agent = $this->getAgent($slug);
            if ($agent !== null) {
                $out[] = $agent;
            }
        }
        usort($out, static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function getAgent(string $slug): ?array
    {
        $slug = $this->sanitizeSlug($slug);
        $dir = $this->agentDir($slug);
        if (!is_dir($dir)) {
            return null;
        }

        $mdcPath = $dir . '/agent.mdc';
        $parsed = is_file($mdcPath)
            ? AiMdcParser::parse((string) file_get_contents($mdcPath))
            : ['meta' => [], 'body' => ''];

        $state = $this->readJson($dir . '/state.json');
        $manual = is_file($dir . '/context.manual.md')
            ? trim((string) file_get_contents($dir . '/context.manual.md'))
            : '';
        $learned = is_file($dir . '/context.learned.mdc')
            ? AiMdcParser::parse((string) file_get_contents($dir . '/context.learned.mdc'))
            : ['meta' => [], 'body' => ''];

        $meta = array_merge([
            'slug' => $slug,
            'name' => $slug,
            'description' => '',
            'category' => '',
            'tags' => [],
            // Creativitate implicită = MAX (1.0) pentru a evita UI/agent desincronizat.
            'temperature' => 1.0,
            'auto_collect' => true,
            'auto_evolve' => true,
        ], $parsed['meta']);

        $agentRow = [
            'slug' => $slug,
            'name' => (string) ($meta['name'] ?? $slug),
            'description' => (string) ($meta['description'] ?? ''),
            'category' => (string) ($meta['category'] ?? ''),
            'tags' => is_array($meta['tags'] ?? null) ? $meta['tags'] : [],
            'temperature' => $this->normalizeAgentTemperature((float) ($meta['temperature'] ?? 1.0), $slug),
            'auto_collect' => !empty($meta['auto_collect']),
            'auto_evolve' => !empty($meta['auto_evolve']),
            'system_agent' => !empty($meta['system_agent']) || $slug === 'ops-composer-repair',
            'prompt' => $parsed['body'],
            'manual_context' => $manual,
            'learned_context' => $learned['body'],
            'learned_meta' => $learned['meta'],
            'state' => $state,
            'paths' => [
                'dir' => $dir,
                'agent_mdc' => $mdcPath,
                'manual' => $dir . '/context.manual.md',
                'learned' => $dir . '/context.learned.mdc',
                'runtime' => $dir . '/runtime.md',
            ],
        ];
        $agentRow['health'] = $this->computeHealth($agentRow, $slug);

        return $agentRow;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createAgent(array $payload): array
    {
        $slug = $this->sanitizeSlug((string) ($payload['slug'] ?? $payload['name'] ?? 'agent'));
        if ($slug === '') {
            throw new \InvalidArgumentException('Slug invalid.');
        }
        $dir = $this->agentDir($slug);
        if (is_dir($dir)) {
            throw new \InvalidArgumentException('Agentul există deja: ' . $slug);
        }
        @mkdir($dir, 0775, true);

        $meta = [
            'slug' => $slug,
            'name' => trim((string) ($payload['name'] ?? $slug)),
            'description' => trim((string) ($payload['description'] ?? '')),
            'category' => trim((string) ($payload['category'] ?? '')),
            'tags' => is_array($payload['tags'] ?? null) ? $payload['tags'] : [],
            'temperature' => $this->normalizeAgentTemperature((float) ($payload['temperature'] ?? 0.85), $slug),
            'auto_collect' => !isset($payload['auto_collect']) || filter_var($payload['auto_collect'], FILTER_VALIDATE_BOOLEAN),
            'auto_evolve' => !isset($payload['auto_evolve']) || filter_var($payload['auto_evolve'], FILTER_VALIDATE_BOOLEAN),
        ];
        $prompt = trim((string) ($payload['prompt'] ?? "# Agent {$meta['name']}\n\nDescrie rolul agentului aici."));
        file_put_contents($dir . '/agent.mdc', AiMdcParser::compose($meta, $prompt));
        file_put_contents($dir . '/context.manual.md', trim((string) ($payload['manual_context'] ?? '')));
        file_put_contents(
            $dir . '/context.learned.mdc',
            AiMdcParser::compose(
                ['description' => 'Auto-generat din acțiuni proiect', 'autoGenerated' => true],
                "# Context învățat automat\n\n_ Se completează la colectare._"
            )
        );
        $this->writeState($slug, ['created_at' => date('c')]);

        return $this->getAgent($slug) ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function listTemplates(): array
    {
        return AiAgentTemplates::all();
    }

    /** @return array<string, mixed> */
    public function listTemplateCatalog(): array
    {
        return AiAgentTemplates::catalog();
    }

    /** @return array<string, mixed> */
    public function createFromTemplate(string $templateId, ?string $customSlug = null): array
    {
        $tpl = AiAgentTemplates::get($templateId);
        if ($tpl === null) {
            throw new \InvalidArgumentException('Template negăsit: ' . $templateId);
        }
        $slug = $this->sanitizeSlug($customSlug ?? (string) ($tpl['slug'] ?? $templateId));
        if ($this->getAgent($slug) !== null) {
            throw new \InvalidArgumentException('Agentul există deja. Alege alt slug sau șterge agentul existent.');
        }

        return $this->createAgent([
            'slug' => $slug,
            'name' => (string) ($tpl['name'] ?? $slug),
            'description' => (string) ($tpl['description'] ?? ''),
            'category' => (string) ($tpl['category'] ?? ''),
            'tags' => is_array($tpl['tags'] ?? null) ? $tpl['tags'] : [],
            'temperature' => (float) ($tpl['temperature'] ?? 1.0),
            'auto_collect' => $tpl['auto_collect'] ?? true,
            'auto_evolve' => $tpl['auto_evolve'] ?? true,
            'prompt' => (string) ($tpl['prompt'] ?? ''),
            'manual_context' => (string) ($tpl['manual_context'] ?? ''),
        ]);
    }

    /** @return array<string, mixed> */
    public function cloneAgent(string $slug, ?string $newSlug = null): array
    {
        $agent = $this->getAgent($slug);
        if ($agent === null) {
            throw new \InvalidArgumentException('Agent negăsit: ' . $slug);
        }
        $target = $this->sanitizeSlug($newSlug ?? $slug . '-copie');
        if ($this->getAgent($target) !== null) {
            $target .= '-' . date('His');
        }

        return $this->createAgent([
            'slug' => $target,
            'name' => ($agent['name'] ?? $slug) . ' (copie)',
            'description' => (string) ($agent['description'] ?? ''),
            'category' => (string) ($agent['category'] ?? ''),
            'tags' => is_array($agent['tags'] ?? null) ? $agent['tags'] : [],
            'temperature' => (float) ($agent['temperature'] ?? 1.0),
            'auto_collect' => $agent['auto_collect'] ?? true,
            'auto_evolve' => $agent['auto_evolve'] ?? true,
            'prompt' => (string) ($agent['prompt'] ?? ''),
            'manual_context' => (string) ($agent['manual_context'] ?? ''),
        ]);
    }

    /** @return array<string, mixed> */
    public function exportAgentMdc(string $slug): array
    {
        $slug = $this->sanitizeSlug($slug);
        $path = $this->agentDir($slug) . '/agent.mdc';
        if (!is_file($path)) {
            throw new \InvalidArgumentException('agent.mdc lipsă pentru: ' . $slug);
        }

        return [
            'slug' => $slug,
            'filename' => $slug . '.mdc',
            'content' => (string) file_get_contents($path),
        ];
    }

    public function loadRuntimeMarkdown(string $slug): string
    {
        $slug = $this->sanitizeSlug($slug);
        $path = $this->agentDir($slug) . '/runtime.md';
        if (is_file($path)) {
            return trim((string) file_get_contents($path));
        }
        $agent = $this->getAgent($slug);
        if ($agent === null) {
            return '';
        }
        $runtime = $this->buildRuntimeContext($agent, (new AiActionEventService())->getCore());

        return (string) ($runtime['markdown'] ?? '');
    }

    public function normalizeSlug(string $slug): string
    {
        return $this->sanitizeSlug($slug);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function updateAgent(string $slug, array $payload): array
    {
        $agent = $this->getAgent($slug);
        if ($agent === null) {
            throw new \InvalidArgumentException('Agent negăsit.');
        }
        $dir = $this->agentDir($slug);

        if (array_key_exists('prompt', $payload) || array_key_exists('name', $payload) || array_key_exists('description', $payload)
            || array_key_exists('category', $payload) || array_key_exists('tags', $payload)
            || array_key_exists('temperature', $payload) || array_key_exists('auto_collect', $payload) || array_key_exists('auto_evolve', $payload)) {
            $meta = [
                'slug' => $slug,
                'name' => trim((string) ($payload['name'] ?? $agent['name'])),
                'description' => trim((string) ($payload['description'] ?? $agent['description'])),
                'category' => trim((string) ($payload['category'] ?? $agent['category'] ?? '')),
                'tags' => array_key_exists('tags', $payload) && is_array($payload['tags'])
                    ? $payload['tags']
                    : (is_array($agent['tags'] ?? null) ? $agent['tags'] : []),
                'temperature' => $this->normalizeAgentTemperature(
                    (float) ($payload['temperature'] ?? $agent['temperature']),
                    $slug
                ),
                'auto_collect' => array_key_exists('auto_collect', $payload)
                    ? filter_var($payload['auto_collect'], FILTER_VALIDATE_BOOLEAN)
                    : $agent['auto_collect'],
                'auto_evolve' => array_key_exists('auto_evolve', $payload)
                    ? filter_var($payload['auto_evolve'], FILTER_VALIDATE_BOOLEAN)
                    : $agent['auto_evolve'],
            ];
            $prompt = trim((string) ($payload['prompt'] ?? $agent['prompt']));
            $written = file_put_contents($dir . '/agent.mdc', AiMdcParser::compose($meta, $prompt));
            if ($written === false) {
                throw new \RuntimeException('Nu am putut salva agent.mdc — verifică permisiunile pe robot/data/ai_agents.');
            }
        }

        if (array_key_exists('manual_context', $payload)) {
            $writtenManual = file_put_contents($dir . '/context.manual.md', trim((string) $payload['manual_context']));
            if ($writtenManual === false) {
                throw new \RuntimeException('Nu am putut salva context.manual.md.');
            }
        }

        $this->writeState($slug, ['updated_at' => date('c')]);

        return $this->getAgent($slug) ?? [];
    }

    public function deleteAgent(string $slug): void
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === 'context-master') {
            throw new \InvalidArgumentException('Agentul implicit nu poate fi șters.');
        }
        $dir = $this->agentDir($slug);
        if (!is_dir($dir)) {
            throw new \InvalidArgumentException('Agent negăsit.');
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /** @return list<array<string, mixed>> */
    public function listImportableMdc(): array
    {
        if (!is_dir($this->importDir)) {
            return [];
        }
        $out = [];
        foreach (glob($this->importDir . '/*.mdc') ?: [] as $file) {
            $parsed = AiMdcParser::parse((string) file_get_contents($file));
            $out[] = [
                'file' => basename($file),
                'path' => $file,
                'name' => (string) ($parsed['meta']['name'] ?? basename($file, '.mdc')),
                'description' => (string) ($parsed['meta']['description'] ?? ''),
                'preview' => mb_substr($parsed['body'], 0, 200),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function importFromBesMdc(string $filename, ?string $slug = null): array
    {
        $filename = basename($filename);
        if (!str_ends_with(strtolower($filename), '.mdc')) {
            throw new \InvalidArgumentException('Doar fișiere .mdc.');
        }
        $path = $this->importDir . '/' . $filename;
        if (!is_file($path)) {
            throw new \InvalidArgumentException('Fișier negăsit în bes/AI.mdc/.');
        }
        $parsed = AiMdcParser::parse((string) file_get_contents($path));
        $slug = $this->sanitizeSlug($slug ?? pathinfo($filename, PATHINFO_FILENAME));

        if ($this->getAgent($slug) !== null) {
            $slug .= '-' . date('His');
        }

        return $this->createAgent([
            'slug' => $slug,
            'name' => (string) ($parsed['meta']['name'] ?? pathinfo($filename, PATHINFO_FILENAME)),
            'description' => (string) ($parsed['meta']['description'] ?? 'Import din bes/AI.mdc/' . $filename),
            'temperature' => (float) ($parsed['meta']['temperature'] ?? 1.0),
            'auto_collect' => $parsed['meta']['auto_collect'] ?? true,
            'auto_evolve' => $parsed['meta']['auto_evolve'] ?? true,
            'prompt' => $parsed['body'],
            'manual_context' => '',
        ]);
    }

    public function getRuntimeBrief(string $slug, int $maxChars = 4000): string
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return '';
        }
        $jsonPath = $this->agentDir($slug) . '/runtime.json';
        if (is_file($jsonPath)) {
            $json = json_decode((string) file_get_contents($jsonPath), true);
            if (is_array($json) && trim((string) ($json['robot_brief'] ?? '')) !== '') {
                return mb_substr(trim((string) $json['robot_brief']), 0, $maxChars, 'UTF-8');
            }
        }
        $md = $this->loadRuntimeMarkdown($slug);

        return mb_substr(trim($md), 0, $maxChars, 'UTF-8');
    }

    /**
     * @return array{agent_slug:string,runtime_brief:string,rag_lines:list<string>,llm_readiness:array<string,mixed>}
     */
    public function bundleForComposer(string $agentSlug, string $userMessage): array
    {
        $slug = $this->sanitizeSlug($agentSlug !== '' ? $agentSlug : 'context-master');
        $library = new AiAgentContextLibraryService();
        $router = new AiAgentRouterService($this);
        $llm = LlmRouterService::create(dirname(__DIR__, 3));

        return [
            'agent_slug' => $slug,
            'runtime_brief' => $router->buildBriefCompositeRuntimeMarkdown($slug, 6500),
            'rag_lines' => $library->formatForRuntime($library->retrieveForUserQuery($slug, $userMessage, 8)),
            'llm_readiness' => $llm->readiness(),
        ];
    }

    /**
     * Rulează agent: colectează, evoluează, sintetizează context runtime.
     *
     * @return array<string, mixed>
     */
    public function runAgent(string $slug, bool $useGlobalCollect = true): array
    {
        $agent = $this->getAgent($slug);
        if ($agent === null) {
            throw new \InvalidArgumentException('Agent negăsit.');
        }

        $contextService = new AiContextAgentService();
        $events = new AiActionEventService();
        $globalBundle = null;
        $core = $events->getCore();

        if ($agent['auto_collect'] && $useGlobalCollect) {
            $globalBundle = $contextService->runFullCycle($slug === 'context-master');
            $core = $events->getCore();
        }

        $recentEvents = $events->readEvents(40);
        if ($agent['auto_evolve']) {
            $this->evolveLearnedContext($slug, $core, $recentEvents);
        }

        $agent = $this->getAgent($slug) ?? $agent;
        $runtime = $this->buildRuntimeContext($agent, $core, $globalBundle, $recentEvents);

        $dir = $this->agentDir($slug);
        file_put_contents($dir . '/runtime.md', $runtime['markdown']);
        file_put_contents($dir . '/runtime.json', json_encode($runtime, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->writeState($slug, [
            'last_run_at' => date('c'),
            'runtime_chars' => strlen($runtime['markdown']),
        ]);

        if ($slug === 'context-master') {
            $ctxDir = dirname($this->agentsDir) . '/ai_context';
            @mkdir($ctxDir, 0775, true);
            file_put_contents($ctxDir . '/latest.md', $runtime['markdown']);
        }

        return $runtime;
    }

    /**
     * Rulează agentul automat dacă contextul e învechit (evenimente noi, interval, runtime lipsă).
     *
     * @return array{ran: bool, reason: string}
     */
    public function maybeAutoRunIfNeeded(string $slug, AiActionEventService $events, int $minIntervalSec = 40): array
    {
        $agent = $this->getAgent($slug);
        if ($agent === null || empty($agent['auto_collect'])) {
            return ['ran' => false, 'reason' => 'skip'];
        }

        $lastRun = (string) (($agent['state'] ?? [])['last_run_at'] ?? '');
        $lastRunTs = $lastRun !== '' ? (int) strtotime($lastRun) : 0;
        $now = time();

        $contextDir = dirname($this->agentsDir) . '/ai_context';
        $syncRequestedAt = is_file($contextDir . '/sync_requested.at')
            ? trim((string) file_get_contents($contextDir . '/sync_requested.at'))
            : '';
        $syncRequestedTs = $syncRequestedAt !== '' ? (int) strtotime($syncRequestedAt) : 0;

        $core = $events->getCore();
        $coreUpdatedTs = isset($core['updated_at']) ? (int) strtotime((string) $core['updated_at']) : 0;

        $runtimePath = (string) ($agent['paths']['runtime'] ?? '');
        $runtimeMissing = $runtimePath === '' || !is_file($runtimePath) || filesize($runtimePath) < 50;

        $hasNewSignals = $syncRequestedTs > $lastRunTs || $coreUpdatedTs > $lastRunTs;
        $intervalElapsed = $lastRunTs === 0 || ($now - $lastRunTs) >= $minIntervalSec;

        if (!$runtimeMissing && !$hasNewSignals && !$intervalElapsed) {
            return ['ran' => false, 'reason' => 'fresh'];
        }

        if (!$intervalElapsed && !$runtimeMissing && !$hasNewSignals) {
            return ['ran' => false, 'reason' => 'interval'];
        }

        $lockPath = $this->agentDir($slug) . '/.sync.lock';
        if (is_file($lockPath) && ($now - (int) filemtime($lockPath)) < 25) {
            return ['ran' => false, 'reason' => 'locked'];
        }
        @file_put_contents($lockPath, (string) $now);

        try {
            $this->runAgent($slug, true);
            if (is_file($contextDir . '/sync_requested.at')) {
                @unlink($contextDir . '/sync_requested.at');
            }

            return ['ran' => true, 'reason' => 'synced'];
        } catch (Throwable) {
            return ['ran' => false, 'reason' => 'error'];
        } finally {
            @unlink($lockPath);
        }
    }

    /** @param array<string, mixed> $agent @param array<string, mixed> $core @param array<string, mixed>|null $global @param list<array<string, mixed>> $recentEvents */
    public function buildRuntimeContext(array $agent, array $core = [], ?array $global = null, array $recentEvents = [], ?string $userQuery = null, bool $includeLibrary = true): array
    {
        $parts = [];
        $parts[] = '# Agent: ' . ($agent['name'] ?? 'Agent');
        $parts[] = '';
        $parts[] = '## Prompt (rezumat)';
        $prompt = trim((string) ($agent['prompt'] ?? ''));
        $parts[] = mb_substr($prompt, 0, 1200) . (mb_strlen($prompt) > 1200 ? '…' : '');

        if (trim((string) ($agent['manual_context'] ?? '')) !== '') {
            $parts[] = '';
            $parts[] = '## Context manual';
            $parts[] = mb_substr(trim((string) $agent['manual_context']), 0, 800);
        }

        $slug = (string) ($agent['slug'] ?? '');
        if ($slug !== '' && $includeLibrary) {
            $library = new AiAgentContextLibraryService(
                defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4)
            );
            $userQuery = trim((string) ($userQuery ?? ''));
            if ($userQuery !== '') {
                $ragChunks = $library->retrieveForUserQuery($slug, $userQuery, 6);
            } elseif ($recentEvents !== []) {
                $ragChunks = $library->retrieveForEvents($slug, $recentEvents, 6);
            } else {
                $ragChunks = $library->listOperatorEntries($slug, 4);
            }
            $ragLines = $library->formatForRuntime($ragChunks);
            if ($ragLines !== []) {
                $parts[] = '';
                $parts[] = '## Bibliotecă RAG (top fragmente)';
                array_push($parts, ...array_slice($ragLines, 0, 6));
            }
        }

        if (trim((string) ($agent['learned_context'] ?? '')) !== '') {
            $parts[] = '';
            $parts[] = '## Context învățat (extras)';
            $learned = trim((string) $agent['learned_context']);
            $parts[] = mb_substr($learned, 0, 1500) . (mb_strlen($learned) > 1500 ? '…' : '');
        }

        if ($core !== []) {
            $parts[] = '';
            $parts[] = '## Core operațional (acțiuni proiect)';
            foreach ($core['robot_directives'] ?? [] as $d) {
                if (is_string($d) && $d !== '') {
                    $parts[] = '- ' . $d;
                }
            }
            foreach ($core['highlights'] ?? [] as $h) {
                if (!is_array($h) || empty($h['text'])) {
                    continue;
                }
                $parts[] = '- ' . (string) $h['text'];
            }
        }

        if (is_array($global) && !empty($global['markdown'])) {
            $parts[] = '';
            $parts[] = '## Semnale globale';
            $parts[] = (string) $global['markdown'];
        }

        $markdown = implode("\n", $parts);

        return [
            'slug' => $agent['slug'] ?? '',
            'generated_at' => date('c'),
            'temperature' => (float) ($agent['temperature'] ?? 1.0),
            'markdown' => $markdown,
            'robot_brief' => mb_substr($markdown, 0, 4000),
        ];
    }

    /** @param array<string, mixed> $core @param list<array<string, mixed>> $recentEvents */
    private function evolveLearnedContext(string $slug, array $core, array $recentEvents): void
    {
        $agent = $this->getAgent($slug);
        if ($agent === null) {
            return;
        }

        $existing = (string) ($agent['learned_context'] ?? '');
        $learningPath = dirname(__DIR__, 3) . '/system/ai_learning.php';
        if (is_file($learningPath)) {
            require_once $learningPath;
            if (function_exists('ai_learning_prune_learned_text')) {
                $existing = ai_learning_prune_learned_text($existing);
            }
        }

        $newBullets = [];
        $stamp = date('Y-m-d H:i');

        foreach ($recentEvents as $event) {
            if (!is_array($event)) {
                continue;
            }
            $library = new AiAgentContextLibraryService();
            if ($library->isNoiseEvent($event)) {
                continue;
            }
            $action = (string) ($event['action'] ?? '');
            $subject = (string) ($event['subject'] ?? '');
            if ($action === 'search' && function_exists('ai_learning_is_resolved')) {
                $meta = is_array($event['meta'] ?? null) ? $event['meta'] : [];
                $qType = (string) ($meta['query_type'] ?? 'name');
                if (ai_learning_is_resolved($qType, $subject)) {
                    continue;
                }
            }
            $line = '- [' . ($event['actor_type'] ?? '?') . '] '
                . $action . ': ' . $subject;
            if (!str_contains($existing, $line) && !in_array($line, $newBullets, true)) {
                $newBullets[] = $line;
            }
        }

        foreach ($core['patterns']['searches_not_found'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $qVal = (string) ($row['query_value'] ?? '');
            $qType = (string) ($row['query_type'] ?? 'name');
            if ($qVal === '') {
                continue;
            }
            if (function_exists('ai_learning_is_resolved') && ai_learning_is_resolved($qType, $qVal)) {
                continue;
            }
            $line = '- Căutare negăsită (' . $qType . '): ' . $qVal;
            if (!str_contains($existing, $line) && !in_array($line, $newBullets, true)) {
                $newBullets[] = $line;
            }
        }

        if ($newBullets === [] && $existing === (string) ($agent['learned_context'] ?? '')) {
            return;
        }

        $body = trim($existing);
        if ($newBullets !== []) {
            $append = "\n\n## Actualizare {$stamp}\n" . implode("\n", array_slice($newBullets, 0, 15));
            $body = trim($body) . $append;
            $library = new AiAgentContextLibraryService();
            foreach (array_slice($newBullets, 0, 15) as $bullet) {
                $library->appendEntry($slug, [
                    'text' => $bullet,
                    'source' => 'event',
                    'tags' => ['auto-evolve', $slug],
                ]);
            }
        }
        if (mb_strlen($body) > 12000) {
            $body = mb_substr($body, -12000);
        }

        $dir = $this->agentDir($slug);
        file_put_contents(
            $dir . '/context.learned.mdc',
            AiMdcParser::compose(
                [
                    'description' => 'Auto-generat din acțiuni proiect',
                    'autoGenerated' => true,
                    'lastEvolveAt' => date('c'),
                ],
                "# Context învățat automat\n\n" . $body
            )
        );
    }

    private function ensureDefaults(): void
    {
        if (!is_dir($this->agentsDir)) {
            @mkdir($this->agentsDir, 0775, true);
        }
        if ($this->getAgent('context-master') !== null) {
            $this->upgradeContextMasterCreativeProfileOnce();
            $this->upgradeContextMasterBrainRulesOnce();
            $this->restoreSpecialistTemperaturesOnce();
        } else {
            $this->createAgent([
                'slug' => 'context-master',
                'name' => 'Context Master',
                'description' => 'Agent implicit — sintetizează tot ce se întâmplă în proiect',
                'temperature' => 1.0,
                'auto_collect' => true,
                'auto_evolve' => true,
                'prompt' => <<<'MD'
# Context Master — Besoiu Piese Auto

Ești agentul central (agent context Besoiu). Rolul tău:
- Observi acțiunile admin și clienților în timp real
- Sintetizezi context factual + briefing creativ pentru roboți (WhatsApp, chat)
- Nu inventezi prețuri, stoc sau disponibilitate — propui alternative reale
- Creativitate MAXIMĂ (temperature 1): ton cald, consultativ auto, orientat spre vânzare
MD,
                'manual_context' => AiCentralBrainRules::manualContextMarkdown(),
            ]);
        }

        $this->ensureCoreOllamaAgents();
    }

    /** Instalează cei 4 agenți Ollama specializați dacă lipsesc. */
    private function ensureCoreOllamaAgents(): void
    {
        $flag = $this->agentsDir . '/.core_ollama_agents_v1.done';
        $templates = ['agent-imagini', 'agent-produse', 'agent-clienti', 'agent-statistici'];

        foreach ($templates as $templateId) {
            $tpl = AiAgentTemplates::get($templateId);
            if ($tpl === null) {
                continue;
            }
            $slug = (string) ($tpl['slug'] ?? $templateId);
            if ($this->getAgent($slug) !== null) {
                continue;
            }
            try {
                $this->createFromTemplate($templateId);
            } catch (Throwable) {
                // agent deja existent sau slug invalid
            }
        }

        if (!is_file($flag)) {
            @file_put_contents($flag, date('c'));
        }
    }

    /** Injectează cele 20 reguli în context.manual.md dacă lipsesc. */
    private function upgradeContextMasterBrainRulesOnce(): void
    {
        $dir = $this->agentDir('context-master');
        $flag = $dir . '/.brain_rules_v1.done';
        if (is_file($flag)) {
            return;
        }

        $manualPath = $dir . '/context.manual.md';
        $existing = is_file($manualPath) ? (string) file_get_contents($manualPath) : '';
        $block = AiCentralBrainRules::manualContextMarkdown();
        if (!str_contains($existing, 'Creier central — 20 reguli Besoiu')) {
            $merged = trim($block . "\n\n" . trim($existing));
            @file_put_contents($manualPath, $merged . "\n");
        }

        @file_put_contents($flag, date('c'));
    }

    /** Migrare unică — nu rescrie temperatura/prompt la fiecare listAgents(). */
    private function upgradeContextMasterCreativeProfileOnce(): void
    {
        $dir = $this->agentDir('context-master');
        $flag = $dir . '/.creative_profile_v1.done';
        if (is_file($flag)) {
            return;
        }

        $this->upgradeContextMasterCreativeProfile();
        @file_put_contents($flag, date('c'));
    }

    private function upgradeContextMasterCreativeProfile(): void
    {
        $slug = 'context-master';
        $dir = $this->agentDir($slug);
        $mdcPath = $dir . '/agent.mdc';
        if (!is_file($mdcPath)) {
            return;
        }

        $parsed = AiMdcParser::parse((string) file_get_contents($mdcPath));
        $meta = is_array($parsed['meta'] ?? null) ? $parsed['meta'] : [];
        $temp = (float) ($meta['temperature'] ?? 0);
        $body = (string) ($parsed['body'] ?? '');
        $needsTemp = $temp < 1.0;
        $needsPrompt = str_contains($body, 'temperature 0')
            || str_contains($body, 'creativitate moderată')
            || str_contains($body, 'Răspuns scurt, română');

        if (!$needsTemp && !$needsPrompt) {
            return;
        }

        if ($needsTemp) {
            $meta['temperature'] = 1.0;
        }
        if ($needsPrompt) {
            $body = <<<'MD'
# Context Master — Besoiu Piese Auto

Ești agentul central (agent context Besoiu). Rolul tău:
- Observi acțiunile admin și clienților în timp real
- Sintetizezi context factual + briefing creativ pentru roboți (WhatsApp, chat)
- Nu inventezi prețuri, stoc sau disponibilitate — propui alternative reale
- Creativitate MAXIMĂ (temperature 1): ton cald, consultativ auto, orientat spre vânzare
MD;
        }

        file_put_contents($mdcPath, AiMdcParser::compose($meta, $body));
    }

    /** Specialistii (catalog, comenzi…) — temperaturi mici; doar Context Master poate fi creativ. */
    private function restoreSpecialistTemperaturesOnce(): void
    {
        $flag = $this->agentsDir . '/.specialist_temp_v1.done';
        if (is_file($flag)) {
            return;
        }

        foreach (AiAgentTemplates::all() as $tpl) {
            $slug = (string) ($tpl['template_id'] ?? '');
            $target = (float) ($tpl['temperature'] ?? 0.35);
            if ($slug === '' || $slug === 'context-master' || $this->getAgent($slug) === null) {
                continue;
            }
            $dir = $this->agentDir($slug);
            $mdcPath = $dir . '/agent.mdc';
            if (!is_file($mdcPath)) {
                continue;
            }
            $parsed = AiMdcParser::parse((string) file_get_contents($mdcPath));
            $meta = is_array($parsed['meta'] ?? null) ? $parsed['meta'] : [];
            $current = (float) ($meta['temperature'] ?? 1.0);
            if ($current >= 0.95 && $target < 0.95) {
                $meta['temperature'] = $target;
                file_put_contents($mdcPath, AiMdcParser::compose($meta, (string) ($parsed['body'] ?? '')));
            }
        }

        @file_put_contents($flag, date('c'));
    }

    private function agentDir(string $slug): string
    {
        $slug = $this->sanitizeSlug($slug);
        $primary = $this->agentsDir . '/' . $slug;
        if (is_dir($primary)) {
            return $primary;
        }
        $root = defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4);
        foreach ([
            $root . '/robot/data/ai_agents/' . $slug,
            $root . '/app/robot/data/ai_agents/' . $slug,
        ] as $alt) {
            if (is_dir($alt)) {
                return $alt;
            }
        }

        return $primary;
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9\-_]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'agent';
    }

    /** @param array<string, mixed> $patch */
    private function writeState(string $slug, array $patch): void
    {
        $path = $this->agentDir($slug) . '/state.json';
        $state = $this->readJson($path);
        file_put_contents($path, json_encode(array_merge($state, $patch), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
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

    /** @param array<string, mixed> $agent */
    private function computeHealth(array $agent, string $slug): array
    {
        if (!empty($agent['system_agent'])) {
            return $this->computeSystemAgentHealth($agent, $slug);
        }

        $score = 0;
        $issues = [];

        if (trim((string) ($agent['prompt'] ?? '')) !== '') {
            $score += 25;
        } else {
            $issues[] = 'Lipsește promptul agent.mdc';
        }

        if (trim((string) ($agent['manual_context'] ?? '')) !== '' || trim((string) ($agent['learned_context'] ?? '')) !== '') {
            $score += 20;
        } else {
            $issues[] = 'Adaugă context manual sau rulează auto-învățarea';
        }

        $lastRun = (string) (($agent['state'] ?? [])['last_run_at'] ?? '');
        if ($lastRun !== '') {
            $days = (time() - (int) strtotime($lastRun)) / 86400;
            if ($days <= 7) {
                $score += 30;
            } elseif ($days <= 30) {
                $score += 15;
            } else {
                $issues[] = 'Nerulat de ' . (int) round($days) . ' zile';
            }
        } else {
            $issues[] = 'Agentul nu a fost rulat — apasă Rulează';
        }

        if (!empty($agent['auto_collect']) && !empty($agent['auto_evolve'])) {
            $score += 15;
        }

        $runtimePath = $this->agentDir($slug) . '/runtime.md';
        if (is_file($runtimePath) && filesize($runtimePath) > 50) {
            $score += 10;
        } else {
            $issues[] = 'Runtime lipsă — roboții nu au context actualizat';
        }

        $score = min(100, $score);
        $label = $score >= 80 ? 'excelent' : ($score >= 50 ? 'ok' : 'slab');

        return ['score' => $score, 'label' => $label, 'issues' => $issues, 'fixes' => $this->healthFixHints($issues), 'kind' => 'specialist'];
    }

    /** @param list<string> $issues @return list<string> */
    private function healthFixHints(array $issues): array
    {
        $fixes = [];
        foreach ($issues as $issue) {
            if (str_contains($issue, 'prompt')) {
                $fixes[] = 'Editor → Prompt (agent.mdc)';
            } elseif (str_contains($issue, 'context')) {
                $fixes[] = 'Editor → Context manual sau ▶ Generează runtime';
            } elseif (str_contains($issue, 'rulat') || str_contains($issue, 'Nerulat')) {
                $fixes[] = '▶ Generează runtime (sau așteaptă Live ~40s)';
            } elseif (str_contains($issue, 'Runtime')) {
                $fixes[] = '▶ Generează runtime';
            }
        }

        return array_values(array_unique($fixes));
    }

    /** @param array<string, mixed> $agent */
    private function computeSystemAgentHealth(array $agent, string $slug): array
    {
        $score = 0;
        $issues = [];
        $fixes = [];

        if (trim((string) ($agent['prompt'] ?? '')) !== '') {
            $score += 40;
        } else {
            $issues[] = 'Lipsește promptul agent.mdc';
            $fixes[] = 'Editor → completează promptul';
        }

        if (trim((string) ($agent['description'] ?? '')) !== '') {
            $score += 10;
        }

        $lastRun = (string) (($agent['state'] ?? [])['last_run_at'] ?? '');
        if ($lastRun !== '') {
            $days = (time() - (int) strtotime($lastRun)) / 86400;
            if ($days <= 7) {
                $score += 40;
            } elseif ($days <= 30) {
                $score += 25;
                $issues[] = 'Ultima rulare acum ' . (int) round($days) . ' zile';
                $fixes[] = 'Supervizor → Rulează Composer Repair';
            } else {
                $issues[] = 'Nerulat de ' . (int) round($days) . ' zile';
                $fixes[] = 'Supervizor → Rulează Composer Repair';
            }
        } else {
            $issues[] = 'Încă nerulat — normal la instalare';
            $fixes[] = 'Supervizor → Rulează Composer Repair (o dată)';
        }

        $runtimePath = $this->agentDir($slug) . '/runtime.md';
        if (is_file($runtimePath) && filesize($runtimePath) > 30) {
            $score += 10;
        }

        $score = min(100, $score);
        $label = $score >= 80 ? 'excelent' : ($score >= 50 ? 'ok' : 'slab');

        return [
            'score' => $score,
            'label' => $label,
            'issues' => $issues,
            'fixes' => $fixes,
            'kind' => 'system',
        ];
    }

    private function normalizeAgentTemperature(float $temperature, string $slug): float
    {
        $temperature = max(0.0, min(1.0, $temperature));
        if ($slug === 'ops-composer-repair') {
            return min($temperature, 0.35);
        }
        if (str_starts_with($slug, 'ops-')) {
            return min($temperature, 0.5);
        }

        return $temperature;
    }
}
