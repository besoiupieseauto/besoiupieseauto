<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

final class QwpDb
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = 'mysql:host=' . qwp_db_host()
            . ';dbname=' . qwp_db_name()
            . ';charset=utf8mb4';

        self::$pdo = new PDO($dsn, qwp_db_user(), qwp_db_pass(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::ensureTable();

        return self::$pdo;
    }

    public static function ensureTable(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 4) . '/SQL/autonet_qwp_data.sql');
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('Lipsește SQL/autonet_qwp_data.sql');
        }

        // Execută doar CREATE TABLE (fără comentarii goale).
        $pdo = self::$pdo ?? new PDO(
            'mysql:host=' . qwp_db_host() . ';dbname=' . qwp_db_name() . ';charset=utf8mb4',
            qwp_db_user(),
            qwp_db_pass(),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        foreach (preg_split('/;\s*\n/', $sql) ?: [] as $chunk) {
            $chunk = trim($chunk);
            if ($chunk !== '' && stripos($chunk, 'CREATE TABLE') !== false) {
                $pdo->exec($chunk);
                break;
            }
        }
    }

    public static function countRows(string $q = ''): int
    {
        $pdo = self::pdo();
        if ($q === '') {
            return (int) $pdo->query('SELECT COUNT(*) FROM autonet_qwp_data')->fetchColumn();
        }

        $like = '%' . $q . '%';
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM autonet_qwp_data
             WHERE ArtNr LIKE ? OR ReferenceBrand LIKE ? OR RefNr LIKE ?'
        );
        $stmt->execute([$like, $like, $like]);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public static function listRows(int $page, int $perPage, string $q = ''): array
    {
        $pdo = self::pdo();
        $offset = max(0, ($page - 1) * $perPage);

        if ($q === '') {
            $stmt = $pdo->prepare(
                'SELECT id, ArtNr, ReferenceBrand, RefNr, created_at, updated_at
                 FROM autonet_qwp_data
                 ORDER BY id ASC
                 LIMIT ? OFFSET ?'
            );
            $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        }

        $like = '%' . $q . '%';
        $stmt = $pdo->prepare(
            'SELECT id, ArtNr, ReferenceBrand, RefNr, created_at, updated_at
             FROM autonet_qwp_data
             WHERE ArtNr LIKE ? OR ReferenceBrand LIKE ? OR RefNr LIKE ?
             ORDER BY id ASC
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $like);
        $stmt->bindValue(2, $like);
        $stmt->bindValue(3, $like);
        $stmt->bindValue(4, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function updateRow(int $id, string $artNr, string $brand, string $refNr): void
    {
        $artNr = trim($artNr);
        $brand = trim($brand);
        $refNr = trim($refNr);

        if ($artNr === '' || $refNr === '') {
            throw InvalidArgumentException('ArtNr și RefNr sunt obligatorii.');
        }

        $stmt = self::pdo()->prepare(
            'UPDATE autonet_qwp_data
             SET ArtNr = ?, ReferenceBrand = ?, RefNr = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->execute([$artNr, $brand, $refNr, $id]);
    }

    public static function insertRow(string $artNr, string $brand, string $refNr): void
    {
        $artNr = trim($artNr);
        $brand = trim($brand);
        $refNr = trim($refNr);

        if ($artNr === '' || $refNr === '') {
            throw InvalidArgumentException('ArtNr și RefNr sunt obligatorii.');
        }

        $stmt = self::pdo()->prepare(
            'INSERT INTO autonet_qwp_data (ArtNr, ReferenceBrand, RefNr)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
               ReferenceBrand = VALUES(ReferenceBrand),
               updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$artNr, $brand, $refNr]);
    }

    public static function deleteRow(int $id): void
    {
        $stmt = self::pdo()->prepare('DELETE FROM autonet_qwp_data WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
    }

    /** @param list<array{artnr:string,brand:string,refnr:string}> $rows */
    public static function importRows(array $rows): array
    {
        $stats = ['inserted' => 0, 'skipped' => 0];
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO autonet_qwp_data (ArtNr, ReferenceBrand, RefNr)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
               ReferenceBrand = VALUES(ReferenceBrand),
               updated_at = CURRENT_TIMESTAMP'
        );

        foreach ($rows as $row) {
            $art = trim((string) ($row['artnr'] ?? ''));
            $brand = trim((string) ($row['brand'] ?? ''));
            $ref = trim((string) ($row['refnr'] ?? ''));
            if ($art === '' || $ref === '') {
                $stats['skipped']++;
                continue;
            }
            $stmt->execute([$art, $brand, $ref]);
            $stats['inserted']++;
        }

        return $stats;
    }
}
