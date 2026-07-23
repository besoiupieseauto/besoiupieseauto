<?php

declare(strict_types=1);

namespace Besoiu\Core\Supplier;

/**
 * Etichete program scanare — fallback când modulul furnizori e oprit.
 */
final class ScanScheduleFallback
{
    /** @param array<string, mixed> $supplier */
    public static function formatLabel(array $supplier): string
    {
        if (!self::isAutoEnabled($supplier)) {
            return 'Automat oprit';
        }

        $mode = strtolower(trim((string) ($supplier['scan_schedule_mode'] ?? 'interval')));

        return match ($mode) {
            'manual' => 'Doar manual',
            'daily' => 'Zilnic la ' . self::formatTime((string) ($supplier['scan_schedule_time'] ?? '06:00')),
            'window' => self::formatWindowLabel($supplier),
            default => 'La ' . max(5, (int) ($supplier['scan_interval_minutes'] ?? 60)) . ' min',
        };
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    public static function shouldRunAuto(array $supplier, array $agentState = [], ?\DateTimeImmutable $now = null): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    public static function estimateNextRunAt(array $supplier, array $agentState = [], ?\DateTimeImmutable $now = null): ?\DateTimeImmutable
    {
        return null;
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    public static function formatNextRunLabel(array $supplier, array $agentState = [], ?\DateTimeImmutable $now = null): string
    {
        return '—';
    }

    /** @param array<string, mixed> $supplier */
    private static function isAutoEnabled(array $supplier): bool
    {
        $enabled = $supplier['scan_auto_enabled'] ?? 1;

        return $enabled === true || $enabled === 1 || $enabled === '1';
    }

    private static function formatTime(string $time): string
    {
        $time = trim($time);

        return $time !== '' ? $time : '06:00';
    }

    /** @param array<string, mixed> $supplier */
    private static function formatWindowLabel(array $supplier): string
    {
        $start = self::formatTime((string) ($supplier['scan_window_start'] ?? '08:00'));
        $end = self::formatTime((string) ($supplier['scan_window_end'] ?? '18:00'));

        return 'Fereastră ' . $start . '–' . $end;
    }
}
