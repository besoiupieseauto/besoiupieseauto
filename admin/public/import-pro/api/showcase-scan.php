<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/ImportCardStaging.php';
require_once __DIR__ . '/lib/ImportShowcaseScanner.php';

@ini_set('memory_limit', '1024M');
@set_time_limit(600);

if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'POST obligatoriu'], 405);
}

import_motor_api_run(static function (): void {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    /** @var list<string> $types */
    $types = $data['types'] ?? [];
    if (!is_array($types) || $types === []) {
        require_once __DIR__ . '/lib/ImportShowcaseConfig.php';
        $types = ImportShowcaseConfig::scanTypes();
    }

    $types = array_values(array_filter(array_map(
        static fn ($t): string => mb_strtolower(trim((string) $t)),
        $types
    )));
    if ($types === []) {
        import_json_response(['success' => false, 'error' => 'Definește cel puțin un tip produs-vitrină'], 422);
    }

    /** @var list<array{supplier:string,filename:string}> $files */
    $files = $data['files'] ?? [];
    if (!is_array($files) || $files === []) {
        import_json_response(['success' => false, 'error' => 'Lipsește lista files (supplier, filename)'], 422);
    }

    $limit = max(1, min(200, (int) ($data['limit'] ?? 20)));
    $offset = max(0, (int) ($data['offset'] ?? 0));
    $minScore = max(50, min(100, (int) ($data['match_min_score'] ?? 72)));
    $perFile = !empty($data['per_file']);
    $useOllama = !array_key_exists('use_ollama', $data) || !empty($data['use_ollama']);
    $ollamaAvailable = import_ollama_available();

    $allCards = [];
    $fileReports = [];
    $startedAt = microtime(true);
    $scanLog = [];

    foreach ($files as $fileIndex => $file) {
        if (!is_array($file)) {
            $fileReports[] = [
                'supplier' => '',
                'filename' => '',
                'status' => 'skipped',
                'message' => 'Intrare fișier invalidă în listă',
            ];
            continue;
        }
        $supplier = strtolower(trim((string) ($file['supplier'] ?? '')));
        $filename = trim((string) ($file['filename'] ?? ''));
        if ($supplier === '' || $filename === '') {
            $fileReports[] = [
                'supplier' => $supplier,
                'filename' => $filename,
                'status' => 'skipped',
                'message' => 'Lipsește furnizor sau nume fișier',
            ];
            continue;
        }

        $fileStartedAt = microtime(true);
        try {
            import_motor_require_allowed_supplier($supplier);
        } catch (Throwable $e) {
            $msg = 'Furnizor nepermis: ' . $e->getMessage();
            $fileReports[] = [
                'supplier' => $supplier,
                'filename' => $filename,
                'status' => 'error',
                'message' => $msg,
                'duration_ms' => (int) round((microtime(true) - $fileStartedAt) * 1000),
            ];
            $scanLog[] = ['at' => date('c'), 'event' => 'supplier_rejected', 'supplier' => $supplier, 'filename' => $filename, 'message' => $msg];
            continue;
        }

        $path = import_resolve_stored_file($supplier, $filename);
        if ($path === null) {
            $msg = 'Fișier negăsit pe server (admin/storage/supplier_feeds/)';
            $fileReports[] = [
                'supplier' => $supplier,
                'filename' => $filename,
                'status' => 'error',
                'message' => $msg,
            ];
            $scanLog[] = ['at' => date('c'), 'event' => 'file_missing', 'supplier' => $supplier, 'filename' => $filename, 'message' => $msg];
            continue;
        }

        // Per fișier: caută până la $limit produse; legacy (per_file=false): total global
        $fileTarget = $perFile
            ? $limit
            : max(1, ($offset + $limit + 5) - count($allCards));

        try {
            [$cards, $scanReport] = ImportShowcaseScanner::scanFile(
                $path,
                $filename,
                $types,
                $minScore,
                $fileTarget,
                ImportShowcaseScanner::DEFAULT_MAX_ROWS,
                $useOllama
            );
        } catch (Throwable $e) {
            $msg = 'Eroare scan CSV: ' . $e->getMessage();
            $fileReports[] = [
                'supplier' => $supplier,
                'filename' => $filename,
                'status' => 'error',
                'message' => $msg,
                'path' => $supplier . '/' . $filename,
                'duration_ms' => (int) round((microtime(true) - $fileStartedAt) * 1000),
                'file_index' => $fileIndex,
            ];
            $scanLog[] = [
                'at' => date('c'),
                'event' => 'scan_exception',
                'supplier' => $supplier,
                'filename' => $filename,
                'message' => $msg,
                'error_class' => $e::class,
            ];
            continue;
        }

        $matchedCount = 0;
        foreach ($cards as $card) {
            if (!is_array($card)) {
                continue;
            }
            try {
                $card['sourceFile'] = $supplier . '/' . $filename;
                $card['sourceSupplier'] = $supplier;
                $allCards[] = import_motor_normalize_card_purchase_prices(
                    import_normalize_card_image_fields(
                        import_attach_local_image_to_card($card)
                    )
                );
                $matchedCount++;
            } catch (Throwable $e) {
                $scanLog[] = [
                    'at' => date('c'),
                    'event' => 'card_enrich_error',
                    'supplier' => $supplier,
                    'filename' => $filename,
                    'message' => 'Card omis: ' . $e->getMessage(),
                ];
            }
            if ($matchedCount >= $fileTarget) {
                break;
            }
        }

        $durationMs = (int) round((microtime(true) - $fileStartedAt) * 1000);
        $fileReports[] = array_merge($scanReport, [
            'supplier' => $supplier,
            'filename' => $filename,
            'status' => ($scanReport['status'] ?? 'done') === 'error' ? 'error' : 'done',
            'path' => $supplier . '/' . $filename,
            'cards_matched' => $matchedCount,
            'cards_added' => $matchedCount,
            'limit_per_file' => $perFile ? $limit : null,
            'duration_ms' => $durationMs,
            'file_index' => $fileIndex,
        ]);

        $scanLog[] = [
            'at' => date('c'),
            'event' => 'file_done',
            'supplier' => $supplier,
            'filename' => $filename,
            'scan_mode' => $scanReport['scan_mode'] ?? 'unknown',
            'rows_scanned' => $scanReport['rows_scanned'] ?? null,
            'cards_matched' => $matchedCount,
            'limit_per_file' => $perFile ? $limit : null,
            'duration_ms' => $durationMs,
            'message' => ($scanReport['message'] ?? '')
                . ($perFile ? ' · limită ' . $limit . '/fișier' : ''),
        ];

        if (!$perFile && count($allCards) >= ($offset + $limit)) {
            $scanLog[] = [
                'at' => date('c'),
                'event' => 'limit_reached',
                'message' => 'Limită totală atinsă — opresc procesarea fișierelor rămase',
                'cards_total' => count($allCards),
            ];
            break;
        }
    }

    $page = $perFile ? $allCards : array_slice($allCards, $offset, $limit);
    $withImageCount = count(array_filter(
        $page,
        static fn (array $c): bool => !empty($c['hasImage']) || !empty($c['imageDisplayUrl'])
    ));

    import_json_response([
        'success' => true,
        'count' => count($page),
        'offset' => $offset,
        'has_more' => !$perFile && count($allCards) > ($offset + count($page)),
        'next_offset' => $offset + count($page),
        'types' => $types,
        'cards' => $page,
        'file_reports' => $fileReports,
        'scan_log' => $scanLog,
        'progress' => [
            'files_processed' => count($fileReports),
            'files_requested' => count($files),
            'cards_total' => count($allCards),
            'cards_returned' => count($page),
            'cards_with_image' => $withImageCount,
            'cards_without_image' => count($page) - $withImageCount,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'min_score' => $minScore,
            'limit_per_file' => $perFile ? $limit : null,
            'ollama_available' => $ollamaAvailable,
            'ollama_requested' => $useOllama,
            'ollama_used' => $useOllama && $ollamaAvailable,
            'scan_engine' => ($useOllama && $ollamaAvailable) ? 'semantic_ollama_rag_v1' : 'semantic_rules_v1',
            'rag' => (static function (): array {
                require_once __DIR__ . '/lib/ImportShowcaseEpiesaRag.php';
                return ImportShowcaseEpiesaRag::stats();
            })(),
        ],
    ]);
}, 'showcase-scan.php');
