<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

/**
 * Program sincronizare per furnizor — interval, oră fixă, fereastră orară sau manual.
 */
final class SupplierScanScheduleService
{
    public const MODE_INTERVAL = 'interval';
    public const MODE_DAILY = 'daily';
    public const MODE_WINDOW = 'window';
    public const MODE_MANUAL = 'manual';

    /** @return list<string> */
    public static function allowedModes(): array
    {
        return [self::MODE_INTERVAL, self::MODE_DAILY, self::MODE_WINDOW, self::MODE_MANUAL];
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    public static function shouldRunAuto(array $supplier, array $agentState = [], ?\DateTimeImmutable $now = null): bool
    {
        if ((string) ($supplier['status'] ?? 'active') !== 'active') {
            return false;
        }

        if (!self::isAutoEnabled($supplier)) {
            return false;
        }

        $mode = self::normalizeMode((string) ($supplier['scan_schedule_mode'] ?? self::MODE_INTERVAL));
        if ($mode === self::MODE_MANUAL) {
            return false;
        }

        $now = $now ?? new \DateTimeImmutable('now');
        $lastRun = self::resolveLastRunAt($supplier, $agentState);

        return match ($mode) {
            self::MODE_INTERVAL => self::isIntervalDue($supplier, $lastRun, $now),
            self::MODE_DAILY => self::isDailyDue($supplier, $lastRun, $now),
            self::MODE_WINDOW => self::isWindowDue($supplier, $lastRun, $now),
            default => false,
        };
    }

    /** @param array<string, mixed> $supplier */
    public static function formatLabel(array $supplier): string
    {
        if (!self::isAutoEnabled($supplier)) {
            return 'Automat oprit';
        }

        $mode = self::normalizeMode((string) ($supplier['scan_schedule_mode'] ?? self::MODE_INTERVAL));

        return match ($mode) {
            self::MODE_MANUAL => 'Doar manual',
            self::MODE_DAILY => 'Zilnic la ' . self::formatTime((string) ($supplier['scan_schedule_time'] ?? '06:00')),
            self::MODE_WINDOW => self::formatWindowLabel($supplier),
            default => 'La ' . max(5, (int) ($supplier['scan_interval_minutes'] ?? 60)) . ' min',
        };
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    public static function estimateNextRunAt(array $supplier, array $agentState = [], ?\DateTimeImmutable $now = null): ?\DateTimeImmutable
    {
        if ((string) ($supplier['status'] ?? 'active') !== 'active') {
            return null;
        }
        if (!self::isAutoEnabled($supplier)) {
            return null;
        }

        $mode = self::normalizeMode((string) ($supplier['scan_schedule_mode'] ?? self::MODE_INTERVAL));
        if ($mode === self::MODE_MANUAL) {
            return null;
        }

        $now = $now ?? new \DateTimeImmutable('now');
        if (self::shouldRunAuto($supplier, $agentState, $now)) {
            return $now;
        }

        $lastRun = self::resolveLastRunAt($supplier, $agentState);

        return match ($mode) {
            self::MODE_INTERVAL => self::nextIntervalRun($supplier, $lastRun, $now),
            self::MODE_DAILY => self::nextDailyRun($supplier, $lastRun, $now),
            self::MODE_WINDOW => self::nextWindowRun($supplier, $lastRun, $now),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    public static function formatNextRunLabel(array $supplier, array $agentState = [], ?\DateTimeImmutable $now = null): string
    {
        if ((string) ($supplier['status'] ?? 'active') !== 'active') {
            return 'Furnizor inactiv';
        }
        if (!self::isAutoEnabled($supplier)) {
            return 'Automat oprit';
        }

        $mode = self::normalizeMode((string) ($supplier['scan_schedule_mode'] ?? self::MODE_INTERVAL));
        if ($mode === self::MODE_MANUAL) {
            return 'Doar manual';
        }

        $now = $now ?? new \DateTimeImmutable('now');
        if (self::shouldRunAuto($supplier, $agentState, $now)) {
            return 'Acum — așteaptă rularea cron';
        }

        $next = self::estimateNextRunAt($supplier, $agentState, $now);
        if (!$next instanceof \DateTimeImmutable) {
            return '—';
        }

        $diff = $next->getTimestamp() - $now->getTimestamp();
        if ($diff <= 90) {
            return 'În curând (' . $next->format('H:i') . ')';
        }

        return $next->format('d.m.Y H:i') . ' (' . self::formatRelativeSeconds($diff) . ')';
    }

    /** @param array<string, mixed> $supplier */
    public static function normalizeSupplierRow(array $supplier): array
    {
        $supplier['scan_schedule_mode'] = self::normalizeMode((string) ($supplier['scan_schedule_mode'] ?? self::MODE_INTERVAL));
        $supplier['scan_interval_minutes'] = max(5, (int) ($supplier['scan_interval_minutes'] ?? 60));
        $supplier['scan_schedule_time'] = self::normalizeTime((string) ($supplier['scan_schedule_time'] ?? '06:00'), '06:00');
        $supplier['scan_window_start'] = self::normalizeTime((string) ($supplier['scan_window_start'] ?? '08:00'), '08:00');
        $supplier['scan_window_end'] = self::normalizeTime((string) ($supplier['scan_window_end'] ?? '18:00'), '18:00');
        $supplier['scan_auto_enabled'] = self::isAutoEnabled($supplier) ? 1 : 0;
        $supplier['scan_schedule_label'] = self::formatLabel($supplier);

        return $supplier;
    }

    /** @param array<string, mixed> $supplier */
    private static function isAutoEnabled(array $supplier): bool
    {
        $value = $supplier['scan_auto_enabled'] ?? 1;

        return !in_array((string) $value, ['0', 'false', 'off', 'no'], true);
    }

    private static function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array($mode, self::allowedModes(), true) ? $mode : self::MODE_INTERVAL;
    }

    private static function normalizeTime(string $value, string $fallback): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m)) {
            $hour = max(0, min(23, (int) $m[1]));
            $minute = max(0, min(59, (int) $m[2]));

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return $fallback;
    }

    private static function formatTime(string $value): string
    {
        return self::normalizeTime($value, '06:00');
    }

    /** @param array<string, mixed> $supplier */
    private static function formatWindowLabel(array $supplier): string
    {
        $start = self::formatTime((string) ($supplier['scan_window_start'] ?? '08:00'));
        $end = self::formatTime((string) ($supplier['scan_window_end'] ?? '18:00'));
        $minutes = max(5, (int) ($supplier['scan_interval_minutes'] ?? 60));

        return 'Între ' . $start . '–' . $end . ', la ' . $minutes . ' min';
    }

    /**
     * @param array<string, mixed> $supplier
     * @param array<string, mixed> $agentState
     */
    private static function resolveLastRunAt(array $supplier, array $agentState): ?\DateTimeImmutable
    {
        $candidates = [];

        $dbLast = trim((string) ($supplier['last_scan_at'] ?? ''));
        if ($dbLast !== '') {
            try {
                $candidates[] = new \DateTimeImmutable($dbLast);
            } catch (\Exception) {
                // ignore invalid datetime
            }
        }

        $syncedAt = trim((string) ($agentState['synced_at'] ?? ''));
        if ($syncedAt !== '') {
            try {
                $candidates[] = new \DateTimeImmutable($syncedAt);
            } catch (\Exception) {
                // ignore invalid ISO
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $b <=> $a);

        return $candidates[0];
    }

    /** @param array<string, mixed> $supplier */
    private static function isIntervalDue(array $supplier, ?\DateTimeImmutable $lastRun, \DateTimeImmutable $now): bool
    {
        $minutes = max(5, (int) ($supplier['scan_interval_minutes'] ?? 60));
        if ($lastRun === null) {
            return true;
        }

        $diff = $now->getTimestamp() - $lastRun->getTimestamp();

        return $diff >= ($minutes * 60);
    }

    /** @param array<string, mixed> $supplier */
    private static function isDailyDue(array $supplier, ?\DateTimeImmutable $lastRun, \DateTimeImmutable $now): bool
    {
        $time = self::formatTime((string) ($supplier['scan_schedule_time'] ?? '06:00'));
        $scheduledToday = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $time);
        if (!$scheduledToday instanceof \DateTimeImmutable) {
            return false;
        }

        if ($now < $scheduledToday) {
            return false;
        }

        if ($lastRun === null) {
            return true;
        }

        return $lastRun < $scheduledToday;
    }

    /** @param array<string, mixed> $supplier */
    private static function isWindowDue(array $supplier, ?\DateTimeImmutable $lastRun, \DateTimeImmutable $now): bool
    {
        if (!self::isInsideWindow($supplier, $now)) {
            return false;
        }

        return self::isIntervalDue($supplier, $lastRun, $now);
    }

    /** @param array<string, mixed> $supplier */
    private static function isInsideWindow(array $supplier, \DateTimeImmutable $now): bool
    {
        $start = self::timeToMinutes(self::formatTime((string) ($supplier['scan_window_start'] ?? '08:00')));
        $end = self::timeToMinutes(self::formatTime((string) ($supplier['scan_window_end'] ?? '18:00')));
        $current = ((int) $now->format('H')) * 60 + (int) $now->format('i');

        if ($start === $end) {
            return true;
        }

        if ($start < $end) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }

    private static function timeToMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', self::formatTime($hhmm)));

        return ($h * 60) + $m;
    }

