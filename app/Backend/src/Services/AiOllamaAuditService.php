<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use ReflectionClass;
use Throwable;

/** Audit 20 pași — Control Ollama / AI Agent. */
final class AiOllamaAuditService
{
    public function __construct(private ?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot
            ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    /** @return array{generated_at:string,summary:array{ok:int,warn:int,fail:int,total:int},steps:list<array<string,mixed>>} */
    public function run(): array
    {
        $steps = [];
        $ok = $warn = $fail = 0;

        foreach ($this->definitions() as $def) {
            $item = $this->runStep($def['id'], $def['title'], $def['fn']);
            $steps[] = $item;
            match ($item['status']) {
                'fail' => $fail++,
                'warn' => $warn++,
                default => $ok++,
            };
        }

        return [
            'generated_at' => date('c'),
            'summary' => ['ok' => $ok, 'warn' => $warn, 'fail' => $fail, 'total' => count($steps)],
            'steps' => $steps,
        ];
    }

    /** @return list<array{id:string,title:string,fn:callable():array{status:string,message:string,extra?:mixed}}> */
    private function definitions(): array
    {
        $root = $this->projectRoot;

        return [
            ['id' => '01', 'title' => 'BESOIU_ROOT', 'fn' => static fn () => [
                'status' => defined('BESOIU_ROOT') ? 'ok' : 'warn',
                'message' => defined('BESOIU_ROOT') ? 'Definit' : 'Fallback path',
            ]],
            ['id' => '02', 'title' => 'Ollama daemon', 'fn' => function () use ($root) {
                $r = (new OllamaLlmClient($root . '/app'))->readiness();

                return [
                    'status' => !empty($r['ready']) ? 'ok' : 'warn',
                    'message' => !empty($r['ready']) ? 'Conectat' : 'Indisponibil',
                ];
            }],
            ['id' => '03', 'title' => '4 agenți instalați', 'fn' => static function () {
                $reg = new AiAgentRegistryService();
                $missing = array_filter(AiOllamaAgentRunnerService::CORE_SLUGS, static fn ($s) => $reg->getAgent($s) === null);

                return [
                    'status' => $missing === [] ? 'ok' : 'fail',
                    'message' => $missing === [] ? '4/4 OK' : 'Lipsesc: ' . implode(', ', $missing),
                ];
            }],
            ['id' => '04', 'title' => 'Bibliotecă agent-produse', 'fn' => function () use ($root) {
                $sum = (new AiAgentContextLibraryService($root))->summary('agent-produse');
                $total = (int) ($sum['total'] ?? 0);

                return [
                    'status' => $total > 0 ? 'ok' : 'fail',
                    'message' => $total . ' fragmente',
                    'extra' => $sum,
                ];
            }],
            ['id' => '05', 'title' => 'Corpus RAG global', 'fn' => function () use ($root) {
                $n = (int) ((new AiRag\AiRagCorpusService($root))->status()['total'] ?? 0);

                return ['status' => $n > 0 ? 'ok' : 'warn', 'message' => $n . ' intrări'];
            }],
            ['id' => '06', 'title' => 'RAG retrieve ZOLLEX', 'fn' => function () use ($root) {
                $hits = (new AiAgentContextLibraryService($root))->retrieve('agent-produse', 'ZOLLEX Tire Doctor T-522Z', 3);
                $score = $hits !== [] ? (int) ($hits[0]['rag_score'] ?? 0) : 0;

                return ['status' => $score >= 6 ? 'ok' : 'fail', 'message' => 'Scor ' . $score];
            }],
            ['id' => '07', 'title' => 'Chat bibliotecă rapid', 'fn' => function () use ($root) {
                $res = (new AiOllamaAgentRunnerService($root))->chat('agent-produse', 'ZOLLEX T-522Z', [
                    'use_knowledge' => true,
                    'use_live_db' => false,
                ]);

                return [
                    'status' => ($res['mode'] ?? '') === 'library_rag_direct' ? 'ok' : 'fail',
                    'message' => (string) ($res['mode'] ?? 'error'),
                ];
            }],
            ['id' => '08', 'title' => 'Chat MySQL live', 'fn' => function () use ($root) {
                $res = (new AiOllamaAgentRunnerService($root))->chat('agent-produse', 'Câte produse active avem?', [
                    'use_knowledge' => false,
                    'use_live_db' => true,
                ]);

                return [
                    'status' => ($res['mode'] ?? '') === 'db_direct' ? 'ok' : 'warn',
                    'message' => (string) ($res['mode'] ?? 'error'),
                ];
            }],
            ['id' => '09', 'title' => 'Conexiune MySQL', 'fn' => function () {
                $snap = AdminDatabaseResolver::aiLiveSnapshot();

                return [
                    'status' => !empty($snap['ok']) ? 'ok' : 'fail',
                    'message' => !empty($snap['ok'])
                        ? number_format((int) (($snap['products'] ?? [])['active'] ?? 0), 0, ',', '.') . ' produse active'
                        : (string) ($snap['error'] ?? 'Eroare BD'),
                    'extra' => $snap,
                ];
            }],
            ['id' => '10', 'title' => 'Catalog RAG', 'fn' => function () use ($root) {
                $s = (new CatalogRagService($root))->searchByMessage('filtru ulei', 5);

                return ['status' => 'ok', 'message' => 'total=' . (int) ($s['total'] ?? 0)];
            }],
            ['id' => '11', 'title' => 'API ai_agent_endpoint', 'fn' => function () use ($root) {
                $p = $root . '/admin/public/api/ai_agent_endpoint.php';

                return ['status' => is_file($p) ? 'ok' : 'fail', 'message' => is_file($p) ? 'OK' : 'Lipsă'];
            }],
            ['id' => '12', 'title' => 'JS + CSS panou', 'fn' => function () use ($root) {
                $ok = is_file($root . '/admin/public/assets/js/admin-ollama-control.js')
                    && is_file($root . '/admin/public/assets/css/admin-ollama-control.css');

                return ['status' => $ok ? 'ok' : 'fail', 'message' => $ok ? 'OK' : 'Lipsă asset'];
            }],
            ['id' => '13', 'title' => 'Template ai-agent', 'fn' => function () use ($root) {
                $p = $root . '/admin/Templates/admin/pages/ai-agent/ai-agent.php';

                return ['status' => is_file($p) ? 'ok' : 'fail', 'message' => is_file($p) ? 'OK' : 'Lipsă'];
            }],
            ['id' => '14', 'title' => 'Human formatter', 'fn' => static fn () => [
                'status' => class_exists(AiHumanResponseService::class) ? 'ok' : 'fail',
                'message' => 'AiHumanResponseService',
            ]],
            ['id' => '15', 'title' => 'Prompt slim', 'fn' => function () use ($root) {
                $runner = new AiOllamaAgentRunnerService($root);
                $ref = new ReflectionClass($runner);
                $m = $ref->getMethod('buildSystemPrompt');
                $m->setAccessible(true);
                $agent = (new AiAgentRegistryService())->getAgent('agent-produse') ?? [];
                $len = mb_strlen((string) $m->invoke($runner, $agent, 'agent-produse', '## test', false, 'test', ['use_knowledge' => true]));

                return ['status' => $len < 12000 ? 'ok' : 'warn', 'message' => $len . ' chars'];
            }],
            ['id' => '16', 'title' => 'Agenți ready', 'fn' => function () use ($root) {
                $st = (new AiOllamaAgentRunnerService($root))->status();
                $ready = count(array_filter($st['agents'] ?? [], static fn ($a) => !empty($a['ready'])));

                return ['status' => $ready >= 3 ? 'ok' : 'warn', 'message' => $ready . '/4 ready'];
            }],
            ['id' => '17', 'title' => 'CSS integrări valid', 'fn' => function () use ($root) {
                $css = file_get_contents($root . '/admin/public/assets/css/admin-ollama-control.css') ?: '';
                $broken = !str_contains($css, '.ollama-integrations {');

                return ['status' => $broken ? 'fail' : 'ok', 'message' => $broken ? 'Selector lipsă' : 'OK'];
            }],
            ['id' => '18', 'title' => 'Smoke flag', 'fn' => function () use ($root) {
                $runner = new AiOllamaAgentRunnerService($root);
                $ref = new ReflectionClass($runner);
                $m = $ref->getMethod('isSmokeTest');
                $m->setAccessible(true);
                $smoke = (bool) $m->invoke($runner, ['smoke_only' => true]);

                return ['status' => $smoke ? 'ok' : 'fail', 'message' => $smoke ? 'OK' : 'Greșit'];
            }],
            ['id' => '19', 'title' => '20 checkpoint-uri', 'fn' => function () use ($root) {
                require_once $root . '/app/Backend/system/ollama_opportunities.php';

                return [
                    'status' => count(ollama_opportunities_registry()) === 20 ? 'ok' : 'fail',
                    'message' => '20 definite',
                ];
            }],
            ['id' => '20', 'title' => 'Verificare structură', 'fn' => function () use ($root) {
                require_once $root . '/app/Backend/system/ollama_opportunities.php';
                $s = ollama_opportunities_run_all($root, ['live' => false])['summary'] ?? [];

                return [
                    'status' => ((int) ($s['fail'] ?? 99)) === 0 ? 'ok' : 'warn',
                    'message' => ($s['ok'] ?? 0) . ' OK · ' . ($s['fail'] ?? 0) . ' fail',
                ];
            }],
        ];
    }

    /** @param callable():array{status:string,message:string,extra?:mixed} $fn */
    private function runStep(string $id, string $title, callable $fn): array
    {
        $t0 = microtime(true);
        try {
            $out = $fn();
            $status = (string) ($out['status'] ?? 'ok');
            $msg = (string) ($out['message'] ?? '');
        } catch (Throwable $e) {
            $status = 'fail';
            $msg = $e->getMessage();
        }

        return [
            'step' => $id,
            'title' => $title,
            'status' => $status,
            'message' => $msg,
            'ms' => (int) round((microtime(true) - $t0) * 1000),
        ];
    }
}
