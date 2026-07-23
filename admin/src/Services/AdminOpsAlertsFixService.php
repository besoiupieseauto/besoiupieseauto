<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use Besoiu\Controllers\Bots\Bots;
use Besoiu\Services\BotsService;
use Besoiu\Core\Supplier\SupplierHooks;
use PDO;
use Throwable;

/**
 * Reparare alerte operaționale — acțiuni „Corectează” din /admin/alerts.
 */
final class AdminOpsAlertsFixService
{
    private static bool $importJobLibLoaded = false;

    private function ensureImportJobLib(): void
    {
        if (self::$importJobLibLoaded) {
            return;
        }

        $lib = dirname(__DIR__) . '/Controllers/Produse/import_job_lib.php';
        if (!is_file($lib)) {
            throw new \RuntimeException('Modul job import indisponibil: ' . $lib);
        }

        require_once $lib;
        self::$importJobLibLoaded = true;
    }

    /** @param array<string, mixed> $params @return array<string, mixed> */
    public function fix(string $code, array $params = []): array
    {
        $code = strtolower(trim($code));
        $fixAction = trim((string) ($params['fix_action'] ?? ''));

        if ($fixAction === '') {
            $fixAction = $this->defaultFixAction($code);
        }

        if ($fixAction === '') {
            return $this->result(false, false, 'Această alertă nu poate fi reparată automat. Folosește linkul de detalii.');
        }

        try {
            $message = match ($fixAction) {
                'resolve_system_error' => $this->fixSystemError($params),
                'refresh_tecdoc' => $this->fixTecdoc(),
                'clear_ai_error' => $this->fixAiError(),
                'dismiss_import_error' => $this->fixImportFailed($params),
                'cancel_blocked_job' => $this->fixBlockedJob($params),
                'test_integration' => $this->fixIntegration($params),
                default => throw new \InvalidArgumentException('Acțiune necunoscută: ' . $fixAction),
            };
        } catch (Throwable $e) {
            return $this->result(false, false, $e->getMessage());
        }

        $feed = (new AdminOpsAlertsService())->feed();
        $stillActive = $this->alertStillActive($feed, $code, $params);

        return $this->result(true, !$stillActive, $message, $feed);
    }

