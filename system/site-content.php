<?php
declare(strict_types=1);

require_once __DIR__ . '/site-defaults.php';

if (!function_exists('site_content_deep_merge')) {
    function site_content_deep_merge(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            if (
                is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && !array_is_list($value)
                && !array_is_list($base[$key])
            ) {
                $base[$key] = site_content_deep_merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}

if (!function_exists('site_cms_h')) {
    function site_cms_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('site_phone_to_tel_href')) {
    /**
     * Normalizează orice format de telefon românesc la tel:+40XXXXXXXXX
     */
    function site_phone_to_tel_href(string $phone): string
    {
        $raw = trim($phone);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^tel:/i', $raw)) {
            $raw = preg_replace('/^tel:/i', '', $raw);
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = '40' . substr($digits, 1);
        } elseif (strlen($digits) === 9 && $digits[0] === '7') {
            $digits = '40' . $digits;
        }

        return 'tel:+' . $digits;
    }
}

if (!function_exists('site_phone_resolve_href')) {
    function site_phone_resolve_href(string $displayPhone, string $phoneHref = ''): string
    {
        if (trim($phoneHref) !== '') {
            return site_phone_to_tel_href($phoneHref);
        }

        return site_phone_to_tel_href($displayPhone);
    }
}

if (!function_exists('site_phone_to_wa_href')) {
    /**
     * Link wa.me din număr de telefon (+ mesaj precompletat opțional).
     */
    function site_phone_to_wa_href(string $phone, string $prefill = ''): string
    {
        $tel = site_phone_to_tel_href($phone);
        if ($tel === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', preg_replace('/^tel:\+/i', '', $tel));
        if ($digits === '') {
            return '';
        }

        $url = 'https://wa.me/' . $digits;
        $prefill = trim($prefill);
        if ($prefill !== '') {
            $url .= '?text=' . rawurlencode($prefill);
        }

        return $url;
    }
}

if (!function_exists('site_cms_normalize_phone_blocks')) {
    /**
     * @param array<string,mixed> $blocks
     * @return array<string,mixed>
     */
    function site_cms_normalize_phone_blocks(array $blocks): array
    {
        foreach ($blocks as $key => $value) {
            if (!is_array($value)) {
                if (in_array($key, ['phone_href', 'link_href', 'subtitle_href'], true) && is_string($value) && preg_match('/^tel:/i', $value)) {
                    $blocks[$key] = site_phone_to_tel_href($value);
                }
                continue;
            }

            if (isset($value['phone']) && is_string($value['phone'])) {
                $value['phone_href'] = site_phone_resolve_href(
                    $value['phone'],
                    (string) ($value['phone_href'] ?? '')
                );
            }

            if (isset($value['link_href']) && is_string($value['link_href']) && preg_match('/^tel:/i', $value['link_href'])) {
                $value['link_href'] = site_phone_to_tel_href($value['link_href']);
            }

            $blocks[$key] = site_cms_normalize_phone_blocks($value);
        }

        return $blocks;
    }
}

if (!function_exists('site_cms_html')) {
    function site_cms_html($value): string
    {
        return (string) $value;
    }
}

if (!function_exists('site_content_load_env')) {
    function site_content_load_env(string $dir): void
    {
        static $loadedDirs = [];

        if (isset($loadedDirs[$dir])) {
            return;
        }

        $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($file)) {
            $loadedDirs[$dir] = true;
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            $loadedDirs[$dir] = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $loadedDirs[$dir] = true;
    }
}

if (!function_exists('site_content_db')) {
    function site_content_db(): ?PDO
    {
        static $pdo = null;
        static $failed = false;

        if ($failed) {
            return null;
        }
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        try {
            site_content_load_env(dirname(__DIR__) . '/admin');

            $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'localhost';
            $name = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? '';
            $user = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? '';
            $pass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? '';

            if ($name === '' || $user === '') {
                $failed = true;
                return null;
            }

            $pdo = new PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (Throwable $e) {
            error_log('[site-content] ' . $e->getMessage());
            $failed = true;
            return null;
        }

        return $pdo;
    }
}

if (!function_exists('site_content_decode_json')) {
    function site_content_decode_json(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('site_content_row')) {
    function site_content_row(string $slug): ?array
    {
        $pdo = site_content_db();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM site_pages WHERE slug = :slug AND is_active = 1 LIMIT 1');
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch();
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            error_log('[site-content-row] ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('site_content_blocks')) {
    function site_content_blocks(string $slug, ?array $defaults = null): array
    {
        $defaults = $defaults ?? site_defaults_blocks($slug);
        $row = site_content_row($slug);
        if (!$row) {
            return $defaults;
        }

        $decoded = site_content_decode_json($row['sections_json'] ?? '');
        if ($decoded === [] || array_is_list($decoded)) {
            return $defaults;
        }

        unset($decoded['_builder']);

        $merged = site_cms_normalize_phone_blocks(site_content_deep_merge($defaults, $decoded));

        return function_exists('besoiu_normalize_hrefs_in_array')
            ? besoiu_normalize_hrefs_in_array($merged)
            : $merged;
    }
}

if (!function_exists('site_content_page')) {
    /**
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    function site_content_page(string $slug, array $defaults): array
    {
        $row = site_content_row($slug);
        if (!$row) {
            return $defaults;
        }

        $page = $defaults;

        if (trim((string) ($row['title'] ?? '')) !== '') {
            $page['title'] = (string) $row['title'];
        }
        if (trim((string) ($row['meta_description'] ?? '')) !== '') {
            $page['description'] = (string) $row['meta_description'];
        }
        if (trim((string) ($row['hero_label'] ?? '')) !== '') {
            $page['hero_label'] = (string) $row['hero_label'];
        }
        if (trim((string) ($row['hero_title'] ?? '')) !== '') {
            $page['hero_title'] = (string) $row['hero_title'];
        }
        if (trim((string) ($row['hero_subtitle'] ?? '')) !== '') {
            $page['hero_subtitle'] = (string) $row['hero_subtitle'];
        }

        $bodyHtml = trim((string) ($row['body_html'] ?? ''));
        if ($bodyHtml !== '') {
            $page['sections'] = array_merge(
                [
                    [
                        'type' => 'content',
                        'title' => (string) ($row['hero_title'] ?? $page['title'] ?? 'Conținut'),
                        'paragraphs' => [$bodyHtml],
                    ],
                ],
                is_array($page['sections'] ?? null) ? $page['sections'] : []
            );
        }

        $sections = site_content_decode_json($row['sections_json'] ?? '');
        if ($sections !== [] && array_is_list($sections)) {
            $page['sections'] = $sections;
        }

        $faq = site_content_decode_json($row['faq_json'] ?? '');
        if ($faq !== []) {
            $page['faq'] = $faq;
        }

        $cta = site_content_decode_json($row['cta_json'] ?? '');
        if ($cta !== []) {
            $page['cta'] = $cta;
        }

        return $page;
    }
}

if (!function_exists('site_content_blog_posts')) {
    function site_content_blog_posts(int $limit = 20): array
    {
        $pdo = site_content_db();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, slug, title, tag, excerpt, body_html, featured_image, published_at
                 FROM blog_posts
                 WHERE is_published = 1
                 ORDER BY COALESCE(published_at, created_at) DESC, id DESC
                 LIMIT ' . max(1, min(100, $limit))
            );
            $stmt->execute();
            $rows = $stmt->fetchAll();
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            error_log('[site-content-blog] ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('site_content_blog_post_by_slug')) {
    function site_content_blog_post_by_slug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $pdo = site_content_db();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, slug, title, tag, excerpt, body_html, featured_image, published_at
                 FROM blog_posts
                 WHERE slug = :slug AND is_published = 1
                 LIMIT 1'
            );
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch();
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            error_log('[site-content-blog-slug] ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('site_content_hero_parts')) {
    /**
     * @return array{main:string,secondary:string}
     */
    function site_content_hero_parts(string $heroTitle): array
    {
        $parts = preg_split('/\r?\n|\|/', $heroTitle, 2) ?: [$heroTitle];
        return [
            'main' => trim((string) ($parts[0] ?? '')),
            'secondary' => trim((string) ($parts[1] ?? '')),
        ];
    }
}

if (!function_exists('site_content_blog_items_for_static_page')) {
    function site_content_blog_items_for_static_page(int $limit = 12): array
    {
        $items = [];
        foreach (site_content_blog_posts($limit) as $post) {
            $items[] = [
                'tag' => (string) ($post['tag'] ?? 'Articole'),
                'title' => (string) ($post['title'] ?? ''),
                'text' => (string) ($post['excerpt'] ?? ''),
                'slug' => (string) ($post['slug'] ?? ''),
            ];
        }

        return $items;
    }
}
