<?php
declare(strict_types=1);

/**
 * Creare job asincron — răspunde imediat 202 Accepted.
 * POST /admin/api/jobs_endpoint.php
 * Body JSON: { type, queue, payload, priority?, idempotency_key?, timeout_sec? }
 */

require_once __DIR__ . '/_autoload.php';

use Besoiu\Async\JobService;
use Besoiu\Core\Bootstrap\ApiBootstrap;

$config = ApiBootstrap::bootJsonApi(true);
ApiBootstrap::requireAuthenticatedSession();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Doar POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($input)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'JSON invalid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cancelAction = trim((string) ($input['action'] ?? ''));
if ($cancelAction === 'cancel') {
    $jobId = trim((string) ($input['job_id'] ?? ''));
    if ($jobId === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'job_id obligatoriu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $store = new \Besoiu\Async\JobStore();
    $store->requestCancel($jobId);
    $job = $store->get($jobId);
    $legacyJobId = is_array($job) ? trim((string) ($job['legacy_job_id'] ?? '')) : '';
    if ($legacyJobId !== '') {
        $lib = dirname(__DIR__, 2) . '/src/Controllers/Produse/import_job_lib.php';
        if (is_file($lib)) {
            require_once $lib;
            if (function_exists('import_job_cancel')) {
                import_job_cancel($legacyJobId);
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Anulare solicitată.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = trim((string) ($input['type'] ?? ''));
$queue = trim((string) ($input['queue'] ?? ''));
$payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
$priority = trim((string) ($input['priority'] ?? 'normal'));
$idempotencyKey = trim((string) ($input['idempotency_key'] ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '')));
$timeoutSec = isset($input['timeout_sec']) ? (int) $input['timeout_sec'] : null;

if ($type === '' || $queue === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'type și queue sunt obligatorii.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($priority, ['high', 'normal', 'low'], true)) {
    $priority = 'normal';
}

try {
    $service = new JobService();
    $created = $service->create($type, $queue, $payload, $priority, $idempotencyKey !== '' ? $idempotencyKey : null, $timeoutSec);

    http_response_code($created['replayed'] ? 200 : 202);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'job_id' => $created['job_id'],
        'status' => $created['status'],
        'replayed' => $created['replayed'],
        'stream_url' => '/admin/api/jobs_sse_endpoint.php?job_id=' . rawurlencode($created['job_id']),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
