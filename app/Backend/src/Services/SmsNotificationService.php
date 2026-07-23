<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Throwable;

/**
 * SMS opțional — WhosMS (același provider ca ERP Laravel).
 */
final class SmsNotificationService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    public static function isConfigured(): bool
    {
        $user = trim((string) ($_ENV['SMS_API_USER'] ?? getenv('SMS_API_USER') ?: ''));
        $pass = trim((string) ($_ENV['SMS_API_PASS'] ?? getenv('SMS_API_PASS') ?: ''));

        return $user !== '' && $pass !== '';
    }

    public static function followupSendEnabled(): bool
    {
        if (!self::isConfigured()) {
            return false;
        }
        $raw = strtolower(trim((string) ($_ENV['SHOP_FOLLOWUP_SEND_SMS'] ?? getenv('SHOP_FOLLOWUP_SEND_SMS') ?: '0')));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    public static function escalationSendEnabled(): bool
    {
        if (!self::isConfigured()) {
            return false;
        }
        $raw = strtolower(trim((string) ($_ENV['SHOP_ESCALATION_SEND_SMS'] ?? getenv('SHOP_ESCALATION_SEND_SMS') ?: '0')));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    public static function operatorPhone(): string
    {
        return trim((string) ($_ENV['SHOP_ESCALATION_OPERATOR_PHONE'] ?? getenv('SHOP_ESCALATION_OPERATOR_PHONE') ?: ''));
    }

    /** @return array{ok:bool,error?:string,details?:array<string,mixed>,skipped?:bool,reason?:string} */
    public function sendText(string $phone, string $message, ?string $sender = null): array
    {
        $phone = $this->formatPhoneNumber($phone);
        $message = $this->truncateMessage(trim($message));
        if ($phone === '' || $message === '') {
            return ['ok' => false, 'skipped' => true, 'reason' => 'telefon_sau_mesaj_gol'];
        }

        if (!self::isConfigured()) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'SMS_API_USER/PASS lipsă'];
        }

        $provider = strtolower(trim((string) ($_ENV['SMS_PROVIDER'] ?? getenv('SMS_PROVIDER') ?: 'whosms')));
        if ($provider === 'msghub') {
            return $this->sendViaMsghub($phone, $message);
        }

        return $this->sendViaWhosms($phone, $message, $sender);
    }

    public function formatPhoneNumber(string $phoneNumber): string
    {
        $phoneNumber = preg_replace('/\s+/', '', $phoneNumber) ?? '';
        $phoneNumber = str_replace(['+', '-', '(', ')', '.'], '', $phoneNumber);
        if ($phoneNumber === '') {
            return '';
        }
        if (str_starts_with($phoneNumber, '0')) {
            $phoneNumber = '40' . substr($phoneNumber, 1);
        }
        if (!str_starts_with($phoneNumber, '40')) {
            $phoneNumber = '40' . $phoneNumber;
        }

        return preg_match('/^40\d{9}$/', $phoneNumber) ? $phoneNumber : '';
    }

    public function truncateMessage(string $message, int $maxLen = 320): string
    {
        if (mb_strlen($message, 'UTF-8') <= $maxLen) {
            return $message;
        }

        return mb_substr($message, 0, $maxLen - 1, 'UTF-8') . '…';
    }

    /** @return array{ok:bool,error?:string,details?:array<string,mixed>} */
    private function sendViaWhosms(string $phoneNumber, string $message, ?string $sender): array
    {
        $user = trim((string) ($_ENV['SMS_API_USER'] ?? getenv('SMS_API_USER') ?: ''));
        $pass = trim((string) ($_ENV['SMS_API_PASS'] ?? getenv('SMS_API_PASS') ?: ''));
        $senderName = $sender ?: trim((string) ($_ENV['SMS_SENDER_ID'] ?? getenv('SMS_SENDER_ID') ?: 'BesoiuAuto'));

        $url = 'http://www.whosms.ro/send.php?'
            . 'user=' . rawurlencode($user)
            . '&pass=' . rawurlencode($pass)
            . '&dela=' . rawurlencode($senderName)
            . '&catre=' . rawurlencode($phoneNumber)
            . '&mesaj=' . rawurlencode($message)
            . '&json=1';

        try {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 20,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            $result = is_string($raw) ? json_decode($raw, true) : null;
            $ok = is_array($result) && isset($result['status']) && (string) $result['status'] === '1';
            $this->logSend($phoneNumber, $ok, is_array($result) ? $result : ['raw' => $raw]);

            if ($ok) {
                return [
                    'ok' => true,
                    'details' => [
                        'id' => $result['id'] ?? null,
                        'parts' => $result['parti'] ?? null,
                        'cost' => $result['cost'] ?? null,
                    ],
                ];
            }

            return [
                'ok' => false,
                'error' => is_array($result) ? (string) ($result['mesaj'] ?? 'Eroare WhosMS') : 'Răspuns WhosMS invalid',
            ];
        } catch (Throwable $e) {
            $this->logSend($phoneNumber, false, ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok:bool,error?:string,details?:array<string,mixed>} */
    private function sendViaMsghub(string $phoneNumber, string $message): array
    {
        $endpoint = trim((string) ($_ENV['MSGHUB_API_ENDPOINT'] ?? getenv('MSGHUB_API_ENDPOINT') ?: 'https://api-test.msghub.cloud/send'));
        $apiKey = trim((string) ($_ENV['MSGHUB_API_KEY'] ?? getenv('MSGHUB_API_KEY') ?: ''));
        $apiSecret = trim((string) ($_ENV['MSGHUB_API_SECRET'] ?? getenv('MSGHUB_API_SECRET') ?: ''));
        if ($apiKey === '' || $apiSecret === '') {
            return ['ok' => false, 'error' => 'MSGHUB_API_KEY/SECRET lipsă'];
        }

        $payload = json_encode([
            'msisdn' => $phoneNumber,
            'sc' => trim((string) ($_ENV['MSGHUB_SC'] ?? getenv('MSGHUB_SC') ?: '3737')),
            'text' => $message,
            'service_id' => trim((string) ($_ENV['MSGHUB_SERVICE_ID'] ?? getenv('MSGHUB_SERVICE_ID') ?: '2219')),
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return ['ok' => false, 'error' => 'Payload JSON invalid'];
        }

        try {
            $ch = curl_init($endpoint);
            if ($ch === false) {
                return ['ok' => false, 'error' => 'curl_init failed'];
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret),
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
            ]);
            $raw = (string) curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $ok = $code >= 200 && $code < 300;
            $this->logSend($phoneNumber, $ok, ['http_code' => $code, 'raw' => $raw]);

            return $ok
                ? ['ok' => true, 'details' => ['http_code' => $code]]
                : ['ok' => false, 'error' => 'MSGHub HTTP ' . $code];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @param array<string, mixed> $meta */
    private function logSend(string $phone, bool $ok, array $meta): void
    {
        $dir = $this->projectRoot . '/admin/storage/sms_send';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = date('Y-m-d H:i:s') . ' ok=' . ($ok ? '1' : '0') . ' phone=' . $phone . ' ' . json_encode($meta, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($dir . '/send.log', $line, FILE_APPEND | LOCK_EX);
    }
}
