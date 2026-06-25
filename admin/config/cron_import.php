<?php

declare(strict_types=1);

/**
 * Import automat din Cron Sync — limită produse per rulare (testare).
 */
function admin_cron_import_limit(): int
{
    $fromEnv = (int) ($_ENV['CRON_IMPORT_LIMIT'] ?? getenv('CRON_IMPORT_LIMIT') ?: 10);

    return max(1, min(50, $fromEnv > 0 ? $fromEnv : 10));
}

function admin_cron_import_publish_mode(): string
{
    $mode = trim((string) ($_ENV['CRON_IMPORT_PUBLISH_MODE'] ?? getenv('CRON_IMPORT_PUBLISH_MODE') ?: 'update'));

    return in_array($mode, ['skip', 'update', 'force'], true) ? $mode : 'update';
}

/** Cron inserează în import_produse (importreview) doar dacă auto-publish e dezactivat. */
function admin_cron_import_via_staging(): bool
{
    if (admin_cron_auto_publish_consumables()) {
        return false;
    }

    $raw = strtolower(trim((string) ($_ENV['CRON_IMPORT_VIA_STAGING'] ?? getenv('CRON_IMPORT_VIA_STAGING') ?: '1')));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

/**
 * dual = vitrină (ulei·lichide, max 8) + catalog piese | consumables = doar consumabile (vechi).
 */
function admin_cron_import_mode(): string
{
    $raw = strtolower(trim((string) ($_ENV['CRON_IMPORT_MODE'] ?? getenv('CRON_IMPORT_MODE') ?: 'dual')));

    if ($raw === 'dual') {
        return 'dual';
    }
    if (in_array($raw, ['consumables', 'consumables_only'], true)) {
        return 'consumables';
    }

    $legacyOnly = strtolower(trim((string) ($_ENV['CRON_CONSUMABLES_ONLY'] ?? getenv('CRON_CONSUMABLES_ONLY') ?: '0')));

    return in_array($legacyOnly, ['1', 'true', 'yes', 'on'], true) ? 'consumables' : 'dual';
}

/** Doar consumabile — mod vechi; în mod dual returnează false. */
function admin_cron_consumables_only(): bool
{
    return admin_cron_import_mode() === 'consumables';
}

/** Max produse pe vitrina homepage (ulei + lichide). */
function admin_cron_vitrina_limit(): int
{
    $siteLimitPath = dirname(__DIR__, 2) . '/system/home-vitrina-render.php';
    if (is_file($siteLimitPath)) {
        require_once $siteLimitPath;
        if (function_exists('besoiu_home_vitrina_limit')) {
            return besoiu_home_vitrina_limit();
        }
    }

    $fromEnv = (int) ($_ENV['CRON_VITRINA_LIMIT'] ?? getenv('CRON_VITRINA_LIMIT') ?: 10);

    return max(1, min(10, $fromEnv > 0 ? $fromEnv : 10));
}

/** Max piese catalog importate per rulare cron (frâne, discuri, etc.). */
function admin_cron_catalog_limit(): int
{
    $fromEnv = (int) ($_ENV['CRON_CATALOG_LIMIT'] ?? getenv('CRON_CATALOG_LIMIT') ?: 20);

    return max(1, min(500, $fromEnv > 0 ? $fromEnv : 20));
}

/** @return array<int, string> */
function admin_cron_consumable_categories(): array
{
    $raw = trim((string) ($_ENV['CRON_CONSUMABLE_CATEGORIES'] ?? getenv('CRON_CONSUMABLE_CATEGORIES') ?: ''));
    if ($raw === '') {
        return ['ulei', 'lichide'];
    }

    $parts = array_values(array_filter(array_map('trim', explode(',', $raw))));

    return $parts !== [] ? $parts : ['ulei', 'lichide'];
}

/** Cron: caută întâi ulei + lichide; ignoră becuri/siguranțe chiar dacă electrice e în listă. */
function admin_cron_priority_fluids_only(): bool
{
    $raw = strtolower(trim((string) ($_ENV['CRON_PRIORITY_FLUIDS'] ?? getenv('CRON_PRIORITY_FLUIDS') ?: '1')));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

/** Publică direct în magazin (ca «Importă consumabile»), nu doar coadă importreview. */
function admin_cron_auto_publish_consumables(): bool
{
    $raw = strtolower(trim((string) ($_ENV['CRON_AUTO_PUBLISH'] ?? getenv('CRON_AUTO_PUBLISH') ?: '1')));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

/** Verificare ePiesa la cron (lentă — scrape.do ~90s/produs). Implicit dezactivată. */
function admin_cron_check_epiesa(): bool
{
    $raw = strtolower(trim((string) ($_ENV['CRON_CHECK_EPIESA'] ?? getenv('CRON_CHECK_EPIESA') ?: '0')));

    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

/** După câte secunde lock-ul de scan e considerat blocat (proces mort). */
function admin_cron_scan_lock_max_age(): int
{
    $fromEnv = (int) ($_ENV['CRON_SCAN_LOCK_MAX_SEC'] ?? getenv('CRON_SCAN_LOCK_MAX_SEC') ?: 1200);

    return max(300, min(7200, $fromEnv > 0 ? $fromEnv : 1200));
}

/** Toate consumabilele publicate din cron primesc pVitrina=1 (homepage). */
function admin_cron_always_vitrina(): bool
{
    if (admin_cron_import_mode() === 'dual') {
        return false;
    }

    $raw = strtolower(trim((string) ($_ENV['CRON_ALWAYS_VITRINA'] ?? getenv('CRON_ALWAYS_VITRINA') ?: '1')));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

/**
 * Cron: fără scanare CSV TecDoc multi-GB (blochează minute/ore).
 */
function admin_cron_skip_tecdoc_csv_lookup(): bool
{
    $raw = strtolower(trim((string) ($_ENV['CRON_SKIP_TECDOC_CSV'] ?? getenv('CRON_SKIP_TECDOC_CSV') ?: '1')));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

/** Sursă imagine la Cron Sync — respectă pipeline-ul din /admin/scraper (Plan 1→N). */
function admin_cron_image_source(): string
{
    $raw = strtolower(trim((string) ($_ENV['CRON_IMAGE_SOURCE'] ?? getenv('CRON_IMAGE_SOURCE') ?: 'auto')));

    return in_array($raw, ['tecdoc', 'auto'], true) ? $raw : 'auto';
}

/** Produse fără preț sau imagine validă merg în importreview în loc de publicare directă. */
function admin_cron_stage_incomplete(): bool
{
    if (!admin_cron_auto_publish_consumables()) {
        return true;
    }

    $raw = strtolower(trim((string) ($_ENV['CRON_STAGE_INCOMPLETE'] ?? getenv('CRON_STAGE_INCOMPLETE') ?: '0')));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

/**
 * Cron: enrich rapid — fără scan CSV TecDoc multi-GB și fără TecDoc API pe fiecare produs.
 * Evită blocarea la «Procesez 1/10» (minute pe produs).
 */
function admin_cron_light_enrich(): bool
{
    $raw = strtolower(trim((string) ($_ENV['CRON_LIGHT_ENRICH'] ?? getenv('CRON_LIGHT_ENRICH') ?: '1')));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

/** Max interogări eMAG per produs la cron (scrape.do ~90–120s fiecare). */
function admin_cron_emag_max_queries(): int
{
    $fromEnv = (int) ($_ENV['CRON_EMAG_MAX_QUERIES'] ?? getenv('CRON_EMAG_MAX_QUERIES') ?: 3);

    return max(1, min(8, $fromEnv > 0 ? $fromEnv : 3));
}

/**
 * Ordinea surselor imagine (config + .env IMAGE_SEARCH_SOURCES).
 *
 * @return array<int, string>
 */
function admin_image_search_source_ids(): array
{
    $pipeline = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
    if (!is_file($pipeline)) {
        return ['caietcomenzi', 'epiesa', 'tecdoc_api'];
    }
    require_once $pipeline;
    $ids = [];
    foreach (besoiu_image_search_sources_ordered() as $row) {
        $ids[] = $row['id'];
    }

    return $ids !== [] ? $ids : ['epiesa', 'tecdoc_api'];
}
