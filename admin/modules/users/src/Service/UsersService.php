<?php
declare(strict_types=1);

namespace Besoiu\Modules\Users\Service;

use Besoiu\Modules\Users\Model\UsersRepository;

final class UsersService
{
    /** @var array<string, mixed> */
    private array $payload = [];

    public function __construct(
        private readonly UsersRepository $repository = new UsersRepository(),
    ) {
    }

    public function getAll(): array
    {
        return $this->repository->findAll();
    }

    public function getByRandomId(int|string $id): array
    {
        return $this->repository->findById($id);
    }

    public function findByLogin(string $login): array
    {
        return $this->repository->findByLogin($login);
    }

    public function findByGoogleOrEmail(string $googleId, string $email): ?array
    {
        if ($googleId !== '') {
            $user = $this->repository->findByConnectId($googleId);
            if ($user !== null) {
                return $user;
            }
        }

        if ($email !== '') {
            $rows = $this->repository->findByLogin($email);

            return $rows[0] ?? null;
        }

        return null;
    }

    public function updatePasswordHash(int $randomnId, string $hash): bool
    {
        return $this->repository->updatePasswordHash($randomnId, $hash);
    }

    public function deleteUser(int $randomnId): array
    {
        $ok = $this->repository->deleteByRandomId($randomnId);

        return [
            'success' => $ok,
            'message' => $ok ? 'Utilizator șters.' : 'Ștergerea a eșuat.',
        ];
    }

    public function updateTaskInfo(string $taskId, array $data): void
    {
        $id = (int) $taskId;
        if ($id <= 0) {
            return;
        }

        $this->repository->updateById($id, $data);
    }

    /**
     * @param array<string, mixed>|null $source
     * @param array<string, mixed> $additional
     * @param list<string> $exceptions
     */
    public function buildPayload(?array $source, array $additional = [], array $exceptions = []): array
    {
        $src = is_array($source) ? $source : [];
        $filtered = [];

        foreach ($src as $key => $value) {
            if (in_array($key, $exceptions, true)) {
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }
            $filtered[$key] = $trimmed;
        }

        $this->payload = array_merge($filtered, $additional);

        return $this->payload;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function addUser(array $data, array $exceptions = []): bool
    {
        $token = bin2hex(random_bytes(32));
        $randomId = random_int(20, 1000);

        $defaults = [
            'token' => $token,
            'randomn_id' => $randomId,
            'id_users' => '',
            'role' => $data['role'] ?? 'new',
            'status' => $data['status'] ?? '1',
        ];

        $cleaned = $this->buildPayload($data, $defaults, $exceptions);
        $cleaned = array_filter($cleaned, static fn ($v) => $v !== '' && $v !== null);

        if (!$this->repository->create($cleaned)) {
            return false;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            $root = defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4);
            $bridge = $root . '/app/Legacy/session-bridge.php';
            if (is_file($bridge)) {
                require_once $bridge;
                besoiu_admin_session_start();
            } else {
                session_name('PHPSESSID');
                session_start();
            }
        }

        $_SESSION['user_id'] = $randomId;

        $expire = time() + 86400 * 30;
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie('UserAcces', 'true', [
            'expires' => $expire,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setcookie('token', $token, [
            'expires' => $expire,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return true;
    }

    public function editUser(int $randomnId, array $data, array $exceptions = []): bool
    {
        if ($randomnId <= 0) {
            throw new \InvalidArgumentException('ID invalid pentru update.');
        }

        $payload = $this->buildPayload($data, [], $exceptions);
        if ($payload === []) {
            return false;
        }

        return $this->repository->updateByRandomId($randomnId, $payload);
    }

    public function registerFromGoogle(array $data): int
    {
        $randomId = bin2hex(random_bytes(16));
        $payload = [
            'nikname' => (string) ($data['nikname'] ?? ($data['fullname'] ?? '')),
            'fullname' => (string) ($data['fullname'] ?? ''),
            'login' => (string) ($data['login'] ?? ''),
            'password' => '',
            'contact' => (string) ($data['contact'] ?? ''),
            'role' => (string) ($data['role'] ?? 'user'),
            'closs' => (string) ($data['closs'] ?? ''),
            'status' => (string) ($data['status'] ?? '1'),
            'id_users' => '',
            'connect_id' => (string) ($data['connect_id'] ?? ''),
            'randomn_id' => $randomId,
        ];

        if (!$this->repository->create($payload)) {
            return 0;
        }

        return (int) ($this->repository->findIdByRandom($randomId) ?? 0);
    }
}
