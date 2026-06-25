<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Users;

use Evasystem\Controllers\Users\UsersService;          // păstrat cum ai cerut
use League\OAuth2\Client\Provider\Google;              // necesar pentru OAuth
use Exception;

class Users
{
    private UsersService $usersService;
    private array $arrayAdd = [];

    /** câmpuri de exclus din payload */
    private const EXCEPTIONS = [
        'type','type_product','id','idusers','ridusers','randomnid','usersveryfi','experiences','duct'
    ];

    /** whitelist de coloane permise în users_connect */
    private const ALLOWED = [
        'nikname','fullname','login','password','contact',
        'role','permissions_json','closs','status','id_users','connect_id','randomn_id',
        'datainsert','datareg'
    ];

    public function __construct(UsersService $usersService)
    {
        $this->usersService = $usersService;
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    /* ======================= GOOGLE OAUTH ======================= */

    private function googleProvider(): Google {
        $appUrl = \Evasystem\Core\AdminUrl::siteBaseUrl();
        $redirectUri = $appUrl . '/public/auth/google/callback';
        return new Google([
            'clientId'     => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
            'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
            'redirectUri'  => $redirectUri,
        ]);
    }

    /** GET /public/auth/google */
    public function googleRedirect(): void {
        $provider = $this->googleProvider();
        $authUrl = $provider->getAuthorizationUrl([
            'scope'  => ['openid', 'email', 'profile'],
            'prompt' => 'select_account',
        ]);
        $_SESSION['oauth2state'] = $provider->getState();
        header('Location: ' . $authUrl);
        exit;
    }

    /** GET /public/auth/google/callback */
    public function googleCallback(): void {
        $provider = $this->googleProvider();

        if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth2state'] ?? '')) {
            unset($_SESSION['oauth2state']);
            http_response_code(400);
            echo 'Invalid OAuth state';
            return;
        }

        try {
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code'] ?? ''
            ]);

            $owner    = $provider->getResourceOwner($token);
            $payload  = $owner->toArray();
            $googleId = (string)$owner->getId();
            $email    = (string)($payload['email'] ?? '');
            $name     = (string)($payload['name'] ?? ($payload['given_name'] ?? ''));

            // căutăm user după connect_id sau login(email)
            $user = null;
            if (method_exists($this->usersService, 'findByGoogleOrEmail')) {
                $user = $this->usersService->findByGoogleOrEmail($googleId, $email);
            }

            // nu există → creăm
            if (!$user) {
                if (method_exists($this->usersService, 'registerFromGoogle')) {
                    $userId = $this->usersService->registerFromGoogle([
                        'login'      => $email,
                        'fullname'   => $name,
                        'nikname'    => $name,
                        'contact'    => $email,
                        'connect_id' => $googleId,
                        'status'     => '1',
                        'role'       => 'user',
                    ]);
                    $user = method_exists($this->usersService, 'getById') ? $this->usersService->getById((int)$userId) : null;
                }
            }

            if (!$user || empty($user['id'])) {
                http_response_code(400);
                echo 'Google auth error: nu s-a putut crea/recupera utilizatorul.';
                return;
            }

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role'] = $user['role'];
            session_regenerate_id(true);

