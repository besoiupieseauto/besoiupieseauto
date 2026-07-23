<?php
/*
 * ============================================================================
 * FIȘIER: app/Backend/config/Database.php (alias autoload Composer — admin)
 * ============================================================================
 * Scop: Punct de încărcare PSR-4 pentru namespace Config\ (Composer mapează
 *       'Config\\' => app/Backend/config). Clasa reală, unică, e definită în
 *       app/Config/Database.php (sursă canonică, partajată storefront+admin).
 *       Acest fișier NU redeclară clasa — doar o include, ca să nu existe
 *       două implementări divergente ale Config\Database în timp.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../Config/Database.php';
