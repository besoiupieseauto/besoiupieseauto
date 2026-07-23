<?php

declare(strict_types=1);

namespace Besoiu\Services\Export;

use Config\Database;
use Besoiu\Services\SearchLogsService;
use Besoiu\Core\Comenzi\ComenziModel;
use PDO;
use RuntimeException;

/**
 * Export centralizat — preview + descărcare per secțiune, cu filtre.
 */
final class ExportHubService
{
    private const MAX_ROWS = 10000;

    /** @return list<array<string, mixed>> */
    public static function sections(): array
    {
        return [
            [
                'id' => 'catalog',
                'label' => 'Catalog produse',
                'desc' => 'Export CSV format Piese Autopro — produse active din magazin.',
                'formats' => [['id' => 'csv_autopro', 'label' => 'CSV Piese Autopro']],
                'filters' => ['catalog_status'],
            ],
            [
                'id' => 'comenzi',
                'label' => 'Comenzi site',
                'desc' => 'Comenzi checkout cu filtre status, canal, plată și perioadă.',
                'formats' => [['id' => 'xls', 'label' => 'Excel (.xls)'], ['id' => 'csv', 'label' => 'CSV']],
                'filters' => ['date_range', 'order_status', 'channel', 'payment_status', 'q'],
            ],
            [
                'id' => 'facturi',
                'label' => 'Facturi',
                'desc' => 'Facturi emise — filtrare după status și scadență.',
                'formats' => [['id' => 'xls', 'label' => 'Excel (.xls)'], ['id' => 'csv', 'label' => 'CSV']],
                'filters' => ['date_range_due', 'invoice_status', 'q'],
            ],
            [
                'id' => 'livrare',
                'label' => 'Livrări / AWB',
                'desc' => 'AWB-uri și livrări — curier, status, perioadă livrare.',
                'formats' => [['id' => 'xls', 'label' => 'Excel (.xls)'], ['id' => 'csv', 'label' => 'CSV']],
                'filters' => ['date_range_delivery', 'delivery_status', 'courier', 'q'],
            ],
            [
                'id' => 'search_logs',
                'label' => 'Jurnal căutări',
                'desc' => 'Căutări VIN/OEM pe site — găsite vs negăsite.',
                'formats' => [['id' => 'csv', 'label' => 'CSV']],
                'filters' => ['date_range', 'query_type', 'not_found_only', 'q'],
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function preview(string $section, array $filters = []): array
    {
        $section = trim($section);
        $filters = $this->normalizeFilters($filters);

        return match ($section) {
            'catalog' => $this->previewCatalog($filters),
            'comenzi' => $this->previewComenzi($filters),
            'facturi' => $this->previewFacturi($filters),
            'livrare' => $this->previewLivrare($filters),
            'search_logs' => $this->previewSearchLogs($filters),
            default => throw new RuntimeException('Secțiune export necunoscută: ' . $section),
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{content:string, filename:string, mime:string}
     */
    public function export(string $section, string $format, array $filters = []): array
    {
        $section = trim($section);
        $format = trim($format);
        $filters = $this->normalizeFilters($filters);

        return match ($section) {
            'catalog' => $this->exportCatalog($format, $filters),
            'comenzi' => $this->exportTable('comenzi', $format, $filters),
            'facturi' => $this->exportTable('facturi', $format, $filters),
            'livrare' => $this->exportTable('livrare', $format, $filters),
            'search_logs' => $this->exportSearchLogs($filters),
            default => throw new RuntimeException('Secțiune export necunoscută: ' . $section),
        };
    }

    /** @param array<string, mixed> $filters */
    private function previewCatalog(array $filters): array
    {
        $pdo = Database::getDB();
        $where = 'status <> :inactive';
        $params = [':inactive' => '0'];
        if (($filters['catalog_status'] ?? '') === 'inactive') {
            $where = 'status = :inactive';
        } elseif (($filters['catalog_status'] ?? '') === 'all') {
            $where = '1=1';
            $params = [];
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM produse WHERE ' . $where);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        return ['count' => $count, 'label' => $count . ' produse pentru export'];
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    private function fetchCatalogRows(PDO $pdo, array $filters): array
    {
        $where = 'status <> :inactive';
        $params = [':inactive' => '0'];
        if (($filters['catalog_status'] ?? '') === 'inactive') {
            $where = 'status = :inactive';
        } elseif (($filters['catalog_status'] ?? '') === 'all') {
            $where = '1=1';
            $params = [];
        }
        $stmt = $pdo->prepare('SELECT * FROM produse WHERE ' . $where . ' ORDER BY id ASC LIMIT ' . self::MAX_ROWS);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $filters */
    private function exportCatalog(string $format, array $filters): array
    {
        if ($format !== 'csv_autopro' && $format !== 'csv') {
            throw new RuntimeException('Format invalid pentru catalog.');
        }

        $this->requireSystemLib('catalog-export-autopro.php');

        $pdo = Database::getDB();
        $rows = $this->fetchCatalogRows($pdo, $filters);
        if ($rows === []) {
            throw new RuntimeException('Nu există produse pentru export cu filtrele selectate.');
        }

        $csv = import_queue_export_autopro_csv_content($rows);

        return [
            'content' => "\xEF\xBB\xBF" . $csv,
            'filename' => catalog_export_autopro_filename(),
            'mime' => 'text/csv; charset=utf-8',
        ];
    }

    private static function siteRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    private function requireSystemLib(string $relativePath): void
    {
        $path = self::siteRoot() . '/system/' . ltrim($relativePath, '/');
        if (!is_file($path)) {
            throw new RuntimeException('Fișier sistem lipsă: system/' . ltrim($relativePath, '/'));
        }
        require_once $path;
    }

    /** @param array<string, mixed> $filters */
    private function previewComenzi(array $filters): array
    {
        $model = new ComenziModel();
        $total = $model->findPaginated(1, 1, $this->comenziFilters($filters))['total'];

        return ['count' => $total, 'label' => $total . ' comenzi (max ' . self::MAX_ROWS . ' în fișier)'];
    }

    /** @param array<string, mixed> $filters */
    private function previewFacturi(array $filters): array
    {
        $total = $this->countFacturi($filters);

        return ['count' => $total, 'label' => $total . ' facturi'];
    }

    /** @param array<string, mixed> $filters */
    private function previewLivrare(array $filters): array
    {
        $total = $this->countLivrare($filters);

        return ['count' => $total, 'label' => $total . ' livrări'];
    }

    /** @param array<string, mixed> $filters */
    private function previewSearchLogs(array $filters): array
    {
        $service = new SearchLogsService();
        $result = $service->list(array_merge($filters, ['limit' => 1]));

        return ['count' => (int) ($result['total'] ?? 0), 'label' => ((int) ($result['total'] ?? 0)) . ' înregistrări căutări'];
    }

    /** @param array<string, mixed> $filters */
    private function exportSearchLogs(array $filters): array
    {
        $service = new SearchLogsService();
        $csv = $service->exportCsv(array_merge($filters, ['limit' => self::MAX_ROWS]));

        return [
            'content' => "\xEF\xBB\xBF" . $csv,
            'filename' => 'search_logs_' . date('Y-m-d_His') . '.csv',
            'mime' => 'text/csv; charset=utf-8',
        ];
    }

    /** @param array<string, mixed> $filters */
    private function exportTable(string $section, string $format, array $filters): array
    {
        [$headers, $rows] = match ($section) {
            'comenzi' => $this->comenziRows($filters),
            'facturi' => $this->facturiRows($filters),
            'livrare' => $this->livrareRows($filters),
            default => throw new RuntimeException('Secțiune invalidă.'),
        };

        if ($rows === []) {
            throw new RuntimeException('Nu există date pentru export cu filtrele selectate.');
        }

        $stamp = date('Y-m-d_His');
        if ($format === 'csv') {
            return [
                'content' => "\xEF\xBB\xBF" . $this->toCsv($headers, $rows),
                'filename' => $section . '_' . $stamp . '.csv',
                'mime' => 'text/csv; charset=utf-8',
            ];
        }

        if ($format === 'xls') {
            return [
                'content' => $this->toExcelHtml($headers, $rows),
                'filename' => $section . '_' . $stamp . '.xls',
                'mime' => 'application/vnd.ms-excel; charset=utf-8',
            ];
        }

        throw new RuntimeException('Format invalid: ' . $format);
    }

    /** @param array<string, mixed> $filters @return array{0:list<string>, 1:list<list<string>>} */
    private function comenziRows(array $filters): array
    {
        $model = new ComenziModel();
        $items = $model->findPaginated(1, self::MAX_ROWS, $this->comenziFilters($filters))['items'];
        $headers = ['Comandă', 'Client', 'Telefon', 'Email', 'VIN', 'Produs', 'Canal', 'Plată', 'Livrare', 'Status livrare', 'Status', 'Cantitate', 'Total', 'Creat la'];
        $rows = [];
        foreach ($items as $o) {
            $rows[] = [
                (string) ($o['order_number'] ?? ('ORD-' . ($o['randomn_id'] ?? ''))),
                (string) ($o['client_name'] ?? ''),
                (string) ($o['phone'] ?? ''),
                (string) ($o['email'] ?? ''),
                (string) ($o['vin'] ?? ''),
                (string) ($o['name'] ?? $o['product_name'] ?? ''),
                (string) ($o['channel'] ?? ''),
                (string) ($o['payment_status'] ?? ''),
                (string) ($o['delivery_method'] ?? ''),
                (string) ($o['delivery_status'] ?? ''),
                (string) ($o['order_status'] ?? ''),
                (string) ($o['quantity'] ?? '1'),
                (string) ($o['total_amount'] ?? '0'),
                (string) ($o['created_at'] ?? ''),
            ];
        }

        return [$headers, $rows];
    }

    /** @param array<string, mixed> $filters */
    private function comenziFilters(array $filters): array
    {
        return array_filter([
            'q' => $filters['q'] ?? '',
            'order_status' => $filters['order_status'] ?? '',
            'channel' => $filters['channel'] ?? '',
            'payment_status' => $filters['payment_status'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
        ], static fn ($v) => $v !== '' && $v !== null);
    }

    /** @param array<string, mixed> $filters @return array{0:list<string>, 1:list<list<string>>} */
    private function facturiRows(array $filters): array
    {
        $items = $this->fetchFacturi($filters);
        $headers = ['Factură', 'Comandă', 'Client', 'Telefon', 'Email', 'Plată', 'Status', 'Sumă', 'Scadență'];
        $rows = [];
        foreach ($items as $r) {
            $rows[] = [
                (string) ($r['invoice_number'] ?? ''),
                (string) ($r['order_number'] ?? ''),
                (string) ($r['client_name'] ?? $r['name'] ?? ''),
                (string) ($r['phone'] ?? ''),
                (string) ($r['email'] ?? ''),
                (string) ($r['payment_method'] ?? ''),
                (string) ($r['invoice_status'] ?? ''),
                (string) ($r['amount'] ?? '0'),
                (string) ($r['due_date'] ?? ''),
            ];
        }

        return [$headers, $rows];
    }

    /** @param array<string, mixed> $filters @return array{0:list<string>, 1:list<list<string>>} */
    private function livrareRows(array $filters): array
    {
        $items = $this->fetchLivrare($filters);
        $headers = ['AWB', 'Comandă', 'Client', 'Telefon', 'Adresă', 'Curier', 'Status', 'Data livrare', 'Total'];
        $rows = [];
        foreach ($items as $r) {
            $rows[] = [
                (string) ($r['awb'] ?? ''),
                (string) ($r['order_number'] ?? ''),
                (string) ($r['client_name'] ?? $r['name'] ?? ''),
                (string) ($r['phone'] ?? ''),
                (string) ($r['address'] ?? ''),
                (string) ($r['courier'] ?? ''),
                (string) ($r['delivery_status'] ?? ''),
                (string) ($r['delivery_date'] ?? ''),
                (string) ($r['total_amount'] ?? '0'),
            ];
        }

        return [$headers, $rows];
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    private function fetchFacturi(array $filters): array
    {
        $pdo = Database::getDB();
        [$where, $params] = $this->buildFacturiWhere($filters);
        $sql = 'SELECT * FROM facturi ' . $where . ' ORDER BY id DESC LIMIT ' . self::MAX_ROWS;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $filters */
    private function countFacturi(array $filters): int
    {
        $pdo = Database::getDB();
        [$where, $params] = $this->buildFacturiWhere($filters);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM facturi ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $filters @return array{0:string, 1:array<string, mixed>} */
    private function buildFacturiWhere(array $filters): array
    {
        $parts = [];
        $params = [];
        if (!empty($filters['q'])) {
            $parts[] = '(invoice_number LIKE :q OR order_number LIKE :q OR client_name LIKE :q OR name LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }
        if (!empty($filters['invoice_status'])) {
            $parts[] = 'invoice_status = :invoice_status';
            $params[':invoice_status'] = (string) $filters['invoice_status'];
        }
        if (!empty($filters['date_from'])) {
            $parts[] = 'DATE(due_date) >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $parts[] = 'DATE(due_date) <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }

        return [$parts ? 'WHERE ' . implode(' AND ', $parts) : '', $params];
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    private function fetchLivrare(array $filters): array
    {
        $pdo = Database::getDB();
        [$where, $params] = $this->buildLivrareWhere($filters);
        $sql = 'SELECT * FROM livrare ' . $where . ' ORDER BY id DESC LIMIT ' . self::MAX_ROWS;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $filters */
    private function countLivrare(array $filters): int
    {
        $pdo = Database::getDB();
        [$where, $params] = $this->buildLivrareWhere($filters);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM livrare ' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $filters @return array{0:string, 1:array<string, mixed>} */
    private function buildLivrareWhere(array $filters): array
    {
        $parts = [];
        $params = [];
        if (!empty($filters['q'])) {
            $parts[] = '(awb LIKE :q OR order_number LIKE :q OR client_name LIKE :q OR name LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }
        if (!empty($filters['delivery_status'])) {
            $parts[] = 'delivery_status = :delivery_status';
            $params[':delivery_status'] = (string) $filters['delivery_status'];
        }
        if (!empty($filters['courier'])) {
            $parts[] = 'courier = :courier';
            $params[':courier'] = (string) $filters['courier'];
        }
        if (!empty($filters['date_from'])) {
            $parts[] = 'DATE(delivery_date) >= :date_from';
            $params[':date_from'] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $parts[] = 'DATE(delivery_date) <= :date_to';
            $params[':date_to'] = (string) $filters['date_to'];
        }

        return [$parts ? 'WHERE ' . implode(' AND ', $parts) : '', $params];
    }

    /** @param list<string> $headers @param list<list<string>> $rows */
    private function toCsv(array $headers, array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            throw new RuntimeException('Nu s-a putut genera CSV.');
        }
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return is_string($content) ? $content : '';
    }

    /** @param list<string> $headers @param list<list<string>> $rows */
    private function toExcelHtml(array $headers, array $rows): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $head = '<tr>' . implode('', array_map(static fn ($h) => '<th>' . $esc($h) . '</th>', $headers)) . '</tr>';
        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>' . implode('', array_map(static fn ($c) => '<td>' . $esc($c) . '</td>', $row)) . '</tr>';
        }

        return '<html><head><meta charset="utf-8"></head><body><table border="1"><thead>' . $head . '</thead><tbody>' . $body . '</tbody></table></body></html>';
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'order_status' => trim((string) ($filters['order_status'] ?? '')),
            'channel' => trim((string) ($filters['channel'] ?? '')),
            'payment_status' => trim((string) ($filters['payment_status'] ?? '')),
            'invoice_status' => trim((string) ($filters['invoice_status'] ?? '')),
            'delivery_status' => trim((string) ($filters['delivery_status'] ?? '')),
            'courier' => trim((string) ($filters['courier'] ?? '')),
            'query_type' => trim((string) ($filters['query_type'] ?? '')),
            'not_found_only' => !empty($filters['not_found_only']),
            'catalog_status' => trim((string) ($filters['catalog_status'] ?? 'active')),
        ];
    }
}
