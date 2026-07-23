<?php

declare(strict_types=1);

namespace Besoiu\Core;

use Besoiu\Core\Sql\SqlIdentifier;
use InvalidArgumentException;
use PDO;
use Config\Database;
use RuntimeException;

/**
 * Layer PDO generic — interacțiuni CRUD cu prepared statements.
 *
 * Metode *new / selectPaginated: preferate (parametri legați).
 * Metode legacy (select/create/update/delete): compatibilitate; WHERE trebuie sanitizat de caller.
 */
final class AdvancedCRUD
{
    private static function quotedTable(string $tableName): string
    {
        return SqlIdentifier::quoteTableName($tableName);
    }

    /**
     * @return array<int, array<string, mixed>>
     * @deprecated Preferă selectnew() cu parametri legați
     */
    public static function select(
        string $tableName,
        string $columnList = '*',
        string $whereClause = '',
        string $orderByClause = '',
        string $limitClause = ''
    ): array {
        SqlIdentifier::assertTableName($tableName);
        SqlIdentifier::assertColumnList($columnList);

        $pdo = Database::getDB();
        $quotedTable = self::quotedTable($tableName);
        $sql = "SELECT {$columnList} FROM {$quotedTable}";

        if ($whereClause !== '') {
            $sql .= " {$whereClause}";
        }
        if ($orderByClause !== '') {
            $sql .= " ORDER BY {$orderByClause}";
        }
        if ($limitClause !== '') {
            $sql .= " LIMIT {$limitClause}";
        }

        $statement = $pdo->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, scalar|null> $boundParameters
     * @return array<int, array<string, mixed>>
     */
    public static function selectnew(
        string $tableName,
        string $columnList = '*',
        string $whereClause = '',
        string $orderByClause = '',
        ?string $limitClause = null,
        array $boundParameters = []
    ): array {
        SqlIdentifier::assertTableName($tableName);
        SqlIdentifier::assertColumnList($columnList);

        $pdo = Database::getDB();
        $quotedTable = self::quotedTable($tableName);
        $sql = "SELECT {$columnList} FROM {$quotedTable}";

        if ($whereClause !== '') {
            $sql .= " {$whereClause}";
        }
        if ($orderByClause !== '') {
            $sql .= " ORDER BY {$orderByClause}";
        }
        if ($limitClause !== null && $limitClause !== '') {
            $sql .= " LIMIT {$limitClause}";
        }

        $statement = $pdo->prepare($sql);
        $executed = $statement->execute($boundParameters);

        if (!$executed) {
            return [];
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, scalar|null> $boundParameters
     */
    public static function countnew(string $tableName, string $whereClause = '', array $boundParameters = []): int
    {
        SqlIdentifier::assertTableName($tableName);

        $pdo = Database::getDB();
        $sql = 'SELECT COUNT(*) FROM ' . self::quotedTable($tableName);

        if ($whereClause !== '') {
            $sql .= " {$whereClause}";
        }

        $statement = $pdo->prepare($sql);
        $statement->execute($boundParameters);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, scalar|null> $boundParameters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function selectPaginated(
        string $tableName,
        string $columnList = '*',
        string $whereClause = '',
        string $orderByClause = '',
        int $pageNumber = 1,
        int $rowsPerPage = Pagination::DEFAULT_PER_PAGE,
        array $boundParameters = []
    ): array {
        SqlIdentifier::assertTableName($tableName);
        SqlIdentifier::assertColumnList($columnList);

        $paginationMeta = Pagination::normalize($pageNumber, $rowsPerPage);
        $quotedTable = self::quotedTable($tableName);
        $sql = "SELECT {$columnList} FROM {$quotedTable}";

        if ($whereClause !== '') {
            $sql .= " {$whereClause}";
        }
        if ($orderByClause !== '') {
            $sql .= " ORDER BY {$orderByClause}";
        }

        $sql .= ' LIMIT ' . (int) $paginationMeta['limit'] . ' OFFSET ' . (int) $paginationMeta['offset'];

        $pdo = Database::getDB();
        $statement = $pdo->prepare($sql);
        $statement->execute($boundParameters);
        $resultItems = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalRows = self::countnew($tableName, $whereClause, $boundParameters);

        return Pagination::envelope(
            $resultItems,
            $totalRows,
            $paginationMeta['page'],
            $paginationMeta['per_page']
        );
    }

    /**
     * @param array<string, mixed> $rowData
     * @deprecated Parolele legacy folosesc md5 — cod nou: password_hash în Service dedicat
     */
    public static function create(string $tableName, array $rowData): bool
    {
        SqlIdentifier::assertTableName($tableName);

        if ($rowData === []) {
            throw new InvalidArgumentException('Nu se poate insera un rând gol.');
        }

        $pdo = Database::getDB();

        // Compatibilitate module vechi — hash securizat
        if (isset($rowData['password']) && is_string($rowData['password']) && $rowData['password'] !== '') {
            $info = password_get_info((string) $rowData['password']);
            if ($info['algo'] === 0) {
                $rowData['password'] = password_hash((string) $rowData['password'], PASSWORD_DEFAULT);
            }
        }

        SqlIdentifier::assertColumnList(implode(', ', array_keys($rowData)));

        $quotedColumns = array_map(
            static fn (string $col): string => '`' . str_replace('`', '``', $col) . '`',
            array_keys($rowData)
        );
        $columnNames = implode(', ', $quotedColumns);
        $placeholders = implode(', ', array_fill(0, count($rowData), '?'));
        $sql = 'INSERT INTO ' . self::quotedTable($tableName) . " ({$columnNames}) VALUES ({$placeholders})";

        $statement = $pdo->prepare($sql);

        return $statement->execute(array_values($rowData));
    }

    /**
     * @param array<string, mixed> $rowData
     */
    public static function update(string $tableName, array $rowData, string $whereClause): bool
    {
        SqlIdentifier::assertTableName($tableName);

        if ($rowData === []) {
            throw new InvalidArgumentException('Nu se poate actualiza fără coloane.');
        }

        $pdo = Database::getDB();
        $setClause = implode(', ', array_map(
            static fn (string $columnName): string => "{$columnName} = ?",
            array_keys($rowData)
        ));
        $sql = 'UPDATE ' . self::quotedTable($tableName) . " SET {$setClause} {$whereClause}";

        $statement = $pdo->prepare($sql);

        return $statement->execute(array_values($rowData));
    }

    public static function delete(string $tableName, string $whereClause): bool
    {
        SqlIdentifier::assertTableName($tableName);

        $pdo = Database::getDB();
        $sql = 'DELETE FROM ' . self::quotedTable($tableName) . " {$whereClause}";
        $statement = $pdo->prepare($sql);

        return $statement->execute();
    }

    /**
     * Șterge înregistrare imagine asociată sesiunii curente.
     */
    public static function deleteImageBySession(string $imageName): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $pdo = Database::getDB();
        $sql = 'DELETE FROM img_bd WHERE nameImg = ? AND id_users = ?';
        $statement = $pdo->prepare($sql);

        return $statement->execute([
            $imageName,
            $_SESSION['user_id'] ?? 0,
        ]);
    }

    /**
     * @deprecated Folosește deleteImageBySession()
     */
    public static function delete_img(string $tableName): bool
    {
        return self::deleteImageBySession($tableName);
    }
}
