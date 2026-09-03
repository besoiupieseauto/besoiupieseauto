<?php
declare(strict_types=1);

/**
 * Viewer Autonet QWP — coloane A, B, C din XLSX, 50 rânduri/pagină.
 * URL Laragon: http://besoiupieseauto.ro.test/admin/tools/autonet_qwp_viewer/
 */

require_once __DIR__ . '/lib/AutonetQwpXlsxReader.php';

header('Content-Type: text/html; charset=utf-8');

const DEFAULT_XLSX = 'C:/Users/Radu/Desktop/Новая папка/Autonet QWP-update filtrare (1).xlsx';
const PER_PAGE = 50;

$xlsxPath = trim((string) ($_GET['file'] ?? DEFAULT_XLSX));
$refresh = isset($_GET['refresh']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$q = trim((string) ($_GET['q'] ?? ''));

$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/rows_' . md5($xlsxPath) . '.json';
$error = '';
$headers = ['a' => 'Coloana A', 'b' => 'Coloana B', 'c' => 'Coloana C'];
$sourceMtime = is_file($xlsxPath) ? (int) filemtime($xlsxPath) : 0;

/** @var list<array{row:int,a:string,b:string,c:string}> $allRows */
$allRows = [];

function loadData(string $xlsxPath, string $cacheFile, int $sourceMtime, bool $refresh): array
{
    if (
        !$refresh
        && is_file($cacheFile)
        && (int) filemtime($cacheFile) >= $sourceMtime
    ) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['rows']) && is_array($cached['rows'])) {
            return [
                'headers' => is_array($cached['headers'] ?? null)
                    ? $cached['headers']
                    : ['a' => 'Coloana A', 'b' => 'Coloana B', 'c' => 'Coloana C'],
                'rows' => $cached['rows'],
            ];
        }
    }

    $payload = AutonetQwpXlsxReader::load($xlsxPath);
    if (($payload['rows'] ?? []) !== []) {
        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0775, true);
        }
        file_put_contents(
            $cacheFile,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    return $payload;
}

function filterRows(array $rows, string $q): array
{
    if ($q === '') {
        return $rows;
    }

    $needle = mb_strtolower($q, 'UTF-8');
    return array_values(array_filter(
        $rows,
        static fn(array $row): bool => str_contains(mb_strtolower($row['a'], 'UTF-8'), $needle)
            || str_contains(mb_strtolower($row['b'], 'UTF-8'), $needle)
            || str_contains(mb_strtolower($row['c'], 'UTF-8'), $needle)
    ));
}

