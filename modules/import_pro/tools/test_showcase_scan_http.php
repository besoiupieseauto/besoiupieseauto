<?php
declare(strict_types=1);

/**
 * Simulează HTTP POST către showcase-scan.php și afișează răspunsul brut.
 */
$root = dirname(__DIR__, 3);
define('BESOIU_ROOT', $root);
define('BESOIU_API_MANUAL_CALL', true);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];

$supplier = $argv[1] ?? 'elit';
$filename = $argv[2] ?? 'Lista pret Elit 16.01.2026.csv';
$limit = (int) ($argv[3] ?? 5);

$body = json_encode([
    'files' => [['supplier' => $supplier, 'filename' => $filename]],
    'types' => ['ulei', 'lichid', 'adeziv', 'baterie'],
    'limit' => $limit,
    'offset' => 0,
    'match_min_score' => 72,
], JSON_UNESCAPED_UNICODE);

// php://input simulation via stream wrapper
stream_wrapper_unregister('php');
stream_wrapper_register('php', 'ShowcaseScanTestInputWrapper');
ShowcaseScanTestInputWrapper::$data = $body;

ob_start();
try {
    require $root . '/app/Import/MatchingPro/api/showcase-scan.php';
} catch (Throwable $e) {
    ob_end_clean();
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
$raw = ob_get_clean();

echo "=== RAW RESPONSE (first 500 chars) ===\n";
echo substr($raw, 0, 500) . (strlen($raw) > 500 ? "\n...[truncated]..." : "") . "\n\n";

$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    echo "JSON PARSE FAILED: " . json_last_error_msg() . "\n";
    echo "Full raw length: " . strlen($raw) . "\n";
    exit(2);
}

echo "success=" . ($decoded['success'] ? 'true' : 'false') . "\n";
echo "error=" . ($decoded['error'] ?? '(none)') . "\n";
echo "count=" . ($decoded['count'] ?? 0) . "\n";
if (!empty($decoded['file_reports'][0])) {
    echo "file_report: " . json_encode($decoded['file_reports'][0], JSON_UNESCAPED_UNICODE) . "\n";
}
exit(($decoded['success'] ?? false) ? 0 : 3);

final class ShowcaseScanTestInputWrapper
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
