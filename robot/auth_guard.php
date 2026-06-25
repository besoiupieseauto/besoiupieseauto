<?php
/**
 * robot/auth_guard.php
 *
 * Guard pentru tool-urile UI ale modulului Roboti.
 * Cere sesiune admin valida (utilizator logat in /admin).
 *
 * NU include in:
 *   - webhook.php (UltraMsg trimite request-uri externe; valideaza prin WEBHOOK_KEY)
 *   - save-lead.php (formular public de pe site)
 *
 * Include in toate UI-urile tool-urilor:
 *   - chat.php, parser.html (via parser.php), vin.php, tecdoc.php, fb_view.html (via fb_parser.php),
 *     run.php, process.php, tecdoc_proxy.php, pars.php, genereaza_mesaj.php, api.php
 *
 * Daca vrei sa testezi un tool fara login (dev), seteaza ROBOTI_GUARD_BYPASS=1 in .env.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/bootstrap.php';

if ((string) env('ROBOTI_GUARD_BYPASS', '0') === '1') {
    return;
}

$role = $_SESSION['role'] ?? null;
if (!$role || $role === 'guest') {
    $isAjax = (
        (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') ||
        (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) ||
        ($_SERVER['REQUEST_METHOD'] !== 'GET')
    );

    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Acces refuzat. Logheaza-te in /admin/login.']);
        exit;
    }

    $back = urlencode($_SERVER['REQUEST_URI'] ?? '/admin/roboti');
    header('Location: /admin/login?back=' . $back);
    exit;
}
