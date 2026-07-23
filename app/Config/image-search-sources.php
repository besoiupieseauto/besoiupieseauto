<?php

declare(strict_types=1);

/**
 * AUTO-GENERAT din /admin/scraper — nu edita manual.
 * La adăugare/ștergere sursă sau salvare pipeline, fișierul se rescrie automat.
 * Sursă de adevăr: storage/scraper/sources_registry.json + integration_config.json
 */
return array (
  'sources' => 
  array (
    'caietcomenzi' => 
    array (
      'label' => 'Plan principal',
      'enabled' => true,
      'priority' => 10,
      'roles' => 
      array (
        0 => 'image',
      ),
      'categories' => 
      array (
        0 => '*',
      ),
    ),
    'tecdoc_csv' => 
    array (
      'label' => 'Plan secundar',
      'enabled' => true,
      'priority' => 20,
      'roles' => 
      array (
        0 => 'image',
        1 => 'description',
      ),
      'categories' => 
      array (
        0 => '*',
      ),
    ),
  ),
  'audit' => 
  array (
    'on_import_cron' => false,
    'on_import_review' => true,
    'auto_retry_on_mismatch' => true,
    'min_score_keep' => 70,
    'verdicts_retry' => 
    array (
      0 => 'mismatch',
      1 => 'error',
      2 => 'no_image',
    ),
    'prompt_extra' => '',
  ),
);
