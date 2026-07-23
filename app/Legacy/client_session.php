<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_learning.php';

function client_session_dir(): string
{
    $dir = ai_learning_dir() . '/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

/** @param array<string, mixed> $event */
function client_session_touch(string $sessionId, array $event): void
{
    $sessionId = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($sessionId)) ?? '';
    if ($sessionId === '') {
        return;
    }

    $path = client_session_dir() . '/' . md5($sessionId) . '.json';
    $state = [];
    if (is_file($path)) {
        $json = json_decode((string) file_get_contents($path), true);
        if (is_array($json)) {
            $state = $json;
        }
    }

    $action = (string) ($event['action'] ?? '');
    $subject = (string) ($event['subject'] ?? '');
    $meta = is_array($event['meta'] ?? null) ? $event['meta'] : [];
    $page = (string) ($meta['page'] ?? $subject);

    $state['session_id'] = $sessionId;
    $state['updated_at'] = date('c');
    $state['first_seen_at'] = (string) ($state['first_seen_at'] ?? date('c'));
    $state['last_page'] = $page !== '' ? $page : (string) ($state['last_page'] ?? '');
    $state['pages'] = is_array($state['pages'] ?? null) ? $state['pages'] : [];
    if ($page !== '' && !in_array($page, $state['pages'], true)) {
        $state['pages'][] = $page;
        $state['pages'] = array_slice($state['pages'], -20);
    }

    $state['actions'] = is_array($state['actions'] ?? null) ? $state['actions'] : [];
    $state['actions'][] = [
        'at' => date('c'),
        'action' => $action,
        'subject' => mb_substr($subject, 0, 160),
    ];
    $state['actions'] = array_slice($state['actions'], -40);

    if ($action === 'add_to_cart') {
        $state['intent'] = 'buy';
        $state['cart_adds'] = (int) ($state['cart_adds'] ?? 0) + 1;
    } elseif (in_array($action, ['cart_view', 'checkout_step'], true)) {
        $state['intent'] = 'buy';
    } elseif (in_array($action, ['product_view', 'product_click', 'search'], true)) {
        $state['intent'] = $state['intent'] ?? 'browse';
    }

    if ($action === 'search' && $subject !== '') {
        $searches = is_array($state['searches'] ?? null) ? $state['searches'] : [];
        $searches[] = $subject;
        $state['searches'] = array_values(array_unique(array_slice($searches, -10)));
    }

    if ($action === 'product_view' && $subject !== '') {
        $products = is_array($state['products_viewed'] ?? null) ? $state['products_viewed'] : [];
        $products[] = $subject;
        $state['products_viewed'] = array_values(array_unique(array_slice($products, -12)));
    }

    $state['event_count'] = (int) ($state['event_count'] ?? 0) + 1;
    file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/** @return list<array<string, mixed>> */
function client_session_list_recent(int $limit = 15): array
{
    $dir = client_session_dir();
    $files = glob($dir . '/*.json') ?: [];
    usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
    $out = [];
    foreach (array_slice($files, 0, max(1, min(50, $limit))) as $file) {
        $json = json_decode((string) file_get_contents($file), true);
        if (is_array($json)) {
            $out[] = $json;
        }
    }

    return $out;
}
