<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use Besoiu\Core\Clienti\ClientiModel;
use PDO;
use Throwable;

final class SectionAssistantClientQueries
{
    public static function countClients(): int
    {
        try {
            $pdo = Database::getDB();
            // Același set ca listClients() — fără filtru status strict (status=0 e încă în BD)
            $stmt = $pdo->query('SELECT COUNT(*) FROM clienti');

            return (int) ($stmt ? $stmt->fetchColumn() : 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function listClients(int $limit = 30, string $query = ''): array
    {
        $limit = max(1, min(50, $limit));
        $query = SectionAssistantQueryHelper::sanitize($query);

        try {
            if ($query !== '') {
                $result = (new ClientiModel())->findPaginated(1, $limit, ['q' => $query]);
                $rows = is_array($result['items'] ?? null) ? $result['items'] : [];
            } else {
                $pdo = Database::getDB();
                $stmt = $pdo->query(
                    "SELECT randomn_id, name, email, phone, city, total_orders, total_paid, status
                     FROM clienti
                     ORDER BY id DESC
                     LIMIT {$limit}"
                );
                $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            }
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rid = (int) ($row['randomn_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? '')) ?: '-';
            $orders = (int) ($row['total_orders'] ?? 0);
            $paid = (float) ($row['total_paid'] ?? 0);
            $out[] = [
                'name' => $name,
                'email' => trim((string) ($row['email'] ?? '')) ?: '-',
                'phone' => trim((string) ($row['phone'] ?? '')) ?: '-',
                'city' => trim((string) ($row['city'] ?? '')) ?: '-',
                'total_orders' => (string) $orders,
                'total_paid' => $paid > 0 ? number_format($paid, 2, ',', '.') . ' RON' : '-',
                'status' => trim((string) ($row['status'] ?? '')) ?: 'active',
                'edit_url' => $rid > 0 ? '/admin/profileclienti?id=' . $rid : '/admin/clienti',
            ];
        }

        return $out;
    }
}
