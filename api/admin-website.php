<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/system/site-live-cms.php';
require_once dirname(__DIR__) . '/system/site-builder.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Neautentificat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodă nepermisă.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$page = trim((string) ($data['page'] ?? ''));
$fields = $data['fields'] ?? [];
$blocks = $data['blocks'] ?? null;
$elementStyles = $data['elementStyles'] ?? null;
if (!is_array($fields)) {
    $fields = [];
}

$messages = [];

if (is_array($elementStyles) && $elementStyles !== []) {
    $styleResult = site_live_save_element_styles($page, $elementStyles);
    if (empty($styleResult['success'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => (string) ($styleResult['message'] ?? 'Eroare stiluri.')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $messages[] = (string) ($styleResult['message'] ?? 'Stiluri salvate.');
}

if ($fields !== []) {
    $byPage = [];
    foreach ($fields as $cmsKey => $value) {
        $key = (string) $cmsKey;
        $val = (string) $value;
        if (!str_contains($key, '.')) {
            $byPage[$page][$key] = $val;
            continue;
        }
        $parts = explode('.', $key, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $fieldPage = $parts[0];
        $fieldKey = $parts[1];
        if (!isset(site_live_pages_registry()[$fieldPage])) {
            $fieldPage = $page;
            $fieldKey = $key;
        }
        $byPage[$fieldPage][$fieldKey] = $val;
    }

    foreach ($byPage as $slug => $pageFields) {
        if ($pageFields === []) {
            continue;
        }
        $result = site_live_save_fields($slug, $pageFields);
        if (empty($result['success'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => (string) ($result['message'] ?? 'Eroare.')], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $messages[] = (string) ($result['message'] ?? 'Câmpuri salvate.');
    }
}

if (is_array($blocks)) {
    $blockResult = site_builder_save_blocks($page, $blocks);
    if (empty($blockResult['success'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => (string) ($blockResult['message'] ?? 'Eroare blocuri.')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $messages[] = (string) ($blockResult['message'] ?? 'Blocuri salvate.');
}

if ($messages === []) {
    $messages[] = 'Nimic de salvat.';
}

echo json_encode([
    'success' => true,
    'message' => implode(' ', $messages),
], JSON_UNESCAPED_UNICODE);
