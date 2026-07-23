<?php

declare(strict_types=1);

/**
 * Hook ușor — înregistrează acțiuni client/admin fără autoload Composer (magazin, system).
 *
 * @param array<string, mixed> $meta
 */
function ai_action_event_record(
    string $actorType,
    string $action,
    string $subject = '',
    array $meta = [],
    ?string $actorId = null
): void {
    $actorType = in_array($actorType, ['admin', 'client', 'robot', 'cron', 'system'], true)
        ? $actorType
        : 'system';

    $root = dirname(__DIR__);
    $dir = $root . '/robot/data/ai_context';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = json_encode([
        'at' => date('c'),
        'actor_type' => $actorType,
        'actor_id' => $actorId,
        'action' => mb_substr(trim($action), 0, 120),
        'subject' => mb_substr(trim($subject), 0, 200),
        'meta' => $meta,
    ], JSON_UNESCAPED_UNICODE);

    if (!is_string($line)) {
        return;
    }

    @file_put_contents($dir . '/events.jsonl', $line . "\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($dir . '/sync_requested.at', date('c'), LOCK_EX);
}
