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
    'autodoc' => 
    array (
      'label' => 'Autodoc.ro',
      'enabled' => true,
      'priority' => 40,
      'roles' => 
      array (
        0 => 'image',
        1 => 'title',
        2 => 'oem',
      ),
      'categories' => 
      array (
        0 => '*',
      ),
      'env_required' => 
      array (
        0 => 'SCRAPE_DO_TOKEN',
      ),
      'domain' => 'autodoc.ro',
      'note' => 'Stub — configurează pașii în scraper',
    ),
    'tecdoc_api' => 
    array (
      'label' => 'TecDoc API',
      'enabled' => true,
      'priority' => 50,
      'roles' => 
      array (
        0 => 'image',
        1 => 'description',
        2 => 'oem',
      ),
      'categories' => 
      array (
        0 => '*',
      ),
      'env_required' => 
      array (
        0 => 'RAPIDAPI_AUTOPARTS_KEY',
      ),
      'domain' => 'rapidapi.com',
    ),
  ),
  'audit' => 
  array (
    'on_import_cron' => true,
    'on_import_review' => true,
    'auto_retry_on_mismatch' => true,
    'min_score_keep' => 70,
    'verdicts_retry' => 
    array (
      0 => 'mismatch',
      1 => 'error',
      2 => 'no_image',
    ),
    'prompt_extra' => 'Respinge mașini întregi, logo-uri și imagini fără piesă clară. Acceptă fundal alb. Imaginea trebuie să corespundă titlului și categoriei (filtru, frână, suspensie etc.).',
  ),
);
