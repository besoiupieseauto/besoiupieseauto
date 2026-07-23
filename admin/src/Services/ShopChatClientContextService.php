<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Context live pentru agent-clienti — politici CMS + comenzi recente.
 */
final class ShopChatClientContextService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /** @param array<string, mixed> $options phone, email, visitor_key */
    public function buildContext(?PDO $pdo = null, array $options = []): string
    {
        $pdo = $pdo ?? Database::getDB();
        $parts = [$this->policiesContext()];

        $phone = trim((string) ($options['phone'] ?? ''));
        $email = trim((string) ($options['email'] ?? ''));
        $orders = $this->recentOrdersContext($pdo, $phone, $email);
        if ($orders !== '') {
            $parts[] = $orders;
        }

        return implode("\n\n", array_filter($parts));
    }

    public function policiesContext(): string
    {
        $lines = ['### Politici magazin (informativ)'];

        $defaults = $this->projectRoot . '/system/site-defaults.php';
        if (is_file($defaults)) {
            require_once $defaults;
        }

        $cms = $this->projectRoot . '/system/site-content.php';
        if (is_file($cms)) {
            require_once $cms;
        }

        $terms = [];
        if (function_exists('site_content_load')) {
            try {
                $content = site_content_load();
                $terms = is_array($content['terms'] ?? null) ? $content['terms'] : [];
            } catch (Throwable) {
                $terms = [];
            }
        }

        $delivery = trim((string) ($terms['delivery'] ?? 'Livrare în România — ridicare locală Utvin sau tarif fix la checkout.'));
        $returns = trim((string) ($terms['returns'] ?? 'Retur în 14 zile conform politicii magazinului.'));
        $payment = trim((string) ($terms['payment'] ?? 'Plată: ramburs, numerar, card la ridicare sau card online (dacă activ).'));
        $warranty = trim((string) ($terms['warranty'] ?? 'Garanție conform producător și legislației RO.'));

        $lines[] = '- Livrare: ' . mb_substr($delivery, 0, 400, 'UTF-8');
        $lines[] = '- Retur: ' . mb_substr($returns, 0, 300, 'UTF-8');
        $lines[] = '- Plată: ' . mb_substr($payment, 0, 300, 'UTF-8');
        $lines[] = '- Garanție: ' . mb_substr($warranty, 0, 300, 'UTF-8');
        $lines[] = '- Comenzi online: finalizare pe site / telefon — roboții nu confirmă comenzi comerciale automat.';

        return implode("\n", $lines);
    }

    private function recentOrdersContext(PDO $pdo, string $phone, string $email): string
    {
        if ($phone === '' && $email === '') {
            return '';
        }

        try {
            $sql = 'SELECT id, order_status, total_amount, created_at, notes FROM comenzi WHERE 1=0';
            $params = [];

            if ($phone !== '') {
                $digits = preg_replace('/\D+/', '', $phone) ?? '';
                if ($digits !== '') {
                    $sql = 'SELECT id, order_status, total_amount, created_at, notes FROM comenzi
                            WHERE REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", "") LIKE ?
                            ORDER BY id DESC LIMIT 5';
                    $params = ['%' . substr($digits, -9) . '%'];
                }
            } elseif ($email !== '') {
                $sql = 'SELECT id, order_status, total_amount, created_at, notes FROM comenzi
                        WHERE email = ? ORDER BY id DESC LIMIT 5';
                $params = [$email];
            }

            if ($params === []) {
                return '';
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows) || $rows === []) {
                return "### Comenzi client\nNicio comandă găsită pentru contactul furnizat.";
            }

            $lines = ['### Comenzi recente client'];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $lines[] = '- #' . ($row['id'] ?? '?')
                    . ' · status: ' . ($row['order_status'] ?? '—')
                    . ' · ' . ($row['total_amount'] ?? '—') . ' RON'
                    . ' · ' . ($row['created_at'] ?? '');
            }

            return implode("\n", $lines);
        } catch (Throwable) {
            return '';
        }
    }
}
