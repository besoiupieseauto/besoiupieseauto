<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/Legacy/imagine-catalog-lookup.php';
require_once $root . '/app/Legacy/imagine-name-ro.php';
imagine_catalog_load_env($root);

$src = strtolower(trim((string) ($_GET['src'] ?? 'ap')));
if (!in_array($src, ['ap', 'poze', 'at'], true)) {
    $src = 'ap';
}
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 24;
$offset = ($page - 1) * $per;

$cfg = [
    'ap' => [
        'label' => 'Autopartner',
        'db' => getenv('IMAGINE_PRODUSE_DB') ?: 'imagine_produse',
        'sql' => 'SELECT i.id, i.brand, i.code_norm, p.code_a, p.code_c, p.name AS product_name,
                         i.original_file AS orig, i.disk_name
                  FROM images i LEFT JOIN products p USING (brand, code_norm)',
        'base' => "i.brand <> '999' AND i.brand <> ''",
        'where' => 'i.disk_name LIKE :q OR i.code_norm LIKE :q OR i.brand LIKE :q OR i.original_file LIKE :q OR IFNULL(p.code_a,\'\') LIKE :q OR IFNULL(p.name,\'\') LIKE :q',
        'hint' => 'Caută cod C (TecDoc), coloana A sau brand. Ex: MGA-5562, 110191, BOSCH',
    ],
    'poze' => [
        'label' => 'Poze TTC',
        'db' => getenv('IMAGINE_POZE_DB') ?: 'imagine_poze',
        'sql' => 'SELECT i.id, i.brand, i.code_norm, i.code_raw AS code_a, i.ttc_art_id AS code_c,
                         p.name AS product_name, i.original_path AS orig, i.disk_name
                  FROM images i LEFT JOIN products p USING (brand, code_norm)',
        'base' => "i.original_path LIKE 'Poze/%' AND i.original_path NOT LIKE '%AP_ZDJECIA%'",
        'where' => 'i.disk_name LIKE :q OR i.code_norm LIKE :q OR i.brand LIKE :q OR i.original_path LIKE :q OR IFNULL(i.code_raw,\'\') LIKE :q OR IFNULL(i.ttc_art_id,\'\') LIKE :q OR IFNULL(p.name,\'\') LIKE :q',
        'hint' => 'Caută owner_code, brand sau TTC id. Ex: CAM749, AE',
    ],
    'at' => [
        'label' => 'Autototal',
        'db' => getenv('IMAGINE_AUTOTOTAL_DB') ?: 'imagine_autototal',
        'sql' => 'SELECT i.id, i.brand, i.code_norm, i.code_raw AS code_a, \'\' AS code_c,
                         p.name AS product_name, i.original_url AS orig, i.disk_name
                  FROM images i LEFT JOIN products p USING (brand, code_norm)',
        'where' => 'i.disk_name LIKE :q OR i.code_norm LIKE :q OR i.brand LIKE :q OR i.original_url LIKE :q OR IFNULL(i.code_raw,\'\') LIKE :q OR IFNULL(p.name,\'\') LIKE :q',
        'hint' => 'Caută coloana B (TecDoc) sau SUP_BRAND. Ex: 0001106025, BOSCH',
    ],
];

