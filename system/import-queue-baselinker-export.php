<?php
declare(strict_types=1);

/**
 * tm_107 — Export produse validate din coada import către BaseLinker via API.
 */

require_once __DIR__ . '/import-queue-export.php';

use Evasystem\Controllers\Marketplace\MarketplaceService;
use Evasystem\Core\Marketplace\MarketplaceModel;

/** @param array<string, mixed> $row @return array<string, mixed> */
function import_queue_baselinker_prepare_row(array $row): array
{
    $images = import_queue_export_all_images($row);
    if ($images !== []) {
        $row['pImages'] = json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (trim((string) ($row['pNoteMarketplace'] ?? '')) === '') {
        $note = trim((string) ($row['pNoteWebsite'] ?? ''));
        if ($note === '') {
            $note = import_queue_export_autopro_plain_text((string) ($row['pNote'] ?? ''));
        }
        if ($note !== '') {
            $row['pNoteMarketplace'] = $note;
        }
    }

    return $row;
}

/**
 * @param array<int, int> $ids
 * @return array{ok:bool,status:string,message:string,sent:int,errors:int,error_details:array<int,string>}
 */
function import_queue_baselinker_export(PDO $pdo, string $supplier = '', array $ids = []): array
{
    $validatedRows = import_queue_export_fetch_validated_rows($pdo, $supplier, $ids);
    if ($validatedRows === []) {
        return [
            'ok' => false,
            'status' => 'skipped',
            'message' => 'Nu există produse validate în coadă pentru export BaseLinker (categorie, brand, preț > 0, imagine).',
            'sent' => 0,
            'errors' => 0,
            'error_details' => [],
        ];
    }

    $prepared = [];
    foreach ($validatedRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $prepared[] = import_queue_baselinker_prepare_row($row);
    }

    $service = new MarketplaceService(new MarketplaceModel());
    $result = $service->syncImportQueueRowsToBaseLinker($prepared);

    $sent = (int) ($result['sent'] ?? 0);
    $errors = (int) ($result['errors'] ?? 0);
    $status = (string) ($result['status'] ?? 'failed');

    return [
        'ok' => $status !== 'failed' || $sent > 0,
        'status' => $status,
        'message' => (string) ($result['message'] ?? ''),
        'sent' => $sent,
        'errors' => $errors,
        'error_details' => is_array($result['error_details'] ?? null) ? $result['error_details'] : [],
    ];
}
