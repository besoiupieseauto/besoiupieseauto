<?php
declare(strict_types=1);

require_once __DIR__ . '/site-content.php';

if (!function_exists('site_live_admin_authenticated')) {
    function site_live_admin_authenticated(): bool
    {
        $activeName = session_status() === PHP_SESSION_ACTIVE ? session_name() : '';
        $activeId = session_status() === PHP_SESSION_ACTIVE ? session_id() : '';

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('PHPSESSID');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        $ok = !empty($_SESSION['user_id']);
        session_write_close();

        if ($activeName !== '' && $activeId !== '') {
            session_name($activeName);
            session_id($activeId);
            session_start();
        }

        return $ok;
    }
}

if (!function_exists('site_live_edit_mode')) {
    function site_live_edit_mode(): bool
    {
        if (empty($_GET['site_cms_edit']) || (string) $_GET['site_cms_edit'] !== '1') {
            return false;
        }

        return site_live_admin_authenticated();
    }
}

if (!function_exists('site_live_public_path')) {
  function site_live_public_path(string $slug): string
  {
    require_once __DIR__ . '/url.php';

    return besoiu_url($slug);
  }
}

if (!function_exists('site_live_frame_url')) {
  function site_live_frame_url(string $slug): string
  {
    $path = site_live_public_path($slug);
    $query = http_build_query([
      'site_cms_edit' => '1',
      'site_cms_page' => $slug,
    ]);
    $relative = $path . (str_contains($path, '?') ? '&' : '?') . $query;

    // URL absolut producție — evită APP_URL/.test sau redirect Apache greșit în iframe
    return besoiu_absolute_url($relative);
  }
}

if (!function_exists('site_live_builtin_slugs')) {
    /**
     * Slug-uri cu fișier PHP dedicat — nu se pot șterge din admin.
     *
     * @return list<string>
     */
    function site_live_builtin_slugs(): array
    {
        return [
            'home',
            'catalog',
            'about',
            'contact',
            'cum-comand',
            'livrare-plata',
            'retur-garantie',
            'intrebari-frecvente',
            'termeni-conditii',
            'politica-confidentialitate',
            'politica-cookies',
            'cariere',
            'blog',
        ];
    }
}

