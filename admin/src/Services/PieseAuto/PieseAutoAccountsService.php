<?php

declare(strict_types=1);

namespace Evasystem\Services\PieseAuto;

use Config\Database;
use Evasystem\Exceptions\ValidationException;
use PDO;

/**
 * Conturi PieseAuto.ro — login browser robot.
 */
final class PieseAutoAccountsService
{
    public function ensureTable(): void
    {
        $pdo = Database::getDB();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `pieseauto_accounts` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `randomn_id` INT UNSIGNED NOT NULL,
            `id_users` INT UNSIGNED NOT NULL DEFAULT 0,
            `company_name` VARCHAR(255) NULL DEFAULT NULL,
            `email` VARCHAR(255) NOT NULL DEFAULT '',
            `pas` VARCHAR(255) NOT NULL DEFAULT '',
            `target_user` VARCHAR(64) NOT NULL DEFAULT 'besoiu',
            `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_randomn_id` (`randomn_id`),
            KEY `idx_id_users` (`id_users`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** @return list<array<string, mixed>> */
    public function listForCurrentUser(): array
    {
        $this->ensureTable();
        $pdo = Database::getDB();
        $stmt = $pdo->prepare(
            'SELECT randomn_id, id_users, company_name, email, pas, target_user, created_at, updated_at
             FROM pieseauto_accounts WHERE id_users = :uid ORDER BY updated_at DESC, id DESC'
        );
        $stmt->execute(['uid' => $this->adminUserId()]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, array{id:string,label:string,name:string,email:string,pass:string,target:string}> */
    public function accountsForUi(): array
    {
        $accounts = [];
        foreach ($this->listForCurrentUser() as $row) {
            $accountId = $row['randomn_id'] ?? null;
            if ($accountId === null || $accountId === '') {
                continue;
            }
            $companyName = trim((string) ($row['company_name'] ?? ''));
            $accounts['client_' . $accountId] = [
                'id' => (string) $accountId,
                'label' => $companyName !== '' ? $companyName : ('Cont: ' . ($row['email'] ?? '')),
                'name' => $companyName,
                'email' => (string) ($row['email'] ?? ''),
                'pass' => (string) ($row['pas'] ?? ''),
                'target' => (string) ($row['target_user'] ?? 'besoiu'),
            ];
        }

        return $accounts;
    }

    /** @param array<string, mixed> $data @return array{success:bool,message:string,randomn_id?:int} */
    public function save(array $data): array
    {
        $this->ensureTable();

        $companyName = trim((string) ($data['company_name'] ?? $data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $pass = (string) ($data['pas'] ?? $data['password'] ?? '');
        $targetUser = trim((string) ($data['target_user'] ?? 'besoiu'));
        $rid = trim((string) ($data['ridusers'] ?? $data['randomn_id'] ?? ''));
        $adminId = $this->adminUserId();

        if ($companyName === '') {
            throw new ValidationException('Introdu denumirea firmei.');
        }
        if ($email === '' || $pass === '') {
            throw new ValidationException('Completează emailul și parola site-ului.');
        }

        $randomnId = $rid !== '' ? (int) $rid : random_int(100, 9999);
        $pdo = Database::getDB();

        if ($rid !== '') {
            $check = $pdo->prepare(
                'SELECT randomn_id FROM pieseauto_accounts WHERE randomn_id = :rid AND id_users = :uid LIMIT 1'
            );
            $check->execute(['rid' => $randomnId, 'uid' => $adminId]);
            if (!$check->fetchColumn()) {
                throw new ValidationException('Contul nu a fost găsit.');
            }

            $stmt = $pdo->prepare(
                'UPDATE pieseauto_accounts SET company_name = :company_name, email = :email, pas = :pas,
                 target_user = :target_user, updated_at = NOW()
                 WHERE randomn_id = :rid AND id_users = :uid'
            );
            $stmt->execute([
                'company_name' => $companyName,
                'email' => $email,
                'pas' => $pass,
                'target_user' => $targetUser !== '' ? $targetUser : 'besoiu',
                'rid' => $randomnId,
                'uid' => $adminId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO pieseauto_accounts (randomn_id, id_users, company_name, email, pas, target_user)
                 VALUES (:randomn_id, :id_users, :company_name, :email, :pas, :target_user)'
            );
            $stmt->execute([
                'randomn_id' => $randomnId,
                'id_users' => $adminId,
                'company_name' => $companyName,
                'email' => $email,
                'pas' => $pass,
                'target_user' => $targetUser !== '' ? $targetUser : 'besoiu',
            ]);
        }

        return ['success' => true, 'message' => 'Cont salvat cu succes.', 'randomn_id' => $randomnId];
    }

    /** @return array{success:bool,message:string} */
    public function delete(string $randomnId): array
    {
        $this->ensureTable();
        $rid = (int) trim($randomnId);
        if ($rid <= 0) {
            throw new ValidationException('Cont invalid.');
        }

        $pdo = Database::getDB();
        $stmt = $pdo->prepare('DELETE FROM pieseauto_accounts WHERE randomn_id = :rid AND id_users = :uid');
        $stmt->execute(['rid' => $rid, 'uid' => $this->adminUserId()]);
        if ($stmt->rowCount() === 0) {
            throw new ValidationException('Contul nu a fost găsit.');
        }

        return ['success' => true, 'message' => 'Cont șters cu succes.'];
    }

    private function adminUserId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }
}
