<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/system/tecdoc_stock.php';
require_once dirname(__DIR__) . '/system/home-vitrina-render.php';

$pdo = tecdoc_db();
$hasCol = tecdoc_produse_has_column($pdo, 'pVitrina');
echo "pVitrina_column: " . ($hasCol ? 'yes' : 'NO') . "\n";

if ($hasCol) {
    $cnt = (int) $pdo->query("SELECT COUNT(*) FROM produse WHERE status <> '0' AND pVitrina = 1")->fetchColumn();
    $cntAll = (int) $pdo->query("SELECT COUNT(*) FROM produse WHERE pVitrina = 1")->fetchColumn();
    echo "vitrina_active: {$cnt}\n";
    echo "vitrina_any_status: {$cntAll}\n";
    $rows = $pdo->query("SELECT id, randomn_id, pName, status, pVitrina, pPrice FROM produse WHERE pVitrina = 1 ORDER BY id DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "  id={$r['id']} status={$r['status']} rand=" . substr((string)($r['randomn_id'] ?? ''), 0, 12) . " name=" . substr((string)$r['pName'], 0, 40) . "\n";
    }
}

$products = besoiu_home_vitrina_products();
echo "status_groups:\n";
foreach ($pdo->query('SELECT status, COUNT(*) c FROM produse GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $g) {
    echo "  status=" . var_export($g['status'], true) . " count={$g['c']}\n";
}

echo "besoiu_home_vitrina_products: " . count($products) . "\n";
foreach (array_slice($products, 0, 3) as $p) {
    echo "  " . ($p['name'] ?? $p['pName'] ?? '?') . " img=" . substr((string)($p['image'] ?? ''), 0, 40) . "\n";
}
