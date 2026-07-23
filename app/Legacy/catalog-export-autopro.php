<?php

declare(strict_types=1);

/**
 * tm_105 — Export CSV format Piese Autopro din catalog produse active.
 */

require_once __DIR__ . '/import-queue-export.php';

function catalog_export_autopro_count(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM produse WHERE status <> :inactive');
    $stmt->execute([':inactive' => '0']);

    return (int) $stmt->fetchColumn();
}

/** @return array<int, array<string, mixed>> */
function catalog_export_autopro_fetch_rows(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM produse WHERE status <> :inactive ORDER BY id ASC'
    );
    $stmt->execute([':inactive' => '0']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

function catalog_export_autopro_filename(): string
{
    return 'produse_piese_autopro_' . date('Y-m-d_His') . '.csv';
}

function catalog_export_autopro_csv_content(PDO $pdo): string
{
    return import_queue_export_autopro_csv_content(catalog_export_autopro_fetch_rows($pdo));
}
