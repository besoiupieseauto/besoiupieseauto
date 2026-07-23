<?php
/*
 * ============================================================================
 * FIȘIER: index.php (punct de intrare public — vitrina / storefront)
 * ============================================================================
 * Scop: Front controller al site-ului public besoiupieseauto.ro.
 *       Orice request HTTP către rădăcina domeniului ajunge aici (via .htaccess
 *       sau configurația serverului web) și este delegat aplicației storefront.
 *
 * Include/require:
 *   - app/bootstrap.php — definește constantele de cale (BESOIU_*) și
 *     încarcă autoloader-ul PSR-4 pentru namespace-ul Storefront\.
 *
 * Bază de date: Nu se conectează direct; conexiunea PDO se deschide lazy
 *               prin Storefront\Core\Database\Connection când un modul o cere.
 * ============================================================================
 */

// Activează verificarea strictă a tipurilor — previne conversii implicite periculoase
declare(strict_types=1);

// Definește calea absolută către rădăcina proiectului (folderul curent al index.php)
define('BESOIU_ROOT', __DIR__);

// Încarcă bootstrap-ul aplicației: constante de cale + autoloader storefront
require __DIR__ . '/app/bootstrap.php';

// Instanțiază aplicația vitrină și pornește procesarea request-ului HTTP curent
(new Storefront\Core\Bootstrap\StorefrontApplication())->run();