            header('Location: /public/firms');
            exit;

        } catch (Exception $e) {
            http_response_code(400);
            echo 'Google auth error: ' . htmlspecialchars($e->getMessage());
        }
    }

    /* =================== HELPERE LOCALE (private) =================== */

    /** elimină EXCEPTIONS, păstrează doar ALLOWED, face trim și doar scalare */
    private function sanitizePayload(array $data): array {
        $filtered = array_diff_key($data, array_flip(self::EXCEPTIONS));
        $clean = [];
        foreach ($filtered as $k => $v) {
            if (!in_array($k, self::ALLOWED, true)) continue;
            if (is_scalar($v) && $v !== '') $clean[$k] = trim((string)$v);
        }
        return $clean;
    }

    /** login: email valid SAU username [a-zA-Z0-9._-]{3,64} */
    private function validateLogin(string $login): void {
        $login = trim($login);
        if ($login === '') throw new \InvalidArgumentException('Login obligatoriu.');
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) return;
        if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $login)) {
            throw new \InvalidArgumentException('Login invalid (email sau 3–64 [a-zA-Z0-9._-]).');
        }
    }

    /** parolă: min 8, cel puțin o literă mare, una mică și o cifră */
    private function validatePassword(string $password): void {
        if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $password)) {
            throw new \InvalidArgumentException('Parola minim 8 caractere, cu cel puțin o literă mare, una mică și o cifră.');
        }
    }

    /* ====================== API-urile tale publice ===================== */

    public function setArrayAdd(array $postData = [], array $additionalData = []): void
    {
        // folosește aceeași regulă de sanitație peste tot
        $this->arrayAdd = array_merge(
            $this->sanitizePayload($postData),
            $this->sanitizePayload($additionalData)
        );
    }

    public function getArrayAdd(): array
    {
        return $this->arrayAdd;
    }

    /**
     * Creează/actualizează profil în users_connect
     * - dacă vine 'ridusers' → UPDATE
     * - altfel → INSERT (cu validare login/parolă)
     */
    public function addProfileInfo(array $data = []): array
    {
        // 1) sanitize minim (păstrează doar scalare nenule)
        $usersConnect = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) && $value !== '') {
                $usersConnect[$key] = trim((string)$value);
            }
        }

        try {
            if (empty($usersConnect)) {
                throw new \InvalidArgumentException('Payload gol.');
            }

            $isUpdate = !empty($data['ridusers']);

            // 2) validări pentru CREATE (login + password)
            if (!$isUpdate) {
                $requestedRole = strtolower(trim((string)($usersConnect['role'] ?? 'manager')));
                $allowedRoles = ['manager', 'super_ambassador', 'regional_ambassador', 'executive', 'operator'];
                if (!in_array($requestedRole, $allowedRoles, true)) {
                    throw new \InvalidArgumentException('Rol invalid.');
                }
                $creatorRole = strtolower((string)($_SESSION['role'] ?? ''));
                if ($requestedRole === 'super_ambassador' && $creatorRole !== 'super_ambassador') {
                    throw new \RuntimeException('Doar super_ambassador poate crea conturi super_ambassador.');
                }
                $usersConnect['role'] = $requestedRole;

                // login obligatoriu: email valid SAU username [a-zA-Z0-9._-]{3,64}
                if (empty($usersConnect['login'])) {
                    throw new \InvalidArgumentException('Login lipsă.');
                }
                $login = $usersConnect['login'];
                if (!filter_var($login, FILTER_VALIDATE_EMAIL) &&
                    !preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $login)) {
                    throw new \InvalidArgumentException('Login invalid (email sau 3–64 [a-zA-Z0-9._-]).');
                }

                // parolă (dacă este prezentă): min 8, 1 majusculă, 1 minusculă, 1 cifră
                if (!empty($usersConnect['password'])) {
                    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $usersConnect['password'])) {
                        throw new \InvalidArgumentException('Parola minim 8 caractere, cu majusculă, minusculă și cifră.');
                    }
                    $usersConnect['password'] = password_hash($usersConnect['password'], PASSWORD_DEFAULT);
                }

                // opțional: login unic, dacă ai metoda
                if (method_exists($this->usersService, 'findByLogin') &&
                    $this->usersService->findByLogin($login)) {
                    throw new \RuntimeException('Login deja folosit.');
                }
            } else {
                // UPDATE: dacă vine parolă, validează + hash
                if (!empty($usersConnect['password'])) {
                    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $usersConnect['password'])) {
                        throw new \InvalidArgumentException('Parola minim 8 caractere, cu majusculă, minusculă și cifră.');
                    }
                    $usersConnect['password'] = password_hash($usersConnect['password'], PASSWORD_DEFAULT);
                }
            }

            // 3) execută și VERIFICĂ rezultatul
            $exceptions = ['type', 'idusers', 'randomnid', 'type_product', 'duct', 'ridusers'];

            if ($isUpdate) {
                if (empty($data['ridusers'])) {
                    throw new \InvalidArgumentException('ID (ridusers) lipsă pentru update.');
                }
                $affected = $this->usersService->editUsers([
                    'data'       => $usersConnect,
                    'db'         => 'users_connect',
                    'id'         => $data['ridusers'],
                    'exceptions' => $exceptions
                ]);

                if (!$affected) { // 0 sau false
                    throw new \RuntimeException('Nicio linie actualizată.');
                }

                return [
                    'success' => true,
                    'message' => 'Profil actualizat.',
                    'results' => ['updated' => (int)$affected]
                ];
            }

            // INSERT
            $insertId = $this->usersService->addUser(
                $usersConnect,
                'users_connect',
                $exceptions
            );

            if (!$insertId) {
                throw new \RuntimeException('Inserția a eșuat (ID=0).');
            }

            return [
                'success' => true,
                'message' => 'Profil creat.',
                'results' => ['insert_id' => (int)$insertId]
            ];

        } catch (\Throwable $e) {
            // doar în log, nu în output
            error_log('[Users.addProfileInfo] ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Update error: ' . $e->getMessage()
            ];
        }
    }
    public function login(array $data = []): array
    {
        // === mini helper pt. log (NU loga parole/hash) ===
        $log = function(string $m, array $ctx = []) {
            unset($ctx['password'], $ctx['stored'], $ctx['hash']);
            \error_log('[Users.login] '.$m.' | '.\json_encode($ctx, JSON_UNESCAPED_UNICODE));
        };

        $login    = \trim((string)($data['login'] ?? ''));
        $password = (string)($data['password'] ?? '');

        // 1) Validări de bază
        if ($login === '' || $password === '') {
            return ['success' => false, 'message' => 'Completează login și parola.'];
        }
        try {
            if (\method_exists($this, 'validateLogin')) {
                $this->validateLogin($login);
            }
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        // La login NU validăm politica parolei noi — doar hash-ul din BD (MD5 legacy sau bcrypt).

        // 2) Caută utilizatorul
        $rec = $this->usersService->findByLogin($login);
        if (!$rec) {
            $log('user not found', ['login' => $login]);
            \usleep(300000); // mic delay anti-bruteforce (optional)
            return ['success' => false, 'message' => 'Login sau parolă incorectă.'];
        }
        $row = $rec[0] ?? $rec;
        if (!\is_array($row)) {
            $log('unexpected record format', ['type' => gettype($rec)]);
            return ['success' => false, 'message' => 'Eroare internă.'];
        }

        // 3) Verifică parola (suportă și migrarea din MD5)
        $stored = (string)($row['password'] ?? '');
        if ($stored === '') {
            $log('empty stored password', ['login' => $login]);
            return ['success' => false, 'message' => 'Login sau parolă incorectă.'];
        }

        $ok = false;

// Normalizează hash-ul din BD (fără whitespace de final/padding)
        $stored = rtrim((string)($row['password'] ?? ''), " \t\n\r\0\x0B");

// Detectează dacă e hash modern creat cu password_hash (bcrypt/argon2*)
        $info = \password_get_info($stored); // ['algo' => 0 dacă e "unknown", ['algoName' => 'bcrypt'/'argon2id' etc.]

        if ($info['algo'] !== 0) {
            // 3a) Hash modern
            $ok = \password_verify($password, $stored);

            if ($ok && \password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                try {
                    $this->usersService->updatePasswordHash((int)($row['randomn_id'] ?? 0), \password_hash($password, PASSWORD_DEFAULT));
                    $log('password rehashed', ['user_id' => (int)($row['randomn_id'] ?? 0), 'algo' => $info['algoName'] ?? null]);
                } catch (\Throwable $e) {
                    $log('rehash failed', ['err' => $e->getMessage()]);
                }
            }
        } else {
            // 3b) Legacy: MD5 în BD -> acceptă o dată și migrează
            if (\strlen($stored) === 32 && \ctype_xdigit($stored) && \hash_equals($stored, \md5($password))) {
                $ok = true;
                try {
                    $this->usersService->updatePasswordHash((int)($row['randomn_id'] ?? 0), \password_hash($password, PASSWORD_DEFAULT));
                    $log('legacy password upgraded', ['user_id' => (int)($row['randomn_id'] ?? 0)]);
                } catch (\Throwable $e) {
                    $log('legacy upgrade failed', ['err' => $e->getMessage()]);
                }
            } elseif (
                \strlen($stored) < 72
                && !\str_starts_with($stored, '$')
                && \hash_equals($stored, $password)
            ) {
                // Parolă salvată plain-text (instalări vechi) — acceptă și migrează la bcrypt
                $ok = true;
                try {
                    $this->usersService->updatePasswordHash((int)($row['randomn_id'] ?? 0), \password_hash($password, PASSWORD_DEFAULT));
                    $log('plaintext password upgraded', ['user_id' => (int)($row['randomn_id'] ?? 0)]);
                } catch (\Throwable $e) {
                    $log('plaintext upgrade failed', ['err' => $e->getMessage()]);
                }
            }
        }

        if (!$ok) {
            $log('password mismatch', ['login' => $login]);
            \usleep(300000);
            return ['success' => false, 'message' => 'Login sau parolă incorectă.'];
        }

        // 4) Sesiune sigură
        if (\session_status() === PHP_SESSION_ACTIVE) {
            \session_regenerate_id(true);
        }
        $userId = (int)$row['randomn_id'];

        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = (string)($row['role'] ?? 'manager');
        $_SESSION['user_login'] = (string)($row['login'] ?? $login);
        $_SESSION['user_name'] = (string)($row['fullname'] ?? $row['nikname'] ?? $login);

        if (class_exists(\Evasystem\Core\Auth\AdminPermissionCatalog::class)) {
            $_SESSION['admin_permissions'] = \Evasystem\Core\Auth\AdminPermissionCatalog::normalizePermissions(
                $row['permissions_json'] ?? null,
                (string) ($row['role'] ?? 'manager')
            );
            $_SESSION['admin_permissions_delegated'] = is_string($row['permissions_json'] ?? null)
                && trim((string) $row['permissions_json']) !== '';
        }
        $sesionid = $_SESSION['user_id'];
        $log('login ok', ['user_id' => $userId]);

        $next = trim((string)($data['next'] ?? $_GET['next'] ?? ''));
        if ($next !== '' && str_starts_with($next, '/admin/')) {
            $redirect = $next;
        } else {
            $redirect = class_exists(\Evasystem\Core\Auth\AdminWorkspace::class)
                ? \Evasystem\Core\Auth\AdminWorkspace::redirectAfterLogin()
                : '/admin/dashboard';
        }

        return [
            'success'  => true,
            'id'       => $sesionid,
            'redirect' => $redirect,
        ];
    }
    public function edit(string $taskId, array $postData): void
    {
        $this->setArrayAdd($postData);
        // presupun un service updateTaskInfo existent
        $this->usersService->updateTaskInfo($taskId, $this->arrayAdd);
    }

    public function editStatus(array $postData): void
    {
        // ID separat, ca să nu fie injectat în payload
        $id = $postData['id'] ?? $postData['ridusers'] ?? null;
        if (!$id) throw new \InvalidArgumentException('ID lipsă pentru update.');

        $clean = $this->sanitizePayload($postData);

        if (!empty($clean['password'])) {
            $this->validatePassword($clean['password']);
            $clean['password'] = password_hash($clean['password'], PASSWORD_DEFAULT);
        }

        $this->usersService->editUsers([
            'data'       => $clean,
            'db'         => 'users_connect',
            'id'         => $id,
            'exceptions' => self::EXCEPTIONS
        ]);
    }
}
