<?php

declare(strict_types=1);

namespace Evasystem\Core\Sql;

use InvalidArgumentException;

/**
 * Validează nume de tabele/coloane — previne injecție prin identificatori dinamici.
 */
final class SqlIdentifier
{
    public static function assertTableName(string $tableName): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $tableName)) {
            throw new InvalidArgumentException(sprintf('Nume tabel invalid: %s', $tableName));
        }
    }

    public static function quoteTableName(string $tableName): string
    {
        self::assertTableName($tableName);

        return '`' . str_replace('`', '``', $tableName) . '`';
    }

    public static function assertColumnList(string $columnList): void
    {
        if ($columnList === '*') {
            return;
        }

        foreach (explode(',', $columnList) as $columnName) {
            $columnName = trim($columnName);
            if ($columnName === '') {
                continue;
            }

            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $columnName)) {
                throw new InvalidArgumentException(sprintf('Nume coloană invalid: %s', $columnName));
            }
        }
    }
}
