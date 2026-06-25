<?php

namespace Evasystem\Core\Users;

use Evasystem\Core\AdvancedCRUD;
use Evasystem\Core\Sql\SqlIdentifier;
use PDO;
use Config\Database;
class UsersModel
{
    public static function getUserssAll()
    {
        return AdvancedCRUD::select('users_connect');
    }

    public static function getUserssId($id, $db = 'users_connect', $where = 'randomn_id')
    {
        return AdvancedCRUD::select($db, '*', "WHERE " . $where . " = '$id' ");
    }
    public static function findByLogin($id, $db = 'users_connect', $where = 'login')
    {
        $login = trim((string) $id);
        if ($login === '') {
            return [];
        }

        // login, contact (email) sau nikname — cum introduce operatorul în formular
        return AdvancedCRUD::selectnew(
            $db,
            '*',
            'WHERE LOWER(`login`) = LOWER(:q1) OR LOWER(`contact`) = LOWER(:q2) OR `nikname` = :q3',
            '',
            '1',
            ['q1' => $login, 'q2' => $login, 'q3' => $login]
        );
    }

    public static function updatePasswordHash(int $randomnId, string $hash, string $db = 'users_connect'): bool
    {
        if ($randomnId <= 0 || $hash === '') {
            return false;
        }

        return AdvancedCRUD::update(
            $db,
            ['password' => $hash],
            'WHERE randomn_id = ' . (int) $randomnId
        );
    }

    public static function createTask($taskData, $db = 'users_connect')
    {
        return AdvancedCRUD::create($db, $taskData);
    }

    public static function updateTask($taskId, $taskData)
    {
        return AdvancedCRUD::update('users_connect', $taskData, "WHERE id = $taskId");
    }

    public static function del($taskId, $db = 'users_connect', $where = 'randomn_id')
    {
        return AdvancedCRUD::delete($db, "WHERE $where = $taskId");
    }

    public static function udape($taskId, $taskData, $db = 'users_connect')
    {
        return AdvancedCRUD::update($db, $taskData, "WHERE randomn_id = $taskId");
    }
}
