<?php

declare(strict_types=1);

/**
 * Sincronizare fișiere import → admin (supplier_feeds + tecdoc).
 *
 * Cod: app/Import/ (besoiupieseauto.ro)
 * Date: BESOIU_IMPORT_DATA_ROOT (implicit besoiupieseimport legacy până la mutare completă)
 */
$dataRoot = trim((string) (getenv('BESOIU_IMPORT_DATA_ROOT') ?: ''));
if ($dataRoot === '' || !is_dir($dataRoot)) {
    $dataRoot = 'F:/laragon/www/besoiupieseimport';
}

return [
    'enabled' => true,

    /** Rădăcina datelor import (Poze, CSV, autoparner). */
    'import_root' => $dataRoot,

    /** URL public Laragon pentru iframe / API extern. */
    'public_url' => 'http://besoiupieseimport.test',

    /** Foldere unde caută liste preț furnizori (relative la import_root). */
    'supplier_source_dirs' => [
        'Fisierile Matc',
        'Prelucrare fisiere bovsoft-base/Lista preturi furniori',
    ],

    /** Catalog Autopartner (relative la import_root). */
    'autopartner_catalog' => 'autoparner/3208129.csv',

    /** CSV Base TecDoc (relative la import_root). */
    'tecdoc_source_dir' => 'Fisierile Matc',

    /** Pattern nume fișier TecDoc. */
    'tecdoc_filename_pattern' => '/TableUseCarsForParts.*-ro\.csv$/i',

    /** Copiază doar dacă sursa e mai nouă sau destinația lipsește. */
    'skip_if_same_size' => true,

    /**
     * Catalog TecDoc în aceeași bază cu magazinul (recomandat).
     * true = citește din DB_NAME (.env) — aceeași conexiune ca produse/comenzi.
     */
    'tecdoc_unified_db' => true,

    /**
     * Enrichment import din indexul tecdoc_*; dezactivează pentru rollback la scanarea CSV.
     * CSV este fallback automat numai dacă indexul DB nu este disponibil.
     */
    'tecdoc_import_db_lookup' => true,

    /** Baza veche separată (doar pentru migrare date). */
    'tecdoc_legacy_db_name' => 'besoiu_tecdoc_base',
    'tecdoc_legacy_db_host' => '127.0.0.1',
    'tecdoc_legacy_db_user' => 'root',
    'tecdoc_legacy_db_pass' => '',

    /** User cu drepturi read legacy + write shop (Laragon: root). */
    'tecdoc_merge_db_host' => null,
    'tecdoc_merge_db_user' => 'root',
    'tecdoc_merge_db_pass' => '',

    /** Folosit doar dacă tecdoc_unified_db = false */
    'tecdoc_db_name' => 'besoiu_tecdoc_base',
    'tecdoc_db_host' => null,
    'tecdoc_db_user' => null,
    'tecdoc_db_pass' => null,

    /**
     * Pentru migrate_base_to_mysql.py din besoiupieseimport:
     * setează MYSQL_DATABASE=besoiupieseauto.ro (sau numele din .env)
     */
    'tecdoc_migrate_target_db' => null,
];
