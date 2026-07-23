<?php

declare(strict_types=1);

$erpRoot = dirname(__DIR__, 4);
require_once $erpRoot . '/app/Import/bootstrap.php';
require_once $erpRoot . '/app/Import/MatchingPro/api/bootstrap.php';
require_once $erpRoot . '/app/Import/MatchingPro/api/lib/ImportScrapedImageStore.php';

$relativePath = trim((string) ($_GET['path'] ?? ''));
$absolutePath = ImportScrapedImageStore::resolveAbsolutePath($relativePath);
if ($absolutePath === null) {
    http_response_code(404);
    exit('Imagine negasita');
}

$ext = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    default => 'image/jpeg',
};

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($absolutePath);
