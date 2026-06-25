<?php

declare(strict_types=1);

/**
 * Suite completă Regula de Aur — rulează toate testele EvaSystem.
 * Usage: php tools/run_all_tests.php
 */

require __DIR__ . '/php_cli.php';

$php = admin_php_cli_binary();
$admin = dirname(__DIR__);
$failed = 0;

$tests = [
    'zeus_smoke.php',
    'test_admin_login_http.php',
    'verify_cron_setup.php',
    'verify_website_footer_social.php',
    'verify_produse_markup_modal.php',
    'verify_tm052_single_source_markup.php',
    'verify_conditional_markup_rules.php',
    'test_price_formation_trace.php',
    'test_product_code_normalize.php',
    '../scripts/test_dual_descriptions.php',
    '../scripts/test_tecdoc_cache.php',
    'scan_syntax.php',
    'test_modules_autoload.php',
    'test_crud_dispatchers.php',
    'test_hub_modernization.php',
    'test_crud_dispatcher_json.php',
    'test_admin_hub_services.php',
    'test_all_api_endpoints.php',
    'test_crud_routes_http.php',
    'test_tm021_tecdoc_structure_deferred.php',
    'test_tm106_baselinker_products.php',
    'test_tm116_caiet_comenzi_selectors.php',
    'audit_sql_conventions.php',
];

echo "=== EvaSystem run_all_tests ===\n\n";

foreach ($tests as $script) {
    $path = $admin . '/tools/' . $script;
    if (!is_file($path)) {
        echo "SKIP missing {$script}\n";
        continue;
    }

    echo "--- {$script} ---\n";
    passthru('"' . $php . '" ' . escapeshellarg($path), $exitCode);
    echo "\n";
    if ($exitCode !== 0) {
        $failed++;
    }
}

foreach (['_test_search_logs_service.php', '_test_system_errors_service.php'] as $migrationScript) {
    $migration = $admin . '/migrations/' . $migrationScript;
    if (!is_file($migration)) {
        continue;
    }
    echo "--- {$migrationScript} ---\n";
    passthru('"' . $php . '" ' . escapeshellarg($migration), $exitCode);
    echo "\n";
    if ($exitCode !== 0) {
        $failed++;
    }
}

if ($failed > 0) {
    echo "FAILED: {$failed} script(s)\n";
    exit(1);
}

echo "ALL TESTS PASSED\n";
