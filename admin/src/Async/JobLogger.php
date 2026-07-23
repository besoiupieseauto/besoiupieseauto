<?php

declare(strict_types=1);

namespace Besoiu\Async;

final class JobLogger
{
    public static function log(string $event, array $context = []): void
    {
        $line = json_encode([
            'ts' => date('c'),
            'event' => $event,
            'pid' => getmypid(),
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($line)) {
            return;
        }

        $dir = AsyncConfig::storageDir() . '/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($dir . '/jobs_' . date('Y-m-d') . '.jsonl', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
