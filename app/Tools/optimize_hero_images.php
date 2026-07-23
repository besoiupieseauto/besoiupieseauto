<?php

declare(strict_types=1);

define('BESOIU_ROOT', dirname(__DIR__, 2));
require BESOIU_ROOT . '/app/bootstrap.php';

$targets = [
    'assets/img/car1.png' => ['maxWidth' => 1120, 'quality' => 82, 'variants' => []],
    'assets/img/hero-car.png' => ['maxWidth' => 1120, 'quality' => 82, 'variants' => []],
    'assets/img/bg_groop.png' => ['maxWidth' => 1600, 'quality' => 78, 'variants' => []],
    'assets/img/logo.png' => ['maxWidth' => 256, 'quality' => 85, 'variants' => []],
    'assets/images/products/1.jpg' => ['maxWidth' => 400, 'quality' => 78, 'variants' => [200 => 72, 400 => 78]],
    'assets/images/products/2.jpg' => ['maxWidth' => 400, 'quality' => 78, 'variants' => [200 => 72, 400 => 78]],
    'assets/images/products/3.jpg' => ['maxWidth' => 400, 'quality' => 78, 'variants' => [200 => 72, 400 => 78]],
];

if (!function_exists('besoiu_optimize_image_to_webp')) {
    function besoiu_optimize_image_to_webp(string $source, string $dest, int $maxWidth, int $quality): bool
    {
        if (!is_file($source) || !function_exists('imagecreatefromstring')) {
            return false;
        }
        $blob = file_get_contents($source);
        if ($blob === false) {
            return false;
        }
        $image = @imagecreatefromstring($blob);
        if ($image === false) {
            return false;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            return false;
        }
        if ($width > $maxWidth) {
            $newHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($maxWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ok = imagewebp($image, $dest, $quality);
        imagedestroy($image);

        return $ok;
    }
}

$done = 0;
foreach ($targets as $relative => $opts) {
    $source = BESOIU_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($source)) {
        echo "Skip (missing): {$relative}\n";
        continue;
    }

    $baseRelative = preg_replace('/\.(png|jpe?g)$/i', '', $relative) ?? $relative;

    foreach ($opts['variants'] as $variantWidth => $quality) {
        $variantRel = $baseRelative . '-' . $variantWidth . '.webp';
        $dest = BESOIU_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $variantRel);
        if (besoiu_optimize_image_to_webp($source, $dest, (int) $variantWidth, (int) $quality)) {
            $dstKb = round(filesize($dest) / 1024, 1);
            echo "OK {$relative} -> {$variantRel} ({$dstKb} KB)\n";
            $done++;
        }
    }

    $webpRelative = $baseRelative . '.webp';
    $dest = BESOIU_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $webpRelative);
    if (besoiu_optimize_image_to_webp($source, $dest, (int) $opts['maxWidth'], (int) $opts['quality'])) {
        $srcKb = round(filesize($source) / 1024, 1);
        $dstKb = round(filesize($dest) / 1024, 1);
        echo "OK {$relative} -> {$webpRelative} ({$srcKb} KB -> {$dstKb} KB)\n";
        $done++;
    } else {
        echo "FAIL {$relative}\n";
    }
}

echo "Optimizate {$done} imagini.\n";
