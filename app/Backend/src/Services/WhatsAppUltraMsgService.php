<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Throwable;

/**
 * Trimitere mesaje WhatsApp via UltraMsg (același API ca robot/webhook.php).
 */
final class WhatsAppUltraMsgService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->bootstrapEnv();
    }

    public static function isConfigured(): bool
    {
        $instance = trim((string) ($_ENV['ULTRAMSG_INSTANCE'] ?? getenv('ULTRAMSG_INSTANCE') ?: ''));
        $token = trim((string) ($_ENV['ULTRAMSG_TOKEN'] ?? getenv('ULTRAMSG_TOKEN') ?: ''));

        return $instance !== '' && $token !== '';
    }

    public static function sendEnabled(): bool
    {
        if (!self::isConfigured()) {
            return false;
        }
        $raw = strtolower(trim((string) ($_ENV['SHOP_FOLLOWUP_SEND_WHATSAPP'] ?? getenv('SHOP_FOLLOWUP_SEND_WHATSAPP') ?: '0')));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array{ok: bool, http_code: int, error: string, raw: string} */
    public function sendText(string $phone, string $body): array
    {
        $phone = $this->normalizeWhatsAppPhone($phone);
        $body = trim($body);
        if ($phone === '' || $body === '') {
            return ['ok' => false, 'http_code' => 0, 'error' => 'Telefon sau mesaj gol.', 'raw' => ''];
        }

        $instance = trim((string) ($_ENV['ULTRAMSG_INSTANCE'] ?? getenv('ULTRAMSG_INSTANCE') ?: ''));
        $token = trim((string) ($_ENV['ULTRAMSG_TOKEN'] ?? getenv('ULTRAMSG_TOKEN') ?: ''));
        if ($instance === '' || $token === '') {
            return ['ok' => false, 'http_code' => 0, 'error' => 'ULTRAMSG_INSTANCE/TOKEN lipsă.', 'raw' => ''];
        }

        $url = 'https://api.ultramsg.com/' . rawurlencode($instance) . '/messages/chat';
        $payload = ['token' => $token, 'to' => $phone, 'body' => $body];

        try {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'http_code' => 0, 'error' => 'curl_init failed', 'raw' => ''];
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $raw = (string) curl_exec($ch);
            $err = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $ok = $code >= 200 && $code < 300 && $err === '';
            $this->logSend($phone, $code, $err, $raw, $ok);

            return [
                'ok' => $ok,
                'http_code' => $code,
                'error' => $err,
                'raw' => mb_substr($raw, 0, 500, 'UTF-8'),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'http_code' => 0, 'error' => $e->getMessage(), 'raw' => ''];
        }
    }

    public function normalizeWhatsAppPhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }
        $phone = preg_replace('/@.*$/', '', $phone) ?? $phone;
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '4' . $digits;
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $digits = '40' . $digits;
        }

        return $digits;
    }

    private function bootstrapEnv(): void
    {
        $robotBootstrap = $this->projectRoot . '/robot/bootstrap.php';
        if (is_file($robotBootstrap)) {
            require_once $robotBootstrap;
        }
        $adminEnv = $this->projectRoot . '/admin/.env';
        if (is_file($adminEnv) && is_file($this->projectRoot . '/admin/vendor/autoload.php')) {
            require_once $this->projectRoot . '/admin/vendor/autoload.php';
            if (class_exists(\Dotenv\Dotenv::class)) {
                $dotenv = \Dotenv\Dotenv::createImmutable($this->projectRoot . '/admin');
                $dotenv->safeLoad();
            }
        }
    }

    private function logSend(string $phone, int $code, string $err, string $raw, bool $ok): void
    {
        $dir = $this->projectRoot . '/admin/storage/whatsapp_send';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = date('c') . ' to=' . $phone . ' ok=' . ($ok ? '1' : '0')
            . ' http=' . $code . ' err=' . $err . ' raw=' . mb_substr($raw, 0, 200, 'UTF-8') . "\n";
        @file_put_contents($dir . '/send.log', $line, FILE_APPEND);
    }
}
