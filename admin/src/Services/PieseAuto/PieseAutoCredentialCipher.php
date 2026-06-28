<?php

declare(strict_types=1);

namespace Besoiu\Services\PieseAuto;

/**
 * Criptare reversibilă credențiale PieseAuto.ro (robotul are nevoie de parola în clar la login).
 */
final class PieseAutoCredentialCipher
{
    private const PREFIX = 'enc:v1:';

    public static function encrypt(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '') {
            return '';
        }

        if (str_starts_with($plain, self::PREFIX)) {
            return $plain;
        }

        $key = self::deriveKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Nu am putut cripta parola contului PieseAuto.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }

        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::deriveKey(), OPENSSL_RAW_DATA, $iv, $tag);

        return is_string($plain) ? $plain : '';
    }

    public static function isEncrypted(string $stored): bool
    {
        return str_starts_with(trim($stored), self::PREFIX);
    }

    private static function deriveKey(): string
    {
        $secret = trim((string) (getenv('PIESEAUTO_CREDENTIAL_KEY') ?: ($_ENV['PIESEAUTO_CREDENTIAL_KEY'] ?? '')));
        if ($secret === '') {
            $secret = trim((string) (getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '')));
        }
        if ($secret === '') {
            $secret = trim((string) (getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? 'besoiu-pieseauto-fallback')));
        }

        return hash('sha256', $secret, true);
    }
}
