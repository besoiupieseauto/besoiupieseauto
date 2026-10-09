<?php

declare(strict_types=1);

/**
 * Endpoint-uri API care își gestionează singure autentificarea (token, CSRF, acțiuni publice).
 * Toate celelalte scripturi din admin/public/api/ cer sesiune admin la încărcare (_autoload.php).
 */
return [
  'comenzi_endpoint.php',
  'messages_endpoint.php',
  'supplier_sync_endpoint.php',
  'furnizori_endpoint.php',
  'intercars_pc_ingest.php',
  'intercars_pc_images.php',
  'intercars_image.php',
  // coada de scanare: PC-ul cere loturi de coduri și raportează rezultatul,
  // autentificat cu X-Intercars-PC-Token (vezi intercars_queue.php)
  'intercars_queue.php',
  'caiet_cron.php',
];
