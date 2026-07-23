<?php
/*
 * ============================================================================
 * FIȘIER: admin/index.php (redirect către front controller admin)
 * ============================================================================
 * Scop: Punct de intrare alternativ pentru panoul admin când serverul web
 *       mapează /admin/ direct la acest fișier. Delegă imediat către
 *       admin/public/index.php unde rulează logica completă HttpApplication.
 *
 * Include/require:
 *   - admin/bootstrap.php — constante BESOIU_ADMIN, BESOIU_APP, etc.
 *   - admin/public/index.php — front controller real al adminului
 *
 * Bază de date: Nu se conectează direct; conexiunea PDO se face în public/index.php.
 * ============================================================================
 */

declare(strict_types=1);

// Bootstrap admin: definește constantele de cale și flag-urile Laragon compact
require_once __DIR__ . '/bootstrap.php';

// Delegă procesarea request-ului către front controller-ul din public/
require __DIR__ . '/public/index.php';
