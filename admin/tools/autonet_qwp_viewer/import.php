<?php
declare(strict_types=1);

/**
 * Import XLSX → autonet_qwp_data (ArtNr / ReferenceBrand / RefNr).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/QwpDb.php';
require_once __DIR__ . '/lib/AutonetQwpXlsxReader.php';

header('Content-Type: text/html; charset=utf-8');

const DEFAULT_XLSX = 'C:/Users/Radu/Desktop/Новая папка/Autonet QWP-update filtrare (1).xlsx';

$file = trim((string) ($_POST['file'] ?? $_GET['file'] ?? DEFAULT_XLSX));
$flash = '';
$error = '';
$stats = null;

function qwp_map_row(array $headers, array $row): ?array
{
    $map = [
        'artnr' => '',
        'brand' => '',
        'refnr' => '',
    ];

    $headerMap = [
        'a' => strtoupper(trim((string) ($headers['a'] ?? ''))),
        'b' => strtoupper(trim((string) ($headers['b'] ?? ''))),
        'c' => strtoupper(trim((string) ($headers['c'] ?? ''))),
    ];

    $values = [
        'a' => trim((string) ($row['a'] ?? '')),
        'b' => trim((string) ($row['b'] ?? '')),
        'c' => trim((string) ($row['c'] ?? '')),
    ];

    foreach ($headerMap as $col => $name) {
        if ($name === 'ARTNR') {
            $map['artnr'] = $values[$col];
        } elseif ($name === 'REFERENCEBRAND') {
            $map['brand'] = $values[$col];
        } elseif ($name === 'REFNR') {
            $map['refnr'] = $values[$col];
        }
    }

    // Fallback: A=ArtNr, B=ReferenceBrand, C=RefNr
    if ($map['artnr'] === '') {
        $map['artnr'] = $values['a'];
    }
    if ($map['brand'] === '') {
        $map['brand'] = $values['b'];
    }
    if ($map['refnr'] === '') {
        $map['refnr'] = $values['c'];
    }

    if ($map['artnr'] === '' || $map['refnr'] === '') {
        return null;
    }

    return $map;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_import'])) {
    try {
        if (!is_file($file)) {
            throw new RuntimeException('Fișierul XLSX nu există: ' . $file);
        }

        $payload = AutonetQwpXlsxReader::load($file);
        $dbRows = [];
        foreach ($payload['rows'] as $row) {
            $mapped = qwp_map_row($payload['headers'], $row);
            if ($mapped !== null) {
                $dbRows[] = $mapped;
            }
        }

        if ($dbRows === []) {
            throw new RuntimeException('Nu s-au găsit rânduri valide în fișier.');
        }

        $stats = QwpDb::importRows($dbRows);
        $flash = 'Import finalizat: ' . $stats['inserted'] . ' rânduri, ' . $stats['skipped'] . ' sărite.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Import Autonet QWP</title>
    <style>
        body { font-family: Segoe UI, sans-serif; margin: 24px; max-width: 900px; }
        input[type=text] { width: 100%; padding: 8px; }
        .ok { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 10px; border-radius: 8px; }
        .err { background: #fef2f2; border: 1px solid #fecaca; padding: 10px; border-radius: 8px; }
        button { padding: 8px 14px; background: #2563eb; color: #fff; border: 0; border-radius: 6px; cursor: pointer; }
        a { color: #2563eb; }
    </style>
</head>
<body>
<h1>Import XLSX → autonet_qwp_data</h1>
<p><a href="index.php">← Înapoi la editor</a></p>

<?php if ($flash !== ''): ?><div class="ok"><?= qwp_h($flash) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="err"><?= qwp_h($error) ?></div><?php endif; ?>

<form method="post">
    <p>
        <label>Cale fișier XLSX<br>
            <input type="text" name="file" value="<?= qwp_h($file) ?>">
        </label>
    </p>
    <p>Mapare coloane: <code>ArtNr</code>, <code>ReferenceBrand</code>, <code>RefNr</code> (din header sau A/B/C).</p>
    <button type="submit" name="run_import" value="1">Importă în baza de date</button>
</form>
</body>
</html>
