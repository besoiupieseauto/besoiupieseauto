<?php
declare(strict_types=1);

namespace Besoiu\Modules\Users\Controller;

use Besoiu\Core\AdminUrl;
use Besoiu\Core\Auth\AdminChatPermissionCatalog;
use Besoiu\Core\Auth\AdminPermissionCatalog;
use Besoiu\Core\Auth\AdminWorkspace;
use Besoiu\Modules\Users\Service\UsersService;
use Exception;
use League\OAuth2\Client\Provider\Google;

final class UsersController
{
    /** @var array<string, mixed> */
    private array $payload = [];

    /** @var list<string> */
    private const EXCEPTIONS = [
        'type', 'type_product', 'id', 'idusers', 'ridusers', 'randomnid',
        'usersveryfi', 'experiences', 'duct',
    ];

    /** @var list<string> */
    private const ALLOWED = [
        'nikname', 'fullname', 'login', 'password', 'contact',
        'role', 'permissions_json', 'chat_permissions_json', 'closs', 'status',
        'id_users', 'connect_id', 'randomn_id', 'datainsert', 'datareg',
    ];

    public function __construct(
        private readonly UsersService $usersService,
    ) {
        self::ensureAdminSession();
    }

    private static function ensureAdminSession(): void
    {
        $root = defined('BESOIU_ROOT')
            ? (string) BESOIU_ROOT
            : dirname(__DIR__, 3);
        $bridge = $root . '/app/Legacy/session-bridge.php';
        if (is_file($bridge)) {
            require_once $bridge;
            besoiu_admin_session_start();

            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('PHPSESSID');
            session_start();
        }
    }

    /* ======================= GOOGLE OAUTH ======================= */

    private function googleProvider(): Google
    {
        $appUrl = AdminUrl::siteBaseUrl();
        $redirectUri = $appUrl . '/public/auth/google/callback';

        return new Google([
            'clientId' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
            'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
            'redirectUri' => $redirectUri,
        ]);
    }

