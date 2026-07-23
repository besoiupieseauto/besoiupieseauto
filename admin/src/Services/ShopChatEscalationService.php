<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Escaladări chat — mesaje nerăspuns / încredere scăzută → operator admin.
 */
final class ShopChatEscalationService
{
    private const TABLE = 'shop_chat_escalations';

    public static function isEnabled(): bool
    {
        $raw = strtolower(trim((string) ($_ENV['SHOP_CHAT_ESCALATION'] ?? getenv('SHOP_CHAT_ESCALATION') ?: '1')));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }

    public static function tableExists(?PDO $pdo = null): bool
    {
        $pdo = $pdo ?? Database::getDB();
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '" . self::TABLE . "'");

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?PDO $pdo = null): int
    {
        if (!self::isEnabled() || !self::tableExists($pdo)) {
            return 0;
        }

        $pdo = $pdo ?? Database::getDB();
        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '') {
            return 0;
        }

        $visitorKey = trim((string) ($data['visitor_key'] ?? ''));
        $channel = trim((string) ($data['channel'] ?? 'shop_chat'));
        if ($this->hasRecentDuplicate($pdo, $visitorKey, $message)) {
            return 0;
        }

        $meta = $data['meta'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::TABLE . ' (visitor_key, channel, phone, email, message, reason, status, meta_json, created_at)
                 VALUES (:vk, :ch, :ph, :em, :msg, :rs, :st, :meta, NOW())'
            );
            $stmt->execute([
                'vk' => mb_substr($visitorKey, 0, 64, 'UTF-8'),
                'ch' => mb_substr($channel, 0, 32, 'UTF-8'),
                'ph' => mb_substr(trim((string) ($data['phone'] ?? '')), 0, 50, 'UTF-8'),
                'em' => mb_substr(trim((string) ($data['email'] ?? '')), 0, 255, 'UTF-8'),
                'msg' => mb_substr($message, 0, 2000, 'UTF-8'),
                'rs' => mb_substr(trim((string) ($data['reason'] ?? 'no_answer')), 0, 64, 'UTF-8'),
                'st' => 'open',
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ]);

            $id = (int) $pdo->lastInsertId();
            $this->recordEvent($id, $channel, $message);
            $this->notifyOperatorSms($id, $message, trim((string) ($data['phone'] ?? '')));

            return $id;
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listOpen(int $limit = 30, ?PDO $pdo = null): array
    {
        if (!self::tableExists($pdo)) {
            return [];
        }

        $pdo = $pdo ?? Database::getDB();
        $limit = max(1, min(100, $limit));

        try {
            $stmt = $pdo->query(
                'SELECT * FROM ' . self::TABLE . " WHERE status = 'open' ORDER BY id DESC LIMIT {$limit}"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    public function countOpen(?PDO $pdo = null): int
    {
        if (!self::tableExists($pdo)) {
            return 0;
        }

        try {
            $pdo = $pdo ?? Database::getDB();

            return (int) $pdo->query("SELECT COUNT(*) FROM " . self::TABLE . " WHERE status = 'open'")->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    public function resolve(int $id, int $userId = 0, string $status = 'resolved', ?PDO $pdo = null): bool
    {
        if (!self::tableExists($pdo) || $id <= 0) {
            return false;
        }

        $pdo = $pdo ?? Database::getDB();
        $status = in_array($status, ['resolved', 'dismissed'], true) ? $status : 'resolved';

        try {
            $stmt = $pdo->prepare(
                'UPDATE ' . self::TABLE . ' SET status = :st, resolved_at = NOW(), resolved_by = :uid WHERE id = :id AND status = :open'
            );
            $stmt->execute(['st' => $status, 'uid' => $userId > 0 ? $userId : null, 'id' => $id, 'open' => 'open']);

            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function shouldEscalate(?array $agentReply, string $message): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        $message = trim($message);
        if (mb_strlen($message, 'UTF-8') < 8) {
            return false;
        }

        if (is_array($agentReply) && !empty($agentReply['ok'])) {
            return false;
        }

        if (preg_match('/\b(salut|buna|bună|hello|mulțumesc|multumesc)\b/iu', $message) && mb_strlen($message, 'UTF-8') < 20) {
            return false;
        }

        return true;
    }

    private function hasRecentDuplicate(PDO $pdo, string $visitorKey, string $message): bool
    {
        if ($visitorKey === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id FROM ' . self::TABLE . "
                 WHERE visitor_key = :vk AND message = :msg AND status = 'open'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR) LIMIT 1"
            );
            $stmt->execute([
                'vk' => $visitorKey,
                'msg' => mb_substr($message, 0, 2000, 'UTF-8'),
            ]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private function recordEvent(int $id, string $channel, string $message): void
    {
        $events = dirname(__DIR__, 3) . '/system/ai_action_events.php';
        if (!is_file($events)) {
            return;
        }
        require_once $events;
        if (!function_exists('ai_action_event_record')) {
            return;
        }

        ai_action_event_record('client', 'chat_escalation', (string) $id, [
            'channel' => $channel,
            'excerpt' => mb_substr($message, 0, 120, 'UTF-8'),
        ]);
    }

    private function notifyOperatorSms(int $id, string $message, string $clientPhone): void
    {
        if (!self::escalationSendEnabled()) {
            return;
        }
        $operator = SmsNotificationService::operatorPhone();
        if ($operator === '') {
            return;
        }

        $sms = new SmsNotificationService();
        $body = 'Escaladare chat #' . $id . ': ' . mb_substr($message, 0, 140, 'UTF-8');
        if ($clientPhone !== '') {
            $body .= ' Tel: ' . $clientPhone;
        }
        $sms->sendText($operator, $sms->truncateMessage($body, 300));
    }

    public static function escalationSendEnabled(): bool
    {
        return SmsNotificationService::escalationSendEnabled();
    }
}
