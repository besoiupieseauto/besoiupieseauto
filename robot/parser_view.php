<?php
/**
 * robot/parser_view.php
 *
 * Wrapper PHP cu auth guard pentru UI-ul parser.html (HTML pur fara PHP).
 * Iframe-ul din admin (/admin/roboti.parser) deschide acest wrapper,
 * nu parser.html direct, pentru a forta autentificare.
 */
require_once __DIR__ . '/auth_guard.php';
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/parser.html');
