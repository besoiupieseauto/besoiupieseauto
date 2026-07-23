<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Services\AiRag\AiRagCorpusService;
use PDO;
use Throwable;

/**
 * Rulează cei 4 agenți Ollama specializați — chat + context live MySQL.
 */
final class AiOllamaAgentRunnerService
{
    /** @var list<string> */
    public const CORE_SLUGS = ['agent-imagini', 'agent-produse', 'agent-clienti', 'agent-statistici'];

    private string $projectRoot;
    private AiAgentRegistryService $registry;
    private OllamaLlmClient $ollama;
    private LlmRouterService $router;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot
            ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->registry = new AiAgentRegistryService();
        $this->ollama = new OllamaLlmClient($this->projectRoot . '/app');
        $this->router = LlmRouterService::create($this->projectRoot . '/app');
    }

    private function pdo(): PDO
    {
        return AdminDatabaseResolver::pdo();
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $text = $this->ollama->readiness();
        $vision = $this->ollama->visionReadiness();
        $agents = [];

        foreach (self::CORE_SLUGS as $slug) {
            $agent = $this->registry->getAgent($slug);
            $model = $this->ollama->modelForAgent($slug);
            $needsVision = $slug === 'agent-imagini';
            $modelReady = $this->ollama->isEnabled()
                && $this->ollama->isModelInstalled($model);
            $ready = $needsVision ? ($vision['ready'] && $modelReady) : $modelReady;

            $agents[] = [
                'slug' => $slug,
                'name' => (string) ($agent['name'] ?? $slug),
                'installed' => $agent !== null,
                'model' => $model,
                'ready' => $agent !== null && $ready,
                'chat_capable' => $agent !== null,
                'vision' => $needsVision,
                'task' => $this->taskContextForSlug($slug),
                'last_run_at' => is_array($agent['state'] ?? null) ? ($agent['state']['last_run_at'] ?? null) : null,
            ];
        }

        $db = ['ok' => false, 'error' => 'Nepornit'];
        try {
            $db = AdminDatabaseResolver::aiLiveSnapshot($this->pdo());
        } catch (Throwable $e) {
            $db = ['ok' => false, 'error' => $e->getMessage()];
        }

        return [
            'ollama' => $text,
            'vision' => $vision,
            'agents' => $agents,
            'db' => $db,
            'router_mode' => $this->router->readiness()['router_mode'] ?? 'ollama_first',
        ];
    }

    /**
     * @param array<string, mixed> $options randomn_id, image_base64, preset
     * @return array<string, mixed>
     */
    public function chat(string $slug, string $message, array $options = []): array
    {
        $slug = $this->registry->normalizeSlug($slug);
        if (!in_array($slug, self::CORE_SLUGS, true)) {
            return ['ok' => false, 'error' => 'Agent necunoscut sau nesuportat pentru Ollama.'];
        }

        $agent = $this->registry->getAgent($slug);
        if ($agent === null) {
            return ['ok' => false, 'error' => 'Agent neinstalat — rulează bootstrap sau adaugă din Șabloane.'];
        }

        $message = trim($message);
        $preset = (string) ($options['preset'] ?? '');
        $useKnowledge = !empty($options['use_knowledge']) || !empty($options['use_live_db']);
        $fastTest = $this->isSmokeTest($options);
        if ($message === '' && $preset === 'daily_stats') {
            $message = 'Generează raport KPI zilnic pentru operator Besoiu: bullet points, cifre din context, recomandări concrete.';
        }
        if ($message === '') {
            return ['ok' => false, 'error' => 'Mesaj gol.'];
        }

        if ($slug === 'agent-imagini') {
            return $this->chatImagini($agent, $message, $options);
        }

        return $this->chatTextAgent($slug, $agent, $message, $options);
    }

    /** @return array<string, mixed> */
    public function runDailyStatsReport(): array
    {
        $result = $this->chat('agent-statistici', '', ['preset' => 'daily_stats']);
        if (empty($result['ok'])) {
            return $result;
        }

        $dir = $this->projectRoot . '/robot/data/ai_agents/agent-statistici';
        if (is_dir($dir)) {
            $report = (string) ($result['content'] ?? '');
            @file_put_contents($dir . '/daily_report.md', "# Raport KPI zilnic\n\n- **Generat:** " . date('c') . "\n\n" . $report);
            @file_put_contents($dir . '/daily_report.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return $result;
    }

    private function taskContextForSlug(string $slug): string
    {
        return match ($slug) {
            'agent-imagini' => 'agent_imagini',
            'agent-produse' => 'agent_produse',
            'agent-clienti' => 'agent_clienti',
            'agent-statistici' => 'agent_statistici',
            default => 'ai_agent',
        };
    }

    /** @param array<string, mixed> $agent @param array<string, mixed> $options */
    private function chatImagini(array $agent, string $message, array $options): array
    {
        $randomnId = trim((string) ($options['randomn_id'] ?? ''));
        if ($randomnId !== '') {
            try {
                $pdo = $this->pdo();
                $audit = new ProductImageAuditService($this->projectRoot);
                $products = $audit->loadProductsByPublicIds($pdo, [$randomnId]);
                if ($products === []) {
                    return ['ok' => false, 'error' => 'Produs negăsit: ' . $randomnId];
                }
                $product = $products[0];
                $wantsAudit = preg_match('/\b(audit|verific|potriv)/iu', $message) === 1
                    || trim((string) ($options['mode'] ?? '')) === 'audit';

                $analysis = $audit->analyzeProductWithOllamaVision($product, $this->ollama);
                if (($analysis['verdict'] ?? '') === 'error') {
                    return ['ok' => false, 'error' => (string) ($analysis['summary_ro'] ?? 'Vision eșuat')];
                }

                if ($wantsAudit) {
                    $summary = (string) ($analysis['summary_ro'] ?? '');

                    return [
                        'ok' => true,
                        'content' => $summary !== '' ? $summary : json_encode($analysis, JSON_UNESCAPED_UNICODE),
                        'provider' => 'ollama',
                        'model' => (string) ($analysis['model'] ?? $this->ollama->modelForAgent('agent-imagini')),
                        'slug' => 'agent-imagini',
                        'mode' => 'vision_audit',
                        'data' => $analysis,
                    ];
                }

                $liveContext = "## Produs selectat\n- Titlu: " . ($product['title'] ?? '')
                    . "\n- Cod: " . ($product['code'] ?? '')
                    . "\n- Verdict audit: " . ($analysis['verdict'] ?? '')
                    . "\n- Rezumat imagine: " . ($analysis['summary_ro'] ?? '');
                $system = $this->buildSystemPrompt($agent, 'agent-imagini', $liveContext);

                return $this->dispatchLlm('agent-imagini', $system, $message, (float) ($agent['temperature'] ?? 0.15));
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        $system = $this->buildSystemPrompt($agent, 'agent-imagini', '');

        return $this->dispatchLlm('agent-imagini', $system, $message, (float) ($agent['temperature'] ?? 0.15));
    }

    /** @param array<string, mixed> $options */
    private function isSmokeTest(array $options): bool
    {
        if (!empty($options['use_knowledge']) || !empty($options['use_live_db'])) {
            return false;
        }

        return !empty($options['smoke_only'])
            || (!empty($options['fast_test']) && empty($options['use_live_db']) && !empty($options['legacy_smoke']));
    }

    /** @param array<string, mixed> $agent @param array<string, mixed> $options */
    private function chatTextAgent(string $slug, array $agent, string $message, array $options = []): array
    {
        $smoke = $this->isSmokeTest($options);

        if (!$smoke && !empty($options['use_live_db'])) {
            try {
                $pdo = $this->pdo();
                $direct = $this->tryDirectDbAnswer($slug, $message, $pdo);
                if ($direct !== null) {
                    return [
                        'ok' => true,
                        'content' => $direct,
                        'provider' => 'mysql_live',
                        'model' => $this->ollama->modelForAgent($slug),
                        'slug' => $slug,
                        'mode' => 'db_direct',
                    ];
                }
            } catch (Throwable) {
                // continue to LLM path
            }
        }

        if (!$smoke && !empty($options['use_knowledge'])) {
            $libraryDirect = $this->tryKnowledgeDirectAnswer($slug, $message);
            if ($libraryDirect !== null) {
                return [
                    'ok' => true,
                    'content' => $libraryDirect,
                    'provider' => 'library_rag',
                    'model' => $this->ollama->modelForAgent($slug),
                    'slug' => $slug,
                    'mode' => 'library_rag_direct',
                ];
            }
        }

        if ($slug === 'agent-produse' && !$smoke && !empty($options['use_live_db'])) {
            try {
                $rag = new CatalogRagService($this->projectRoot);
                $search = $rag->searchByMessage($message, 10, $this->pdo());
                $direct = $rag->formatReplyForChat($search);
                if ($direct !== '') {
                    $human = new AiHumanResponseService();
                    $humanDirect = $human->formatCatalogProducts(
                        is_array($search['products'] ?? null) ? $search['products'] : [],
                        trim((string) ($search['vehicle_context'] ?? ''))
                    );
                    if ($humanDirect !== '') {
                        $direct = $humanDirect;
                    }
                    return [
                        'ok' => true,
                        'content' => $direct,
                        'provider' => 'catalog_rag',
                        'model' => $this->ollama->modelForAgent($slug),
                        'slug' => $slug,
                        'mode' => 'rag_direct',
                        'rag_total' => (int) ($search['total'] ?? 0),
                    ];
                }
            } catch (Throwable) {
                // fallback LLM
            }
        }

        $liveContext = '';
        if (!$smoke) {
            try {
                $liveContext = $this->gatherLiveContext($this->pdo(), $slug, $message, $options);
            } catch (Throwable) {
                $liveContext = '## Date live: indisponibile (BD)';
            }
        }

        $system = $this->buildSystemPrompt($agent, $slug, $liveContext, $smoke, $message, $options);

        return $this->dispatchLlm($slug, $system, $message, (float) ($agent['temperature'] ?? 0.25), $smoke);
    }

    private function tryKnowledgeDirectAnswer(string $slug, string $message): ?string
    {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        $library = new AiAgentContextLibraryService($this->projectRoot);
        $hits = $library->retrieve($slug, $message, 6);
        $corpusHits = [];
        try {
            $corpusHits = (new AiRagCorpusService($this->projectRoot))->search($message, 4);
        } catch (Throwable) {
            // optional
        }

        if ($hits === [] && $corpusHits === []) {
            return null;
        }

        $hits = $this->dedupeLibraryHits($hits);
        $corpusHits = $this->filterCorpusHits($corpusHits);

        $topScore = $hits !== [] ? (int) ($hits[0]['rag_score'] ?? 0) : 0;
        if ($topScore >= 10) {
            $corpusHits = [];
        }
        if ($topScore < 6 && $corpusHits === []) {
            return null;
        }

        $human = new AiHumanResponseService();
        $formatted = $human->formatLibraryAnswer($message, $hits, $corpusHits);

        return $formatted !== '' ? $formatted : null;
    }

    /** @param list<array<string, mixed>> $hits @return list<array<string, mixed>> */
    private function dedupeLibraryHits(array $hits): array
    {
        $out = [];
        $seen = [];
        foreach ($hits as $hit) {
            $text = trim((string) ($hit['text'] ?? ''));
            if ($text === '' || $this->isLowQualityFragment($text)) {
                continue;
            }
            $norm = preg_replace('/^produs:\s*/iu', '', $text) ?? $text;
            $key = md5(mb_strtolower(preg_replace('/\s+/u', ' ', mb_substr($norm, 0, 100)) ?? $norm));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $hit;
            if (count($out) >= 4) {
                break;
            }
        }

        usort($out, static function (array $a, array $b): int {
            $ta = (string) ($a['text'] ?? '');
            $tb = (string) ($b['text'] ?? '');
            $pa = str_starts_with($ta, 'Produs:') ? 1 : 0;
            $pb = str_starts_with($tb, 'Produs:') ? 1 : 0;
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }

            return ((int) ($b['rag_score'] ?? 0)) <=> ((int) ($a['rag_score'] ?? 0));
        });

        return $out;
    }

    /** @param list<array<string, mixed>> $hits @return list<array<string, mixed>> */
    private function filterCorpusHits(array $hits): array
    {
        $out = [];
        foreach ($hits as $hit) {
            $text = trim((string) ($hit['text'] ?? ''));
            if ($text === '' || $this->isLowQualityFragment($text)) {
                continue;
            }
            $out[] = $hit;
            if (count($out) >= 2) {
                break;
            }
        }

        return $out;
    }

    private function isLowQualityFragment(string $text): bool
    {
        if (mb_strlen($text) < 12) {
            return true;
        }
        $lower = mb_strtolower($text);
        foreach (['cookie-urile sunt', 'cosul tau', 'lista favorite', 'cauta cont', '×'] as $noise) {
            if (str_contains($lower, $noise)) {
                return true;
            }
        }
        if (preg_match('/\b(div|span|class=|<\/)\b/i', $text)) {
            return true;
        }
        $spaces = substr_count($text, ' ');
        if ($spaces > 40 && mb_strlen($text) / max(1, $spaces) < 3) {
            return true;
        }

        return false;
    }

    private function tryDirectDbAnswer(string $slug, string $message, PDO $pdo): ?string
    {
        if (trim($message) === '') {
            return null;
        }

        if (preg_match('/\b(c[âa]te|cat|num[aă]r|total|cate)\b.*\b(produse?)\b.*\b(activ|online|magazin|live)\b/iu', $message)
            || preg_match('/\b(produse?)\b.*\b(activ|active)\b/iu', $message)) {
            if (!AdminDatabaseResolver::hasTable($pdo, 'produse')) {
                $dbName = (string) ($pdo->query('SELECT DATABASE()')?->fetchColumn() ?: '');

                return 'Tabelul produse nu există în baza conectată'
                    . ($dbName !== '' ? ' (' . $dbName . ')' : '')
                    . '. Verifică DB_NAME în admin/.env sau importă catalogul (Import Pro → coadă → publicare).';
            }
            $snap = AdminDatabaseResolver::aiLiveSnapshot($pdo);
            $products = is_array($snap['products'] ?? null) ? $snap['products'] : [];

            return (new AiHumanResponseService())->formatDbStats('products_active', [
                'active' => number_format((int) ($products['active'] ?? 0), 0, ',', '.'),
                'cats' => number_format((int) ($products['categories'] ?? 0), 0, ',', '.'),
                'no_img' => number_format((int) ($products['no_image'] ?? 0), 0, ',', '.'),
                'vitrina' => isset($products['vitrina'])
                    ? number_format((int) $products['vitrina'], 0, ',', '.')
                    : null,
            ]);
        }

        if (preg_match('/\b(import|staging|coad[aă])\b.*\b(pending|a[sș]tept)\b/iu', $message)
            || preg_match('/\b(import|staging|coad[aă])\b.*\bpending\b/iu', $message)
            || preg_match('/\bpending\b.*\b(import|staging)\b/iu', $message)
            || preg_match('/\b(coad[aă])\s+(de\s+)?import\b/iu', $message)) {
            if (!AdminDatabaseResolver::hasTable($pdo, 'import_produse')) {
                return 'Tabelul import_produse nu există — coada Import Pro nu e disponibilă în această bază.';
            }
            $snap = AdminDatabaseResolver::aiLiveSnapshot($pdo);
            $queue = is_array($snap['import_queue'] ?? null) ? $snap['import_queue'] : [];

            return (new AiHumanResponseService())->formatDbStats('import_queue', [
                'pending' => (string) ((int) ($queue['pending'] ?? 0)),
                'conflict' => (string) ((int) ($queue['conflict_live'] ?? 0)),
            ]);
        }

        if (preg_match('/\b(comenzi|comanda)\b/iu', $message) && preg_match('/\b(c[âa]te|total|ultim)/iu', $message)) {
            if (!AdminDatabaseResolver::hasTable($pdo, 'comenzi')) {
                return 'Tabelul comenzi nu există în baza conectată.';
            }
            $total = $this->scalar($pdo, 'SELECT COUNT(*) FROM comenzi', true);
            $week = (string) AdminDatabaseResolver::countOrdersLastDays($pdo, 7);

            return (new AiHumanResponseService())->formatDbStats('orders', [
                'total' => $total,
                'week' => $week,
            ]);
        }

        if (preg_match('/\b(categorii|top categorii)\b/iu', $message)) {
            $cats = $this->scalar($pdo, "SELECT COUNT(DISTINCT pCategory) FROM produse WHERE status <> '0' AND pCategory <> ''");
            try {
                $stmt = $pdo->query(
                    "SELECT pCategory AS cat, COUNT(*) AS cnt FROM produse WHERE status <> '0' AND pCategory <> '' GROUP BY pCategory ORDER BY cnt DESC LIMIT 5"
                );
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $lines = ['Date live MySQL — top categorii (' . $cats . ' total):'];
                foreach ($rows as $row) {
                    $lines[] = '- ' . ($row['cat'] ?? '?') . ': ' . ($row['cnt'] ?? 0);
                }

                return implode("\n", $lines);
            } catch (Throwable) {
                return 'Categorii distincte (live): ' . $cats;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $agent @param array<string, mixed> $options */
    private function buildSystemPrompt(array $agent, string $slug, string $liveContext, bool $fastTest = false, ?string $userQuery = null, array $options = []): string
    {
        if ($fastTest) {
            return 'Ești agent Besoiu (' . $slug . ') — mod SMOKE TEST conexiune Ollama. '
                . 'NU ai acces la MySQL. NU inventa cifre, produse, medicamente sau alte date fictive. '
                . 'Răspunde DOAR că smoke test-ul e OK și că pentru date reale din magazin trebuie folosit chat normal (date live).';
        }

        $events = new AiActionEventService();
        $includeLibrary = !empty($options['use_knowledge']);
        $runtime = $this->registry->buildRuntimeContext(
            $agent,
            $events->getCore(),
            null,
            $events->readEvents(8),
            $userQuery,
            $includeLibrary
        );

        $parts = [
            '## Runtime agent (slim chat)',
            (string) ($runtime['markdown'] ?? ''),
        ];
        if ($liveContext !== '') {
            $parts[] = '';
            $parts[] = mb_substr($liveContext, 0, 6000);
        }
        $parts[] = '';
        $parts[] = 'Ești asistent Besoiu Piese Auto — vorbești natural, ca un coleg din magazin.';
        $parts[] = 'Răspunde în română, clar și prietenos (max 12 propoziții). Folosește bullet points când listezi.';
        $parts[] = 'Folosește DOAR contextul de mai sus. Dacă lipsește informația, spune sincer ce nu știi — nu inventa.';

        return implode("\n", $parts);
    }

    /** @return array<string, mixed> */
    private function dispatchLlm(string $slug, string $system, string $message, float $temperature, bool $fastTest = false): array
    {
        $task = $this->taskContextForSlug($slug);
        $model = $this->ollama->modelForAgent($slug);
        $helper = dirname(__DIR__, 2) . '/system/ollama_llm.php';
        $profile = 'hybrid';
        if (is_file($helper)) {
            require_once $helper;
            $profile = besoiu_llm_task_profile($task);
        }

        $timeoutSec = $fastTest ? 45 : 120;

        if ($this->ollama->isEnabled()) {
            $needsVision = $slug === 'agent-imagini';
            $ready = $needsVision
                ? ($this->ollama->visionReadiness()['ready'] && $this->ollama->isModelInstalled($model))
                : $this->ollama->isModelInstalled($model);

            if ($ready) {
                $this->ollama->pushCallContext([
                    'source' => 'ai-rag.' . $slug,
                    'section' => 'ai-rag',
                    'task_label' => $this->agentDisplayName($slug),
                    'agent' => $slug,
                    'path' => $this->adminRequestPath(),
                ]);
                try {
                    $res = $this->ollama->complete($system, $message, $fastTest ? 0.1 : $temperature, $timeoutSec, $model);
                } finally {
                    $this->ollama->clearCallContext();
                }
                if (!empty($res['ok'])) {
                    $res['slug'] = $slug;
                    $res['mode'] = 'ollama_direct';

                    return $res;
                }
                if ($profile === 'local') {
                    return array_merge($res, ['slug' => $slug]);
                }
            } elseif ($profile === 'local') {
                return [
                    'ok' => false,
                    'error' => 'Ollama indisponibil — model: ' . $model,
                    'slug' => $slug,
                ];
            }
        }

        $res = $this->router->complete($system, $message, $temperature, $timeoutSec, $task);
        $res['slug'] = $slug;
        $res['mode'] = 'router';

        return $res;
    }

    /** @param array<string, mixed> $options */
    private function gatherLiveContext(PDO $pdo, string $slug, string $message = '', array $options = []): string
    {
        if (empty($options['use_live_db'])) {
            return '';
        }

        $lines = ['## Date live MySQL (' . date('Y-m-d H:i') . ')'];

        if ($slug === 'agent-produse' && $message !== '') {
            $rag = new CatalogRagService($this->projectRoot);
            $search = $rag->searchByMessage($message, 6, $pdo);
            if ($search['context'] !== '') {
                $lines[] = '';
                $lines[] = mb_substr((string) $search['context'], 0, 2500);
            }
            if ($search['total'] === 0) {
                (new ShopSearchMissLogService())->logMiss(
                    $message,
                    (string) ($options['channel'] ?? 'admin_agent'),
                    0,
                    isset($options['visitor_key']) ? (string) $options['visitor_key'] : null,
                    $pdo
                );
            }
        }

        if ($slug === 'agent-clienti') {
            $clientCtx = new ShopChatClientContextService($this->projectRoot);
            $lines[] = '';
            $lines[] = $clientCtx->buildContext($pdo, [
                'phone' => (string) ($options['phone'] ?? ''),
                'email' => (string) ($options['email'] ?? ''),
                'visitor_key' => (string) ($options['visitor_key'] ?? ''),
            ]);
        }

        if (in_array($slug, ['agent-produse', 'agent-statistici'], true)) {
            $snap = AdminDatabaseResolver::aiLiveSnapshot($pdo);
            $products = is_array($snap['products'] ?? null) ? $snap['products'] : [];
            if ($products !== []) {
                $lines[] = '- Produse active: ' . (int) ($products['active'] ?? 0);
                $lines[] = '- Produse fără imagine: ' . (int) ($products['no_image'] ?? 0);
                $lines[] = '- Categorii distincte: ' . (int) ($products['categories'] ?? 0);
            }
        }

        if (in_array($slug, ['agent-statistici'], true)) {
            $snap = AdminDatabaseResolver::aiLiveSnapshot($pdo);
            $queue = is_array($snap['import_queue'] ?? null) ? $snap['import_queue'] : null;
            if ($queue !== null) {
                $lines[] = '- Import staging pending: ' . (int) ($queue['pending'] ?? 0);
                $lines[] = '- Import conflict_live: ' . (int) ($queue['conflict_live'] ?? 0);
            } elseif (AdminDatabaseResolver::hasTable($pdo, 'import_produse')) {
                $lines[] = '- Import staging pending: ' . $this->scalar($pdo, "SELECT COUNT(*) FROM import_produse WHERE status = 'pending'");
            }
            $orders = is_array($snap['orders'] ?? null) ? $snap['orders'] : null;
            if ($orders !== null) {
                $lines[] = '- Comenzi site (total): ' . (int) ($orders['total'] ?? 0);
                $lines[] = '- Comenzi ultimele 7 zile: ' . (int) ($orders['last_7_days'] ?? 0);
            }
            $lines[] = '';
            $lines[] = (new ShopSearchMissLogService())->formatTopMissesContext(7, 10, $pdo);
        }

        if (in_array($slug, ['agent-clienti', 'agent-statistici'], true)) {
            $recent = $this->fetchRecentOrders($pdo, 5);
            if ($recent !== []) {
                $lines[] = '';
                $lines[] = '### Ultimele comenzi site';
                foreach ($recent as $row) {
                    $lines[] = '- #' . ($row['id'] ?? '?')
                        . ' · ' . ($row['order_status'] ?? '—')
                        . ' · ' . ($row['total_amount'] ?? $row['total'] ?? '—') . ' RON'
                        . ' · ' . ($row['created_at'] ?? $row['data'] ?? '');
                }
            }
        }

        if ($slug === 'agent-imagini') {
            $lines[] = '- Produse active fără imagine: ' . $this->scalar($pdo, "SELECT COUNT(*) FROM produse WHERE status <> '0' AND (pImages IS NULL OR pImages = '' OR pImages = '[]')");
            $auditDir = $this->projectRoot . '/admin/storage/image_audit/reports';
            $reports = is_dir($auditDir) ? glob($auditDir . '/report_*.md') : [];
            $lines[] = '- Rapoarte audit imagini: ' . (is_array($reports) ? count($reports) : 0);
        }

        if ($message !== '' && !empty($options['use_knowledge'])) {
            try {
                $corpus = new AiRagCorpusService($this->projectRoot);
                $hits = $corpus->search($message, 4);
                $ragLines = $corpus->formatForPrompt($hits);
                if ($ragLines !== []) {
                    $lines[] = '';
                    $lines[] = '### Corpus RAG (' . count($hits) . ' fragmente)';
                    array_push($lines, ...array_slice($ragLines, 0, 4));
                }
            } catch (Throwable) {
                // optional
            }
        }

        return implode("\n", $lines);
    }

    private function scalar(PDO $pdo, string $sql, bool $optional = false): string
    {
        try {
            $val = $pdo->query($sql)?->fetchColumn();

            return (string) (false === $val ? '0' : $val);
        } catch (Throwable) {
            return $optional ? 'n/a' : '0';
        }
    }

    /** @return list<array<string, mixed>> */
    private function fetchRecentOrders(PDO $pdo, int $limit): array
    {
        if (!AdminDatabaseResolver::hasTable($pdo, 'comenzi')) {
            return [];
        }

        try {
            $cols = AdminDatabaseResolver::comenziSelectColumns($pdo);
            $select = implode(', ', array_map(static fn (string $c) => '`' . str_replace('`', '', $c) . '`', $cols));
            $orderCol = in_array('created_at', $cols, true) ? 'created_at' : (in_array('id', $cols, true) ? 'id' : $cols[0]);
            $stmt = $pdo->query(
                'SELECT ' . $select . ' FROM comenzi ORDER BY `' . str_replace('`', '', $orderCol) . '` DESC LIMIT ' . (int) $limit
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function adminRequestPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri === '') {
            return '/admin/ai-rag';
        }
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/admin/ai-rag';
    }

    private function agentDisplayName(string $slug): string
    {
        return match ($slug) {
            'agent-produse' => 'Chat agent produse',
            'agent-statistici' => 'Statistici zilnice',
            'agent-imagini' => 'Analiză imagini',
            'agent-import' => 'Asistent import',
            'agent-furnizori' => 'Agent furnizori',
            'agent-comenzi' => 'Agent comenzi',
            default => 'Agent ' . str_replace('agent-', '', $slug),
        };
    }
}
