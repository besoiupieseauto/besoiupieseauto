<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/admin/bootstrap.php';
require dirname(__DIR__) . '/tools/besoiupieseimport_tecdoc_db.php';
require BESOIU_LEGACY . '/product-code-normalize.php';

echo "=== TecDoc DB probe ===\n\n";

try {
    $p = besoiupieseimport_tecdoc_pdo();
    echo 'Unified DB: ' . $p->query('SELECT DATABASE()')->fetchColumn() . "\n";
    foreach (['tecdoc_products', 'tecdoc_product_codes', 'tecdoc_brands'] as $t) {
        if (besoiupieseimport_tecdoc_table_exists($p, $t)) {
            echo "  {$t}: " . besoiupieseimport_tecdoc_approximate_row_count($p, $t) . " rows\n";
        } else {
            echo "  {$t}: MISSING\n";
        }
    }
    if (besoiupieseimport_tecdoc_table_exists($p, 'tecdoc_product_codes')) {
        $rows = $p->query(
            'SELECT c.code_norm, b.name AS brand, p.art_name
             FROM tecdoc_product_codes c
             INNER JOIN tecdoc_products p ON p.id = c.product_id
             INNER JOIN tecdoc_brands b ON b.id = p.brand_id
             LIMIT 5'
        )->fetchAll(PDO::FETCH_ASSOC);
        echo "\nSample unified codes:\n";
        foreach ($rows as $row) {
            echo '  ' . $row['code_norm'] . ' | ' . $row['brand'] . ' | ' . mb_substr((string) $row['art_name'], 0, 60) . "\n";
        }
    }
} catch (Throwable $e) {
    echo 'Unified error: ' . $e->getMessage() . "\n";
}

echo "\n";

try {
    $lp = besoiupieseimport_tecdoc_legacy_pdo();
    echo 'Legacy DB: ' . $lp->query('SELECT DATABASE()')->fetchColumn() . "\n";
    $cnt = (int) $lp->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() "
        . "AND table_name IN ('products','product_codes','brands')"
    )->fetchColumn();
    echo "  legacy core tables: {$cnt}/3\n";
    if ($cnt >= 3) {
        $products = (int) $lp->query('SELECT COUNT(*) FROM products')->fetchColumn();
        $codes = (int) $lp->query('SELECT COUNT(*) FROM product_codes')->fetchColumn();
        echo "  products: {$products}, product_codes: {$codes}\n";
        $rows = $lp->query(
            'SELECT c.code_norm, b.name AS brand, p.art_name
             FROM product_codes c
             INNER JOIN products p ON p.id = c.product_id
             INNER JOIN brands b ON b.id = p.brand_id
             LIMIT 5'
        )->fetchAll(PDO::FETCH_ASSOC);
        echo "\nSample legacy codes:\n";
        foreach ($rows as $row) {
            echo '  ' . $row['code_norm'] . ' | ' . $row['brand'] . ' | ' . mb_substr((string) $row['art_name'], 0, 60) . "\n";
        }
    }
} catch (Throwable $e) {
    echo 'Legacy error: ' . $e->getMessage() . "\n";
}
