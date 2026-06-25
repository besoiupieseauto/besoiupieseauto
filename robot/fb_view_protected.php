<?php
/**
 * robot/fb_view_protected.php
 *
 * Wrapper PHP cu auth guard pentru UI-ul fb_view.html (HTML pur fara PHP).
 * Iframe-ul din admin deschide acest wrapper, nu fb_view.html direct.
 */
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/fb_view.html');