    /** @param array<string, mixed> $feed @param array<string, mixed> $params */
    private function alertStillActive(array $feed, string $code, array $params): bool
    {
        $needle = $this->alertIdentityKey($code, $params);

        foreach (($feed['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ($this->alertIdentityKey((string) ($item['code'] ?? ''), $item) === $needle) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $item */
    private function alertIdentityKey(string $code, array $item): string
    {
        $errorId = (int) ($item['error_id'] ?? 0);
        if ($errorId > 0) {
            return $code . '#err:' . $errorId;
        }
        $jobId = trim((string) ($item['job_id'] ?? ''));
        if ($jobId !== '') {
            return $code . '#job:' . $jobId;
        }
        $entityId = trim((string) ($item['entity_id'] ?? ''));
        if ($entityId !== '') {
            return $code . '#ent:' . ($item['entity_type'] ?? '') . ':' . $entityId;
        }

        return $code;
    }

    /** @param array<string, mixed> $params */
    private function fixSystemError(array $params): string
    {
        require_once dirname(__DIR__, 3) . '/system/system_errors.php';
        $pdo = Database::getDB();

        $errorId = (int) ($params['error_id'] ?? 0);
        if ($errorId > 0 && \system_errors_mark_resolved($pdo, $errorId, true)) {
            return 'Eroarea a fost marcată rezolvată.';
        }

        $channel = trim((string) ($params['channel'] ?? ''));
        if ($channel === '' && !empty($params['code'])) {
            $raw = (string) $params['code'];
            if (str_starts_with($raw, 'system_error_')) {
                $channel = substr($raw, strlen('system_error_'));
            }
        }

        if ($channel !== '') {
            $count = \system_errors_resolve_channel($pdo, $channel);
            if ($count > 0) {
                return $count === 1
                    ? 'Eroarea din jurnal a fost marcată rezolvată.'
                    : $count . ' erori din canalul „' . $channel . '” marcate rezolvate.';
            }
        }

        throw new \RuntimeException('Nu am găsit eroarea în jurnal sau era deja rezolvată.');
    }

    private function fixTecdoc(): string
    {
        require_once dirname(__DIR__, 3) . '/system/tecdoc_stock.php';

        $flag = tecdoc_quota_flag_path();
        if (is_file($flag)) {
            @unlink($flag);
        }
        tecdoc_clear_api_error();
        tecdoc_maybe_clear_stale_quota_flag(0);

        if (function_exists('tecdoc_probe_ip_status')) {
            tecdoc_probe_ip_status(true);
        }

        $this->resolveChannels(['rapidapi', 'tecdoc', 'tecdoc_api', 'catalog']);

        if (function_exists('tecdoc_api_is_unavailable') && tecdoc_api_is_unavailable()) {
            $msg = function_exists('tecdoc_api_unavailable_message')
                ? trim((string) tecdoc_api_unavailable_message())
                : 'API TecDoc / RapidAPI încă indisponibil.';

            throw new \RuntimeException($msg !== '' ? $msg : 'API încă indisponibil — verifică tokenii RapidAPI sau limita lunară.');
        }

        return 'TecDoc / RapidAPI răspunde din nou — alerta a dispărut.';
    }

    private function fixAiError(): string
    {
        $helper = dirname(__DIR__, 3) . '/system/ai_api_errors.php';
        if (!is_file($helper)) {
            throw new \RuntimeException('Modul AI indisponibil.');
        }
        require_once $helper;

        if (!ai_api_key_configured()) {
            throw new \RuntimeException('Cheia AI lipsește — adaugă GROQ_KEY / OPENAI_KEY sau activează Ollama în admin/.env.');
        }

        ai_api_clear_last_error();
        $this->resolveChannels(['ai', 'groq', 'openai']);

        return 'Eroarea AI a fost ștearsă din monitor. Dacă API-ul funcționează, alerta dispare.';
    }

    /** @param array<string, mixed> $params */
    private function fixImportFailed(array $params): string
    {
        $this->ensureImportJobLib();

        $jobId = trim((string) ($params['job_id'] ?? ''));
        $cleaned = 0;

        if ($jobId === '') {
            throw new \RuntimeException(
                'Lipsește job_id — specifică jobul de eliminat. Ștergerea în masă a joburilor eșuate nu este permisă automat.'
            );
        }

        import_job_cleanup($jobId);
        $cleaned = 1;

        $this->clearImportStatsCache();

        if ($cleaned === 0) {
            throw new \RuntimeException('Nu există job import eșuat de curățat.');
        }

        return $cleaned === 1
            ? 'Jobul eșuat a fost eliminat. Poți relansa importul.'
            : $cleaned . ' joburi eșuate eliminate. Poți relansa importul.';
    }

    /** @param array<string, mixed> $params */
    private function fixBlockedJob(array $params): string
    {
        $this->ensureImportJobLib();

        $jobId = trim((string) ($params['job_id'] ?? ''));
        if ($jobId === '') {
            throw new \RuntimeException('Lipsește ID job blocat.');
        }

        if (!import_job_cancel($jobId)) {
            throw new \RuntimeException('Nu am putut opri jobul blocat.');
        }

        $this->clearImportStatsCache();

        return 'Job blocat oprit. Relansează importul dacă e nevoie.';
    }

    /** @param array<string, mixed> $params */
    private function fixIntegration(array $params): string
    {
        $entityType = trim((string) ($params['entity_type'] ?? ''));
        $entityId = trim((string) ($params['entity_id'] ?? ''));

        if ($entityId === '') {
            $resolved = $this->resolveBrokenEntity($entityType);
            $entityType = (string) ($resolved['entity_type'] ?? $entityType);
            $entityId = (string) ($resolved['entity_id'] ?? '');
        }

        if ($entityId === '') {
            throw new \RuntimeException('Lipsește entitatea pentru test conexiune — deschide furnizorul și rulează test manual.');
        }

        if ($entityType === 'furnizor') {
            $result = SupplierHooks::testConnection((int) $entityId, 'api');
            if ((string) ($result['last_test_status'] ?? '') !== 'success') {
                throw new \RuntimeException((string) ($result['last_test_message'] ?? 'Test conexiune furnizor eșuat.'));
            }

            return 'Conexiunea furnizorului funcționează — alerta dispare.';
        }

        if ($entityType === 'bot') {
            $controller = new Bots(new BotsService());
            $result = $controller->test(['randomn_id' => (int) $entityId]);
            if ((string) ($result['last_test_status'] ?? '') !== 'success') {
                throw new \RuntimeException((string) ($result['last_test_message'] ?? 'Test bot eșuat.'));
            }

            return 'Botul răspunde — alerta dispare.';
        }

        throw new \RuntimeException('Tip integrare necunoscut pentru reparare automată.');
    }

    /** @return array{entity_type: string, entity_id: string} */
    private function resolveBrokenEntity(string $preferredType = ''): array
    {
        $pdo = Database::getDB();
        $tryFurnizor = $preferredType === '' || $preferredType === 'furnizor';
        $tryBot = $preferredType === '' || $preferredType === 'bot';

        if ($tryFurnizor) {
            try {
                $row = $pdo->query(
                    "SELECT randomn_id FROM furnizori
                     WHERE status = 'active' AND LOWER(COALESCE(last_test_status, '')) = 'failed'
                     ORDER BY last_test_at DESC LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
                if (is_array($row) && (string) ($row['randomn_id'] ?? '') !== '') {
                    return ['entity_type' => 'furnizor', 'entity_id' => (string) $row['randomn_id']];
                }
            } catch (Throwable) {
                // optional
            }
        }

        if ($tryBot) {
            try {
                $row = $pdo->query(
                    "SELECT randomn_id FROM bots
                     WHERE LOWER(COALESCE(last_test_status, '')) = 'failed'
                     ORDER BY last_test_at DESC LIMIT 1"
                )->fetch(PDO::FETCH_ASSOC);
                if (is_array($row) && (string) ($row['randomn_id'] ?? '') !== '') {
                    return ['entity_type' => 'bot', 'entity_id' => (string) $row['randomn_id']];
                }
            } catch (Throwable) {
                // optional
            }
        }

        return ['entity_type' => '', 'entity_id' => ''];
    }

    /** @param list<string> $channels */
    private function resolveChannels(array $channels): void
    {
        try {
            require_once dirname(__DIR__, 3) . '/system/system_errors.php';
            $pdo = Database::getDB();
            foreach ($channels as $channel) {
                \system_errors_resolve_channel($pdo, $channel);
            }
        } catch (Throwable) {
            // optional
        }
    }

    private function clearImportStatsCache(): void
    {
        $cache = dirname(__DIR__, 3) . '/storage/cache/dashboard_import_stats.json';
        if (is_file($cache)) {
            @unlink($cache);
        }
    }

    private function defaultFixAction(string $code): string
    {
        return match ($code) {
            'tecdoc_unified', 'tecdoc_dead', 'tecdoc_api', 'tecdoc_ip_invalid' => 'refresh_tecdoc',
            'ai_api_error' => 'clear_ai_error',
            'import_failed' => 'dismiss_import_error',
            'job_blocked' => 'cancel_blocked_job',
            'link_broken' => 'test_integration',
            default => str_starts_with($code, 'system_error_') ? 'resolve_system_error' : '',
        };
    }

    /** @param array<string, mixed>|null $feed @return array<string, mixed> */
    private function result(bool $success, bool $fixed, string $message, ?array $feed = null): array
    {
        $out = [
            'success' => $success,
            'fixed' => $fixed,
            'message' => $message,
        ];
        if ($feed !== null) {
            $out['data'] = $feed;
        }

        return $out;
    }
}
