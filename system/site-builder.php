<?php
declare(strict_types=1);

require_once __DIR__ . '/site-live-cms.php';

if (!function_exists('site_builder_style_defaults')) {
    /** @return array<string, string> */
    function site_builder_style_defaults(): array
    {
        return [
            'bgColor' => '',
            'textColor' => '',
            'bgImage' => '',
            'bgOverlay' => '',
            'padding' => 'md',
            'marginTop' => 'none',
            'marginBottom' => 'none',
            'textAlign' => 'left',
            'maxWidth' => 'container',
            'borderRadius' => 'none',
        ];
    }
}

if (!function_exists('site_builder_style_fields')) {
    /** @return list<array<string, mixed>> */
    function site_builder_style_fields(): array
    {
        return [
            ['key' => 'bgColor', 'label' => 'Fundal', 'type' => 'color'],
            ['key' => 'textColor', 'label' => 'Culoare text', 'type' => 'color'],
            ['key' => 'bgImage', 'label' => 'Imagine fundal', 'type' => 'image'],
            ['key' => 'bgOverlay', 'label' => 'Overlay fundal (rgba)', 'type' => 'text', 'placeholder' => 'rgba(0,0,0,0.45)'],
            ['key' => 'padding', 'label' => 'Padding', 'type' => 'select', 'options' => [
                'none' => 'Fără', 'sm' => 'Mic', 'md' => 'Mediu', 'lg' => 'Mare', 'xl' => 'Extra mare',
            ]],
            ['key' => 'marginTop', 'label' => 'Spațiu sus', 'type' => 'select', 'options' => [
                'none' => 'Fără', 'sm' => 'Mic', 'md' => 'Mediu', 'lg' => 'Mare',
            ]],
            ['key' => 'marginBottom', 'label' => 'Spațiu jos', 'type' => 'select', 'options' => [
                'none' => 'Fără', 'sm' => 'Mic', 'md' => 'Mediu', 'lg' => 'Mare',
            ]],
            ['key' => 'textAlign', 'label' => 'Aliniere', 'type' => 'select', 'options' => [
                'left' => 'Stânga', 'center' => 'Centru', 'right' => 'Dreapta',
            ]],
            ['key' => 'maxWidth', 'label' => 'Lățime conținut', 'type' => 'select', 'options' => [
                'full' => '100%', 'container' => 'Container', 'narrow' => 'Îngust',
            ]],
            ['key' => 'borderRadius', 'label' => 'Colțuri rotunjite', 'type' => 'select', 'options' => [
                'none' => 'Fără', 'sm' => 'Mic', 'md' => 'Mediu', 'lg' => 'Mare',
            ]],
        ];
    }
}

