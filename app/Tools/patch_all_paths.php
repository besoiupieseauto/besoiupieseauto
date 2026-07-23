<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$app = $root . '/app';

$map = [
    "dirname(__DIR__) . '/system/" => "BESOIU_LEGACY . '/",
    "dirname(__DIR__) . '/lib/" => "BESOIU_LIB . '/",
    "dirname(__DIR__, 2) . '/system/" => "BESOIU_LEGACY . '/",
    "dirname(__DIR__) . '/../config/" => "BESOIU_CONFIG . '/",
    "dirname(__DIR__) . '/../backend/" => "BESOIU_BACKEND . '/",
    "dirname(__DIR__) . '/config/" => "BESOIU_CONFIG . '/",
    "dirname(__DIR__) . '/backend/" => "BESOIU_BACKEND . '/",
    "dirname(__DIR__) . '/admin/" => "BESOIU_BACKEND . '/",
    "dirname(__DIR__) . '/cache_tecdoc/" => "BESOIU_CACHE_TECDOC . '/",
    "dirname(__DIR__, 2) . '/cache_tecdoc/" => "BESOIU_CACHE_TECDOC . '/",
    "__DIR__ . '/../config/" => "BESOIU_CONFIG . '/",
    "__DIR__ . '/../backend/" => "BESOIU_BACKEND . '/",
    "/system/bootstrap.php" => "/bootstrap.php",
    "require_once __DIR__ . '/paths.php';" => "// paths in app/bootstrap",
    "return dirname(__DIR__) . '/admin';" => "return defined('BESOIU_BACKEND') ? BESOIU_BACKEND : dirname(__DIR__, 2) . '/app/Backend';",
    "Dotenv\\Dotenv::createImmutable(dirname(__DIR__) . '/admin')" => "Dotenv\\Dotenv::createImmutable(BESOIU_CONFIG)",
    "Dotenv\\Dotenv::createImmutable(__DIR__ . '/admin')" => "Dotenv\\Dotenv::createImmutable(BESOIU_CONFIG)",
    "dirname(__DIR__) . '/config/product-badges.php'" => "BESOIU_CONFIG . '/product-badges.php'",
    "require __DIR__ . '/api/robots.php';" => "require __DIR__ . '/app/Legacy/robots-handler.php';",
];

$dirs = [$app . '/Views', $app . '/Legacy', $app . '/Modules', $root . '/api'];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        $new = str_replace(array_keys($map), array_values($map), $content);
        if ($new !== $content) {
            file_put_contents($path, $new);
            echo "patched: {$path}\n";
        }
    }
}

// Views: ensure bootstrap first line
foreach (glob($app . '/Views/*.php') ?: [] as $viewFile) {
    if (str_ends_with($viewFile, '_bootstrap.php')) {
        continue;
    }
    $content = file_get_contents($viewFile);
    if ($content === false || str_contains($content, "app/bootstrap.php")) {
        continue;
    }
    if (str_contains($content, '/bootstrap.php') && !str_contains($content, 'BESOIU_LEGACY')) {
        $content = preg_replace(
            '/^<\?php\s*\n(?:require[^\n]+\n)+/',
            "<?php\nrequire_once dirname(__DIR__, 2) . '/bootstrap.php';\n",
            $content,
            1
        ) ?? $content;
        file_put_contents($viewFile, $content);
        echo "bootstrap: {$viewFile}\n";
    }
}

echo "Done.\n";
