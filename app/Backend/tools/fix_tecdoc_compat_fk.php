<?php
declare(strict_types=1);

require __DIR__ . '/besoiupieseimport_tecdoc_db.php';

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(\Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    \Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

$shop = besoiupieseimport_tecdoc_merge_pdo();
$target = besoiupieseimport_tecdoc_db_target();
$shopDb = str_replace('`', '``', (string) $target['name']);

$total = (int) $shop->query("SELECT COUNT(*) FROM `{$shopDb}`.tecdoc_product_compatibilities")->fetchColumn();
echo "Total compat: {$total}\n";

$orphans = (int) $shop->query(
    "SELECT COUNT(*) FROM `{$shopDb}`.tecdoc_product_compatibilities c
     LEFT JOIN `{$shopDb}`.tecdoc_products p ON p.id = c.product_id
     WHERE p.id IS NULL"
)->fetchColumn();
echo "Orfane (fără produs): {$orphans}\n";

if ($orphans > 0) {
    echo "Șterg rânduri orfane...\n";
    $shop->exec(
        "DELETE c FROM `{$shopDb}`.tecdoc_product_compatibilities c
         LEFT JOIN `{$shopDb}`.tecdoc_products p ON p.id = c.product_id
         WHERE p.id IS NULL"
    );
}

besoiupieseimport_tecdoc_restore_compat_bulk_table($shop, static function (string $msg): void {
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
});

besoiupieseimport_tecdoc_clear_compat_migration_state();

$final = (int) $shop->query("SELECT COUNT(*) FROM `{$shopDb}`.tecdoc_product_compatibilities")->fetchColumn();
echo "Final compat: {$final}\n";

$sample = $shop->query(
    "SELECT car_brand, car_model, car_typ FROM `{$shopDb}`.tecdoc_product_compatibilities
     WHERE product_id = (SELECT id FROM `{$shopDb}`.tecdoc_products WHERE code = '31335' LIMIT 1)
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

echo "Sample ABS 31335:\n";
print_r($sample);
