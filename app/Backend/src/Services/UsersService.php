<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Modules\Users\Model\UsersRepository;
use Besoiu\Modules\Users\Service\UsersService as ModuleUsersService;

/**
 * Bridge compatibilitate — delegă către modulul modules/users/.
 */
class UsersService extends ModuleUsersService
{
    private static ?UsersRepository $legacyRepository = null;

    public function getAllUserss(): array
    {
        return $this->getAll();
    }

    public function getIdUserss($id): array
    {
        return $this->getByRandomId($id);
    }

    public function deleteUsers($id): array
    {
        return $this->deleteUser((int) $id);
    }

    /** @param array<string, mixed>|null $arrayadd */
    public function setArrayadd($arrayadd = null, array $additionalData = [], array $exceptions = []): void
    {
        $this->buildPayload(is_array($arrayadd) ? $arrayadd : null, $additionalData, $exceptions);
    }

    public function getArrayadd(): array
    {
        return $this->getPayload();
    }

    public function addUser(array $data, string $db, array $ex = []): bool
    {
        return parent::addUser($data, $ex);
    }

    /** @param array<string, mixed> $options */
    public function editUsers(array $options = []): bool
    {
        $data = $options['data'] ?? [];
        $exceptions = $options['exceptions'] ?? [];
        $id = isset($options['id']) ? (int) $options['id'] : 0;

        return $this->editUser($id, is_array($data) ? $data : [], is_array($exceptions) ? $exceptions : []);
    }

    public function getById(int $id): ?array
    {
        $rows = $this->getByRandomId($id);

        return $rows[0] ?? null;
    }
}
