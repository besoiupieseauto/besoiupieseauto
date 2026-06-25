<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

$file = $_FILES['file'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fișier invalid sau lipsă.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tmp = (string) ($file['tmp_name'] ?? '');
$orig = (string) ($file['name'] ?? 'image');
$size = (int) ($file['size'] ?? 0);

if ($size <= 0 || $size > 8 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Imaginea trebuie să fie între 1 B și 8 MB.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp) ?: '';
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];
if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format permis: JPG, PNG, WebP, GIF.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$subdir = date('Y/m');
$baseDir = dirname(__DIR__) . '/uploads/cms/' . $subdir;
if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Nu pot crea folderul uploads.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$baseName = preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($orig, PATHINFO_FILENAME)) ?: 'img';
$baseName = trim(substr($baseName, 0, 48), '-');
$filename = $baseName . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $allowed[$mime];
$dest = $baseDir . '/' . $filename;

if (!move_uploaded_file($tmp, $dest)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload eșuat.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = '/uploads/cms/' . $subdir . '/' . $filename;

echo json_encode([
    'success' => true,
    'url' => $url,
    'message' => 'Imagine încărcată.',
], JSON_UNESCAPED_UNICODE);
