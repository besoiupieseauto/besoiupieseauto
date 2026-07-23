<?php

declare(strict_types=1);

/**
 * Clasifică erorile de rețea cu adevărat tranzitorii (DNS, conexiune resetată,
 * răspuns gol) — merită 1 reîncercare imediată, spre deosebire de blocaje
 * aplicative (Cloudflare, Turnstile, 524) care sunt tratate de ScraperSourceBreaker
 * și NU se rezolvă printr-o reîncercare instantă în același job.
 */
final class ScraperTransientError
{
    /** @var list<string> */
    private const NEEDLES = [
        'could not resolve host',
        "couldn't resolve host",
        'connection reset',
        'connection refused',
        "couldn't connect",
        'could not connect',
        'empty reply from server',
        'operation timed out',
        'ssl connection timeout',
        'network is unreachable',
        'temporarily unavailable',
        'name or service not known',
        'resolving timed out',
        'send failure',
        'recv failure',
    ];

    public static function isTransient(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');
        foreach (self::NEEDLES as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
