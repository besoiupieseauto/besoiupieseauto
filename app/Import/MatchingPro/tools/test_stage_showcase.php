<?php
declare(strict_types=1);

/**
 * CLI: test stage-cards pentru vitrină.
 * Usage: php tools/test_stage_showcase.php [count]
 */
$root = dirname(__DIR__);
define('BESOIU_ROOT', dirname($root, 3));
define('BESOIU_API_MANUAL_CALL', true);

$_SERVER['REQUEST_METHOD'] = 'POST';

$t0 = microtime(true);
require_once BESOIU_ROOT . '/app/Import/bootstrap.php';
\Besoiu\Import\Support\ImportPathResolver::applyEnv();
require_once $root . '/api/bootstrap.php';
require_once $root . '/api/lib/ImportShowcaseScanner.php';

$count = max(1, min(5, (int) ($argv[1] ?? 3)));

$path = import_resolve_stored_file('autonet', 'Lista pret Autonet 27.01.2026.csv');
if ($path === null) {
    fwrite(STDERR, "CSV negăsit\n");
    exit(1);
}

[$cards] = ImportShowcaseScanner::scanFile($path, basename($path), ['ulei'], 72, $count, 80000, false);
if ($cards === []) {
    fwrite(STDERR, "Niciun card vitrină\n");
    exit(2);
}

$cards = array_slice($cards, 0, $count);
$body = json_encode(['cards' => $cards, 'import_lane' => 'showcase'], JSON_UNESCAPED_UNICODE);

stream_wrapper_unregister('php');
stream_wrapper_register('php', 'StageShowcaseTestInputWrapper');
StageShowcaseTestInputWrapper::$data = $body;

$tStage = microtime(true);
ob_start();
try {
    require $root . '/api/stage-cards.php';
} catch (Throwable $e) {
    ob_end_clean();
    fwrite(STDERR, "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(3);
}
$raw = ob_get_clean();
$tEnd = microtime(true);
echo 'stage_api: ' . round($tEnd - $tStage, 2) . 's total: ' . round($tEnd - $t0, 2) . 's' . PHP_EOL;

echo substr($raw, 0, 2000) . (strlen($raw) > 2000 ? "\n...[truncated]..." : "") . "\n";
$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    echo "JSON FAIL: " . json_last_error_msg() . "\n";
    exit(4);
}
echo "success=" . ($decoded['success'] ? 'true' : 'false') . " error=" . ($decoded['error'] ?? $decoded['message'] ?? '-') . "\n";
exit(($decoded['success'] ?? false) ? 0 : 5);

final class StageShowcaseTestInputWrapper
{
    public static string $data = '';
    private int $pos = 0;

    /** @var resource */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        if ($path !== 'php://input') {
            return false;
        }
        $this->pos = 0;
        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$data, $this->pos, $count);
        $this->pos += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->pos >= strlen(self::$data);
    }

    public function stream_stat(): array
    {
        return [];
    }
}
