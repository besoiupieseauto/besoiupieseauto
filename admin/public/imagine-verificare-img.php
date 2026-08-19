<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/Legacy/imagine-catalog-lookup.php';
imagine_catalog_load_env($root);

$src = strtolower(trim((string) ($_GET['src'] ?? '')));
$id = (int) ($_GET['id'] ?? 0);
if ($id < 1 || !in_array($src, ['ap', 'poze', 'at'], true)) {
    http_response_code(404);
    exit;
}

$map = [
    'ap' => [
        'db' => getenv('IMAGINE_PRODUSE_DB') ?: 'imagine_produse',
        'sql' => 'SELECT brand, disk_name, rel_path FROM images WHERE id = :id',
        'dirs' => [
            rtrim((string) (getenv('AUTOPARTNER_IMAGE_DIR') ?: 'C:/Users/Radu/Desktop/autoparner'), '/\\') . '/IMAGINI_RENUMITE',
        ],
    ],
    'poze' => [
        'db' => getenv('IMAGINE_POZE_DB') ?: 'imagine_poze',
        'sql' => 'SELECT brand, disk_name, rel_path FROM images WHERE id = :id',
        'dirs' => [
            rtrim((string) (getenv('TTC_POZE_RENAMED_DIR') ?: 'C:/laragon/www/besoiupieseimport/Poze_RENUMITE'), '/\\'),
            rtrim((string) (getenv('TTC_POZE_DIR') ?: 'C:/laragon/www/besoiupieseimport/Poze'), '/\\'),
        ],
    ],
    'at' => [
        'db' => getenv('IMAGINE_AUTOTOTAL_DB') ?: 'imagine_autototal',
        'sql' => 'SELECT brand, disk_name, rel_path FROM images WHERE id = :id',
        'dirs' => [
            rtrim((string) (getenv('AUTOTAL_IMAGE_DIR') ?: 'C:/laragon/www/besoiupieseimport/Autotal'), '/\\'),
        ],
    ],
];

$cfg = $map[$src];
$pdo = imagine_catalog_pdo((string) $cfg['db']);
if (!$pdo instanceof PDO) {
    http_response_code(404);
    exit;
}
$st = $pdo->prepare((string) $cfg['sql']);
$st->execute([':id' => $id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!is_array($row)) {
    http_response_code(404);
    exit;
}

$disk = (string) $row['disk_name'];
$brand = (string) $row['brand'];
$rel = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, (string) $row['rel_path']);
$candidates = [];
foreach ($cfg['dirs'] as $dir) {
    $candidates[] = $dir . DIRECTORY_SEPARATOR . $brand . DIRECTORY_SEPARATOR . $disk;
    $candidates[] = $dir . DIRECTORY_SEPARATOR . $disk;
    $candidates[] = dirname($dir) . DIRECTORY_SEPARATOR . $rel;
}
$abs = imagine_catalog_first_file($candidates);
if ($abs === '') {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
header('Content-Type: ' . ($types[$ext] ?? 'image/jpeg'));
header('Cache-Control: public, max-age=86400');
readfile($abs);