    private static function formatRelativeSeconds(int $seconds): string
    {
        if ($seconds < 3600) {
            return 'în ' . max(1, (int) round($seconds / 60)) . ' min';
        }
        if ($seconds < 86400) {
            $h = (int) floor($seconds / 3600);
            $m = (int) round(($seconds % 3600) / 60);

            return $m > 0 ? ('în ' . $h . 'h ' . $m . 'm') : ('în ' . $h . 'h');
        }

        $days = (int) floor($seconds / 86400);

        return 'în ' . $days . ' zile';
    }

    /** @param array<string, mixed> $supplier */
    private static function nextIntervalRun(array $supplier, ?\DateTimeImmutable $lastRun, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $minutes = max(5, (int) ($supplier['scan_interval_minutes'] ?? 60));
        if ($lastRun === null) {
            return $now;
        }

        return $lastRun->modify('+' . $minutes . ' minutes');
    }

    /** @param array<string, mixed> $supplier */
    private static function nextDailyRun(array $supplier, ?\DateTimeImmutable $lastRun, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $time = self::formatTime((string) ($supplier['scan_schedule_time'] ?? '06:00'));
        $todaySlot = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $time);
        if (!$todaySlot instanceof \DateTimeImmutable) {
            return $now->modify('+1 day');
        }

        if ($now < $todaySlot) {
            return $todaySlot;
        }

        if ($lastRun === null || $lastRun < $todaySlot) {
            return $now;
        }

        return $todaySlot->modify('+1 day');
    }

    /** @param array<string, mixed> $supplier */
    private static function nextWindowRun(array $supplier, ?\DateTimeImmutable $lastRun, \DateTimeImmutable $now): \DateTimeImmutable
    {
        if (!self::isInsideWindow($supplier, $now)) {
            return self::nextWindowOpenAt($supplier, $now);
        }

        return self::nextIntervalRun($supplier, $lastRun, $now);
    }

    /** @param array<string, mixed> $supplier */
    private static function nextWindowOpenAt(array $supplier, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $start = self::formatTime((string) ($supplier['scan_window_start'] ?? '08:00'));
        $todayOpen = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $start);
        if (!$todayOpen instanceof \DateTimeImmutable) {
            return $now->modify('+1 hour');
        }

        if ($now < $todayOpen) {
            return $todayOpen;
        }

        return $todayOpen->modify('+1 day');
    }
}
