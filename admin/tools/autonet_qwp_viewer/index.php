<?php
declare(strict_types=1);

/**
 * Editor simplu autonet_qwp_data — listare + editare + paginare 50.
 * URL: http://besoiupieseauto.ro.test/admin/tools/autonet_qwp_viewer/
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/QwpDb.php';

header('Content-Type: text/html; charset=utf-8');

const PER_PAGE = 50;

$page = max(1, (int) ($_GET['page'] ?? 1));
$q = trim((string) ($_GET['q'] ?? ''));
$flash = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'update') {
            QwpDb::updateRow(
                (int) ($_POST['id'] ?? 0),
                (string) ($_POST['ArtNr'] ?? ''),
                (string) ($_POST['ReferenceBrand'] ?? ''),
                (string) ($_POST['RefNr'] ?? '')
            );
            $flash = 'Rând actualizat.';
        } elseif ($action === 'delete') {
            QwpDb::deleteRow((int) ($_POST['id'] ?? 0));
            $flash = 'Rând șters.';
        } elseif ($action === 'add') {
            QwpDb::insertRow(
                (string) ($_POST['ArtNr'] ?? ''),
                (string) ($_POST['ReferenceBrand'] ?? ''),
                (string) ($_POST['RefNr'] ?? '')
            );
            $flash = 'Rând adăugat.';
        }

        $back = '?page=' . $page;
        if ($q !== '') {
            $back .= '&q=' . urlencode($q);
        }
        if ($flash !== '') {
            $back .= '&ok=1';
        }
        qwp_redirect($back);
    }

    $totalRows = QwpDb::countRows($q);
    $totalPages = max(1, (int) ceil($totalRows / PER_PAGE));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $rows = QwpDb::listRows($page, PER_PAGE, $q);
    $dbName = qwp_db_name();

    if (isset($_GET['ok'])) {
        $flash = 'Salvat.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
    $rows = [];
    $totalRows = 0;
    $totalPages = 1;
    $dbName = qwp_db_name();
}

function qwp_query(array $params): string
{
    return http_build_query(array_filter($params, static fn($v) => $v !== '' && $v !== null));
}

$offset = ($page - 1) * PER_PAGE;
$window = 5;
$start = max(1, $page - $window);
$end = min($totalPages, $page + $window);
$baseParams = ['q' => $q];

?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autonet QWP — editor BD</title>
    <style>
        body { font-family: Segoe UI, sans-serif; margin: 24px; background: #f6f8fb; color: #1f2937; }
        .wrap { max-width: 1200px; margin: 0 auto; }
        h1 { margin: 0 0 6px; font-size: 1.45rem; }
        .sub { color: #6b7280; margin-bottom: 18px; font-size: 14px; }
        .panel { background: #fff; border: 1px solid #d8dee9; border-radius: 10px; padding: 14px 16px; margin-bottom: 14px; }
        .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
        .err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d8dee9; border-radius: 10px; overflow: hidden; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; vertical-align: top; font-size: 13px; }
        th { background: #eef2ff; text-align: left; }
        input[type=text] { width: 100%; min-width: 90px; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; }
        button, .btn { padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; font-size: 13px; text-decoration: none; color: #111; display: inline-block; }
        .btn-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
        .btn-danger { background: #fee2e2; border-color: #fecaca; color: #991b1b; }
        .pager { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 14px 0; }
        .pager a, .pager span { min-width: 34px; text-align: center; padding: 6px 10px; border-radius: 6px; border: 1px solid #d8dee9; text-decoration: none; color: #111; background: #fff; font-size: 13px; }
        .pager .active { background: #2563eb; color: #fff; border-color: #2563eb; }
        .pager .disabled { opacity: .45; pointer-events: none; }
        .meta { font-size: 13px; color: #6b7280; }
        .row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .topbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
        .topbar label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: #6b7280; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Autonet QWP — editor bază de date</h1>
    <p class="sub">
        Tabel: <code>autonet_qwp_data</code> · BD: <code><?= qwp_h($dbName) ?></code> ·
        <a href="import.php">Import din XLSX</a>
    </p>

    <?php if ($flash !== ''): ?><div class="ok"><?= qwp_h($flash) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="err"><?= qwp_h($error) ?></div><?php endif; ?>

    <div class="panel topbar">
        <form method="get">
            <label>Căutare (ArtNr / Brand / RefNr)
                <input type="text" name="q" value="<?= qwp_h($q) ?>" placeholder="ex: QWP, BOSCH, 12345">
            </label>
            <button type="submit" class="btn-primary">Caută</button>
            <?php if ($q !== ''): ?>
                <a class="btn" href="?">Reset</a>
            <?php endif; ?>
        </form>
        <div class="meta">
            Total: <strong><?= number_format($totalRows, 0, ',', '.') ?></strong> ·
            Pagina <?= $page ?> / <?= $totalPages ?>
        </div>
    </div>

    <div class="panel">
        <h3 style="margin-top:0;">Adaugă rând nou</h3>
        <form method="post" style="display:flex; flex-wrap:wrap; gap:8px; align-items:end;">
            <input type="hidden" name="action" value="add">
            <label>ArtNr<br><input type="text" name="ArtNr" required></label>
            <label>ReferenceBrand<br><input type="text" name="ReferenceBrand"></label>
            <label>RefNr<br><input type="text" name="RefNr" required></label>
            <button type="submit" class="btn-primary">Adaugă</button>
        </form>
    </div>

    <nav class="pager">
        <?php $prev = max(1, $page - 1); $next = min($totalPages, $page + 1); ?>
        <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="?<?= qwp_h(qwp_query($baseParams + ['page' => $prev])) ?>">&laquo;</a>
        <?php for ($p = $start; $p <= $end; $p++): ?>
            <?php if ($p === $page): ?>
                <span class="active"><?= $p ?></span>
            <?php else: ?>
                <a href="?<?= qwp_h(qwp_query($baseParams + ['page' => $p])) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="?<?= qwp_h(qwp_query($baseParams + ['page' => $next])) ?>">&raquo;</a>
        <span class="meta" style="margin-left:auto;">
            Rânduri <?= $totalRows ? ($offset + 1) : 0 ?>–<?= min($offset + PER_PAGE, $totalRows) ?>
        </span>
    </nav>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>ArtNr</th>
            <th>ReferenceBrand</th>
            <th>RefNr</th>
            <th>Actualizat</th>
            <th>Acțiuni</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="6">Niciun rând.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <?php $formId = 'edit-' . (int) $row['id']; ?>
                <tr>
                    <td><?= (int) $row['id'] ?></td>
                    <td><input form="<?= qwp_h($formId) ?>" type="text" name="ArtNr" value="<?= qwp_h((string) $row['ArtNr']) ?>"></td>
                    <td><input form="<?= qwp_h($formId) ?>" type="text" name="ReferenceBrand" value="<?= qwp_h((string) $row['ReferenceBrand']) ?>"></td>
                    <td><input form="<?= qwp_h($formId) ?>" type="text" name="RefNr" value="<?= qwp_h((string) $row['RefNr']) ?>"></td>
                    <td><?= qwp_h((string) ($row['updated_at'] ?? '')) ?></td>
                    <td class="row-actions">
                        <form id="<?= qwp_h($formId) ?>" method="post">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" class="btn-primary">Salvează</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Ștergi rândul <?= (int) $row['id'] ?>?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" class="btn-danger">Șterge</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <nav class="pager">
        <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="?<?= qwp_h(qwp_query($baseParams + ['page' => $prev])) ?>">&laquo;</a>
        <?php for ($p = $start; $p <= $end; $p++): ?>
            <?php if ($p === $page): ?>
                <span class="active"><?= $p ?></span>
            <?php else: ?>
                <a href="?<?= qwp_h(qwp_query($baseParams + ['page' => $p])) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="?<?= qwp_h(qwp_query($baseParams + ['page' => $next])) ?>">&raquo;</a>
    </nav>
</div>
</body>
</html>
