<?php
declare(strict_types=1);

require dirname(__DIR__) . '/admin/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/admin')->safeLoad();
$c = require dirname(__DIR__) . '/admin/config/config.php';
Config\Database::getInstance($c['db_host'], $c['db_name'], $c['db_user'], $c['db_pass']);

$s = new Evasystem\Controllers\Produse\ProduseService();
$count = $s->countVitrinaProducts();
$paged = $s->getVitrinaProductsPaginated(1, max(8, $count), '');
$picker = $s->getVitrinaAdminPickerPaginated(1, 20, '');

echo "vitrina_count={$count}\n";
echo 'vitrina_items=' . count($paged['items'] ?? []) . "\n";
echo 'picker_total=' . (int) ($picker['total'] ?? 0) . "\n";
echo "template_marker=Produse din magazin\n";
