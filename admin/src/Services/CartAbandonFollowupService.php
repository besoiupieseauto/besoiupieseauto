<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Follow-up coș abandonat — chat + checkout (informativ, fără discount automat).
 */
final class CartAbandonFollowupService
{
    private string $projectRoot;
    private string $storageDir;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->storageDir = $this->projectRoot . '/admin/storage/chat_followup_sent';
    }

    public static function isEnabled(): bool
    {
        $raw = strtolower(trim((string) ($_ENV['SHOP_CART_FOLLOWUP'] ?? getenv('SHOP_CART_FOLLOWUP') ?: '1')));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }

    /** @return array{processed: int, chat: int, checkout: int, errors: list<string>} */
    public function processDue(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? Database::getDB();
        $result = ['processed' => 0, 'chat' => 0, 'checkout' => 0, 'errors' => []];

        if (!self::isEnabled()) {
            return $result;
        }

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }

        $result['chat'] = $this->processChatFollowups($pdo, $result['errors']);
        $result['checkout'] = $this->processCheckoutAbandonments($pdo, $result['errors']);
        $result['processed'] = $result['chat'] + $result['checkout'];

        return $result;
    }

    /** @param list<string> $errors */
    private function processChatFollowups(PDO $pdo, array &$errors): int
    {
        try {
            $stmt = $pdo->query(
                "SELECT id, profile_id, visitor_key, phone, email, cart_json, reason
                 FROM shop_chat_followups
                 WHERE status = 'pending' AND scheduled_at <= NOW()
                 ORDER BY scheduled_at ASC LIMIT 50"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $errors[] = 'shop_chat_followups: ' . $e->getMessage();

            return 0;
        }

        $done = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $cart = json_decode((string) ($row['cart_json'] ?? '[]'), true);
            $cart = is_array($cart) ? $cart : [];
            $message = $this->buildMessage($cart, 'chat');
            $phone = (string) ($row['phone'] ?? '');
            $notify = $this->maybeSendOutbound($phone, $message, $cart, 'chat');

            if ($this->saveSentRecord('chat_' . $id, [
                'followup_id' => $id,
                'type' => 'shop_chat',
                'phone' => $phone,
                'visitor_key' => (string) ($row['visitor_key'] ?? ''),
                'message' => $message,
                'cart' => $cart,
                'whatsapp' => $notify['whatsapp'] ?? [],
                'sms' => $notify['sms'] ?? [],
            ])) {
                $pdo->prepare("UPDATE shop_chat_followups SET status = 'sent', sent_at = NOW() WHERE id = :id")
                    ->execute(['id' => $id]);
                ++$done;
            }
        }

        return $done;
    }

    /** @param list<string> $errors */
    private function processCheckoutAbandonments(PDO $pdo, array &$errors): int
    {
        if (!CartAbandonmentService::tableExists($pdo)) {
            return 0;
        }

        try {
            $stmt = $pdo->query(
                "SELECT id, session_id, client_name, phone, email, cart_json, total_amount, items_count
                 FROM cart_abandonments
                 WHERE status = 'open'
                   AND items_count > 0
                   AND last_seen_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                 ORDER BY last_seen_at ASC LIMIT 30"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $errors[] = 'cart_abandonments: ' . $e->getMessage();

            return 0;
        }

        $done = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $cart = json_decode((string) ($row['cart_json'] ?? '[]'), true);
            $cart = is_array($cart) ? $cart : [];
            $message = $this->buildMessage($cart, 'checkout', $row);
            $phone = (string) ($row['phone'] ?? '');
            $notify = $this->maybeSendOutbound($phone, $message, $cart, 'checkout', $row);

            if ($this->saveSentRecord('checkout_' . $id, [
                'abandonment_id' => $id,
                'type' => 'checkout',
                'phone' => $phone,
                'client_name' => (string) ($row['client_name'] ?? ''),
                'message' => $message,
                'total_amount' => (float) ($row['total_amount'] ?? 0),
                'cart' => $cart,
                'whatsapp' => $notify['whatsapp'] ?? [],
                'sms' => $notify['sms'] ?? [],
            ])) {
                $pdo->prepare("UPDATE cart_abandonments SET status = 'contacted', notes = CONCAT(COALESCE(notes,''), '\n[followup ', NOW(), ']'), updated_at = NOW() WHERE id = :id")
                    ->execute(['id' => $id]);
                ++$done;
            }
        }

        return $done;
    }

    /** @param list<array<string, mixed>> $cart @param array<string, mixed> $meta */
    public function buildMessage(array $cart, string $type = 'chat', array $meta = []): string
    {
        $siteUrl = trim((string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'https://besoiupieseauto.ro'));
        $lines = ['Bună ziua!'];
        if ($type === 'checkout') {
            $name = trim((string) ($meta['client_name'] ?? ''));
            if ($name !== '') {
                $lines[0] = 'Bună ziua, ' . $name . '!';
            }
            $lines[] = 'Am observat că ai produse în coș pe besoiupieseauto.ro.';
        } else {
            $lines[] = 'Ai lăsat produse în conversația de pe site.';
        }

        $preview = $this->cartPreviewLines($cart, 3);
        if ($preview !== []) {
            $lines[] = 'Produse: ' . implode('; ', $preview);
        }

        $total = (float) ($meta['total_amount'] ?? 0);
        if ($total > 0) {
            $lines[] = 'Total orientativ: ' . number_format($total, 2, '.', '') . ' RON.';
        }

        $lines[] = 'Poți finaliza comanda aici: ' . rtrim($siteUrl, '/') . '/cart.php';
        $lines[] = 'Răspuns informativ — pentru ofertă personalizată sună-ne sau scrie pe WhatsApp.';

        return implode("\n", $lines);
    }

    /** @param list<array<string, mixed>> $cart @return list<string> */
    private function cartPreviewLines(array $cart, int $max = 3): array
    {
        $lines = [];
        foreach ($cart as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? $item['product_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $lines[] = $qty > 1 ? ($qty . '× ' . $name) : $name;
            if (count($lines) >= $max) {
                break;
            }
        }

        return $lines;
    }

    /** @param list<array<string, mixed>> $cart @param array<string, mixed> $meta */
    public function buildSmsMessage(array $cart, string $type = 'chat', array $meta = []): string
    {
        $siteUrl = rtrim(trim((string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'https://besoiupieseauto.ro')), '/');
        $preview = $this->cartPreviewLines($cart, 2);
        $parts = ['Besoiu Piese Auto:'];
        if ($type === 'checkout') {
            $parts[] = 'Ai produse în coș.';
        } else {
            $parts[] = 'Ai produse salvate în chat.';
        }
        if ($preview !== []) {
            $parts[] = implode(', ', $preview);
        }
        $parts[] = 'Finalizare: ' . $siteUrl . '/cart.php';

        return (new SmsNotificationService($this->projectRoot))->truncateMessage(implode(' ', $parts), 300);
    }

    /** @param list<array<string, mixed>> $cart @param array<string, mixed> $meta @return array{whatsapp:array<string,mixed>,sms:array<string,mixed>} */
    private function maybeSendOutbound(string $phone, string $message, array $cart, string $type = 'chat', array $meta = []): array
    {
        $wa = $this->maybeSendWhatsApp($phone, $message);
        $sms = ['ok' => false, 'skipped' => true, 'reason' => 'not_attempted'];
        $waOk = !empty($wa['ok']);
        if (!$waOk && SmsNotificationService::followupSendEnabled()) {
            $smsSvc = new SmsNotificationService($this->projectRoot);
            $smsMsg = $this->buildSmsMessage($cart, $type, $meta);
            $sms = $smsSvc->sendText($phone, $smsMsg);
        } elseif (!SmsNotificationService::followupSendEnabled()) {
            $sms = ['ok' => false, 'skipped' => true, 'reason' => 'SHOP_FOLLOWUP_SEND_SMS=0'];
        }

        return ['whatsapp' => $wa, 'sms' => $sms];
    }

    /** @return array<string, mixed> */
    private function maybeSendWhatsApp(string $phone, string $message): array
    {
        if (!WhatsAppUltraMsgService::sendEnabled()) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'SHOP_FOLLOWUP_SEND_WHATSAPP=0'];
        }

        $wa = new WhatsAppUltraMsgService($this->projectRoot);
        $normalized = $wa->normalizeWhatsAppPhone($phone);
        if ($normalized === '') {
            return ['ok' => false, 'skipped' => true, 'reason' => 'telefon_invalid'];
        }

        return $wa->sendText($normalized, $message);
    }

    /** @param array<string, mixed> $payload */
    private function saveSentRecord(string $key, array $payload): bool
    {
        $payload['sent_at'] = date('c');
        $path = $this->storageDir . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $key) . '.json';

        return @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
    }
}
