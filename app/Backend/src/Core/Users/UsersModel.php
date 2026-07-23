<?php

declare(strict_types=1);

namespace Besoiu\Core\Users;

use Besoiu\Modules\Users\Model\UsersRepository;

/**
 * Bridge compatibilitate — delegă către modulul modules/users/.
 */
class UsersModel
{
    private static ?UsersRepository $repository = null;

    private static function repo(): UsersRepository
    {
        return self::$repository ??= new UsersRepository();
    }

    public static function getUserssAll()
    {
        return self::repo()->findAll();
    }

    public static function getUserssId($id, $db = 'users_connect', $where = 'randomn_id')
    {
        return self::repo()->findById($id, $where);
    }

    public static function findByLogin($id, $db = 'users_connect', $where = 'login')
    {
        return self::repo()->findByLogin((string) $id);
    }

    public static function updatePasswordHash(int $randomnId, string $hash, string $db = 'users_connect'): bool
    {
        return self::repo()->updatePasswordHash($randomnId, $hash);
    }

    public static function createTask($taskData, $db = 'users_connect')
    {
        return self::repo()->create(is_array($taskData) ? $taskData : []);
    }

    public static function updateTask($taskId, $taskData)
    {
        return self::repo()->updateById((int) $taskId, is_array($taskData) ? $taskData : []);
    }

    public static function del($taskId, $db = 'users_connect', $where = 'randomn_id')
    {
        return self::repo()->deleteByRandomId((int) $taskId);
    }

    public static function udape($taskId, $taskData, $db = 'users_connect')
    {
        return self::repo()->updateByRandomId((int) $taskId, is_array($taskData) ? $taskData : []);
    }

    public static function updateById(int $id, array $payload, string $db = 'users_connect'): bool
    {
        return self::repo()->updateById($id, $payload);
    }

    public static function findByConnectId(string $connectId): ?array
    {
        return self::repo()->findByConnectId($connectId);
    }

    public static function findIdByRandom(string $table, string $randomId): ?int
    {
        return self::repo()->findIdByRandom($randomId);
    }
}
