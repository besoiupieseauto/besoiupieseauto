<?php

declare(strict_types=1);

use Besoiu\Core\AdminUrl;

/**
 * Endpoint-uri API care își gestionează singure autentificarea (token, CSRF, acțiuni publice).
 * Toate celelalte scripturi din admin/public/api/ cer sesiune admin la încărcare (_autoload.php).
 */
return [
  'comenzi_endpoint.php',
  'messages_endpoint.php',
  'supplier_sync_endpoint.php',
  'furnizori_endpoint.php',
];
