<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';

/**
 * Circuit breaker minimal pentru surse stealth flaky (Autodoc, ePiesa).
 *
 * După N eșecuri de tip timeout/Cloudflare/Turnstile într-o fereastră scurtă,
 * blochează sursa temporar — evită să piardă 3-5 minute pe fiecare produs
 * din coadă lovind o sursă deja căzută (Turnstile activ, Cloudflare 524 etc.).
 *
 * Nu se aplică surselor rapide (BD, TecDoc CSV, local) — doar celor care
 * rulează Chrome headless și pot rămâne blocate minute întregi.
 */
final class ScraperSourceBreaker
{
    private const THRESHOLD = 3;
    private const WINDOW_SEC = 900;
    private const BLOCK_SEC = 900;

    /** Turnstile = profil Chrome ars, necesită click manual — nu se rezolvă singur în același batch. */
    private const HARD_BLOCK_SEC = 1800;

    /** @var list<string> */
    private const APPLICABLE_SOURCES = ['autodoc', 'epiesa'];

    public static function isApplicable(string $sourceId): bool
    {
        return in_array(strtolower(trim($sourceId)), self::APPLICABLE_SOURCES, true);
    }

    private static function statePath(string $sourceId): string
    {
        $dir = ScraperPaths::projectRoot() . '/storage/scraper';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/.circuit_' . preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($sourceId))) . '.json';
    }

    /** @return array<string, mixed> */
    private static function readState(string $sourceId): array
    {
        $path = self::statePath($sourceId);
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $state */
    private static function writeState(string $sourceId, array $state): void
    {
        @file_put_contents(self::statePath($sourceId), json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function isBlocked(string $sourceId): bool
    {
        $state = self::readState($sourceId);
        $blockedUntil = strtotime((string) ($state['blocked_until'] ?? ''));
        if ($blockedUntil === false || $blockedUntil <= 0) {
            return false;
        }
        if (time() >= $blockedUntil) {
            self::writeState($sourceId, []);

            return false;
        }

        return true;
    }

    public static function blockedMessage(string $sourceId): string
    {
        $state = self::readState($sourceId);
        $blockedUntil = strtotime((string) ($state['blocked_until'] ?? ''));
        $remainingMin = $blockedUntil > 0 ? max(1, (int) ceil(($blockedUntil - time()) / 60)) : 0;
        $reason = trim((string) ($state['last_reason'] ?? ''));

        $msg = 'Sursă blocată temporar (' . self::THRESHOLD . ' eșecuri consecutive) — reîncercare automată în ~' . $remainingMin . ' min.';
        if ($reason !== '') {
            $msg .= ' Ultimul motiv: ' . mb_substr($reason, 0, 120, 'UTF-8');
        }

        return $msg;
    }

    public static function recordFailure(string $sourceId, string $reason): void
    {
        $now = time();

        // Turnstile/bot-check = profil Chrome ars — un singur eșec e suficient ca semnal;
        // așteptarea a încă 2 eșecuri (prag normal) ar însemna alte ~10 minute irosite
        // pe un blocaj care necesită oricum click manual, nu se rezolvă singur.
        if (self::isHardBlockReason($reason)) {
            $state = self::readState($sourceId);
            $state['count'] = self::THRESHOLD;
            $state['window_start'] = $now;
            $state['last_reason'] = $reason;
            $state['last_failure_at'] = date('c', $now);
            $state['blocked_until'] = date('c', $now + self::HARD_BLOCK_SEC);
            self::writeState($sourceId, $state);

            return;
        }

        $state = self::readState($sourceId);
        $windowStart = (int) ($state['window_start'] ?? 0);
        $count = (int) ($state['count'] ?? 0);

        if ($windowStart <= 0 || ($now - $windowStart) > self::WINDOW_SEC) {
            $windowStart = $now;
            $count = 0;
        }
        ++$count;

        $state['window_start'] = $windowStart;
        $state['count'] = $count;
        $state['last_reason'] = $reason;
        $state['last_failure_at'] = date('c', $now);

        if ($count >= self::THRESHOLD) {
            $state['blocked_until'] = date('c', $now + self::BLOCK_SEC);
        }

        self::writeState($sourceId, $state);
    }

    /** Turnstile/bot-check — blocare imediată (1 strike), nu așteaptă pragul normal. */
    public static function isHardBlockReason(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        foreach (['turnstile', 'bot check', 'botcheck', 'profil chrome ars', 'captcha'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function recordSuccess(string $sourceId): void
    {
        $path = self::statePath($sourceId);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Detectează dacă mesajul de eroare justifică incrementarea circuit breaker-ului. */
    public static function isFailureReason(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');

        foreach (['timeout', 'timed out', 'cloudflare', 'turnstile', '524', 'just a moment', 'bot check', 'ocupat'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
