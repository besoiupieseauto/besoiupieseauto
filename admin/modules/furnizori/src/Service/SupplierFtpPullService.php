<?php
declare(strict_types=1);

namespace Besoiu\Modules\Furnizori\Service;

use Besoiu\Modules\Furnizori\Model\FurnizoriRepository;

/**
 * Job automat: trage listele FTP/SFTP in admin/storage/supplier_feeds/{cod}/.
 */
class SupplierFtpPullService
{
    private const STATE_FILE = '/storage/supplier_sync_agent_state.json';

    /**
     * @return array{
     *   success:bool,
     *   message:string,
     *   pulled:int,
     *   skipped:int,
     *   failed:int,
     *   suppliers:array<int,array<string,mixed>>
     * }
     */
    public function run(bool $force = false, string $onlyCode = ''): array
    {
        $onlyCode = $this->normalizeCode($onlyCode);
        $repo = new FurnizoriRepository();
        $sync = new FurnizoriRemoteSyncService();
        $agentState = $this->loadAgentState();

        $rows = [];
        $failed = 0;
        $pulled = 0;
        $skippedSchedule = 0;

        foreach ($repo->findAll() as $furnizor) {
            if (!is_array($furnizor)) {
                continue;
            }

            $code = $this->normalizeCode((string) ($furnizor['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            if ($onlyCode !== '' && $code !== $onlyCode) {
                continue;
            }

            $status = strtolower(trim((string) ($furnizor['status'] ?? 'active')));
            if ($status === 'blocked' || $status === 'inactive') {
                continue;
            }

            $connection = strtolower(trim((string) ($furnizor['connection_type'] ?? '')));
            if (!in_array($connection, ['ftp', 'sftp'], true)) {
                continue;
            }

            $host = trim((string) ($furnizor['conn_host'] ?? ''));
            $accessOurs = FtpAccessMode::isOurs($furnizor);
            if ($host === '' && !$accessOurs) {
                $rows[] = [
                    'code' => $code,
                    'status' => 'skip',
                    'message' => 'Host FTP/SFTP lipsa in profil (IP-ul de la furnizor).',
                ];
                continue;
            }

            $scheduleRow = $furnizor;
            unset($scheduleRow['last_scan_at']);
            if (!$force && !SupplierScanScheduleService::shouldRunAuto($scheduleRow, $agentState[$code] ?? [])) {
                ++$skippedSchedule;
                $rows[] = [
                    'code' => $code,
                    'status' => 'wait',
                    'message' => SupplierScanScheduleService::formatNextRunLabel($scheduleRow, $agentState[$code] ?? []),
                ];
                continue;
            }

            $result = $sync->syncFromFtp($furnizor);
            $ok = !empty($result['success']);
            if ($ok) {
                ++$pulled;
            } else {
                ++$failed;
            }

            $files = is_array($result['files'] ?? null) ? $result['files'] : [];
            $rows[] = [
                'code' => $code,
                'status' => $ok ? 'ok' : 'error',
                'message' => (string) ($result['message'] ?? ''),
                'files' => count($files),
                'folder' => (string) ($result['folder'] ?? ''),
            ];
        }

        $message = 'FTP/SFTP pull: ' . $pulled . ' furnizor(i) sincronizat(i)';
        if ($skippedSchedule > 0) {
            $message .= ', ' . $skippedSchedule . ' in asteptare (program)';
        }
        if ($failed > 0) {
            $message .= ', ' . $failed . ' eroare/erori';
        }
        if ($rows === []) {
            $message = $onlyCode !== ''
                ? 'Niciun furnizor FTP/SFTP cu codul ' . $onlyCode . '.'
                : 'Niciun furnizor FTP/SFTP activ de descarcat.';
        }

        return [
            'success' => $failed === 0,
            'message' => $message,
            'pulled' => $pulled,
            'skipped' => $skippedSchedule,
            'failed' => $failed,
            'suppliers' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private function loadAgentState(): array
    {
        $path = (defined('BESOIU_ADMIN') ? BESOIU_ADMIN : dirname(__DIR__, 5) . '/admin') . self::STATE_FILE;
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeCode(string $value): string
    {
        $value = trim($value);

        return $value === ''
            ? ''
            : (function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value));
    }
}
