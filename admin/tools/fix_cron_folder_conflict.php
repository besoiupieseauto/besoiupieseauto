<?php

declare(strict_types=1);

/**
 * Elimină folderul fizic admin/cron/ care blochează URL-ul /admin/cron.
 * Usage: php admin/tools/fix_cron_folder_conflict.php
 */

$adminRoot = dirname(__DIR__);
$cronDir = $adminRoot . DIRECTORY_SEPARATOR . 'cron';
$cronCli = $adminRoot . DIRECTORY_SEPARATOR . 'cron_cli';

echo "=== fix_cron_folder_conflict ===\n";

if (!is_dir($cronCli)) {
    fwrite(STDERR, "WARN: admin/cron_cli/ lipsește — scripturile CLI trebuie în cron_cli/\n");
}

if (!is_dir($cronDir)) {
    echo "OK  admin/cron/ nu există — nimic de șters.\n";
    exit(0);
}

$removed = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cronDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
    if ($item->isDir()) {
        @rmdir($item->getPathname());
    } else {
        if (@unlink($item->getPathname())) {
            $removed++;
        }
    }
}
@rmdir($cronDir);

if (is_dir($cronDir)) {
    fwrite(STDERR, "FAIL: nu am putut șterge complet {$cronDir}\n");
    exit(1);
}

echo "OK  Șters folder admin/cron/ ({$removed} fișiere). Scripturile CLI sunt în admin/cron_cli/\n";
echo "OK  Rulați: php admin/migrations/run_047_cron_clean_route.php\n";
echo "OK  Verificare: php admin/tools/diagnose_cron_page.php\n";