function buildQuery(array $params): string
{
    return http_build_query(array_filter($params, static fn($v) => $v !== '' && $v !== null));
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (!is_file($xlsxPath)) {
    $error = 'Fișierul XLSX nu există sau nu poate fi citit: ' . $xlsxPath;
} else {
    $payload = loadData($xlsxPath, $cacheFile, $sourceMtime, $refresh);
    $headers = is_array($payload['headers'] ?? null) ? $payload['headers'] : $headers;
    $allRows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
    if ($allRows === []) {
        $error = 'Nu s-au putut extrage rânduri din fișier. Verifică extensia zip PHP sau fallback PowerShell.';
    }
}

$filteredRows = $error === '' ? filterRows($allRows, $q) : [];
$totalRows = count($filteredRows);
$totalPages = max(1, (int) ceil($totalRows / PER_PAGE));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * PER_PAGE;
$pageRows = array_slice($filteredRows, $offset, PER_PAGE);

$baseParams = ['file' => $xlsxPath];
if ($q !== '') {
    $baseParams['q'] = $q;
}

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autonet QWP — mapare A/B/C</title>
    <style>
        :root {
            --bg: #f6f8fb;
            --card: #fff;
            --line: #d8dee9;
            --text: #1f2937;
            --muted: #6b7280;
            --accent: #2563eb;
            --accent-soft: #dbeafe;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { margin: 0 0 8px; font-size: 1.5rem; }
        .sub { color: var(--muted); margin-bottom: 20px; font-size: 14px; }
        .panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .panel form { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; }
        label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: var(--muted); }
        input[type="text"] {
            min-width: 280px;
            padding: 8px 10px;
            border: 1px solid var(--line);
            border-radius: 6px;
            font-size: 14px;
        }
        button, .btn {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        button.primary, .btn.primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
        .stats { display: flex; flex-wrap: wrap; gap: 16px; font-size: 14px; }
        .stats strong { color: var(--text); }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            border-bottom: 1px solid var(--line);
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }
        th { background: #eef2ff; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        td.num { color: var(--muted); width: 70px; white-space: nowrap; }
        td.cell { word-break: break-word; max-width: 360px; }
        .pager {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            margin: 16px 0;
        }
        .pager a, .pager span {
            min-width: 36px;
            text-align: center;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid var(--line);
            text-decoration: none;
            color: var(--text);
            font-size: 13px;
            background: #fff;
        }
        .pager a:hover { background: var(--accent-soft); border-color: #93c5fd; }
        .pager .active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            font-weight: 600;
        }
        .pager .disabled {
            opacity: .45;
            pointer-events: none;
        }
        .pager-info { margin-left: auto; color: var(--muted); font-size: 13px; }
        @media (max-width: 760px) {
            input[type="text"] { min-width: 100%; width: 100%; }
            .pager-info { width: 100%; margin-left: 0; margin-top: 8px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Autonet QWP — mapare coloane A / B / C</h1>
    <p class="sub">Citește integral fișierul XLSX și afișează datele paginat (<?= PER_PAGE ?> rânduri/pagină).</p>

    <div class="panel">
        <form method="get">
            <label>
                Cale fișier XLSX
                <input type="text" name="file" value="<?= h($xlsxPath) ?>" size="70">
            </label>
            <label>
                Căutare (A, B sau C)
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="filtru text...">
            </label>
            <button type="submit" class="primary">Încarcă</button>
            <a class="btn" href="?<?= h(buildQuery($baseParams + ['refresh' => 1, 'page' => 1])) ?>">Reîmprospătează cache</a>
        </form>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php else: ?>
        <div class="panel stats">
            <div>Total rânduri date: <strong><?= number_format(count($allRows), 0, ',', '.') ?></strong></div>
            <div>După filtru: <strong><?= number_format($totalRows, 0, ',', '.') ?></strong></div>
            <div>Pagina: <strong><?= $page ?></strong> / <?= $totalPages ?></div>
            <div>Ultima modificare fișier: <strong><?= $sourceMtime ? date('d.m.Y H:i', $sourceMtime) : '—' ?></strong></div>
        </div>

        <?php
        $window = 5;
        $start = max(1, $page - $window);
        $end = min($totalPages, $page + $window);
        ?>
        <nav class="pager" aria-label="Paginare">
            <?php
            $prevParams = $baseParams + ['page' => max(1, $page - 1)];
            $nextParams = $baseParams + ['page' => min($totalPages, $page + 1)];
            ?>
            <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="?<?= h(buildQuery($prevParams)) ?>">&laquo; Prev</a>

            <?php if ($start > 1): ?>
                <a href="?<?= h(buildQuery($baseParams + ['page' => 1])) ?>">1</a>
                <?php if ($start > 2): ?><span>…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="active"><?= $p ?></span>
                <?php else: ?>
                    <a href="?<?= h(buildQuery($baseParams + ['page' => $p])) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><span>…</span><?php endif; ?>
                <a href="?<?= h(buildQuery($baseParams + ['page' => $totalPages])) ?>"><?= $totalPages ?></a>
            <?php endif; ?>

            <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="?<?= h(buildQuery($nextParams)) ?>">Next &raquo;</a>
            <div class="pager-info">
                Afișez rândurile <?= $totalRows ? ($offset + 1) : 0 ?>–<?= min($offset + PER_PAGE, $totalRows) ?> din <?= number_format($totalRows, 0, ',', '.') ?>
            </div>
        </nav>

        <table>
            <thead>
            <tr>
                <th># Excel</th>
                <th><?= h((string) ($headers['a'] ?? 'Coloana A')) ?></th>
                <th><?= h((string) ($headers['b'] ?? 'Coloana B')) ?></th>
                <th><?= h((string) ($headers['c'] ?? 'Coloana C')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($pageRows === []): ?>
                <tr><td colspan="4">Niciun rând pentru filtrul curent.</td></tr>
            <?php else: ?>
                <?php foreach ($pageRows as $row): ?>
                    <tr>
                        <td class="num"><?= (int) $row['row'] ?></td>
                        <td class="cell"><?= h($row['a']) ?></td>
                        <td class="cell"><?= h($row['b']) ?></td>
                        <td class="cell"><?= h($row['c']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <nav class="pager" aria-label="Paginare jos">
            <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="?<?= h(buildQuery($prevParams)) ?>">&laquo; Prev</a>
            <?php for ($p = $start; $p <= $end; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="active"><?= $p ?></span>
                <?php else: ?>
                    <a href="?<?= h(buildQuery($baseParams + ['page' => $p])) ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="?<?= h(buildQuery($nextParams)) ?>">Next &raquo;</a>
        </nav>
    <?php endif; ?>
</div>
</body>
</html>