if (!function_exists('site_builder_block_types')) {
    /** @return array<string, array<string, mixed>> */
    function site_builder_block_types(): array
    {
        return [
            'section' => [
                'label' => 'Secțiune',
                'icon' => 'fa-layer-group',
                'desc' => 'Container full-width cu fundal și padding',
                'container' => true,
                'defaults' => ['label' => 'Secțiune nouă'],
                'fields' => [
                    ['key' => 'label', 'label' => 'Nume secțiune (editor)', 'type' => 'text'],
                ],
            ],
            'row' => [
                'label' => 'Rând coloane',
                'icon' => 'fa-table-columns',
                'desc' => '2–4 coloane — Grid sau Flex — trage blocuri în coloane',
                'container' => true,
                'defaults' => ['cols' => '2', 'gap' => 'md', 'layout' => 'grid', 'align' => 'stretch', 'justify' => 'start'],
                'fields' => [
                    ['key' => 'layout', 'label' => 'Tip layout', 'type' => 'select', 'options' => [
                        'grid' => 'Grid (grilă)', 'flex' => 'Flex (rând flexibil)',
                    ]],
                    ['key' => 'cols', 'label' => 'Coloane', 'type' => 'select', 'options' => ['2' => '2 coloane', '3' => '3 coloane', '4' => '4 coloane']],
                    ['key' => 'gap', 'label' => 'Spațiu între coloane', 'type' => 'select', 'options' => ['sm' => 'Mic', 'md' => 'Mediu', 'lg' => 'Mare']],
                    ['key' => 'align', 'label' => 'Aliniere verticală (flex)', 'type' => 'select', 'options' => [
                        'stretch' => 'Întins', 'start' => 'Sus', 'center' => 'Centru', 'end' => 'Jos',
                    ]],
                    ['key' => 'justify', 'label' => 'Aliniere orizontală (flex)', 'type' => 'select', 'options' => [
                        'start' => 'Stânga', 'center' => 'Centru', 'end' => 'Dreapta', 'between' => 'Spațiat egal',
                    ]],
                ],
            ],
            'heading' => [
                'label' => 'Titlu',
                'icon' => 'fa-heading',
                'desc' => 'Titlu H1–H4, editabil direct pe pagină',
                'defaults' => ['level' => 'h2', 'text' => 'Titlu nou'],
                'fields' => [
                    ['key' => 'level', 'label' => 'Nivel', 'type' => 'select', 'options' => [
                        'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4',
                    ]],
                    ['key' => 'text', 'label' => 'Text', 'type' => 'text', 'inline' => true],
                ],
            ],
            'text' => [
                'label' => 'Paragraf',
                'icon' => 'fa-align-left',
                'desc' => 'Text rich — click pe pagină pentru editare',
                'defaults' => ['html' => '<p>Scrie aici conținutul. Poți folosi <strong>bold</strong> și linkuri.</p>'],
                'fields' => [
                    ['key' => 'html', 'label' => 'Conținut', 'type' => 'html', 'inline' => true],
                ],
            ],
            'message' => [
                'label' => 'Mesaj / Alertă',
                'icon' => 'fa-message',
                'desc' => 'Casetă informativă colorată',
                'defaults' => ['variant' => 'info', 'title' => 'Informare', 'text' => 'Mesaj pentru vizitatori.'],
                'fields' => [
                    ['key' => 'variant', 'label' => 'Stil', 'type' => 'select', 'options' => [
                        'info' => 'Info', 'success' => 'Succes', 'warning' => 'Atenție', 'danger' => 'Important',
                    ]],
                    ['key' => 'title', 'label' => 'Titlu', 'type' => 'text', 'inline' => true],
                    ['key' => 'text', 'label' => 'Mesaj', 'type' => 'textarea', 'inline' => true],
                ],
            ],
            'image' => [
                'label' => 'Imagine',
                'icon' => 'fa-image',
                'desc' => 'Fotografie sau banner — upload sau URL',
                'defaults' => ['url' => '', 'alt' => 'Imagine', 'caption' => '', 'link' => '', 'height' => 'auto', 'width' => 'container'],
                'fields' => [
                    ['key' => 'url', 'label' => 'Imagine', 'type' => 'image'],
                    ['key' => 'alt', 'label' => 'Text alternativ', 'type' => 'text'],
                    ['key' => 'caption', 'label' => 'Legendă', 'type' => 'text'],
                    ['key' => 'link', 'label' => 'Link la click', 'type' => 'text'],
                    ['key' => 'height', 'label' => 'Înălțime', 'type' => 'select', 'options' => [
                        'auto' => 'Auto', 'sm' => '200px', 'md' => '320px', 'lg' => '480px', 'cover' => 'Cover', 'full' => 'Full width (100%)',
                    ]],
                    ['key' => 'width', 'label' => 'Lățime', 'type' => 'select', 'options' => [
                        'container' => 'Container', 'full' => '100% pagină',
                    ]],
                ],
            ],
            'button' => [
                'label' => 'Buton',
                'icon' => 'fa-hand-pointer',
                'desc' => 'Call-to-action',
                'defaults' => ['label' => 'Află mai multe', 'url' => '/catalog', 'style' => 'accent', 'size' => 'md'],
                'fields' => [
                    ['key' => 'label', 'label' => 'Text buton', 'type' => 'text', 'inline' => true],
                    ['key' => 'url', 'label' => 'Link', 'type' => 'text'],
                    ['key' => 'style', 'label' => 'Stil', 'type' => 'select', 'options' => [
                        'accent' => 'Accent (verde)', 'ghost' => 'Contur', 'glow' => 'Evidențiat', 'dark' => 'Întunecat',
                    ]],
                    ['key' => 'size', 'label' => 'Mărime', 'type' => 'select', 'options' => ['sm' => 'Mic', 'md' => 'Mediu', 'lg' => 'Mare']],
                ],
            ],
            'iconbox' => [
                'label' => 'Cutie icon',
                'icon' => 'fa-star',
                'desc' => 'Icon + titlu + text',
                'defaults' => [
                    'icon' => 'fa-solid fa-check',
                    'title' => 'Titlu',
                    'text' => 'Descriere scurtă.',
                    'link' => '',
                    'image' => '',
                ],
                'fields' => [
                    ['key' => 'icon', 'label' => 'Clasă icon FontAwesome', 'type' => 'text', 'placeholder' => 'fa-solid fa-truck'],
                    ['key' => 'image', 'label' => 'Imagine (opțional)', 'type' => 'image'],
                    ['key' => 'title', 'label' => 'Titlu', 'type' => 'text', 'inline' => true],
                    ['key' => 'text', 'label' => 'Text', 'type' => 'textarea', 'inline' => true],
                    ['key' => 'link', 'label' => 'Link', 'type' => 'text'],
                ],
            ],
            'cards' => [
                'label' => 'Grilă carduri',
                'icon' => 'fa-grip',
                'desc' => 'Carduri în grilă responsive',
                'defaults' => [
                    'title' => 'Titlu secțiune',
                    'cols' => '3',
                    'items' => '[{"title":"Card 1","text":"Descriere.","icon":"fa-solid fa-box"},{"title":"Card 2","text":"Descriere.","icon":"fa-solid fa-truck"},{"title":"Card 3","text":"Descriere.","icon":"fa-solid fa-shield"}]',
                ],
                'fields' => [
                    ['key' => 'title', 'label' => 'Titlu secțiune', 'type' => 'text', 'inline' => true],
                    ['key' => 'cols', 'label' => 'Coloane', 'type' => 'select', 'options' => ['2' => '2', '3' => '3', '4' => '4']],
                    ['key' => 'items', 'label' => 'Carduri (JSON)', 'type' => 'json'],
                ],
            ],
            'steps' => [
                'label' => 'Pași',
                'icon' => 'fa-list-ol',
                'desc' => 'Listă numerotată de pași',
                'defaults' => [
                    'title' => 'Pașii comenzii',
                    'items' => '[{"title":"Pas 1","text":"Descriere."},{"title":"Pas 2","text":"Descriere."}]',
                ],
                'fields' => [
                    ['key' => 'title', 'label' => 'Titlu', 'type' => 'text', 'inline' => true],
                    ['key' => 'items', 'label' => 'Pași (JSON)', 'type' => 'json'],
                ],
            ],
            'faq' => [
                'label' => 'Întrebări FAQ',
                'icon' => 'fa-circle-question',
                'desc' => 'Acordeon întrebări / răspunsuri',
                'defaults' => [
                    'title' => 'Întrebări frecvente',
                    'items' => '[{"q":"Întrebare?","a":"<p>Răspuns.</p>"}]',
                ],
                'fields' => [
                    ['key' => 'title', 'label' => 'Titlu', 'type' => 'text', 'inline' => true],
                    ['key' => 'items', 'label' => 'FAQ (JSON)', 'type' => 'json'],
                ],
            ],
            'video' => [
                'label' => 'Video',
                'icon' => 'fa-play',
                'desc' => 'YouTube sau Vimeo embed',
                'defaults' => ['url' => '', 'caption' => ''],
                'fields' => [
                    ['key' => 'url', 'label' => 'URL video', 'type' => 'text'],
                    ['key' => 'caption', 'label' => 'Legendă', 'type' => 'text'],
                ],
            ],
            'html' => [
                'label' => 'HTML liber',
                'icon' => 'fa-code',
                'desc' => 'Cod HTML personalizat',
                'defaults' => ['html' => '<div class="bpa-custom-html"><p>HTML personalizat</p></div>'],
                'fields' => [
                    ['key' => 'html', 'label' => 'HTML', 'type' => 'html'],
                ],
            ],
            'columns' => [
                'label' => '2 coloane text',
                'icon' => 'fa-columns',
                'desc' => 'Text simplu în două coloane',
                'defaults' => [
                    'left' => '<p><strong>Stânga</strong></p>',
                    'right' => '<p><strong>Dreapta</strong></p>',
                ],
                'fields' => [
                    ['key' => 'left', 'label' => 'Coloana stânga', 'type' => 'html', 'inline' => true],
                    ['key' => 'right', 'label' => 'Coloana dreapta', 'type' => 'html', 'inline' => true],
                ],
            ],
            'spacer' => [
                'label' => 'Spațiu',
                'icon' => 'fa-arrows-up-down',
                'desc' => 'Spațiu vertical',
                'defaults' => ['size' => 'md'],
                'fields' => [
                    ['key' => 'size', 'label' => 'Înălțime', 'type' => 'select', 'options' => ['sm' => 'Mic', 'md' => 'Mediu', 'lg' => 'Mare', 'xl' => 'Extra']],
                ],
            ],
            'divider' => [
                'label' => 'Separator',
                'icon' => 'fa-minus',
                'desc' => 'Linie de separare',
                'defaults' => ['style' => 'solid'],
                'fields' => [
                    ['key' => 'style', 'label' => 'Stil', 'type' => 'select', 'options' => ['solid' => 'Linie', 'dashed' => 'Întreruptă', 'dots' => 'Puncte']],
                ],
            ],
        ];
    }
}