$meta = $cfg[$src];
$rows = [];
$total = 0;
$error = '';
$pdo = imagine_catalog_pdo((string) $meta['db']);
if (!$pdo instanceof PDO) {
    $error = 'Nu mă conectez la ' . $meta['db'];
} else {
    $where = (string) ($meta['base'] ?? '1=1');
    $params = [];
    if ($q !== '') {
        $where = '(' . $where . ') AND (' . $meta['where'] . ')';
        $params[':q'] = '%' . str_replace([' ', '-', '.', '/', '_'], '', strtoupper($q)) . '%';
        if (!str_contains($q, ' ') && !str_contains($q, '-')) {
            $params[':q'] = '%' . $q . '%';
        } else {
            $params[':q'] = '%' . $q . '%';
        }
    }
    $countSql = 'SELECT COUNT(*) FROM (' . $meta['sql'] . ' WHERE ' . $where . ') x';
    try {
        $st = $pdo->prepare($countSql);
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $st = $pdo->prepare($meta['sql'] . ' WHERE ' . $where . ' ORDER BY brand, code_norm, disk_name LIMIT ' . (int) $per . ' OFFSET ' . (int) $offset);
        $st->execute($params);
        $fetched = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rows = $src === 'poze' ? $fetched : imagine_rows_with_romanian_names($pdo, $fetched);
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

$pages = max(1, (int) ceil($total / $per));
$self = basename($_SERVER['SCRIPT_NAME'] ?? 'imagine-verificare.php');

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificare mapare imagini</title>
    <style>
        body { font-family: Segoe UI, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        header { padding: 16px 20px; background: #111827; border-bottom: 1px solid #334155; }
        h1 { margin: 0 0 8px; font-size: 20px; }
        .tabs a, button, .page { display: inline-block; padding: 8px 12px; margin: 0 6px 0 0; border-radius: 8px; text-decoration: none; color: #e2e8f0; background: #1e293b; }
        .tabs a.on { background: #2563eb; }
        form { margin-top: 12px; }
        input[type=search] { width: min(420px, 70vw); padding: 8px 10px; border-radius: 8px; border: 1px solid #475569; background: #0b1220; color: #fff; }
        .hint { color: #94a3b8; font-size: 13px; margin-top: 6px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; padding: 16px 20px 40px; }
        .card { background: #111827; border: 1px solid #334155; border-radius: 12px; overflow: hidden; }
        .card img { width: 100%; height: 180px; object-fit: contain; background: #fff; }
        .meta { padding: 10px 12px; font-size: 13px; line-height: 1.45; }
        .meta b { color: #93c5fd; }
        .pname { color: #f8fafc; font-weight: 600; margin: 0 0 6px; }
        .err { color: #fca5a5; padding: 16px 20px; }
        .pager { padding: 0 20px 24px; }
    </style>
</head>
<body>
<header>
    <h1>Verificare mapare poze</h1>
    <div class="tabs">
        <?php foreach ($cfg as $k => $c): ?>
            <a class="<?= $k === $src ? 'on' : '' ?>" href="?src=<?= h($k) ?>&amp;q=<?= h($q) ?>"><?= h($c['label']) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get">
        <input type="hidden" name="src" value="<?= h($src) ?>">
        <input type="search" name="q" value="<?= h($q) ?>" placeholder="Cod, brand, nume fișier…">
        <button type="submit">Caută</button>
    </form>
    <div class="hint"><?= h((string) $meta['hint']) ?> — <?= number_format($total, 0, ',', '.') ?> rezultate</div>
</header>
<?php if ($error !== ''): ?>
    <div class="err"><?= h($error) ?></div>
<?php endif; ?>
<div class="grid">
<?php foreach ($rows as $r): ?>
    <article class="card">
        <img src="/admin/imagine-verificare-img.php?src=<?= h($src) ?>&amp;id=<?= (int) $r['id'] ?>" alt="<?= h((string) $r['disk_name']) ?>" loading="lazy">
        <div class="meta">
            <?php $pname = trim((string) ($r['product_name'] ?? '')); ?>
            <?php if ($pname !== ''): ?><div class="pname"><?= h($pname) ?></div><?php endif; ?>
            <div><b><?= h((string) $r['brand']) ?></b> <?= h((string) $r['disk_name']) ?></div>
            <div>cod: <?= h((string) $r['code_norm']) ?></div>
            <?php if (trim((string) ($r['code_a'] ?? '')) !== ''): ?><div>sursă: <?= h((string) $r['code_a']) ?></div><?php endif; ?>
            <?php if (trim((string) ($r['code_c'] ?? '')) !== ''): ?><div>extra: <?= h((string) $r['code_c']) ?></div><?php endif; ?>
            <div style="color:#94a3b8;word-break:break-all"><?= h((string) $r['orig']) ?></div>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php if ($pages > 1): ?>
<div class="pager">
    <?php if ($page > 1): ?><a class="page" href="?src=<?= h($src) ?>&amp;q=<?= h($q) ?>&amp;p=<?= $page - 1 ?>">← Înapoi</a><?php endif; ?>
    <span>pagina <?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="page" href="?src=<?= h($src) ?>&amp;q=<?= h($q) ?>&amp;p=<?= $page + 1 ?>">Înainte →</a><?php endif; ?>
</div>
<?php endif; ?>
</body>
</html>
