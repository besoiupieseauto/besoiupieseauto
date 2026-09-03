<?php
declare(strict_types=1);

/**
 * UI simplu browser — status + lookup + batch upload.
 * URL Laragon: http://besoiupieseauto.ro.test/admin/tools/matc/
 */

require_once __DIR__ . '/lib/MatcLookup.php';
require_once __DIR__ . '/lib/MatcBatchMatcher.php';

header('Content-Type: text/html; charset=utf-8');

$activeDb = matc_read_active_db();
$action = $_POST['action'] ?? $_GET['action'] ?? 'status';
$db = trim((string) ($_POST['db'] ?? $_GET['db'] ?? $activeDb));

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <title>Matc DB Tools</title>
    <style>
        body { font-family: Segoe UI, sans-serif; margin: 24px; max-width: 960px; }
        code, pre { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; }
        pre { padding: 12px; overflow: auto; }
        table { border-collapse: collapse; width: 100%; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 14px; }
        th { background: #eee; }
        .ok { color: #0a0; }
        .bad { color: #a00; }
        textarea { width: 100%; min-height: 160px; font-family: Consolas, monospace; }
        .tabs a { margin-right: 12px; }
    </style>
</head>
<body>
<h1>Matc DB — besoiu_tecdoc_matc</h1>
<p>Baza activa: <code><?= htmlspecialchars($activeDb, ENT_QUOTES, 'UTF-8') ?></code></p>

<div class="tabs">
    <a href="?action=status">Status</a>
    <a href="?action=lookup">Lookup</a>
    <a href="?action=batch">Batch match</a>
</div>

<hr>

<?php if ($action === 'lookup'): ?>
<h2>Lookup brand + cod</h2>
<form method="post">
    <input type="hidden" name="action" value="lookup">
    <p>
        <label>DB: <input name="db" value="<?= htmlspecialchars($db, ENT_QUOTES, 'UTF-8') ?>" size="40"></label>
    </p>
    <p>
        <label>Brand: <input name="brand" value="<?= htmlspecialchars((string) ($_POST['brand'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
        <label>Cod: <input name="code" value="<?= htmlspecialchars((string) ($_POST['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
    </p>
    <button type="submit">Cauta</button>
</form>
<?php
    if (!empty($_POST['brand']) && !empty($_POST['code'])) {
        $res = MatcLookup::find((string) $_POST['brand'], [(string) $_POST['code']], $db);
        echo '<pre>' . htmlspecialchars(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
?>

<?php elseif ($action === 'batch'): ?>
<h2>Batch match (mii de linii)</h2>
<p>Format: <code>BRAND;COD</code> cate o linie.</p>
<form method="post">
    <input type="hidden" name="action" value="batch">
    <p><label>DB: <input name="db" value="<?= htmlspecialchars($db, ENT_QUOTES, 'UTF-8') ?>" size="40"></label></p>
    <textarea name="lines" placeholder="DAYCO;KTBWP1230&#10;BOSCH;0451103316"><?= htmlspecialchars((string) ($_POST['lines'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    <p><button type="submit">Match batch</button></p>
</form>
<?php
    if (!empty($_POST['lines'])) {
        $parsed = [];
        $lineNo = 0;
        foreach (preg_split('/\R/', (string) $_POST['lines']) ?: [] as $line) {
            ++$lineNo;
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $brand = '';
            $code = '';
            if (str_contains($line, ';')) {
                [$brand, $code] = array_map('trim', explode(';', $line, 2));
            } else {
                $code = $line;
            }
            $parsed[] = ['brand' => $brand, 'code' => $code, 'line' => $lineNo];
        }
        $result = MatcBatchMatcher::match($parsed, $db);
        $matched = count(array_filter($result['results'], static fn($r) => !empty($r['matched'])));
        echo '<p>Total: ' . (int) $result['total'] . ', matched: ' . $matched . ', ' . $result['elapsed_ms'] . ' ms</p>';
        echo '<table><tr><th>Linie</th><th>Brand</th><th>Cod</th><th>Match</th><th>Denumire</th></tr>';
        foreach ($result['results'] as $row) {
            $cls = !empty($row['matched']) ? 'ok' : 'bad';
            echo '<tr><td>' . (int) $row['line'] . '</td><td>' . htmlspecialchars($row['brand'], ENT_QUOTES, 'UTF-8')
                . '</td><td>' . htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8')
                . '</td><td class="' . $cls . '">' . (!empty($row['matched']) ? 'DA' : 'NU')
                . '</td><td>' . htmlspecialchars($row['art_name'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        echo '</table>';
    }
?>

<?php else: ?>
<h2>Status baze Matc</h2>
<table>
    <tr><th>Baza</th><th>Produse</th><th>Coduri</th><th>Index lookup</th><th>Activ</th></tr>
    <?php foreach (matc_known_databases() as $name):
        if (!MatcDb::databaseExists($name)) {
            echo '<tr><td>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td><td colspan="4" class="bad">lipseste</td></tr>';
            continue;
        }
        $st = MatcDb::stats($name);
        if (empty($st['available'])) {
            echo '<tr><td>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td><td colspan="4" class="bad">' . htmlspecialchars($st['error'] ?? 'incompleta', ENT_QUOTES, 'UTF-8') . '</td></tr>';
            continue;
        }
        ?>
    <tr>
        <td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format((int) ($st['counts']['products'] ?? 0), 0, ',', '.') ?></td>
        <td><?= number_format((int) ($st['counts']['product_codes'] ?? 0), 0, ',', '.') ?></td>
        <td class="<?= !empty($st['lookup_index_ready']) ? 'ok' : 'bad' ?>"><?= !empty($st['lookup_index_ready']) ? 'OK' : 'LIPSA' ?></td>
        <td><?= $name === $activeDb ? '>>' : '' ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h3>Activare rapida</h3>
<form method="post" action="activate.php" style="display:none"></form>
<p>CLI recomandat:</p>
<pre>php admin/tools/matc/activate.php --db=besoiu_tecdoc_matc_20260826 --indexes
php admin/tools/matc/batch_match.php --file=coduri.txt --out=rezultat.csv</pre>
<?php endif; ?>

</body>
</html>
