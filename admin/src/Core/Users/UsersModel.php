<?php

namespace Besoiu\Core\Users;

use Besoiu\Core\AdvancedCRUD;
use Besoiu\Core\Sql\SqlIdentifier;
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
        $allowed = ['randomn_id', 'id', 'login', 'contact'];
        if (!in_array($where, $allowed, true)) {
            $where = 'randomn_id';
        }

        return AdvancedCRUD::selectnew(
            $db,
            '*',
            'WHERE `' . $where . '` = :id',
            '',
            '1',
            ['id' => (string) $id]
        );
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
        $id = (int) $taskId;
        if ($id <= 0) {
            return false;
        }

        return AdvancedCRUD::update('users_connect', $taskData, 'WHERE id = ' . $id);
    }

    public static function del($taskId, $db = 'users_connect', $where = 'randomn_id')
    {
        SqlIdentifier::assertTableName($db);
        SqlIdentifier::assertColumnList($where);
        $id = (int) $taskId;
        if ($id <= 0) {
            return false;
        }

        return AdvancedCRUD::delete($db, 'WHERE `' . str_replace('`', '', $where) . '` = ' . $id);
    }

    public static function udape($taskId, $taskData, $db = 'users_connect')
    {
        $id = (int) $taskId;
        if ($id <= 0) {
            return false;
        }

        return AdvancedCRUD::update($db, $taskData, 'WHERE randomn_id = ' . $id);
    }
}