    public function googleRedirect(): void
    {
        $provider = $this->googleProvider();
        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['openid', 'email', 'profile'],
            'prompt' => 'select_account',
        ]);
        $_SESSION['oauth2state'] = $provider->getState();
        header('Location: ' . $authUrl);
        exit;
    }

    public function googleCallback(): void
    {
        $provider = $this->googleProvider();

        if (empty($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth2state'] ?? '')) {
            unset($_SESSION['oauth2state']);
            http_response_code(400);
            echo 'Invalid OAuth state';

            return;
        }

        try {
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code'] ?? '',
            ]);

            $owner = $provider->getResourceOwner($token);
            $payload = $owner->toArray();
            $googleId = (string) $owner->getId();
            $email = (string) ($payload['email'] ?? '');
            $name = (string) ($payload['name'] ?? ($payload['given_name'] ?? ''));

            $user = $this->usersService->findByGoogleOrEmail($googleId, $email);

            if (!$user) {
                $userId = $this->usersService->registerFromGoogle([
                    'login' => $email,
                    'fullname' => $name,
                    'nikname' => $name,
                    'contact' => $email,
                    'connect_id' => $googleId,
                    'status' => '1',
                    'role' => 'user',
                ]);
                $user = $userId > 0 ? ($this->usersService->getByRandomId($userId)[0] ?? null) : null;
            }

            if (!$user || empty($user['id'])) {
                http_response_code(400);
                echo 'Google auth error: nu s-a putut crea/recupera utilizatorul.';

                return;
            }

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role'] = $user['role'];
            session_regenerate_id(true);

            header('Location: /public/firms');
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo 'Google auth error: ' . htmlspecialchars($e->getMessage());
        }
    }

    /** @param array<string, mixed> $postData */
    public function setPayload(array $postData = [], array $additionalData = []): void
    {
        $this->payload = array_merge(
            $this->sanitizePayload($postData),
            $this->sanitizePayload($additionalData)
        );
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $data */
    public function addProfileInfo(array $data = []): array
    {
        $usersConnect = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) && $value !== '') {
                $usersConnect[$key] = trim((string) $value);
            }
        }

        try {
            if ($usersConnect === []) {
                throw new \InvalidArgumentException('Payload gol.');
            }

            $isUpdate = !empty($data['ridusers']);

            if (!$isUpdate) {
                if (empty($_SESSION['user_id'])) {
                    throw new \RuntimeException('Înregistrarea publică este dezactivată.');
                }
                $requestedRole = strtolower(trim((string) ($usersConnect['role'] ?? 'operator')));
                $allowedRoles = ['manager', 'super_ambassador', 'regional_ambassador', 'executive', 'operator'];
                if (!in_array($requestedRole, $allowedRoles, true)) {
                    throw new \InvalidArgumentException('Rol invalid.');
                }
                $creatorRole = strtolower((string) ($_SESSION['role'] ?? ''));
                if ($requestedRole === 'super_ambassador' && $creatorRole !== 'super_ambassador') {
                    throw new \RuntimeException('Doar super_ambassador poate crea conturi super_ambassador.');
                }
                $usersConnect['role'] = $requestedRole;

                if (empty($usersConnect['login'])) {
                    throw new \InvalidArgumentException('Login lipsă.');
                }
                $login = $usersConnect['login'];
                if (!filter_var($login, FILTER_VALIDATE_EMAIL)
                    && !preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $login)) {
                    throw new \InvalidArgumentException('Login invalid (email sau 3–64 [a-zA-Z0-9._-]).');
                }

                if (!empty($usersConnect['password'])) {
                    $this->validatePassword((string) $usersConnect['password']);
                    $usersConnect['password'] = password_hash((string) $usersConnect['password'], PASSWORD_DEFAULT);
                }

                if ($this->usersService->findByLogin($login)) {
                    throw new \RuntimeException('Login deja folosit.');
                }
            } else {
                if (!empty($usersConnect['password'])) {
                    $this->validatePassword((string) $usersConnect['password']);
                    $usersConnect['password'] = password_hash((string) $usersConnect['password'], PASSWORD_DEFAULT);
                }
            }

            $exceptions = ['type', 'idusers', 'randomnid', 'type_product', 'duct', 'ridusers'];

            if ($isUpdate) {
                if (empty($data['ridusers'])) {
                    throw new \InvalidArgumentException('ID (ridusers) lipsă pentru update.');
                }
                $affected = $this->usersService->editUser((int) $data['ridusers'], $usersConnect, $exceptions);
                if (!$affected) {
                    throw new \RuntimeException('Nicio linie actualizată.');
                }

                return [
                    'success' => true,
                    'message' => 'Profil actualizat.',
                    'results' => ['updated' => 1],
                ];
            }

            if (!$this->usersService->addUser($usersConnect, $exceptions)) {
                throw new \RuntimeException('Inserția a eșuat.');
            }

            return [
                'success' => true,
                'message' => 'Profil creat.',
                'results' => ['insert_id' => 1],
            ];
        } catch (\Throwable $e) {
            error_log('[UsersModule.addProfileInfo] ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Update error: ' . $e->getMessage(),
            ];
        }
    }

    /** @param array<string, mixed> $data */
    public function login(array $data = []): array
    {
        $log = static function (string $message, array $context = []): void {
            unset($context['password'], $context['stored'], $context['hash']);
            error_log('[UsersModule.login] ' . $message . ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE));
        };

        $login = trim((string) ($data['login'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $guardPath = dirname(__DIR__, 4) . '/system/shop-order-guard.php';
        if (!is_file($guardPath)) {
            $guardPath = dirname(__DIR__, 4) . '/app/Legacy/shop-order-guard.php';
        }
        if (is_file($guardPath)) {
            require_once $guardPath;
        }
        if (function_exists('admin_login_rate_limit_check') && !admin_login_rate_limit_check(40)) {
            usleep(300000);

            return ['success' => false, 'message' => 'Prea multe încercări de login. Așteaptă 10–15 minute sau șterge cache-ul din admin/storage/rate_limits/.'];
        }

        if ($login === '' || $password === '') {
            return ['success' => false, 'message' => 'Completează login și parola.'];
        }

        try {
            $this->validateLogin($login);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $rec = $this->usersService->findByLogin($login);
        if (!$rec) {
            $log('user not found', ['login' => $login]);
            usleep(300000);

            return ['success' => false, 'message' => 'Login sau parolă incorectă.'];
        }

        $row = $rec[0] ?? $rec;
        if (!is_array($row)) {
            $log('unexpected record format', ['type' => gettype($rec)]);

            return ['success' => false, 'message' => 'Eroare internă.'];
        }

        $stored = rtrim((string) ($row['password'] ?? ''), " \t\n\r\0\x0B");
        if ($stored === '') {
            $log('empty stored password', ['login' => $login]);

            return ['success' => false, 'message' => 'Login sau parolă incorectă.'];
        }

        $ok = false;
        $info = password_get_info($stored);

        if ($info['algo'] !== 0) {
            $ok = password_verify($password, $stored);
            if ($ok && password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                try {
                    $this->usersService->updatePasswordHash(
                        (int) ($row['randomn_id'] ?? 0),
                        password_hash($password, PASSWORD_DEFAULT)
                    );
                    $log('password rehashed', ['user_id' => (int) ($row['randomn_id'] ?? 0)]);
                } catch (\Throwable $e) {
                    $log('rehash failed', ['err' => $e->getMessage()]);
                }
            }
        } elseif (strlen($stored) === 32 && ctype_xdigit($stored) && hash_equals($stored, md5($password))) {
            $ok = true;
            try {
                $this->usersService->updatePasswordHash(
                    (int) ($row['randomn_id'] ?? 0),
                    password_hash($password, PASSWORD_DEFAULT)
                );
                $log('legacy password upgraded', ['user_id' => (int) ($row['randomn_id'] ?? 0)]);
            } catch (\Throwable $e) {
                $log('legacy upgrade failed', ['err' => $e->getMessage()]);
            }
        }

        if (!$ok) {
            $log('password mismatch', ['login' => $login]);
            usleep(300000);

            return ['success' => false, 'message' => 'Login sau parolă incorectă.'];
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $userId = (int) $row['randomn_id'];
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = (string) ($row['role'] ?? 'manager');
        $_SESSION['user_login'] = (string) ($row['login'] ?? $login);
        $_SESSION['user_name'] = (string) ($row['fullname'] ?? $row['nikname'] ?? $login);

        if (class_exists(AdminPermissionCatalog::class)) {
            $_SESSION['admin_permissions'] = AdminPermissionCatalog::normalizePermissions(
                $row['permissions_json'] ?? null,
                (string) ($row['role'] ?? 'manager')
            );
            $_SESSION['admin_permissions_delegated'] = is_string($row['permissions_json'] ?? null)
                && trim((string) $row['permissions_json']) !== '';
        }
        if (class_exists(AdminChatPermissionCatalog::class)) {
            $_SESSION['admin_chat_permissions_raw'] = $row['chat_permissions_json'] ?? null;
            $_SESSION['admin_chat_permissions'] = AdminChatPermissionCatalog::normalizePermissions(
                $_SESSION['admin_chat_permissions_raw'],
                (string) ($row['role'] ?? 'manager')
            );
        }

        $log('login ok', ['user_id' => $userId]);

        $next = trim((string) ($data['next'] ?? $_GET['next'] ?? ''));
        if ($next !== '' && str_starts_with($next, '/admin/')) {
            $redirect = $next;
        } else {
            $redirect = class_exists(AdminWorkspace::class)
                ? AdminWorkspace::redirectAfterLogin()
                : AdminUrl::path('workspace');
        }

        return [
            'success' => true,
            'id' => $userId,
            'redirect' => $redirect,
        ];
    }

    /** @param array<string, mixed> $postData */
    public function editStatus(array $postData): void
    {
        $id = $postData['id'] ?? $postData['ridusers'] ?? null;
        if (!$id) {
            throw new \InvalidArgumentException('ID lipsă pentru update.');
        }

        $clean = $this->sanitizePayload($postData);
        if (!empty($clean['password'])) {
            $this->validatePassword((string) $clean['password']);
            $clean['password'] = password_hash((string) $clean['password'], PASSWORD_DEFAULT);
        }

        $this->usersService->editUser((int) $id, $clean, self::EXCEPTIONS);
    }

    public function edit(string $taskId, array $postData): void
    {
        $this->setPayload($postData);
        $this->usersService->updateTaskInfo($taskId, $this->payload);
    }

    /** @param array<string, mixed> $data */
    private function sanitizePayload(array $data): array
    {
        $filtered = array_diff_key($data, array_flip(self::EXCEPTIONS));
        $clean = [];
        foreach ($filtered as $key => $value) {
            if (!in_array($key, self::ALLOWED, true)) {
                continue;
            }
            if (is_scalar($value) && $value !== '') {
                $clean[$key] = trim((string) $value);
            }
        }

        return $clean;
    }

    private function validateLogin(string $login): void
    {
        $login = trim($login);
        if ($login === '') {
            throw new \InvalidArgumentException('Login obligatoriu.');
        }
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $login)) {
            throw new \InvalidArgumentException('Login invalid (email sau 3–64 [a-zA-Z0-9._-]).');
        }
    }

    private function validatePassword(string $password): void
    {
        if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $password)) {
            throw new \InvalidArgumentException('Parola minim 8 caractere, cu cel puțin o literă mare, una mică și o cifră.');
        }
    }
}
