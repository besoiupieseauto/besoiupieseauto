<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Users;

use Evasystem\Core\Users\UsersModel;

class UsersService
{
    /** @var array<string,mixed> */
    private array $arrayadd = [];

    /** --- getters --- */
    public function getArrayadd(): array { return $this->arrayadd; }

    public function getAllUserss(): array { return UsersModel::getUserssAll(); }

    public function getIdUserss($id): array { return UsersModel::getUserssId($id); }

    public function deleteUsers($id): array
    {
        $ok = (bool) UsersModel::del($id);
        return [
            'success' => $ok,
            'message' => $ok ? 'Utilizator șters.' : 'Ștergerea a eșuat.',
        ];
    }

    public function updateTaskInfo(string $taskId, array $data): void
    {
        UsersModel::updateTaskInfo($taskId, $data);
    }

    /**
     * Curăță payload-ul:
     * - scoate cheile din $exceptions
     * - păstrează doar valori SCALARE ne-goale
     * - face trim la stringuri
     * - unește cu $additionalData
     */
    public function setArrayadd($arrayadd = null, array $additionalData = [], array $exceptions = []): void
    {
        $src = is_array($arrayadd) ? $arrayadd : [];
        $exceptions = is_array($exceptions) ? $exceptions : [];

        $filtered = [];
        foreach ($src as $k => $v) {
            if (in_array($k, $exceptions, true)) continue;
            if (!is_scalar($v)) continue;
            $vv = trim((string)$v);
            if ($vv === '') continue;
            $filtered[$k] = $vv;
        }

        $this->arrayadd = array_merge($filtered, $additionalData);
    }

    /**
     * Creează user în $db (ex: users_connect), pune role/status implicite,
     * setează sesiunea + cookie-urile și întoarce true/false.
     */
    public function addUser(array $data, string $db, array $ex = []): bool
    {
        // 1) token sigur (devine randomn_id)
        $token = $this->generateToken();
        $randomid = rand(20, 1000);
        // 2) completări implicite
        $taskData = [
            'token' => $token,
            'randomn_id' => $randomid,
            'id_users'   => '',
        ];
        if (empty($data['role']))  $taskData['role']  = 'new';
        if (!isset($data['status']) || $data['status'] === '') $taskData['status'] = '1';

        // 3) curățare + merge
        $this->setArrayadd($data, $taskData, $ex);
        $cleanedData = array_filter($this->getArrayadd(), static function ($v) {
            return $v !== '' && $v !== null;
        });

        // 4) INSERT (UsersModel::createTask trebuie să întoarcă bool)
        $ok = UsersModel::createTask($cleanedData, $db);
        if (!$ok) {
            return false;
        }

        // 5) găsește ID-ul noului user după randomn_id = $token
        //   >>> NECESITĂ UsersModel::findIdByRandom($table, $randomnId)
        $idusers = $this->getIdUserss($randomid);
        if ($idusers) {
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $_SESSION['user_id'] = $randomid;

            // cookie-uri 30 zile
            $expire = time() + 86400 * 30;
            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

            setcookie('UserAcces', 'true', [
                'expires'  => $expire,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            setcookie('token', $token, [
                'expires'  => $expire,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        return true;
    }

    /** Token sigur pentru cookie / randomn_id */
    private function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Update generic; folosește updateById(...) dacă există în Model,
     * altfel cade pe udape(...).
     */
    public function editUsers(array $options = []): bool
    {
        $data       = $options['data']       ?? [];
        $db         = $options['db']         ?? 'users_connect';
        $exceptions = $options['exceptions'] ?? [];
        $id         = isset($options['id']) ? (int)$options['id'] : 0;

        if ($id <= 0) {
            throw new \InvalidArgumentException('ID invalid pentru update.');
        }

        $this->setArrayadd($data, [], $exceptions);
        $payload = $this->getArrayadd();
        if (!$payload) {
            // nimic de actualizat
            return false;
        }

        // Preferă updateById dacă există; altfel păstrează compatibilitatea cu "udape"
        if (is_callable([UsersModel::class, 'updateById'])) {
            return (bool) UsersModel::updateById($id, $payload, $db);
        }

        // fallback pe metoda ta existentă
        if (is_callable([UsersModel::class, 'udape'])) {
            return (bool) UsersModel::udape($id, $payload, $db);
        }

        throw new \RuntimeException('Nu există metodă de update în UsersModel (updateById/udape).');
    }

    /* ---------- helper-e extra opționale (dacă le folosești în Controller) ---------- */


    public function findByLogin($login): array { return UsersModel::findByLogin($login); }

    public function updatePasswordHash(int $randomnId, string $hash): bool
    {
        return UsersModel::updatePasswordHash($randomnId, $hash);
    }
    public function findByGoogleOrEmail(string $googleId, string $email): ?array
    {
        if (is_callable([UsersModel::class, 'findByConnectId']) && $googleId !== '') {
            $u = UsersModel::findByConnectId($googleId);
            if ($u) return $u;
        }
        if (is_callable([UsersModel::class, 'findByLogin']) && $email !== '') {
            $u = UsersModel::findByLogin($email);
            if ($u) return $u;
        }
        return null;
    }

    public function registerFromGoogle(array $data): int
    {
        // pregătește minim payload-ul; dacă ai deja alt flux, poți șterge metoda
        $payload = [
            'nikname'    => (string)($data['nikname'] ?? ($data['fullname'] ?? '')),
            'fullname'   => (string)($data['fullname'] ?? ''),
            'login'      => (string)($data['login'] ?? ''),
            'password'   => '', // fără parolă locală
            'contact'    => (string)($data['contact'] ?? ''),
            'role'       => (string)($data['role'] ?? 'user'),
            'closs'      => (string)($data['closs'] ?? ''),
            'status'     => (string)($data['status'] ?? '1'),
            'id_users'   => '',
            'connect_id' => (string)($data['connect_id'] ?? ''),
            'randomn_id' => $this->generateToken(),
        ];

        // INSERT: dacă ai createTask care întoarce bool, poți înlocui cu o versiune ce întoarce ID
        if (is_callable([UsersModel::class, 'createTask'])) {
            $ok = UsersModel::createTask($payload, 'users_connect');
            if (!$ok) return 0;
            // caută ID-ul după randomn_id:
            return (int) (UsersModel::findIdByRandom('users_connect', $payload['randomn_id']) ?? 0);
        }

        return 0;
    }
}
