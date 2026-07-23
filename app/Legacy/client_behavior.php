<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_learning.php';
require_once __DIR__ . '/client_session.php';

/**
 * Comportament client storefront — legacy JSONL (deprecat).
 * Sursa activă: POST /api/events → coadă ai-events → ai_intel_* (MySQL).
 */

/** @return list<string> */
function client_behavior_allowed_actions(): array
{
    return [
        'page_view',
        'scroll',
        'click',
        'product_view',
        'product_click',
        'add_to_cart',
        'cart_view',
        'search',
        'time_on_page',
        'checkout_step',
        'idle_return',
    ];
}

function client_behavior_sanitize_session_id(string $sid): string
{
    $sid = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($sid)) ?? '';

    return mb_substr($sid, 0, 64);
}

function client_behavior_rate_ok(string $sessionId, int $incomingCount): bool
{
    if ($incomingCount < 1 || $incomingCount > 40) {
        return false;
    }

    $dir = ai_learning_dir() . '/client_rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $file = $dir . '/' . md5($sessionId) . '.json';
    $now = time();
    $window = 3600;
    $maxPerHour = 400;

    $state = ['count' => 0, 'reset_at' => $now + $window];
    if (is_file($file)) {
        $json = json_decode((string) file_get_contents($file), true);
        if (is_array($json)) {
            $state = $json;
        }
    }

    if (($state['reset_at'] ?? 0) < $now) {
        $state = ['count' => 0, 'reset_at' => $now + $window];
    }

    if ((int) ($state['count'] ?? 0) + $incomingCount > $maxPerHour) {
        return false;
    }

    $state['count'] = (int) ($state['count'] ?? 0) + $incomingCount;
    @file_put_contents($file, json_encode($state));

    return true;
}

/** @param array<string, mixed> $event */
function client_behavior_record(array $event, string $sessionId): bool
{
    $action = (string) ($event['action'] ?? '');
    if (!in_array($action, client_behavior_allowed_actions(), true)) {
        return false;
    }

    $subject = mb_substr(trim((string) ($event['subject'] ?? '')), 0, 200);
    $meta = is_array($event['meta'] ?? null) ? $event['meta'] : [];
    $meta['page'] = mb_substr(trim((string) ($meta['page'] ?? '')), 0, 180);
    $meta['session_id'] = $sessionId;
    if (isset($event['ts'])) {
        $meta['client_ts'] = (string) $event['ts'];
    }

    $helper = __DIR__ . '/ai_action_events.php';
    if (!is_file($helper)) {
        return false;
    }
    require_once $helper;
    if (!function_exists('ai_action_event_record')) {
        return false;
    }

    ai_action_event_record('client', $action, $subject, $meta);
    client_session_touch($sessionId, [
        'action' => $action,
        'subject' => $subject,
        'meta' => $meta,
    ]);

    return true;
}
