<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use Besoiu\Controllers\AdaosComercial\AdaosComercial;
use Besoiu\Services\AdaosComercial\AdaosComercialService;
use Besoiu\Core\Supplier\SupplierHooks;
use PDO;
use Throwable;

/** Acțiuni avansate admin — import, adaos, furnizori, export, coadă. */
final class SectionAssistantExtendedActions
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /** @param array<string, mixed> $context @param array<string, mixed> $input @return array<string, mixed>|null */
    public function tryHandle(string $message, array $context, array $input): ?array
    {
        $pending = is_array($input['pending_action'] ?? null) ? $input['pending_action'] : null;
        $shouldExecute = !empty($input['execute_action']) || !empty($input['confirm_execute']);

        if ($pending !== null && $shouldExecute && str_starts_with((string) ($pending['type'] ?? ''), 'extended_')) {
            $executed = $this->executePending($pending, $context);

            return $executed ?? null;
        }

        foreach ($this->detectIntents($message) as $intent) {
            $plan = $this->buildPreview($intent, $context, $input);
            if ($plan !== null) {
                return $plan;
            }
        }

        return null;
    }

    public static function messageLooksLikeAction(string $message): bool
    {
        return (new self())->detectIntents($message) !== [];
    }

    /** @return list<array<string, mixed>> */
    private function detectIntents(string $message): array
    {
        $lower = mb_strtolower($message, 'UTF-8');
        $out = [];

        if (preg_match('/\b(porn(?:este|i)|start)\s+scan\s+consumabil|\bscan\s+consumabil\b/u', $lower)) {
            $cats = [];
            foreach (['ulei', 'lichide', 'electrice'] as $c) {
                if (preg_match('/\b' . preg_quote($c, '/') . '\w*\b/u', $lower)) {
                    $cats[] = $c === 'lichide' ? 'lichide' : ($c === 'electrice' ? 'electrice' : 'ulei');
                }
            }
            if ($cats === []) {
                $cats = ['ulei', 'lichide', 'electrice'];
            }
            $out[] = ['type' => 'extended_consumable_scan', 'categories' => array_values(array_unique($cats))];
        }

        if (preg_match('/\bseteaz[aă]?\s+adaos\s+global\s+(\d+(?:[.,]\d+)?)\s*%/u', $lower, $m)) {
            $out[] = ['type' => 'extended_adaos_global', 'percent' => (float) str_replace(',', '.', $m[1])];
        }

        if (preg_match('/\baplic[aă]?\s+regul[aă]?\s+adaos(?:\s+(\d+)|\s+(.+))?/u', $lower, $m)) {
            $out[] = [
                'type' => 'extended_adaos_apply',
                'rule_id' => isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 0,
                'rule_name' => trim((string) ($m[2] ?? '')),
            ];
        }

        if (preg_match('/\badaug[aă]?\s+regul[aă]?\s+adaos\s+(\d+(?:[.,]\d+)?)\s*%/u', $lower, $m)) {
            $name = 'Regula Composer';
            if (preg_match('/\b(?:categor\w*|pe)\s+([a-z0-9\-]+)/u', $lower, $nm)) {
                $name = 'Adaos ' . ucfirst($nm[1]);
            }
            $out[] = [
                'type' => 'extended_adaos_create',
                'percent' => (float) str_replace(',', '.', $m[1]),
                'name' => $name,
                'category_filter' => preg_match('/\bulei\w*\b/u', $lower) ? 'Ulei' : null,
            ];
        }

        if (preg_match('/\b(pune|mut[aă]?)\s+([a-z0-9]+)\s+(?:primul|prima\s+pozit|poziti[aă]?\s*1)/u', $lower, $m)) {
            $out[] = ['type' => 'extended_supplier_reorder', 'supplier' => strtoupper($m[2])];
        }

        if (preg_match('/\b(scan(?:eaza|eaz[aă])?|caut[aă]?)\s+imagini\b/u', $lower) && preg_match('/\b(coad[aă]|import|pending)\b/u', $lower)) {
            $limit = 20;
            if (preg_match('/\b(\d{1,3})\s*produse?\b/u', $lower, $lm)) {
                $limit = (int) $lm[1];
            }
            $out[] = ['type' => 'extended_scan_images', 'limit' => min(50, max(1, $limit))];
        }

        if (preg_match('/\b(reprocess|re-proceseaz[aă]|reproceseaz[aă])\b/u', $lower) && preg_match('/\b(coad[aă]|import|tecdoc)\b/u', $lower)) {
            $limit = 5;
            if (preg_match('/\b(\d{1,2})\s*produse?\b/u', $lower, $lm)) {
                $limit = (int) $lm[1];
            }
            $out[] = ['type' => 'extended_reprocess_queue', 'limit' => min(20, max(1, $limit))];
        }

        if (preg_match('/\bexport\s+baselinker\b/u', $lower)) {
            $out[] = ['type' => 'extended_export_baselinker'];
        }

        if (preg_match('/\bexport\s+autopro\b/u', $lower)) {
            $out[] = ['type' => 'extended_export_autopro'];
        }

        return $out;
    }

    /** @param array<string, mixed> $intent @param array<string, mixed> $context @param array<string, mixed> $input @return array<string, mixed>|null */
    private function buildPreview(array $intent, array $context, array $input): ?array
    {
        if (!empty($input['execute_action']) || !empty($input['confirm_execute'])) {
            return $this->executeIntent($intent, $context);
        }

        $type = (string) ($intent['type'] ?? '');
        $title = 'Confirmare actiune';
        $subtitle = $type;
        $stats = [];
        $hints = ['Apasa Confirma pentru a executa.'];

        switch ($type) {
            case 'extended_consumable_scan':
                $files = $this->listUploadedImportFiles();
                $title = 'Scan consumabile';
                $subtitle = 'Pornire scan: ' . implode(', ', (array) ($intent['categories'] ?? []));
                $stats = [
                    ['key' => 'files', 'label' => 'Fisiere CSV incarcate', 'value' => (string) count($files)],
                ];
                if ($files === []) {
                    return $this->errorPlan('Nu exista fisiere CSV incarcate. Mergi la /admin/import si incarca listele furnizor.');
                }
                break;

            case 'extended_adaos_global':
                $title = 'Adaos comercial global';
                $subtitle = 'Setare adaos global ' . ($intent['percent'] ?? 0) . '%';
                $snap = SectionAssistantAdaosQueries::snapshot();
                $stats = [
                    ['key' => 'current', 'label' => 'Adaos acum', 'value' => number_format((float) ($snap['global_markup_percent'] ?? 0), 2) . '%'],
                    ['key' => 'new', 'label' => 'Adaos nou', 'value' => number_format((float) ($intent['percent'] ?? 0), 2) . '%', 'warn' => true],
                ];
                break;

            case 'extended_adaos_apply':
                $rule = $this->resolveAdaosRule($intent);
                if ($rule === null) {
                    return $this->errorPlan('Nu am gasit regula de adaos. Spune ID-ul sau numele regulii.');
                }
                $title = 'Aplica regula adaos';
                $subtitle = (string) ($rule['name'] ?? '');
                $stats = [['key' => 'id', 'label' => 'Regula ID', 'value' => (string) ($rule['id'] ?? 0)]];
                $intent['rule_id'] = (int) ($rule['id'] ?? 0);
                break;

            case 'extended_adaos_create':
                $title = 'Creare regula adaos';
                $subtitle = (string) ($intent['name'] ?? 'Regula noua') . ' +' . ($intent['percent'] ?? 0) . '%';
                break;

            case 'extended_supplier_reorder':
                $title = 'Pozitionare furnizor';
                $subtitle = 'Mut ' . ($intent['supplier'] ?? '') . ' pe pozitia 1';
                break;

            case 'extended_scan_images':
                $title = 'Scan imagini coada import';
                $subtitle = 'Modul import dezactivat';
                $stats = [['key' => 'queue', 'label' => 'In coada', 'value' => '0']];
                break;

            case 'extended_reprocess_queue':
                $title = 'Reprocess TecDoc coada';
                $subtitle = 'Re-procesare max ' . ($intent['limit'] ?? 5) . ' produse';
                break;

            case 'extended_export_baselinker':
                $title = 'Export BaseLinker';
                $subtitle = 'Trimite produse validate din coada';
                break;

            case 'extended_export_autopro':
                $title = 'Export Autopro CSV';
                $subtitle = 'Export CSV format Autopro din coada';
                break;

            default:
                return null;
        }

        return [
            'source' => 'action_preview',
            'reply_ro' => $subtitle,
            'intent' => 'extended',
            'pending_action' => $intent,
            'cheat_sheet' => [
                'kind' => 'action_preview',
                'title' => $title,
                'subtitle' => $subtitle,
                'updated_at' => date('d.m.Y H:i'),
                'stats' => $stats,
                'hints' => $hints,
                'confirm_required' => true,
                'confirm_question' => 'Doresti sa executi: ' . $title . '?',
                'confirm' => ['label' => 'Da, executa', 'action' => $type],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @param array<string, mixed> $intent @param array<string, mixed> $context @return array<string, mixed>|null */
    private function executeIntent(array $intent, array $context): ?array
    {
        return $this->executePending($intent, $context);
    }

    /** @param array<string, mixed> $pending @param array<string, mixed> $context @return array<string, mixed>|null */
    public function executePendingAction(array $pending, array $context): ?array
    {
        if (!str_starts_with((string) ($pending['type'] ?? ''), 'extended_')) {
            return null;
        }

        return $this->executePending($pending, $context);
    }

    /** @param array<string, mixed> $pending @param array<string, mixed> $context @return array<string, mixed>|null */
    private function executePending(array $pending, array $context): ?array
    {
        $type = (string) ($pending['type'] ?? '');

        try {
            return match ($type) {
                'extended_consumable_scan' => $this->runConsumableScan($pending),
                'extended_adaos_global' => $this->runAdaosGlobal($pending),
                'extended_adaos_apply' => $this->runAdaosApply($pending),
                'extended_adaos_create' => $this->runAdaosCreate($pending),
                'extended_supplier_reorder' => $this->runSupplierReorder($pending),
                'extended_scan_images' => $this->runScanImages($pending),
                'extended_reprocess_queue' => $this->runReprocessQueue($pending),
                'extended_export_baselinker' => $this->runExportBaselinker(),
                'extended_export_autopro' => $this->runExportAutopro(),
                default => null,
            };
        } catch (Throwable $e) {
            return $this->errorPlan('Eroare executie: ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $intent @return array<string, mixed> */
    private function runConsumableScan(array $intent): array
    {
        $this->ensureImportLibs();
        $filesMeta = $this->listUploadedImportFiles();

        $start = import_consumable_scan_job_start($filesMeta, [
            'categories' => (array) ($intent['categories'] ?? ['ulei', 'lichide', 'electrice']),
            'max_preview' => 200,
        ]);

        if (empty($start['ok'])) {
            return $this->errorPlan((string) ($start['message'] ?? $start['error'] ?? 'Nu am putut porni scanarea consumabile.'));
        }

        $jobId = (string) ($start['job_id'] ?? '');
        $steps = 0;
        $result = null;
        while ($steps < 50) {
            ++$steps;
            $meta = import_job_load_meta($jobId);
            if ($meta === null) {
                break;
            }
            if (($meta['status'] ?? '') === 'done') {
                $result = is_array($meta['result'] ?? null) ? $meta['result'] : null;
                break;
            }
            $state = import_job_load_state($jobId);
            if ($state === null) {
                break;
            }
            $step = import_consumable_scan_job_step($jobId, $meta, $state);
            if (empty($step['ok'])) {
                return $this->errorPlan((string) ($step['error'] ?? 'Pas scan consumabile eșuat.'));
            }
            if (!empty($step['result'])) {
                $result = $step['result'];
            }
            if (!empty(($step['status'] ?? [])['done'])) {
                break;
            }
        }

        $count = is_array($result) ? count($result['products'] ?? []) : 0;

        return $this->successPlan(
            ($result['message'] ?? 'Scan consumabile finalizat.') . ' (' . $count . ' produse).',
            'Scan consumabile',
            [
                ['key' => 'job', 'label' => 'Job ID', 'value' => $jobId],
                ['key' => 'found', 'label' => 'Produse', 'value' => (string) $count],
            ],
            [['label' => 'Import CSV', 'url' => '/admin/import'], ['label' => 'Coada import', 'url' => '/admin/importreview']]
        );
    }

    /** @param array<string, mixed> $intent @return array<string, mixed> */
    private function runAdaosGlobal(array $intent): array
    {
        $ctrl = new AdaosComercial();
        $res = $ctrl->saveGlobalCommercialMarkupSettings([
            'global_commercial_markup_percent' => (float) ($intent['percent'] ?? 0),
        ]);
        if (empty($res['success'])) {
            return $this->errorPlan((string) ($res['message'] ?? 'Esuat'));
        }

        return $this->successPlan(
            (string) ($res['message'] ?? 'Adaos global salvat.'),
            'Adaos global actualizat',
            [['key' => 'markup', 'label' => 'Adaos', 'value' => number_format((float) ($intent['percent'] ?? 0), 2) . '%']],
            [['label' => 'Adaos comercial', 'url' => '/admin/adaoscomercial']]
        );
    }

    /** @param array<string, mixed> $intent @return array<string, mixed> */
    private function runAdaosApply(array $intent): array
    {
        $rule = $this->resolveAdaosRule($intent);
        if ($rule === null) {
            return $this->errorPlan('Regula adaos negasita.');
        }
        $ctrl = new AdaosComercial();
        $res = $ctrl->apply(['id' => (int) ($rule['id'] ?? 0)]);
        if (empty($res['success'])) {
            return $this->errorPlan((string) ($res['message'] ?? 'Apply esuat'));
        }
        $updated = (int) ($res['data']['updated_count'] ?? $res['data']['updated'] ?? 0);

        return $this->successPlan(
            (string) ($res['message'] ?? 'Regula aplicata.'),
            'Regula adaos aplicata',
            [
                ['key' => 'rule', 'label' => 'Regula', 'value' => (string) ($rule['name'] ?? '')],
                ['key' => 'updated', 'label' => 'Produse', 'value' => (string) $updated],
            ],
            [['label' => 'Adaos comercial', 'url' => '/admin/adaoscomercial']]
        );
    }

    /** @param array<string, mixed> $intent @return array<string, mixed> */
    private function runAdaosCreate(array $intent): array
    {
        $ctrl = new AdaosComercial();
        $payload = [
            'name' => (string) ($intent['name'] ?? 'Regula Composer'),
            'adjustment_type' => 'percentage',
            'adjustment_value' => (float) ($intent['percent'] ?? 0),
            'is_active' => 1,
            'priority' => 100,
        ];
        if (!empty($intent['category_filter'])) {
            $payload['category_filter'] = (string) $intent['category_filter'];
        }
        $res = $ctrl->save($payload);
        if (empty($res['success'])) {
            return $this->errorPlan((string) ($res['message'] ?? 'Creare regula esuata'));
        }

        return $this->successPlan(
            (string) ($res['message'] ?? 'Regula creata.'),
            'Regula adaos creata',
            [['key' => 'id', 'label' => 'ID', 'value' => (string) ($res['id'] ?? '-')]],
            [['label' => 'Adaos comercial', 'url' => '/admin/adaoscomercial']]
        );
    }

    /** @param array<string, mixed> $intent @return array<string, mixed> */
    private function runSupplierReorder(array $intent): array
    {
        $supplier = strtoupper(trim((string) ($intent['supplier'] ?? '')));
        if ($supplier === '') {
            return $this->errorPlan('Lipseste codul furnizor.');
        }
        $service = SupplierHooks::priceLogic();
        if ($service === null) {
            throw new \RuntimeException('Modulul furnizori nu este disponibil.');
        }
        $config = $service->getConfig();
        $order = is_array($config['scan_order'] ?? null) ? $config['scan_order'] : [];
        $order = array_values(array_filter($order, static fn ($c) => strtoupper((string) $c) !== $supplier));
        array_unshift($order, $supplier);
        $config['scan_order'] = $order;
        $service->saveConfig($config);

        return $this->successPlan(
            $supplier . ' mutat pe pozitia 1 in comparare furnizori.',
            'Ordine furnizori actualizata',
            [['key' => 'first', 'label' => 'Pozitia 1', 'value' => $supplier]],
            [['label' => 'Comparare furnizori', 'url' => '/admin/suppliers?tab=compare']]
        );
    }

    /** @param array<string, mixed> $intent @return array<string, mixed> */
    private function runScanImages(array $intent): array
    {
        return $this->errorPlan('Modulul de import produse a fost dezactivat.');
    }

    /** @param array<string, mixed> $intent @return array<string, mixed> */
    private function runReprocessQueue(array $intent): array
    {
        return $this->errorPlan('Modulul de import produse a fost dezactivat.');
    }

    /** @return array<string, mixed> */
    private function runExportBaselinker(): array
    {
        return $this->errorPlan('Modulul de import produse a fost dezactivat.');
    }

    /** @return array<string, mixed> */
    private function runExportAutopro(): array
    {
        return $this->errorPlan('Modulul de import produse a fost dezactivat.');
    }

    /** @return list<array<string, mixed>> */
    private function listUploadedImportFiles(): array
    {
        if (!function_exists('list_uploaded_import_files')) {
            require_once dirname(__DIR__) . '/Controllers/Produse/import_uploaded_files_lib.php';
        }

        return list_uploaded_import_files();
    }

    /** @param array<string, mixed> $intent @return array<string, mixed>|null */
    private function resolveAdaosRule(array $intent): ?array
    {
        $id = (int) ($intent['rule_id'] ?? 0);
        $name = mb_strtolower(trim((string) ($intent['rule_name'] ?? '')), 'UTF-8');
        $service = new AdaosComercialService();
        foreach ($service->getAll() as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if ($id > 0 && (int) ($rule['id'] ?? 0) === $id) {
                return $rule;
            }
            if ($name !== '' && str_contains(mb_strtolower((string) ($rule['name'] ?? ''), 'UTF-8'), $name)) {
                return $rule;
            }
        }

        return $id > 0 ? $service->getById($id) : null;
    }

    private function ensureImportLibs(): void
    {
        require_once dirname(__DIR__) . '/Controllers/Produse/import_uploaded_files_lib.php';
        require_once dirname(__DIR__) . '/Controllers/Produse/import_supplier_lib.php';
        require_once dirname(__DIR__) . '/Controllers/Produse/import_tecdoc_library_lib.php';
        require_once dirname(__DIR__) . '/Controllers/Produse/import_job_lib.php';
        require_once dirname(__DIR__) . '/Controllers/Produse/import_consumable_scan_lib.php';
    }

    private function ensureImportActionLib(): void
    {
    }

    /** @param list<array<string, mixed>> $stats @param list<array{label:string,url:string}> $shortcuts @return array<string, mixed> */
    private function successPlan(string $reply, string $title, array $stats, array $shortcuts): array
    {
        return [
            'source' => 'action_executed',
            'reply_ro' => $reply,
            'intent' => 'extended',
            'cheat_sheet' => [
                'kind' => 'action_result',
                'title' => $title,
                'subtitle' => $reply,
                'updated_at' => date('d.m.Y H:i'),
                'stats' => $stats,
                'shortcuts' => $shortcuts,
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function errorPlan(string $message): array
    {
        return [
            'source' => 'action_error',
            'reply_ro' => $message,
            'intent' => 'extended',
            'cheat_sheet' => [
                'kind' => 'action_error',
                'title' => 'Actiune esuata',
                'subtitle' => $message,
                'updated_at' => date('d.m.Y H:i'),
                'hints' => [$message],
            ],
            'suggestions' => [],
            'next_steps' => [],
        ];
    }
}