if (!function_exists('site_builder_page_zones')) {
    /** @return array<string, list<string>> */
    function site_builder_page_zones(): array
    {
        $default = ['main', 'after_hero', 'before_footer'];
        $homeZones = [
            'main', 'after_hero', 'before_special', 'before_categories',
            'before_products', 'before_brands', 'before_why', 'before_footer',
        ];
        $registry = site_live_pages_registry();
        $out = [];
        foreach ($registry as $slug => $meta) {
            if (!empty($meta['live'])) {
                $out[$slug] = $slug === 'home' ? $homeZones : $default;
            }
        }
        return $out;
    }
}

if (!function_exists('site_builder_zone_labels')) {
    /** @return array<string, string> */
    function site_builder_zone_labels(): array
    {
        return [
            'main' => 'Conținut principal',
            'after_hero' => 'După hero',
            'before_special' => 'Înainte de produse speciale',
            'before_categories' => 'Înainte de categorii',
            'before_products' => 'Înainte de produse recomandate',
            'before_brands' => 'Înainte de mărci',
            'before_why' => 'Înainte de „De ce noi”',
            'before_footer' => 'Înainte de footer',
        ];
    }
}

if (!function_exists('site_builder_new_id')) {
    function site_builder_new_id(): string
    {
        return 'blk_' . date('Ymd') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
}

if (!function_exists('site_builder_normalize_style')) {
    /** @param array<string, mixed> $styleIn */
    function site_builder_normalize_style(array $styleIn): array
    {
        $style = site_builder_style_defaults();
        foreach ($styleIn as $k => $v) {
            if (array_key_exists((string) $k, $style)) {
                $style[(string) $k] = is_string($v) ? trim($v) : (string) $v;
            }
        }
        return $style;
    }
}

if (!function_exists('site_builder_normalize_block')) {
    /** @param array<string, mixed> $block */
    function site_builder_normalize_block(array $block): ?array
    {
        $types = site_builder_block_types();
        $type = trim((string) ($block['type'] ?? ''));
        if (!isset($types[$type])) {
            return null;
        }
        $id = trim((string) ($block['id'] ?? ''));
        if ($id === '') {
            $id = site_builder_new_id();
        }
        $zone = trim((string) ($block['zone'] ?? 'main'));
        $parentId = trim((string) ($block['parentId'] ?? ''));
        $column = max(0, min(3, (int) ($block['column'] ?? 0)));
        $propsIn = is_array($block['props'] ?? null) ? $block['props'] : [];
        $props = $types[$type]['defaults'];
        foreach ($propsIn as $k => $v) {
            $props[(string) $k] = is_string($v) ? $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        $style = site_builder_normalize_style(is_array($block['style'] ?? null) ? $block['style'] : []);

        return [
            'id' => $id,
            'type' => $type,
            'zone' => $zone,
            'parentId' => $parentId,
            'column' => $column,
            'props' => $props,
            'style' => $style,
        ];
    }
}

if (!function_exists('site_builder_load_blocks')) {
    /** @return list<array<string, mixed>> */
    function site_builder_load_blocks(string $page): array
    {
        $row = site_content_row($page);
        if (!$row) {
            return [];
        }
        $sections = site_content_decode_json($row['sections_json'] ?? '');
        $stored = $sections['_builder'] ?? [];
        if (!is_array($stored)) {
            return [];
        }
        $blocks = [];
        foreach ($stored as $block) {
            if (!is_array($block)) {
                continue;
            }
            $normalized = site_builder_normalize_block($block);
            if ($normalized !== null) {
                $blocks[] = $normalized;
            }
        }
        return $blocks;
    }
}

if (!function_exists('site_builder_zone_has_blocks')) {
    function site_builder_zone_has_blocks(string $page, string $zone): bool
    {
        foreach (site_builder_load_blocks($page) as $block) {
            if ((string) ($block['zone'] ?? '') === $zone && trim((string) ($block['parentId'] ?? '')) === '') {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('site_builder_save_blocks')) {
    /** @param list<array<string, mixed>> $blocks */
    function site_builder_save_blocks(string $page, array $blocks): array
    {
        $registry = site_live_pages_registry();
        if (!isset($registry[$page]) || empty($registry[$page]['live'])) {
            return ['success' => false, 'message' => 'Pagină necunoscută sau fără constructor.'];
        }

        $pdo = site_content_db();
        if (!$pdo) {
            return ['success' => false, 'message' => 'Conexiune BD indisponibilă.'];
        }

        $stmt = $pdo->prepare('SELECT id, sections_json FROM site_pages WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $page]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return ['success' => false, 'message' => 'Pagina nu există în CMS.'];
        }

        $zones = site_builder_page_zones()[$page] ?? ['main'];
        $normalized = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $rowBlock = site_builder_normalize_block($block);
            if ($rowBlock === null) {
                continue;
            }
            if (!in_array($rowBlock['zone'], $zones, true)) {
                $rowBlock['zone'] = $zones[0];
            }
            $normalized[] = $rowBlock;
        }

        $sections = site_content_decode_json($row['sections_json'] ?? '');
        if ($sections === [] || array_is_list($sections)) {
            $sections = [];
        }
        $sections['_builder'] = $normalized;

        $upd = $pdo->prepare('UPDATE site_pages SET sections_json = :json WHERE id = :id');
        $upd->execute([
            ':json' => json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id' => (int) $row['id'],
        ]);

        return ['success' => true, 'message' => 'Layout salvat.', 'blocks' => $normalized];
    }
}

if (!function_exists('site_builder_sanitize_html')) {
    function site_builder_sanitize_html(string $html): string
    {
        return strip_tags($html, '<p><br><strong><b><em><i><u><a><ul><ol><li><span><h2><h3><h4><div>');
    }
}

if (!function_exists('site_builder_esc')) {
    function site_builder_esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('site_builder_parse_json_items')) {
    /** @return list<array<string, string>> */
    function site_builder_parse_json_items(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $item) {
            if (is_array($item)) {
                $row = [];
                foreach ($item as $k => $v) {
                    $row[(string) $k] = is_string($v) ? $v : (string) $v;
                }
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('site_builder_style_classes')) {
    /** @param array<string, string> $style */
    function site_builder_style_classes(array $style): string
    {
        $classes = ['bpa-el'];
        $map = [
            'padding' => 'bpa-pad',
            'marginTop' => 'bpa-mt',
            'marginBottom' => 'bpa-mb',
            'textAlign' => 'bpa-ta',
            'maxWidth' => 'bpa-mw',
            'borderRadius' => 'bpa-br',
        ];
        foreach ($map as $key => $prefix) {
            $val = $style[$key] ?? '';
            if ($val !== '' && $val !== 'none') {
                $classes[] = $prefix . '-' . preg_replace('/[^a-z0-9_-]/i', '', $val);
            }
        }
        return implode(' ', $classes);
    }
}

if (!function_exists('site_builder_style_inline')) {
    /** @param array<string, string> $style */
    function site_builder_style_inline(array $style): string
    {
        $css = [];
        if (($style['bgColor'] ?? '') !== '') {
            $css[] = 'background-color:' . $style['bgColor'];
        }
        if (($style['textColor'] ?? '') !== '') {
            $css[] = 'color:' . $style['textColor'];
        }
        if (($style['bgImage'] ?? '') !== '') {
            $url = site_builder_esc($style['bgImage']);
            $css[] = "background-image:url('{$url}')";
            $css[] = 'background-size:cover';
            $css[] = 'background-position:center';
        }
        return $css === [] ? '' : implode(';', $css);
    }
}

if (!function_exists('site_builder_video_embed')) {
    function site_builder_video_embed(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{6,})#', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        if (str_contains($url, 'youtube.com/embed/') || str_contains($url, 'player.vimeo.com')) {
            return $url;
        }
        return '';
    }
}

if (!function_exists('site_builder_filter_children')) {
    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    function site_builder_filter_children(array $blocks, string $parentId, ?int $column = null): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if ((string) ($block['parentId'] ?? '') !== $parentId) {
                continue;
            }
            if ($column !== null && (int) ($block['column'] ?? 0) !== $column) {
                continue;
            }
            $out[] = $block;
        }
        return $out;
    }
}

if (!function_exists('site_builder_render_block_inner')) {
    /**
     * @param array<string, mixed> $block
     * @param list<array<string, mixed>> $allBlocks
     */
    function site_builder_render_block_inner(array $block, array $allBlocks, bool $editMode): void
    {
        $type = (string) ($block['type'] ?? '');
        $id = (string) ($block['id'] ?? '');
        $zone = (string) ($block['zone'] ?? 'main');
        $props = is_array($block['props'] ?? null) ? $block['props'] : [];
        $style = is_array($block['style'] ?? null) ? $block['style'] : site_builder_style_defaults();
        $styleClass = site_builder_style_classes($style);
        $styleInline = site_builder_style_inline($style);
        $inlineAttr = $styleInline !== '' ? ' style="' . site_builder_esc($styleInline) . '"' : '';

        switch ($type) {
            case 'section':
                $overlay = ($style['bgOverlay'] ?? '') !== '' && ($style['bgImage'] ?? '') !== ''
                    ? '<div class="bpa-section__overlay" style="background:' . site_builder_esc($style['bgOverlay']) . '"></div>'
                    : '';
                echo '<section class="bpa-section ' . site_builder_esc($styleClass) . '"' . $inlineAttr . ' data-section-id="' . site_builder_esc($id) . '">';
                echo $overlay;
                echo '<div class="bpa-section__inner">';
                site_builder_render_children($allBlocks, $id, $editMode);
                if ($editMode) {
                    echo '<div class="bpa-drop-slot bpa-drop-slot--section" data-drop-parent="' . site_builder_esc($id) . '" data-drop-column="0" data-drop-zone="' . site_builder_esc($zone) . '">+ Click sau trage bloc aici</div>';
                }
                echo '</div></section>';
                break;

            case 'row':
                $cols = in_array(($props['cols'] ?? '2'), ['2', '3', '4'], true) ? (string) $props['cols'] : '2';
                $gap = in_array(($props['gap'] ?? 'md'), ['sm', 'md', 'lg'], true) ? (string) $props['gap'] : 'md';
                $layout = ($props['layout'] ?? 'grid') === 'flex' ? 'flex' : 'grid';
                $align = in_array(($props['align'] ?? 'stretch'), ['stretch', 'start', 'center', 'end'], true)
                    ? (string) $props['align'] : 'stretch';
                $justify = in_array(($props['justify'] ?? 'start'), ['start', 'center', 'end', 'between'], true)
                    ? (string) $props['justify'] : 'start';
                $rowClass = 'bpa-row bpa-row--' . site_builder_esc($cols)
                    . ' bpa-row--gap-' . site_builder_esc($gap)
                    . ' bpa-row--layout-' . site_builder_esc($layout)
                    . ' bpa-row--align-' . site_builder_esc($align)
                    . ' bpa-row--justify-' . site_builder_esc($justify)
                    . ' ' . site_builder_esc($styleClass);
                echo '<div class="' . $rowClass . '"' . $inlineAttr . '>';
                $colCount = (int) $cols;
                for ($c = 0; $c < $colCount; $c++) {
                    echo '<div class="bpa-row__col" data-column="' . $c . '">';
                    site_builder_render_children($allBlocks, $id, $editMode, $c);
                    if ($editMode) {
                        echo '<div class="bpa-drop-slot" data-drop-parent="' . site_builder_esc($id) . '" data-drop-column="' . $c . '" data-drop-zone="' . site_builder_esc($zone) . '">+ Adaugă în col ' . ($c + 1) . '</div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
                break;

            case 'heading':
                $level = in_array(($props['level'] ?? 'h2'), ['h1', 'h2', 'h3', 'h4'], true) ? (string) $props['level'] : 'h2';
                $text = (string) ($props['text'] ?? '');
                $editable = $editMode ? ' contenteditable="true" data-inline-prop="text"' : '';
                echo '<div class="bpa-block-heading ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                echo '<' . $level . ' class="bpa-block-heading__text"' . $editable . '>' . site_builder_esc($text) . '</' . $level . '>';
                echo '</div></div>';
                break;

            case 'text':
                $editable = $editMode ? ' contenteditable="true" data-inline-prop="html" data-inline-html="1"' : '';
                echo '<div class="bpa-block-text ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                echo '<div class="bpa-block-text__body"' . $editable . '>' . site_builder_sanitize_html((string) ($props['html'] ?? '')) . '</div>';
                echo '</div></div>';
                break;

            case 'message':
                $variant = (string) ($props['variant'] ?? 'info');
                if (!in_array($variant, ['info', 'success', 'warning', 'danger'], true)) {
                    $variant = 'info';
                }
                $titleEdit = $editMode ? ' contenteditable="true" data-inline-prop="title"' : '';
                $textEdit = $editMode ? ' contenteditable="true" data-inline-prop="text"' : '';
                echo '<div class="bpa-block-message ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                echo '<div class="bpa-block-alert bpa-block-alert--' . site_builder_esc($variant) . '">';
                echo '<strong class="bpa-block-alert__title"' . $titleEdit . '>' . site_builder_esc((string) ($props['title'] ?? '')) . '</strong>';
                echo '<p class="bpa-block-alert__text"' . $textEdit . '>' . nl2br(site_builder_esc((string) ($props['text'] ?? ''))) . '</p></div></div></div>';
                break;

            case 'image':
                $url = trim((string) ($props['url'] ?? ''));
                $height = (string) ($props['height'] ?? 'auto');
                $heightClass = in_array($height, ['auto', 'sm', 'md', 'lg', 'cover', 'full'], true) ? $height : 'auto';
                $width = (string) ($props['width'] ?? 'container');
                $widthClass = $width === 'full' ? ' bpa-block-image--w-full' : '';
                echo '<figure class="bpa-block-image bpa-block-image--h-' . site_builder_esc($heightClass) . $widthClass . ' ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                if ($url !== '') {
                    $img = '<img src="' . site_builder_esc($url) . '" alt="' . site_builder_esc((string) ($props['alt'] ?? '')) . '" loading="lazy">';
                    $link = trim((string) ($props['link'] ?? ''));
                    echo $link !== '' ? '<a href="' . site_builder_esc($link) . '">' . $img . '</a>' : $img;
                    $caption = trim((string) ($props['caption'] ?? ''));
                    if ($caption !== '') {
                        echo '<figcaption>' . site_builder_esc($caption) . '</figcaption>';
                    }
                } elseif ($editMode) {
                    echo '<div class="bpa-block-image--placeholder"><i class="fa-solid fa-image"></i> Încarcă imagine din panoul Design</div>';
                }
                echo '</div></figure>';
                break;

            case 'button':
                $styleBtn = (string) ($props['style'] ?? 'accent');
                $size = in_array(($props['size'] ?? 'md'), ['sm', 'md', 'lg'], true) ? (string) $props['size'] : 'md';
                $btnClass = match ($styleBtn) {
                    'ghost' => 'bpa-btn-ghost',
                    'glow' => 'bpa-btn-glow',
                    'dark' => 'bpa-btn-dark',
                    default => 'bpa-btn-accent',
                };
                $labelEdit = $editMode ? ' contenteditable="true" data-inline-prop="label"' : '';
                echo '<div class="bpa-block-button bpa-ta-' . site_builder_esc($style['textAlign'] ?? 'left') . ' ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                echo '<a class="bpa-btn ' . $btnClass . ' bpa-btn--' . site_builder_esc($size) . '" href="' . site_builder_esc((string) ($props['url'] ?? '#')) . '"><span' . $labelEdit . '>' . site_builder_esc((string) ($props['label'] ?? 'Click')) . '</span></a>';
                echo '</div></div>';
                break;

            case 'iconbox':
                $img = trim((string) ($props['image'] ?? ''));
                $icon = trim((string) ($props['icon'] ?? 'fa-solid fa-star'));
                $link = trim((string) ($props['link'] ?? ''));
                $titleEdit = $editMode ? ' contenteditable="true" data-inline-prop="title"' : '';
                $textEdit = $editMode ? ' contenteditable="true" data-inline-prop="text"' : '';
                echo '<div class="bpa-iconbox ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                if ($link !== '') {
                    echo '<a href="' . site_builder_esc($link) . '" class="bpa-iconbox__link">';
                }
                echo '<div class="bpa-iconbox__inner">';
                if ($img !== '') {
                    echo '<img class="bpa-iconbox__img" src="' . site_builder_esc($img) . '" alt="">';
                } else {
                    echo '<div class="bpa-iconbox__icon"><i class="' . site_builder_esc($icon) . '"></i></div>';
                }
                echo '<strong class="bpa-iconbox__title"' . $titleEdit . '>' . site_builder_esc((string) ($props['title'] ?? '')) . '</strong>';
                echo '<p class="bpa-iconbox__text"' . $textEdit . '>' . nl2br(site_builder_esc((string) ($props['text'] ?? ''))) . '</p>';
                echo '</div>';
                if ($link !== '') {
                    echo '</a>';
                }
                echo '</div></div>';
                break;

            case 'cards':
                $items = site_builder_parse_json_items((string) ($props['items'] ?? '[]'));
                $cols = in_array(($props['cols'] ?? '3'), ['2', '3', '4'], true) ? (string) $props['cols'] : '3';
                $title = (string) ($props['title'] ?? '');
                $titleEdit = $editMode ? ' contenteditable="true" data-inline-prop="title"' : '';
                echo '<div class="bpa-cards ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                if ($title !== '' || $editMode) {
                    echo '<h2 class="bpa-cards__title"' . $titleEdit . '>' . site_builder_esc($title) . '</h2>';
                }
                echo '<div class="bpa-cards__grid bpa-cards__grid--' . site_builder_esc($cols) . '">';
                foreach ($items as $item) {
                    echo '<div class="bpa-cards__item">';
                    $icon = trim((string) ($item['icon'] ?? ''));
                    if ($icon !== '') {
                        echo '<div class="bpa-cards__icon"><i class="' . site_builder_esc($icon) . '"></i></div>';
                    }
                    echo '<strong>' . site_builder_esc((string) ($item['title'] ?? '')) . '</strong>';
                    echo '<p>' . site_builder_sanitize_html((string) ($item['text'] ?? '')) . '</p></div>';
                }
                echo '</div></div></div>';
                break;

            case 'steps':
                $items = site_builder_parse_json_items((string) ($props['items'] ?? '[]'));
                $title = (string) ($props['title'] ?? '');
                $titleEdit = $editMode ? ' contenteditable="true" data-inline-prop="title"' : '';
                echo '<div class="bpa-steps ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                if ($title !== '' || $editMode) {
                    echo '<h2 class="bpa-steps__title"' . $titleEdit . '>' . site_builder_esc($title) . '</h2>';
                }
                echo '<div class="bpa-steps__list">';
                foreach ($items as $index => $item) {
                    echo '<div class="bpa-step"><div class="bpa-step__num">' . ((int) $index + 1) . '</div><div>';
                    echo '<strong>' . site_builder_esc((string) ($item['title'] ?? '')) . '</strong>';
                    echo '<p>' . site_builder_sanitize_html((string) ($item['text'] ?? '')) . '</p></div></div>';
                }
                echo '</div></div></div>';
                break;

            case 'faq':
                $items = site_builder_parse_json_items((string) ($props['items'] ?? '[]'));
                $title = (string) ($props['title'] ?? '');
                $titleEdit = $editMode ? ' contenteditable="true" data-inline-prop="title"' : '';
                echo '<div class="bpa-faq ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                if ($title !== '' || $editMode) {
                    echo '<h2 class="bpa-faq__title"' . $titleEdit . '>' . site_builder_esc($title) . '</h2>';
                }
                echo '<div class="bpa-faq__list">';
                foreach ($items as $i => $item) {
                    $open = $i === 0 ? ' open' : '';
                    echo '<details class="bpa-faq__item"' . $open . '>';
                    echo '<summary>' . site_builder_esc((string) ($item['q'] ?? '')) . '</summary>';
                    echo '<div class="bpa-faq__a">' . site_builder_sanitize_html((string) ($item['a'] ?? '')) . '</div></details>';
                }
                echo '</div></div></div>';
                break;

            case 'video':
                $embed = site_builder_video_embed((string) ($props['url'] ?? ''));
                echo '<div class="bpa-video ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                if ($embed !== '') {
                    echo '<div class="bpa-video__wrap"><iframe src="' . site_builder_esc($embed) . '" title="Video" loading="lazy" allowfullscreen></iframe></div>';
                } elseif ($editMode) {
                    echo '<div class="bpa-video__placeholder">Adaugă URL YouTube/Vimeo</div>';
                }
                $caption = trim((string) ($props['caption'] ?? ''));
                if ($caption !== '') {
                    echo '<p class="bpa-video__caption">' . site_builder_esc($caption) . '</p>';
                }
                echo '</div></div>';
                break;

            case 'html':
                echo '<div class="bpa-block-html ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap">';
                echo site_builder_sanitize_html((string) ($props['html'] ?? ''));
                echo '</div></div>';
                break;

            case 'columns':
                $leftEdit = $editMode ? ' contenteditable="true" data-inline-prop="left" data-inline-html="1"' : '';
                $rightEdit = $editMode ? ' contenteditable="true" data-inline-prop="right" data-inline-html="1"' : '';
                echo '<div class="bpa-block-columns ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '><div class="bpa-mw-wrap"><div class="bpa-block-columns__grid">';
                echo '<div class="bpa-block-columns__col"' . $leftEdit . '>' . site_builder_sanitize_html((string) ($props['left'] ?? '')) . '</div>';
                echo '<div class="bpa-block-columns__col"' . $rightEdit . '>' . site_builder_sanitize_html((string) ($props['right'] ?? '')) . '</div>';
                echo '</div></div></div>';
                break;

            case 'spacer':
                $size = in_array(($props['size'] ?? 'md'), ['sm', 'md', 'lg', 'xl'], true) ? (string) $props['size'] : 'md';
                echo '<div class="bpa-block-spacer bpa-block-spacer--' . site_builder_esc($size) . '" aria-hidden="true"></div>';
                break;

            case 'divider':
                $dstyle = in_array(($props['style'] ?? 'solid'), ['solid', 'dashed', 'dots'], true) ? (string) $props['style'] : 'solid';
                echo '<hr class="bpa-block-divider bpa-block-divider--' . site_builder_esc($dstyle) . ' ' . site_builder_esc($styleClass) . '"' . $inlineAttr . '>';
                break;
        }
    }
}

