<?php

declare(strict_types=1);



require_once __DIR__ . '/bootstrap.php';



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(204);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    import_json_response(['success' => false, 'error' => 'Metoda POST obligatorie'], 405);

}



$raw = file_get_contents('php://input');

$input = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

if (!is_array($input)) {

    $input = $_POST;

}



$supplier = trim((string) ($input['supplier'] ?? ''));

$headers = $input['headers'] ?? [];

$currentColumns = $input['current_columns'] ?? [];

$sampleRows = $input['sample_rows'] ?? [];

$rowsInspected = $input['rows_inspected'] ?? [];

$normalizationIssues = $input['normalization_issues'] ?? [];

$normalizationRules = trim((string) ($input['normalization_rules'] ?? ''));

$userNote = trim((string) ($input['user_note'] ?? ''));

$filename = trim((string) ($input['filename'] ?? ''));

$extraCodes = $input['extra_codes'] ?? [];



if ($supplier === '' || !is_array($headers) || $headers === []) {

    import_json_response(['success' => false, 'error' => 'Lipsesc supplier sau headers'], 400);

}



$headerList = array_values(array_map(static fn ($h) => (string) $h, $headers));

$headersLine = implode(', ', $headerList);

$columnsJson = json_encode($currentColumns, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';

$sampleJson = json_encode(array_slice(is_array($sampleRows) ? $sampleRows : [], 0, 5), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]';

$extraCodesJson = json_encode(is_array($extraCodes) ? $extraCodes : [], JSON_UNESCAPED_UNICODE) ?: '[]';



$rowsForPrompt = [];

if (is_array($rowsInspected)) {

    foreach (array_slice($rowsInspected, 0, 8) as $row) {

        if (!is_array($row)) {

            continue;

        }

        $n = is_array($row['normalized'] ?? null) ? $row['normalized'] : [];

        $m = is_array($row['match'] ?? null) ? $row['match'] : [];

        $mp = is_array($m['matched_product'] ?? null) ? $m['matched_product'] : null;

        $rowsForPrompt[] = [

            'row_num' => $row['row_num'] ?? null,

            'sku_supplier' => $n['sku_supplier'] ?? '',

            'brand' => $n['brand'] ?? '',

            'code_variants' => $n['code_variants'] ?? ($n['codes'] ?? []),

            'match_status' => $m['status'] ?? 'no_match',

            'match_method' => $m['method'] ?? null,

            'tecdoc_brand' => $mp['brand'] ?? null,

            'tecdoc_name' => isset($mp['name']) ? mb_substr((string) $mp['name'], 0, 60) : null,

        ];

    }

}

$rowsJson = json_encode($rowsForPrompt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]';



$issuesForPrompt = [];

if (is_array($normalizationIssues)) {

    foreach (array_slice($normalizationIssues, 0, 12) as $issue) {

        if (!is_array($issue)) {

            continue;

        }

        $issuesForPrompt[] = [

            'row_num' => $issue['row_num'] ?? 0,

            'severity' => $issue['severity'] ?? 'info',

            'type' => $issue['type'] ?? '',

            'message_ro' => $issue['message_ro'] ?? '',

        ];

    }

}

$issuesJson = json_encode($issuesForPrompt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]';



if ($normalizationRules === '') {

    $normalizationRules = "normalizeCode: UPPER, elimină spații - / _ ., apoi non-alfanumeric.\n"

        . "Autonet: prefix/sufix brand 3 litere + brand+cod.\n"

        . "Autototal: ART_ARTICLE_NR + CODE_ECHIV.\n"

        . "Materom: COD NPF + EAN. Intercars: P2 Code + Active No. Autopartner: INDEX TECDOC + INDEX AUTOPARTNER.";

}



$prompt = "Ești expert în import CSV piese auto (România) și matching TecDoc MySQL.\n\n";

$prompt .= "Furnizor: {$supplier}\n";

$prompt .= "Fișier: {$filename}\n\n";

$prompt .= "Antet CSV (coloane în ordine):\n{$headersLine}\n\n";

$prompt .= "Mapping curent (field → alias coloane):\n{$columnsJson}\n\n";

$prompt .= "Coduri extra configurate: {$extraCodesJson}\n\n";

$prompt .= "Reguli normalizare OEM (obligatorii):\n{$normalizationRules}\n\n";

$prompt .= "Rânduri inspectate (normalizare + match TecDoc):\n{$rowsJson}\n\n";

$prompt .= "Probleme detectate automat (heuristic):\n{$issuesJson}\n\n";

$prompt .= "Primele rânduri brute CSV:\n{$sampleJson}\n\n";



if ($userNote !== '') {

    $prompt .= "Problemă raportată de utilizator:\n{$userNote}\n\n";

}



$prompt .= <<<PROMPT

Sarcină combinată:

1) Propune mapping corect: sku, ean, name, price, stock, brand — alias-uri care EXISTĂ în antet.

2) Analizează erori de normalizare OEM și matching TecDoc: mapping greșit, coduri cu separatori, prefix/sufix brand lipsă, CODE_ECHIV, brand alias.

3) Sugerează extra_codes (coloane suplimentare pentru variante cod) dacă lipsesc.



Răspunde DOAR JSON valid (fără markdown):

{

  "columns": {

    "sku": ["..."],

    "ean": ["..."],

    "name": ["..."],

    "price": ["..."],

    "stock": ["..."],

    "brand": ["..."]

  },

  "extra_codes": ["coloane extra pentru coduri OEM"],

  "explanation_ro": "explicație scurtă mapping",

  "warnings": ["probleme generale"],

  "normalization": {

    "summary_ro": "rezumat analiză normalizare",

    "issues": [

      {

        "row_num": 0,

        "severity": "error|warn|info",

        "type": "wrong_column|brand_alias|missing_variant|no_match|mapping",

        "message_ro": "descriere problemă",

        "suggestion_ro": "ce să facă utilizatorul",

        "try_codes": ["variante cod suplimentare de încercat"]

      }

    ],

    "brand_alias_suggest": {"QWP": "QWP", "MARELLI": "MAGNETIMARELLI"},

    "confidence": 0.85

  }

}

PROMPT;



try {

    $ollama = import_ollama_chat_json($prompt, import_ollama_timeout_sec());

} catch (Throwable $e) {

    import_json_response([

        'success' => false,

        'error' => 'Ollama: ' . $e->getMessage(),

        'ollama_available' => import_ollama_available(),

        'ollama_model' => import_ollama_model(),

    ], 503);

}



import_json_response([

    'success' => true,

    'suggestion' => $ollama,

    'ollama_model' => import_ollama_model(),

    'ollama_available' => true,

]);

