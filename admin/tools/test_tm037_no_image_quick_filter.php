<?php
declare(strict_types=1);

/**
 * Smoke test tm_037 — filtru rapid „Produse fără imagine” (lista admin).
 */
require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
require_once $admin . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($admin);
$dotenv->safeLoad();
$config = require $admin . '/config/config.php';
\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

use Evasystem\Controllers\Produse\ProduseService;

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

$ok = static function (string $msg): void {
    echo "OK: {$msg}\n";
};

$service = new ProduseService();
$count = $service->countOnlineWithoutImage();
if ($count < 0) {
    $fail('countOnlineWithoutImage a returnat valoare invalida.');
}
$ok('countOnlineWithoutImage = ' . $count);

$paged = $service->getProdusesPaginated(1, 50, 'no_image');
$items = $paged['items'] ?? [];
$total = (int) ($paged['total'] ?? 0);
if ($total !== $count) {
    $fail('Total paginat no_image (' . $total . ') difera de countOnlineWithoutImage (' . $count . ').');
}
$ok('Paginare no_image total = ' . $total);

foreach ($items as $row) {
    $status = (string) ($row['status'] ?? '');
    if ($status === '0') {
        $fail('Produs offline in filtrul no_image: ' . ($row['randomn_id'] ?? $row['id'] ?? '?'));
    }
    $images = trim((string) ($row['pImages'] ?? ''));
    if ($images !== '' && !in_array($images, ['[]', 'null'], true)) {
        $fail('Produs cu imagine in filtrul no_image: ' . ($row['randomn_id'] ?? $row['id'] ?? '?'));
    }
}
$ok('Toate itemele din pagina 1 no_image sunt online si fara pImages');

$viewPath = $admin . '/Templates/admin/pages/produse/produse.php';
$html = (string) file_get_contents($viewPath);
foreach (['id="quickFilterNoImage"', 'filter=no_image', 'noImageFilterBanner', 'countOnlineWithoutImage'] as $needle) {
    if (!str_contains($html, $needle)) {
        $fail('Marker lipsa in produse.php: ' . $needle);
    }
}
$ok('Markup filtru rapid present in produse.php');

$headers = @get_headers('http://besoiupieseauto.ro.test/admin/product?filter=no_image&page=1', true);
$statusLine = is_array($headers) ? (string) ($headers[0] ?? '') : '';
if ($statusLine === '' || !preg_match('/HTTP\/\d\.\d\s(200|301|302)/', $statusLine)) {
    $fail('URL admin cu filter=no_image nu raspunde: ' . ($statusLine ?: 'fara raspuns'));
}
$ok('HTTP admin/product?filter=no_image — ' . trim($statusLine));

echo "TM037_NO_IMAGE_QUICK_FILTER_OK\n";
