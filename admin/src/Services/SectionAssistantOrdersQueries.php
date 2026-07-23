<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

final class SectionAssistantOrdersQueries
{
    /** @return list<array<string, mixed>> */
    public static function listInvoices(int $limit = 25): array
    {
        $limit = max(1, min(50, $limit));
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                "SELECT randomn_id, invoice_number, order_number, client_name, name, phone,
                        payment_method, invoice_status, amount, due_date, created_at
                 FROM facturi
                 ORDER BY id DESC
                 LIMIT {$limit}"
            );
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            $out = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rid = (int) ($row['randomn_id'] ?? 0);
                $client = trim((string) ($row['client_name'] ?? ''));
                if ($client === '') {
                    $client = trim((string) ($row['name'] ?? ''));
                }
                $out[] = [
                    'invoice_number' => trim((string) ($row['invoice_number'] ?? '')) ?: ('INV-' . $rid),
                    'order_number' => trim((string) ($row['order_number'] ?? '')) ?: '-',
                    'client_name' => $client !== '' ? $client : '-',
                    'phone' => trim((string) ($row['phone'] ?? '')) ?: '-',
                    'payment_method' => trim((string) ($row['payment_method'] ?? '')) ?: '-',
                    'invoice_status' => trim((string) ($row['invoice_status'] ?? '')) ?: 'neachitata',
                    'amount' => number_format((float) ($row['amount'] ?? 0), 2, ',', '.'),
                    'due_date' => trim((string) ($row['due_date'] ?? '')) ?: '-',
                    'edit_url' => '/admin/facturi',
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array{total:int,achitata:int,neachitata:int,anulata:int,storno:int,total_amount:float} */
    public static function invoiceStats(): array
    {
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                "SELECT invoice_status, COUNT(*) AS cnt, COALESCE(SUM(CAST(amount AS DECIMAL(12,2))), 0) AS sum_amount
                 FROM facturi GROUP BY invoice_status"
            );
            $stats = [
                'total' => 0,
                'achitata' => 0,
                'neachitata' => 0,
                'anulata' => 0,
                'storno' => 0,
                'total_amount' => 0.0,
            ];
            foreach ($stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $status = trim((string) ($row['invoice_status'] ?? ''));
                $cnt = (int) ($row['cnt'] ?? 0);
                $stats['total'] += $cnt;
                if (array_key_exists($status, $stats)) {
                    $stats[$status] = $cnt;
                }
                $stats['total_amount'] += (float) ($row['sum_amount'] ?? 0);
            }

            return $stats;
        } catch (Throwable) {
            return [
                'total' => 0,
                'achitata' => 0,
                'neachitata' => 0,
                'anulata' => 0,
                'storno' => 0,
                'total_amount' => 0.0,
            ];
        }
    }

    /** @return list<array<string, mixed>> */
    public static function listAwbs(int $limit = 25): array
    {
        $limit = max(1, min(50, $limit));
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                "SELECT randomn_id, awb, order_number, client_name, name, phone, address,
                        courier, delivery_status, delivery_date, total_amount
                 FROM livrare
                 WHERE TRIM(COALESCE(awb, '')) <> ''
                    OR LOWER(TRIM(COALESCE(delivery_status, ''))) IN ('awb_generat', 'in_tranzit', 'livrat')
                 ORDER BY id DESC
                 LIMIT {$limit}"
            );
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            $out = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rid = (int) ($row['randomn_id'] ?? 0);
                $client = trim((string) ($row['client_name'] ?? ''));
                if ($client === '') {
                    $client = trim((string) ($row['name'] ?? ''));
                }
                $awb = trim((string) ($row['awb'] ?? ''));
                $out[] = [
                    'awb' => $awb !== '' ? $awb : ('AWB-' . $rid),
                    'order_number' => trim((string) ($row['order_number'] ?? '')) ?: '-',
                    'client_name' => $client !== '' ? $client : '-',
                    'phone' => trim((string) ($row['phone'] ?? '')) ?: '-',
                    'courier' => trim((string) ($row['courier'] ?? '')) ?: '-',
                    'delivery_status' => trim((string) ($row['delivery_status'] ?? '')) ?: '-',
                    'delivery_date' => trim((string) ($row['delivery_date'] ?? '')) ?: '-',
                    'total_amount' => number_format((float) ($row['total_amount'] ?? 0), 2, ',', '.'),
                    'edit_url' => $rid > 0 ? '/admin/livrare?highlight=' . $rid : '/admin/livrare',
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array{total:int,with_awb:int,in_transit:int,delivered:int} */
    public static function awbStats(): array
    {
        try {
            $pdo = Database::getDB();
            $total = (int) $pdo->query('SELECT COUNT(*) FROM livrare')->fetchColumn();
            $withAwb = (int) $pdo->query(
                "SELECT COUNT(*) FROM livrare WHERE TRIM(COALESCE(awb, '')) <> ''"
            )->fetchColumn();
            $inTransit = (int) $pdo->query(
                "SELECT COUNT(*) FROM livrare WHERE LOWER(TRIM(COALESCE(delivery_status, ''))) = 'in_tranzit'"
            )->fetchColumn();
            $delivered = (int) $pdo->query(
                "SELECT COUNT(*) FROM livrare WHERE LOWER(TRIM(COALESCE(delivery_status, ''))) = 'livrat'"
            )->fetchColumn();

            return [
                'total' => $total,
                'with_awb' => $withAwb,
                'in_transit' => $inTransit,
                'delivered' => $delivered,
            ];
        } catch (Throwable) {
            return ['total' => 0, 'with_awb' => 0, 'in_transit' => 0, 'delivered' => 0];
        }
    }

    /** @return list<array<string, mixed>> */
    public static function listOrders(int $limit = 30): array
    {
        $limit = max(1, min(50, $limit));
        try {
            $rows = (new \Besoiu\Core\Comenzi\ComenziModel())->findRecent($limit);
        } catch (Throwable) {
            $rows = [];
        }

        if ($rows === []) {
            try {
                $pdo = Database::getDB();
                $stmt = $pdo->query(
                    "SELECT randomn_id, order_number, client_name, name, phone, order_status,
                            payment_status, channel, total_amount, quantity, created_at
                     FROM comenzi
                     ORDER BY id DESC
                     LIMIT {$limit}"
                );
                $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            } catch (Throwable) {
                return [];
            }
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rid = (int) ($row['randomn_id'] ?? 0);
            $client = trim((string) ($row['client_name'] ?? ''));
            $orderNo = trim((string) ($row['order_number'] ?? ''));
            if ($orderNo === '') {
                $orderNo = $rid > 0 ? ('ORD-' . $rid) : ('CMD-' . ($row['id'] ?? ''));
            }
            $productName = trim((string) ($row['product_name'] ?? $row['name'] ?? ''));
            $created = trim((string) ($row['created_at'] ?? ''));
            if ($created !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $created, $m)) {
                $created = date('d.m.Y', strtotime($m[1]));
            }

            $out[] = [
                'order_number' => $orderNo !== '' ? $orderNo : '-',
                'client_name' => $client !== '' ? $client : '-',
                'product_name' => $productName !== '' ? $productName : '-',
                'phone' => trim((string) ($row['phone'] ?? '')) ?: '-',
                'order_status' => trim((string) ($row['order_status'] ?? '')) ?: '-',
                'payment_status' => trim((string) ($row['payment_status'] ?? '')) ?: '-',
                'channel' => trim((string) ($row['channel'] ?? '')) ?: '-',
                'total_amount' => number_format((float) ($row['total_amount'] ?? 0), 2, ',', '.'),
                'quantity' => (string) max(1, (int) ($row['quantity'] ?? 1)),
                'created_at' => $created !== '' ? $created : '-',
                'edit_url' => $rid > 0 ? '/admin/orders?highlight=' . $rid : '/admin/orders',
            ];
        }

        return $out;
    }

    /** Elimină cuvinte românești care nu fac parte din numele clientului. */
    public static function sanitizeClientQuery(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return '';
        }

        $stopWords = [
            'sunt', 'exista', 'există', 'exist', 'avem', 'ce', 'cate', 'câte', 'cite', 'cîte',
            'care', 'total', 'acum', 'noi', 'toate', 'live', 'in', 'din', 'sistem', 'system',
            'comenzi', 'comenzile', 'comanda', 'comenzile', 'orders', 'order', 'client', 'clientul',
            'pentru', 'lui', 'gasite', 'găsite', 'gasit', 'găsit', 'afiseaza', 'afișează', 'lista',
            'listă', 'te', 'rog', 'multumesc', 'mulțumesc', 'hello', 'buna', 'bună',
        ];

        $parts = preg_split('/\s+/u', $name) ?: [];
        while ($parts !== []) {
            $last = mb_strtolower(rtrim((string) end($parts), '?!.,;'), 'UTF-8');
            if (!in_array($last, $stopWords, true)) {
                break;
            }
            array_pop($parts);
        }
        while ($parts !== []) {
            $first = mb_strtolower(rtrim((string) $parts[0], '?!.,;'), 'UTF-8');
            if (!in_array($first, $stopWords, true)) {
                break;
            }
            array_shift($parts);
        }

        return trim(implode(' ', $parts));
    }

    /** @return list<array<string, mixed>> */
    public static function listOrdersByClient(string $clientQuery, int $limit = 30): array
    {
        $clientQuery = self::sanitizeClientQuery(trim($clientQuery));
        if ($clientQuery === '' || mb_strlen($clientQuery, 'UTF-8') < 2) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $words = self::clientSearchWords($clientQuery);
        $matched = [];
        $searchMode = 'none';

        // 1) Același flux ca orders_summary (findRecent) — AND apoi OR (client_name poate fi doar prenumele)
        foreach (self::fetchRecentOrderRows(200) as $row) {
            if (!is_array($row) || !self::rowMatchesClientWords($row, $words)) {
                continue;
            }
            $matched[] = $row;
            if (count($matched) >= $limit) {
                break;
            }
        }
        if ($matched !== []) {
            $searchMode = 'findRecent_and';
        }
        if ($matched === []) {
            foreach (self::fetchRecentOrderRows(200) as $row) {
                if (!is_array($row) || !self::rowMatchesAnyClientWord($row, $words)) {
                    continue;
                }
                $matched[] = $row;
                if (count($matched) >= $limit) {
                    break;
                }
            }
            if ($matched !== []) {
                $searchMode = 'findRecent_or';
            }
        }

        // 2) SQL flexibil (fără coloane opționale)
        if ($matched === []) {
            $matched = self::searchClientOrdersSql($clientQuery, $limit, false);
            if ($matched !== []) {
                $searchMode = 'sql_and';
            }
        }
        if ($matched === []) {
            $matched = self::searchClientOrdersSql($clientQuery, $limit, true);
            if ($matched !== []) {
                $searchMode = 'sql_or';
            }
        }

        // 3) ComenziModel — același filtru ca /admin/orders
        if ($matched === []) {
            try {
                $model = new \Besoiu\Core\Comenzi\ComenziModel();
                $result = $model->findPaginated(1, $limit, ['q' => $clientQuery]);
                $matched = is_array($result['items'] ?? null) ? $result['items'] : [];
                if ($matched !== []) {
                    $searchMode = 'comenzi_model_q';
                }
            } catch (Throwable) {
                $matched = [];
            }
        }

        $mapped = self::mapClientOrderRows($matched, $clientQuery);
        if ($mapped !== [] && isset($mapped[0])) {
            $mapped[0]['_search_mode'] = $searchMode;
        }

        return $mapped;
    }

    /** @return list<string> */
    private static function clientSearchWords(string $query): array
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower(trim($query), 'UTF-8')) ?: [],
            static fn ($w) => mb_strlen(trim((string) $w), 'UTF-8') >= 2
        ));

        return $words !== [] ? $words : [mb_strtolower(trim($query), 'UTF-8')];
    }

    /** @return list<array<string, mixed>> */
    private static function fetchRecentOrderRows(int $limit): array
    {
        $limit = max(1, min(150, $limit));
        try {
            $rows = (new \Besoiu\Core\Comenzi\ComenziModel())->findRecent($limit);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            // fallback mai jos
        }

        try {
            $pdo = Database::getDB();
            $stmt = $pdo->query(
                "SELECT randomn_id, order_number, client_name, name, phone, email, notes, order_status,
                        payment_status, channel, total_amount, quantity, created_at
                 FROM comenzi
                 ORDER BY id DESC
                 LIMIT {$limit}"
            );

            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function searchClientOrdersSql(string $clientQuery, int $limit, bool $anyWord = false): array
    {
        $limit = max(1, min(50, $limit));
        $words = array_values(array_filter(
            preg_split('/\s+/u', $clientQuery) ?: [],
            static fn ($w) => mb_strlen(trim((string) $w), 'UTF-8') >= 2
        ));
        if ($words === []) {
            $words = [$clientQuery];
        }

        try {
            $pdo = Database::getDB();
            $columns = self::comenziSearchColumns($pdo);
            $wordClauses = [];
            $params = [];
            foreach ($words as $i => $word) {
                $like = '%' . trim((string) $word) . '%';
                $parts = [];
                foreach ($columns as $col) {
                    $key = ':w' . $i . '_' . $col;
                    $parts[] = 'TRIM(COALESCE(' . $col . ", '')) LIKE {$key}";
                    $params[$key] = $like;
                }
                $wordClauses[] = '(' . implode(' OR ', $parts) . ')';
            }

            $selectCols = 'randomn_id, order_number, client_name, name, phone, order_status,
                        payment_status, channel, total_amount, quantity, created_at';
            if (in_array('email', $columns, true)) {
                $selectCols = 'randomn_id, order_number, client_name, name, phone, email, notes, order_status,
                        payment_status, channel, total_amount, quantity, created_at';
            }

            $glue = $anyWord ? ' OR ' : ' AND ';
            $stmt = $pdo->prepare(
                "SELECT {$selectCols}
                 FROM comenzi
                 WHERE " . implode($glue, $wordClauses) . "
                 ORDER BY id DESC
                 LIMIT {$limit}"
            );
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[SectionAssistantOrdersQueries] searchClientOrdersSql: ' . $e->getMessage());

            return [];
        }
    }

    /** @return list<string> */
    private static function comenziSearchColumns(PDO $pdo): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $candidates = ['client_name', 'phone', 'order_number', 'name', 'notes', 'email', 'vin'];
        $cache = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM comenzi');
            $existing = [];
            foreach ($stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $col) {
                if (is_array($col) && !empty($col['Field'])) {
                    $existing[(string) $col['Field']] = true;
                }
            }
            foreach ($candidates as $col) {
                if (isset($existing[$col])) {
                    $cache[] = $col;
                }
            }
        } catch (Throwable) {
            $cache = ['client_name', 'phone', 'order_number', 'name'];
        }

        if ($cache === []) {
            $cache = ['client_name', 'phone', 'order_number', 'name'];
        }

        return $cache;
    }

    /**
     * Potrivire flexibilă — păstrat pentru compat; folosit intern prin fetchRecentOrderRows.
     *
     * @return list<array<string, mixed>>
     */
    private static function searchClientOrdersFuzzy(string $clientQuery, int $scanLimit, int $resultLimit): array
    {
        $words = self::clientSearchWords($clientQuery);
        $matched = [];
        foreach (self::fetchRecentOrderRows($scanLimit) as $row) {
            if (!is_array($row) || !self::rowMatchesClientWords($row, $words)) {
                continue;
            }
            $matched[] = $row;
            if (count($matched) >= $resultLimit) {
                break;
            }
        }

        return $matched;
    }

    /** @param list<string> $words */
    private static function rowMatchesClientWords(array $row, array $words): bool
    {
        $hay = mb_strtolower(implode(' ', [
            (string) ($row['client_name'] ?? ''),
            (string) ($row['phone'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) ($row['notes'] ?? ''),
            (string) ($row['order_number'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['product_name'] ?? ''),
        ]), 'UTF-8');
        $digitsHay = preg_replace('/\D+/u', '', $hay) ?? '';

        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '' || mb_strlen($word, 'UTF-8') < 2) {
                continue;
            }
            if (str_contains($hay, $word)) {
                continue;
            }
            $wordDigits = preg_replace('/\D+/u', '', $word) ?? '';
            if ($wordDigits !== '' && strlen($wordDigits) >= 4 && str_contains($digitsHay, $wordDigits)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /** @param list<string> $words */
    private static function rowMatchesAnyClientWord(array $row, array $words): bool
    {
        $hay = mb_strtolower(implode(' ', [
            (string) ($row['client_name'] ?? ''),
            (string) ($row['phone'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) ($row['notes'] ?? ''),
            (string) ($row['order_number'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['product_name'] ?? ''),
        ]), 'UTF-8');
        $digitsHay = preg_replace('/\D+/u', '', $hay) ?? '';

        foreach ($words as $word) {
            $word = mb_strtolower(trim($word), 'UTF-8');
            if ($word === '' || mb_strlen($word, 'UTF-8') < 2) {
                continue;
            }
            if (str_contains($hay, $word)) {
                return true;
            }
            $wordDigits = preg_replace('/\D+/u', '', $word) ?? '';
            if ($wordDigits !== '' && strlen($wordDigits) >= 4 && str_contains($digitsHay, $wordDigits)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private static function mapClientOrderRows(array $rows, string $clientQuery): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rid = (int) ($row['randomn_id'] ?? 0);
            $client = trim((string) ($row['client_name'] ?? ''));
            if ($client === '') {
                $client = self::clientNameFromNotes((string) ($row['notes'] ?? ''));
            }
            if ($client === '') {
                $client = $clientQuery;
            }
            $orderNo = trim((string) ($row['order_number'] ?? ''));
            if ($orderNo === '') {
                $orderNo = $rid > 0 ? ('ORD-' . $rid) : '-';
            }
            $productName = trim((string) ($row['name'] ?? ''));
            $created = trim((string) ($row['created_at'] ?? ''));
            if ($created !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $created, $m)) {
                $created = date('d.m.Y', strtotime($m[1]));
            }

            $out[] = [
                'order_number' => $orderNo !== '' ? $orderNo : '-',
                'client_name' => $client !== '' ? $client : '-',
                'product_name' => $productName !== '' ? $productName : '-',
                'phone' => trim((string) ($row['phone'] ?? '')) ?: '-',
                'email' => trim((string) ($row['email'] ?? '')) ?: '-',
                'order_status' => trim((string) ($row['order_status'] ?? '')) ?: '-',
                'payment_status' => trim((string) ($row['payment_status'] ?? '')) ?: '-',
                'channel' => trim((string) ($row['channel'] ?? '')) ?: '-',
                'total_amount' => number_format((float) ($row['total_amount'] ?? 0), 2, ',', '.'),
                'quantity' => (string) max(1, (int) ($row['quantity'] ?? 1)),
                'created_at' => $created !== '' ? $created : '-',
                'edit_url' => $rid > 0 ? '/admin/orders?highlight=' . $rid : '/admin/orders',
            ];
        }

        return $out;
    }

    private static function clientNameFromNotes(string $notes): string
    {
        if (preg_match('/\bClient:\s*([^\n\r]+)/u', $notes, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
