<?php
declare(strict_types=1);

namespace Besoiu\Modules\Users\Model;

use Besoiu\Core\AdvancedCRUD;
use Besoiu\Core\Sql\SqlIdentifier;

/**
 * Repository OOP pentru tabela users_connect.
 */
final class UsersRepository
{
    private string $table = 'users_connect';

    public function findAll(): array
    {
        return AdvancedCRUD::select($this->table);
    }

    public function findById(int|string $id, string $column = 'randomn_id'): array
    {
        $allowed = ['randomn_id', 'id', 'login', 'contact'];
        if (!in_array($column, $allowed, true)) {
            $column = 'randomn_id';
        }

        return AdvancedCRUD::selectnew(
            $this->table,
            '*',
            'WHERE `' . $column . '` = :id',
            '',
            '1',
            ['id' => (string) $id]
        );
    }

    public function findByLogin(string $login): array
    {
        $login = trim($login);
        if ($login === '') {
            return [];
        }

        return AdvancedCRUD::selectnew(
            $this->table,
            '*',
            'WHERE LOWER(`login`) = LOWER(:q1) OR LOWER(`contact`) = LOWER(:q2) OR `nikname` = :q3',
            '',
            '1',
            ['q1' => $login, 'q2' => $login, 'q3' => $login]
        );
    }

    public function findByConnectId(string $connectId): ?array
    {
        if ($connectId === '') {
            return null;
        }

        $rows = AdvancedCRUD::selectnew(
            $this->table,
            '*',
            'WHERE `connect_id` = :cid',
            '',
            '1',
            ['cid' => $connectId]
        );

        return $rows[0] ?? null;
    }

    public function findIdByRandom(string $randomId): ?int
    {
        $rows = AdvancedCRUD::selectnew(
            $this->table,
            'id',
            'WHERE `randomn_id` = :rid',
            '',
            '1',
            ['rid' => $randomId]
        );

        if (!isset($rows[0]['id'])) {
            return null;
        }

        return (int) $rows[0]['id'];
    }

    public function updatePasswordHash(int $randomnId, string $hash): bool
    {
        if ($randomnId <= 0 || $hash === '') {
            return false;
        }

        return AdvancedCRUD::update(
            $this->table,
            ['password' => $hash],
            'WHERE randomn_id = ' . $randomnId
        );
    }

    public function create(array $data): bool
    {
        return AdvancedCRUD::create($this->table, $data);
    }

    public function updateByRandomId(int $randomnId, array $data): bool
    {
        if ($randomnId <= 0) {
            return false;
        }

        return AdvancedCRUD::update($this->table, $data, 'WHERE randomn_id = ' . $randomnId);
    }

    public function updateById(int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }

        return AdvancedCRUD::update($this->table, $data, 'WHERE id = ' . $id);
    }

    public function deleteByRandomId(int $randomnId): bool
    {
        SqlIdentifier::assertTableName($this->table);
        if ($randomnId <= 0) {
            return false;
        }

        return AdvancedCRUD::delete($this->table, 'WHERE randomn_id = ' . $randomnId);
    }
}