if (!function_exists('site_builder_render_children')) {
    /**
     * @param list<array<string, mixed>> $allBlocks
     */
    function site_builder_render_children(array $allBlocks, string $parentId, bool $editMode, ?int $column = null): void
    {
        $children = site_builder_filter_children($allBlocks, $parentId, $column);
        foreach ($children as $child) {
            site_builder_render_block($child, $allBlocks, $editMode);
        }
    }
}

if (!function_exists('site_builder_render_block')) {
    /**
     * @param array<string, mixed> $block
     * @param list<array<string, mixed>> $allBlocks
     */
    function site_builder_render_block(array $block, array $allBlocks, bool $editMode): void
    {
        $type = (string) ($block['type'] ?? '');
        $id = (string) ($block['id'] ?? '');
        $props = is_array($block['props'] ?? null) ? $block['props'] : [];
        $style = is_array($block['style'] ?? null) ? $block['style'] : site_builder_style_defaults();
        $propsJson = htmlspecialchars(json_encode($props, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $styleJson = htmlspecialchars(json_encode($style, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $types = site_builder_block_types();
        $label = (string) ($types[$type]['label'] ?? $type);
        $container = !empty($types[$type]['container']);

        if ($editMode) {
            echo '<div class="bpa-block' . ($container ? ' bpa-block--container' : '') . '" draggable="true"';
            echo ' data-block-id="' . site_builder_esc($id) . '"';
            echo ' data-block-type="' . site_builder_esc($type) . '"';
            echo ' data-block-label="' . site_builder_esc($label) . '"';
            echo ' data-block-parent="' . site_builder_esc((string) ($block['parentId'] ?? '')) . '"';
            echo ' data-block-column="' . (int) ($block['column'] ?? 0) . '"';
            echo ' data-block-zone="' . site_builder_esc((string) ($block['zone'] ?? 'main')) . '"';
            echo ' data-block-props=\'' . $propsJson . '\'';
            echo ' data-block-style=\'' . $styleJson . '\'>';
            echo '<div class="bpa-block-drag" title="Trage pentru a muta">⋮⋮</div>';
        }

        site_builder_render_block_inner($block, $allBlocks, $editMode);

        if ($editMode) {
            echo '<div class="bpa-block-controls" role="toolbar" aria-label="Acțiuni bloc">';
            echo '<button type="button" class="bpa-block-ctrl bpa-block-ctrl--add" data-block-act="insert-above" title="Adaugă deasupra">+↑</button>';
            echo '<button type="button" class="bpa-block-ctrl bpa-block-ctrl--add" data-block-act="insert-below" title="Adaugă dedesubt">+↓</button>';
            echo '<button type="button" class="bpa-block-ctrl" data-block-act="up" title="Mută sus">▲</button>';
            echo '<button type="button" class="bpa-block-ctrl" data-block-act="down" title="Mută jos">▼</button>';
            echo '<button type="button" class="bpa-block-ctrl bpa-block-ctrl--danger" data-block-act="delete" title="Șterge">✕</button>';
            echo '</div></div>';
        }
    }
}

if (!function_exists('site_builder_render_zone')) {
    function site_builder_render_zone(string $page, string $zone): void
    {
        $editMode = site_live_edit_mode();
        $allBlocks = site_builder_load_blocks($page);
        $blocks = array_values(array_filter(
            $allBlocks,
            static fn(array $b): bool => (string) ($b['zone'] ?? '') === $zone && trim((string) ($b['parentId'] ?? '')) === ''
        ));
        $zoneLabel = site_builder_zone_labels()[$zone] ?? $zone;
        $zoneClass = $zone === 'main' ? ' bpa-builder-zone--main' : '';

        $isEmpty = $blocks === [];
        $emptyCls = ($editMode && $isEmpty) ? ' bpa-builder-zone--empty' : '';

        echo '<div class="bpa-builder-zone' . $zoneClass . $emptyCls . '" data-builder-zone="' . site_builder_esc($zone) . '" data-builder-page="' . site_builder_esc($page) . '" data-zone-label="' . site_builder_esc($zoneLabel) . '">';
        foreach ($blocks as $block) {
            site_builder_render_block($block, $allBlocks, $editMode);
        }
        if ($editMode && !$isEmpty) {
            echo '<div class="bpa-drop-slot bpa-drop-slot--root" data-drop-parent="" data-drop-column="0" data-drop-zone="' . site_builder_esc($zone) . '">+ Adaugă la final</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('site_builder_export_js_config')) {
    function site_builder_export_js_config(string $page): array
    {
        return [
            'page' => $page,
            'blocks' => site_builder_load_blocks($page),
            'types' => site_builder_block_types(),
            'styleFields' => site_builder_style_fields(),
            'styleDefaults' => site_builder_style_defaults(),
            'zones' => site_builder_page_zones()[$page] ?? ['main'],
            'zoneLabels' => site_builder_zone_labels(),
            'mediaApi' => '/api/admin-cms-media.php',
            'cmsStyles' => site_live_cms_styles_load($page),
            'cmsImages' => site_live_cms_images_export($page),
        ];
    }
}
