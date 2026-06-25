<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/system/product_dual_description.php';

$generatedHtml = '<h3>Descrierea</h3><dl class="tecdoc-desc-sheet">'
    . '<dt>Număr articol:</dt><dd>ABC123</dd>'
    . '<dt>Producătorul:</dt><dd>Bosch</dd></dl>';

$row = [];
besoiu_apply_product_description($row, $generatedHtml);

$checks = [
    'apply_sets_pNote' => ($row['pNote'] ?? '') === $generatedHtml,
    'apply_syncs_website' => ($row['pNoteWebsite'] ?? '') === $generatedHtml,
    'apply_syncs_marketplace' => ($row['pNoteMarketplace'] ?? '') === $generatedHtml,
    'resolve_from_pNote' => besoiu_resolve_product_description($row) === $generatedHtml,
    'resolve_website_alias' => besoiu_resolve_website_product_description($row) === $generatedHtml,
    'resolve_marketplace_alias' => besoiu_resolve_marketplace_product_description($row) === $generatedHtml,
    'legacy_dual_wrapper' => true,
];

$legacyRow = [];
besoiu_apply_dual_product_descriptions($legacyRow, $generatedHtml);
$checks['legacy_dual_wrapper'] = ($legacyRow['pNote'] ?? '') === $generatedHtml
    && ($legacyRow['pNoteWebsite'] ?? '') === $generatedHtml;

$failed = array_keys(array_filter($checks, static fn ($ok) => !$ok));

if ($failed !== []) {
    fwrite(STDERR, 'FAILED checks: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'OK: single product description helpers (' . count($checks) . " checks)\n";
