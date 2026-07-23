<?php
declare(strict_types=1);

/**
 * Jurnal ultimă rulare cron — produse migrate / sărite (vizibil în UI tab Cron).
 */
final class CronStagingJournal
{
    private const MAX_ITEMS = 500;

    public static function path(): string
    {
        return IMPORT_STATE_DIR . DIRECTORY_SEPARATOR . 'cron_staging_last.json';
    }

    /** @return array<string, mixed> */
    public static function read(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return self::empty();
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : self::empty();
    }

    public static function resetRun(?string $runId = null): void
    {
        $data = self::empty();
        $data['run_id'] = $runId ?? date('Ymd_His');
        $data['started_at'] = date('c');
        self::write($data);
    }

    /**
     * @param array<string, int|float> $statsDelta
     * @param list<array<string, mixed>> $items
     */
    public static function append(string $reportId, array $statsDelta, array $items = []): void
    {
        $data = self::read();
        if ($data['run_id'] === '') {
            $data['run_id'] = date('Ymd_His');
            $data['started_at'] = date('c');
        }

        $stats = is_array($data['stats'] ?? null) ? $data['stats'] : self::emptyStats();
        foreach ($statsDelta as $key => $value) {
            $stats[$key] = (int) ($stats[$key] ?? 0) + (int) $value;
        }
        $data['stats'] = $stats;

        $existing = is_array($data['items'] ?? null) ? $data['items'] : [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $existing[] = $item;
        }
        if (count($existing) > self::MAX_ITEMS) {
            $existing = array_slice($existing, -self::MAX_ITEMS);
        }
        $data['items'] = $existing;
        $data['updated_at'] = date('c');
        $data['last_report_id'] = $reportId;
        self::write($data);
    }

    /** @param array<string, mixed> $data */
    private static function write(array $data): void
    {
        if (!is_dir(IMPORT_STATE_DIR)) {
            @mkdir(IMPORT_STATE_DIR, 0775, true);
        }
        file_put_contents(
            self::path(),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            LOCK_EX
        );
    }

    /** @return array<string, mixed> */
    public static function empty(): array
    {
        return [
            'run_id' => '',
            'started_at' => null,
            'updated_at' => null,
            'last_report_id' => '',
            'stats' => self::emptyStats(),
            'items' => [],
        ];
    }

    /** @return array<string, int> */
    private static function emptyStats(): array
    {
        return [
            'scanned' => 0,
            'matched' => 0,
            'skipped_no_match' => 0,
            'showcase_candidates' => 0,
            'standard_candidates' => 0,
            'queued' => 0,
            'skipped_no_image' => 0,
            'skipped_incomplete' => 0,
            'with_image' => 0,
        ];
    }
}
