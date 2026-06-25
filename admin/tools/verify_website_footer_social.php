<?php

declare(strict_types=1);

/**
 * Verifică selectori CMS footer social (admin + frontend).
 * Usage: php admin/tools/verify_website_footer_social.php
 */

require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
$root = dirname($admin);
$failures = 0;

echo "=== verify_website_footer_social ===\n\n";

$selectorChecks = [
    'Templates/admin/pages/website/website.php' => [
        'id="website-footer-social-admin"',
        'data-global-path="footer.social.',
        'Footer — rețele sociale',
    ],
    '../system/footer.php' => [
        'id="footer-social-links"',
        'footer-social-icon',
        'footer[\'social\']',
    ],
];

foreach ($selectorChecks as $rel => $needles) {
    $path = $admin . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        echo "FAIL missing: {$rel}\n";
        $failures++;
        continue;
    }
    $html = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($html, $needle)) {
            echo "FAIL selector {$needle} lipsește din {$rel}\n";
            $failures++;
        } else {
            echo "OK  selector {$needle} in {$rel}\n";
        }
    }
}

$defaultsFile = $root . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'site-defaults.php';
$defaults = is_file($defaultsFile) ? (string) file_get_contents($defaultsFile) : '';
if ($defaults === '' || !str_contains($defaults, "'social' =>")) {
    echo "FAIL footer.social defaults in site-defaults.php\n";
    $failures++;
} else {
    echo "OK  footer.social defaults in site-defaults.php\n";
}

if ($failures > 0) {
    echo "\nFAILED: {$failures}\n";
    exit(1);
}

echo "\nWEBSITE FOOTER SOCIAL OK\n";
exit(0);
