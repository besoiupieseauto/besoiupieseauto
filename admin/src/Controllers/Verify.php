<?php
declare(strict_types=1);

namespace Evasystem\Controllers;

use Evasystem\Controllers\Users\UsersService;

class Verify
{
    private UsersService $users;

    public function __construct(?UsersService $users = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $this->users = $users ?? new UsersService();
    }

    /**
     * Verifică sesiunea curentă și redirecționează (sau răspunde JSON 401) dacă nu e validă.
     */
    public function verifyusers(): void
    {
        // Evită verificarea în CLI / joburi
        if (\PHP_SAPI === 'cli') {
            return;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // ==== Allow-list pentru rute publice ====
        // Adaugă aici absolut tot ce trebuie să fie accesibil fără login
        $publicPrefixes = [
            \Evasystem\Core\AdminUrl::path('login'),
            \Evasystem\Core\AdminUrl::path('reg'),
            \Evasystem\Core\AdminUrl::path('addusersadd'),
            \Evasystem\Core\AdminUrl::LEGACY_PREFIX . '/login',
            \Evasystem\Core\AdminUrl::LEGACY_PREFIX . '/reg',
            \Evasystem\Core\AdminUrl::LEGACY_PREFIX . '/addusersadd',
            \Evasystem\Core\AdminUrl::LEGACY_PREFIX . '/pages',
            // '/public/forgot',
            // '/public/reset',
            // '/public/api/some-endpoint',
        ];
        foreach ($publicPrefixes as $prefix) {
            if (\strncmp($path, $prefix, \strlen($prefix)) === 0) {
                return; // page/endpoint public -> nu cerem sesiune
            }
        }

        // ==== Verificare sesiune ====
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            $this->respondUnauthorized(); // JSON 401 pentru AJAX, redirect pt. restul
        }

        try {
            $user = $this->users->getIdUserss((int)$id); // ia DOAR userul curent
        } catch (\Throwable $e) {
            error_log('[Verify] DB error: ' . $e->getMessage());
            $this->respondUnauthorized();
        }

        $row = (is_array($user) && array_key_exists(0, $user)) ? $user[0] : $user;

        if (empty($row)) {
            // sesiune invalidă: curăță și răspunde cu 401/redirect
            unset($_SESSION['user_id']);

            // ajustează atributele la fel ca la creare
            $expireOpts = ['expires' => time() - 3600, 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax'];
            setcookie('UserAcces', '', $expireOpts);
            setcookie('token',     '', $expireOpts);

            session_regenerate_id(true);
            $this->respondUnauthorized();
        }

        // valid -> continuă execuția
    }

    /**
     * Decide dacă request-ul e AJAX / vrea JSON.
     */
    private function wantsJson(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            \strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (\stripos($accept, 'application/json') !== false) {
            return true;
        }
        $ctype = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (\stripos($ctype, 'application/json') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Răspunde cu 401 JSON pentru AJAX, altfel redirect la /public/login?next=...
     */
    private function respondUnauthorized(): never
    {
        $loginUrl = '/admin/login';
        $next     = $_SERVER['REQUEST_URI'] ?? '/';
        $loginWithNext = $loginUrl . '?next=' . \rawurlencode($next);

        if ($this->wantsJson()) {
            // Răspuns pentru AJAX / API
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo \json_encode([
                'ok'       => false,
                'redirect' => $loginUrl,
                'next'     => $next,
                'message'  => 'Necesită autentificare.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Răspuns pentru pagini normale (preferă redirect HTTP, apoi fallback script)
        if (!headers_sent()) {
            header('Location: ' . $loginWithNext, true, 302);
            exit;
        }

        $safe = \htmlspecialchars($loginWithNext, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirect...</title></head><body>';
        echo '<script>try{window.location.assign("'.$safe.'");}catch(e){location.href="'.$safe.'";}</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url='.$safe.'"></noscript>';
        echo '</body></html>';
        exit;
    }
}