if (!function_exists('site_live_pages_registry')) {
    /**
     * @return array<string, array{label:string,file:string,live:bool,cms_only?:bool}>
     */
    function site_live_pages_registry(): array
    {
        $base = [
            'home' => ['label' => 'Acasă', 'file' => 'index.php', 'live' => true],
            'catalog' => ['label' => 'Catalog', 'file' => 'catalog.php', 'live' => true],
            'about' => ['label' => 'Despre noi', 'file' => 'about.php', 'live' => true],
            'contact' => ['label' => 'Contact', 'file' => 'contact.php', 'live' => true],
            'cum-comand' => ['label' => 'Cum comand', 'file' => 'cum-comand.php', 'live' => true],
            'livrare-plata' => ['label' => 'Livrare și plată', 'file' => 'livrare-plata.php', 'live' => true],
            'retur-garantie' => ['label' => 'Retur și garanție', 'file' => 'retur-garantie.php', 'live' => true],
            'intrebari-frecvente' => ['label' => 'Întrebări frecvente', 'file' => 'intrebari-frecvente.php', 'live' => true],
            'termeni-conditii' => ['label' => 'Termeni și condiții', 'file' => 'termeni-conditii.php', 'live' => true],
            'politica-confidentialitate' => ['label' => 'Confidențialitate', 'file' => 'politica-confidentialitate.php', 'live' => true],
            'politica-cookies' => ['label' => 'Cookies', 'file' => 'politica-cookies.php', 'live' => true],
            'cariere' => ['label' => 'Cariere', 'file' => 'cariere.php', 'live' => true],
            'blog' => ['label' => 'Blog', 'file' => 'blog.php', 'live' => true],
            'global' => ['label' => 'Header & Footer', 'file' => '', 'live' => false],
        ];

        try {
            if (!function_exists('site_content_db')) {
                require_once __DIR__ . '/site-content.php';
            }
            $pdo = site_content_db();
            if ($pdo instanceof PDO) {
                $stmt = $pdo->query('SELECT slug, label FROM site_pages ORDER BY sort_order ASC, id ASC');
                if ($stmt !== false) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $slug = trim((string) ($row['slug'] ?? ''));
                        if ($slug === '' || $slug === 'global' || isset($base[$slug])) {
                            continue;
                        }
                        $base[$slug] = [
                            'label' => trim((string) ($row['label'] ?? $slug)),
                            'file' => $slug,
                            'live' => true,
                            'cms_only' => true,
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[site-live-cms] registry merge: ' . $e->getMessage());
        }

        return $base;
    }
}

if (!function_exists('site_live_page_slug')) {
    function site_live_page_slug(): string
    {
        return trim((string) ($GLOBALS['bpaCmsPage'] ?? ''));
    }
}

if (!function_exists('site_live_nested_get')) {
    function site_live_nested_get(array $data, string $path): mixed
    {
        $current = $data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }
}

if (!function_exists('site_live_nested_set')) {
    function site_live_nested_set(array &$data, string $path, string $value): void
    {
        $parts = explode('.', $path);
        $current = &$data;
        $last = count($parts) - 1;
        foreach ($parts as $index => $part) {
            if ($index === $last) {
                $current[$part] = $value;
                return;
            }
            if (!isset($current[$part]) || !is_array($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }
    }
}

if (!function_exists('site_live_field_value')) {
    function site_live_field_value(string $page, string $key, ?string $default = null): string
    {
        $value = $default ?? '';
        $row = site_content_row($page);

        if ($row !== null) {
            if (in_array($key, ['hero_label', 'hero_title', 'hero_subtitle', 'title', 'meta_description', 'body_html'], true)) {
                $dbVal = trim((string) ($row[$key] ?? ''));
                if ($dbVal !== '') {
                    return $dbVal;
                }
            } elseif (str_starts_with($key, 'cta.')) {
                $ctaKey = substr($key, 4);
                $cta = site_content_decode_json($row['cta_json'] ?? '');
                $ctaVal = site_live_nested_get($cta, $ctaKey);
                if ($ctaVal !== null && trim((string) $ctaVal) !== '') {
                    return is_array($ctaVal) ? (string) ($ctaVal['label'] ?? json_encode($ctaVal)) : (string) $ctaVal;
                }
            } elseif (str_contains($key, '.')) {
                $sections = site_content_decode_json($row['sections_json'] ?? '');
                if (is_array($sections) && !array_is_list($sections)) {
                    $nested = site_live_nested_get($sections, $key);
                    if ($nested !== null && trim((string) $nested) !== '') {
                        return (string) $nested;
                    }
                }
            }
        }

        if ($value !== '' && $value !== null) {
            return (string) $value;
        }

        if (function_exists('site_defaults_blocks')) {
            $defaults = site_defaults_blocks($page);
            $nested = site_live_nested_get($defaults, $key);
            if ($nested !== null && is_scalar($nested)) {
                return (string) $nested;
            }
        }

        return (string) ($default ?? '');
    }
}

if (!function_exists('site_live_cms_styles_load')) {
    /** @return array<string, array<string, string>> */
    function site_live_cms_styles_load(string $page): array
    {
        $row = site_content_row($page);
        if ($row === null) {
            return [];
        }
        $sections = site_content_decode_json($row['sections_json'] ?? '');
        if (!is_array($sections)) {
            return [];
        }
        $stored = $sections['_cms_styles'] ?? [];
        if (!is_array($stored)) {
            return [];
        }
        $out = [];
        foreach ($stored as $key => $style) {
            if (!is_array($style)) {
                continue;
            }
            $normalized = [];
            foreach ($style as $sk => $sv) {
                $normalized[(string) $sk] = is_string($sv) ? trim($sv) : (string) $sv;
            }
            $out[(string) $key] = $normalized;
        }
        return $out;
    }
}

if (!function_exists('site_live_cms_element_style')) {
    /** @return array<string, string> */
    function site_live_cms_element_style(string $page, string $key): array
    {
        $styles = site_live_cms_styles_load($page);
        $shortKey = str_starts_with($key, $page . '.') ? substr($key, strlen($page) + 1) : $key;

        return $styles[$shortKey] ?? $styles[$key] ?? [];
    }
}

if (!function_exists('site_live_cms_style_attrs')) {
    /** @param array<string, string> $style */
    function site_live_cms_style_attrs(array $style): string
    {
        if ($style === []) {
            return '';
        }
        $classes = ['bpa-cms-styled'];
        $map = [
            'padding' => 'bpa-pad',
            'marginTop' => 'bpa-mt',
            'marginBottom' => 'bpa-mb',
            'textAlign' => 'bpa-ta',
            'maxWidth' => 'bpa-mw',
            'borderRadius' => 'bpa-br',
        ];
        foreach ($map as $prop => $prefix) {
            $val = $style[$prop] ?? '';
            if ($val !== '' && $val !== 'none') {
                $classes[] = $prefix . '-' . preg_replace('/[^a-z0-9_-]/i', '', $val);
            }
        }
        $css = [];
        if (($style['bgColor'] ?? '') !== '') {
            $css[] = 'background-color:' . $style['bgColor'];
        }
        if (($style['textColor'] ?? '') !== '') {
            $css[] = 'color:' . $style['textColor'];
        }
        if (($style['bgImage'] ?? '') !== '') {
            $css[] = "background-image:url('" . htmlspecialchars($style['bgImage'], ENT_QUOTES) . "')";
            $css[] = 'background-size:cover';
            $css[] = 'background-position:center';
        }
        $attr = ' class="' . htmlspecialchars(implode(' ', $classes), ENT_QUOTES) . '"';
        if ($css !== []) {
            $attr .= ' style="' . htmlspecialchars(implode(';', $css), ENT_QUOTES) . '"';
        }
        return $attr;
    }
}

if (!function_exists('site_live_save_element_styles')) {
    /**
     * @param array<string, array<string, string>> $styles
     * @return array{success:bool,message:string}
     */
    function site_live_save_element_styles(string $pageSlug, array $styles): array
    {
        $pdo = site_content_db();
        if (!$pdo) {
            return ['success' => false, 'message' => 'Conexiune BD indisponibilă.'];
        }
        $stmt = $pdo->prepare('SELECT id, sections_json FROM site_pages WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $pageSlug]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return ['success' => false, 'message' => 'Pagina nu există.'];
        }
        $sections = site_content_decode_json($row['sections_json'] ?? '');
        if (!is_array($sections) || array_is_list($sections)) {
            $sections = [];
        }
        $normalized = [];
        foreach ($styles as $key => $style) {
            if (!is_array($style)) {
                continue;
            }
            $k = (string) $key;
            if (str_starts_with($k, $pageSlug . '.')) {
                $k = substr($k, strlen($pageSlug) + 1);
            }
            $rowStyle = [];
            foreach ($style as $sk => $sv) {
                $rowStyle[(string) $sk] = trim((string) $sv);
            }
            $normalized[$k] = $rowStyle;
        }
        $sections['_cms_styles'] = $normalized;
        $upd = $pdo->prepare('UPDATE site_pages SET sections_json = :json WHERE id = :id');
        $upd->execute([
            ':json' => json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id' => (int) $row['id'],
        ]);
        return ['success' => true, 'message' => 'Stiluri elemente salvate.'];
    }
}

if (!function_exists('site_live_cms_tag')) {
    /**
     * @param array<string, string> $attrs
     */
    function site_live_cms_tag(string $page, string $key, string $tag, ?string $default = null, array $attrs = [], bool $html = false): void
    {
        $value = site_live_field_value($page, $key, $default);
        $cmsKey = htmlspecialchars($page . '.' . $key, ENT_QUOTES, 'UTF-8');
        $elStyle = site_live_cms_element_style($page, $key);
        $styleAttr = site_live_cms_style_attrs($elStyle);
        $attrStr = '';
        foreach ($attrs as $name => $val) {
            if ($name === 'class' && $elStyle !== []) {
                $val = trim((string) $val . ' bpa-cms-styled');
            }
            $attrStr .= ' ' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8')
                . '="' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '"';
        }

        if (site_live_edit_mode()) {
            $editClass = isset($attrs['class']) ? '' : ' class="bpa-cms-field"';
            if ($styleAttr !== '' && $editClass === '') {
                // styleAttr already has class
            }
            echo '<' . $tag . ($styleAttr !== '' ? $styleAttr : $editClass) . $attrStr
                . ' data-cms="' . $cmsKey . '" data-cms-page="' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-cms-key="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-cms-html="' . ($html ? '1' : '0') . '" contenteditable="true" spellcheck="true">';
            echo $html ? $value : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            echo '</' . $tag . '>';
            return;
        }

        $outAttr = $styleAttr . $attrStr;
        echo '<' . $tag . ($outAttr !== '' ? $outAttr : '') . '>';
        echo $html ? $value : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        echo '</' . $tag . '>';
    }
}

if (!function_exists('site_live_save_fields')) {
    /**
     * @param array<string, string> $fields
     * @return array{success:bool,message:string}
     */
    function site_live_save_fields(string $pageSlug, array $fields): array
    {
        $registry = site_live_pages_registry();
        if (!isset($registry[$pageSlug])) {
            return ['success' => false, 'message' => 'Pagină necunoscută.'];
        }

        $pdo = site_content_db();
        if (!$pdo) {
            return ['success' => false, 'message' => 'Conexiune BD indisponibilă.'];
        }

        $stmt = $pdo->prepare('SELECT * FROM site_pages WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $pageSlug]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return ['success' => false, 'message' => 'Pagina nu există în CMS.'];
        }

        $columns = ['hero_label', 'hero_title', 'hero_subtitle', 'title', 'meta_description', 'body_html'];
        $updates = [];
        $params = [':id' => (int) $row['id']];
        $sections = site_content_decode_json($row['sections_json'] ?? '');
        if (!is_array($sections) || array_is_list($sections)) {
            $sections = [];
        }
        $builderBackup = $sections['_builder'] ?? null;
        $cmsStylesBackup = $sections['_cms_styles'] ?? null;
        $sectionsChanged = false;
        $cta = site_content_decode_json($row['cta_json'] ?? '');
        if (!is_array($cta)) {
            $cta = [];
        }
        $ctaChanged = false;

        foreach ($fields as $rawKey => $rawValue) {
            $key = (string) $rawKey;
            $value = trim((string) $rawValue);
            if (in_array($key, $columns, true)) {
                $updates[$key] = $value;
                continue;
            }
            if (str_starts_with($key, 'cta.')) {
                $ctaKey = substr($key, 4);
                site_live_nested_set($cta, $ctaKey, $value);
                $ctaChanged = true;
                continue;
            }
            if (str_contains($key, '.')) {
                site_live_nested_set($sections, $key, $value);
                $sectionsChanged = true;
            }
        }

        if ($ctaChanged) {
            $updates['cta_json'] = json_encode($cta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($sectionsChanged) {
            if ($builderBackup !== null) {
                $sections['_builder'] = $builderBackup;
            }
            if ($cmsStylesBackup !== null) {
                $sections['_cms_styles'] = $cmsStylesBackup;
            }
            $updates['sections_json'] = json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($updates === []) {
            return ['success' => true, 'message' => 'Nicio modificare de text.'];
        }

        $setParts = [];
        foreach ($updates as $col => $val) {
            $param = ':u_' . $col;
            $setParts[] = "`{$col}` = {$param}";
            $params[$param] = $val;
        }

        $sql = 'UPDATE site_pages SET ' . implode(', ', $setParts) . ' WHERE id = :id';
        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        return ['success' => true, 'message' => 'Text salvat.'];
    }
}

if (!function_exists('site_live_cms_image_registry')) {
    /**
     * Registru imagini editabile per pagină (+ globale header/footer).
     *
     * @return list<array{key:string,label:string,group:string,variant:string,page?:string}>
     */
    function site_live_cms_image_registry(string $page): array
    {
        $items = [];

        if ($page === 'home') {
            $benefitLabels = ['Compatibilitate verificată', 'Livrare rapidă', 'Suport telefonic', 'Peste 10k produse'];
            foreach (range(0, 3) as $i) {
                $items[] = [
                    'key' => 'hero.benefits.' . $i . '.icon',
                    'label' => 'Hero — icon ' . ($benefitLabels[$i] ?? (string) ($i + 1)),
                    'group' => 'Hero',
                    'variant' => 'icon',
                ];
            }
            $items[] = [
                'key' => 'hero.popup_product.image',
                'label' => 'Hero — imagine promo (fallback)',
                'group' => 'Hero',
                'variant' => 'full',
            ];
            foreach (range(0, 7) as $i) {
                $items[] = [
                    'key' => 'hero.promo_slides.' . $i . '.image',
                    'label' => 'Carousel hero — slide ' . ($i + 1) . ' (manual)',
                    'group' => 'Carousel hero (fără BD)',
                    'variant' => 'full',
                ];
            }
            $trustLabels = ['Livrare rapidă', 'Retur simplu', 'Plată ramburs', 'Verificare compatibilitate', 'Suport telefonic'];
            foreach (range(0, 4) as $i) {
                $items[] = [
                    'key' => 'products.trust.' . $i . '.icon',
                    'label' => 'Trust bar — ' . ($trustLabels[$i] ?? (string) ($i + 1)),
                    'group' => 'Trust bar',
                    'variant' => 'icon',
                ];
            }
            $items[] = [
                'key' => 'why.car_image',
                'label' => 'De ce noi — imagine mașină (full)',
                'group' => 'De ce noi',
                'variant' => 'full',
            ];
        }

        if ($page === 'about') {
            $items[] = [
                'key' => 'story.image',
                'label' => 'Poveste — imagine principală',
                'group' => 'Despre',
                'variant' => 'full',
            ];
        }

        $globalItems = [
            ['key' => 'header.logo', 'label' => 'Logo header', 'group' => 'Header & Footer', 'variant' => 'logo'],
        ];
        foreach (range(0, 2) as $i) {
            $globalItems[] = [
                'key' => 'topbar.' . $i . '.icon',
                'label' => 'Topbar — icon ' . ($i + 1),
                'group' => 'Header & Footer',
                'variant' => 'icon',
            ];
        }
        $socialLabels = ['Facebook', 'Instagram', 'YouTube', 'TikTok'];
        foreach (range(0, 3) as $i) {
            $globalItems[] = [
                'key' => 'footer.social.' . $i . '.icon',
                'label' => 'Footer social — ' . ($socialLabels[$i] ?? (string) ($i + 1)),
                'group' => 'Header & Footer',
                'variant' => 'icon',
            ];
        }

        foreach ($globalItems as $gi) {
            $gi['page'] = 'global';
            $items[] = $gi;
        }

        return $items;
    }
}

if (!function_exists('site_live_cms_image_page_key')) {
    /** @return array{page:string,key:string} */
    function site_live_cms_image_page_key(string $page, string $key, ?string $itemPage = null): array
    {
        if ($itemPage !== null && $itemPage !== '') {
            return ['page' => $itemPage, 'key' => $key];
        }
        if (str_starts_with($key, 'global.')) {
            return ['page' => 'global', 'key' => substr($key, 7)];
        }

        return ['page' => $page, 'key' => $key];
    }
}

if (!function_exists('site_live_cms_image_url')) {
    function site_live_cms_image_url(string $page, string $key, ?string $default = null, ?string $itemPage = null): string
    {
        $resolved = site_live_cms_image_page_key($page, $key, $itemPage);
        $url = trim(site_live_field_value($resolved['page'], $resolved['key'], $default));
        if ($url === '' && $default !== null) {
            return trim($default);
        }

        return $url;
    }
}

if (!function_exists('site_live_cms_image_cms_attr')) {
    function site_live_cms_image_cms_attr(string $page, string $key, ?string $itemPage = null): string
    {
        $resolved = site_live_cms_image_page_key($page, $key, $itemPage);

        return htmlspecialchars($resolved['page'] . '.' . $resolved['key'], ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('site_live_cms_image_tag')) {
    /**
     * @param array<string, string> $attrs
     */
    function site_live_cms_image_tag(string $page, string $key, ?string $default = null, array $attrs = [], ?string $itemPage = null): void
    {
        $resolved = site_live_cms_image_page_key($page, $key, $itemPage);
        $url = site_live_cms_image_url($page, $key, $default, $itemPage);
        $cmsAttr = site_live_cms_image_cms_attr($page, $key, $itemPage);
        $variant = (string) ($attrs['data-cms-variant'] ?? 'default');
        unset($attrs['data-cms-variant']);

        $attrStr = '';
        foreach ($attrs as $name => $val) {
            $attrStr .= ' ' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8')
                . '="' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '"';
        }

        if ($url === '') {
            if (site_live_edit_mode()) {
                echo '<span class="bpa-cms-image-wrap bpa-cms-image-wrap--empty bpa-cms-image-wrap--' . htmlspecialchars($variant, ENT_QUOTES) . '"'
                    . ' data-cms-image="' . $cmsAttr . '" data-cms-variant="' . htmlspecialchars($variant, ENT_QUOTES) . '">';
                echo '<span class="bpa-cms-image-placeholder"><i class="fa-solid fa-image"></i> Adaugă imagine</span></span>';
            }
            return;
        }

        if (site_live_edit_mode()) {
            echo '<span class="bpa-cms-image-wrap bpa-cms-image-wrap--' . htmlspecialchars($variant, ENT_QUOTES) . '">';
            echo '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '"'
                . $attrStr
                . ' data-cms-image="' . $cmsAttr . '"'
                . ' data-cms-variant="' . htmlspecialchars($variant, ENT_QUOTES) . '"'
                . ' class="bpa-cms-image' . (isset($attrs['class']) ? ' ' . htmlspecialchars((string) $attrs['class'], ENT_QUOTES) : '') . '">';
            echo '<span class="bpa-cms-image-badge">Schimbă imaginea</span></span>';
            return;
        }

        echo '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $attrStr . '>';
    }
}

if (!function_exists('site_live_cms_promo_slides')) {
    /**
     * Slide-uri carousel hero din CMS (hero.promo_slides + tab Imagini).
     *
     * @param array<string, mixed> $home
     * @return list<array<string, mixed>>
     */
    function site_live_cms_promo_slides(array $home): array
    {
        if (!function_exists('besoiu_hero_promo_clean_badge')) {
            require_once __DIR__ . '/hero-promo-carousel.php';
        }

        $raw = $home['hero']['promo_slides'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $popup = is_array($home['hero']['popup_product'] ?? null) ? $home['hero']['popup_product'] : [];
        $defaultTitle = trim((string) ($popup['title'] ?? 'Ofertă specială'));
        $defaultPrice = trim((string) ($popup['price'] ?? ''));
        $defaultBadge = besoiu_hero_promo_clean_badge(trim((string) ($popup['stock'] ?? 'În stoc')));
        $defaultHref = trim((string) ($popup['url'] ?? '/catalog'));
        $out = [];

        for ($i = 0; $i < 8; $i++) {
            $slideData = [];
            if (isset($raw[$i]) && is_array($raw[$i])) {
                $slideData = $raw[$i];
            } elseif (isset($raw[(string) $i]) && is_array($raw[(string) $i])) {
                $slideData = $raw[(string) $i];
            }

            $image = trim((string) ($slideData['image'] ?? ''));
            if ($image === '') {
                $image = trim(site_live_cms_image_url('home', 'hero.promo_slides.' . $i . '.image', ''));
            }

            $title = trim((string) ($slideData['title'] ?? ''));
            $price = trim((string) ($slideData['price'] ?? ''));
            $badge = trim((string) ($slideData['badge'] ?? ''));
            $href = trim((string) ($slideData['href'] ?? ''));

            if ($image === '' && $title === '' && $price === '') {
                continue;
            }

            $out[] = [
                'id' => 'cms-slide-' . $i,
                'title' => $title !== '' ? $title : ($i === 0 ? $defaultTitle : 'Ofertă specială'),
                'price' => $price !== '' ? $price : ($i === 0 ? $defaultPrice : ''),
                'badge' => besoiu_hero_promo_clean_badge($badge !== '' ? $badge : $defaultBadge),
                'image' => $image,
                'href' => $href !== '' ? $href : $defaultHref,
                'external' => false,
            ];
        }

        if ($out !== []) {
            return $out;
        }

        foreach ($raw as $i => $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $image = trim((string) ($slide['image'] ?? ''));
            $title = trim((string) ($slide['title'] ?? ''));
            if ($image === '' && $title === '') {
                continue;
            }
            $out[] = [
                'id' => 'cms-slide-' . $i,
                'title' => $title !== '' ? $title : $defaultTitle,
                'price' => trim((string) ($slide['price'] ?? $defaultPrice)),
                'badge' => besoiu_hero_promo_clean_badge(trim((string) ($slide['badge'] ?? $defaultBadge))),
                'image' => $image,
                'href' => trim((string) ($slide['href'] ?? $defaultHref)),
                'external' => false,
            ];
        }

        return $out;
    }
}

if (!function_exists('site_live_cms_images_export')) {
    /** @return list<array<string, mixed>> */
    function site_live_cms_images_export(string $page): array
    {
        $out = [];
        foreach (site_live_cms_image_registry($page) as $item) {
            $itemPage = (string) ($item['page'] ?? $page);
            $key = (string) $item['key'];
            $defaults = site_defaults_blocks($itemPage);
            $default = site_live_nested_get($defaults, $key);
            $defaultUrl = is_string($default) ? $default : '';
            $out[] = array_merge($item, [
                'page' => $itemPage,
                'cmsKey' => $itemPage . '.' . $key,
                'url' => site_live_cms_image_url($page, $key, $defaultUrl, $itemPage),
                'defaultUrl' => $defaultUrl,
            ]);
        }

        return $out;
    }
}
