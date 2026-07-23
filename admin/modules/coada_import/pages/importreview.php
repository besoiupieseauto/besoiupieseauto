<?php
declare(strict_types=1);

$view = trim((string) ($_GET['view'] ?? 'queue'));
if ($view === 'normalize') {
    require __DIR__ . '/views/importreview-normalize.php';
    return;
}

require __DIR__ . '/views/importreview.php';

