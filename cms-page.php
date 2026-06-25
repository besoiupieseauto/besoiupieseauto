<?php
declare(strict_types=1);

require_once __DIR__ . '/system/static-page.php';
require_once __DIR__ . '/system/site-content.php';
require_once __DIR__ . '/system/site-live-cms.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
    http_response_code(404);
    echo 'Pagina nu a fost găsită.';
    exit;
}

$reserved = ['admin', 'api', 'assets', 'cache', 'config', 'robot', 'bes', 'system', 'uploads', 'img', 'cache_tecdoc'];
if (in_array($slug, $reserved, true)) {
    http_response_code(404);
    echo 'Pagina nu a fost găsită.';
    exit;
}

if (in_array($slug, site_live_builtin_slugs(), true)) {
    http_response_code(404);
    echo 'Pagina nu a fost găsită.';
    exit;
}

$row = site_content_row($slug);
if ($row === null) {
    http_response_code(404);
    echo 'Pagina nu a fost găsită.';
    exit;
}

$GLOBALS['bpaCmsPage'] = $slug;

$label = trim((string) ($row['label'] ?? $slug));
$title = trim((string) ($row['title'] ?? ''));
if ($title === '') {
    $title = $label;
}

$defaults = [
    'title' => $title,
    'description' => trim((string) ($row['meta_description'] ?? '')),
    'hero_label' => trim((string) ($row['hero_label'] ?? '')),
    'hero_title' => trim((string) ($row['hero_title'] ?? $title)),
    'hero_subtitle' => trim((string) ($row['hero_subtitle'] ?? '')),
    'body_html' => trim((string) ($row['body_html'] ?? '')),
    'sections' => [],
    'faq' => [],
    'cta' => null,
];

render_static_page(site_content_page($slug, $defaults));
