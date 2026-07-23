<?php
declare(strict_types=1);

require_once __DIR__ . '/site-defaults.php';

if (!function_exists('site_content_deep_merge')) {
    require_once __DIR__ . '/site-content.php';
}

if (!function_exists('site_admin_json_pretty')) {
    function site_admin_json_pretty(mixed $data): string
    {
        if (!is_array($data) || $data === []) {
            return '';
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}

if (!function_exists('site_admin_extract_php_array')) {
    function site_admin_extract_php_array(string $filePath, string $variableName): array
    {
        if (!is_readable($filePath)) {
            return [];
        }

        $source = file_get_contents($filePath);
        if ($source === false) {
            return [];
        }

        $needle = '$' . $variableName . ' = [';
        $start = strpos($source, $needle);
        if ($start === false) {
            return [];
        }

        $start += strlen('$' . $variableName . ' = ');
        $depth = 0;
        $length = strlen($source);

        for ($i = $start; $i < $length; $i++) {
            $char = $source[$i];
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    $arrayCode = substr($source, $start, $i - $start + 1);
                    try {
                        /** @var array $result */
                        $result = eval('return ' . $arrayCode . ';');
                        return is_array($result) ? $result : [];
                    } catch (Throwable $e) {
                        error_log('[site-admin-form] extract fail: ' . $e->getMessage());
                        return [];
                    }
                }
            }
        }

        return [];
    }
}

if (!function_exists('site_admin_static_page_file')) {
    function site_admin_static_page_file(string $slug): ?string
    {
        $root = dirname(__DIR__);
        $map = [
            'cum-comand' => 'cum-comand.php',
            'livrare-plata' => 'livrare-plata.php',
            'retur-garantie' => 'retur-garantie.php',
            'intrebari-frecvente' => 'intrebari-frecvente.php',
            'termeni-conditii' => 'termeni-conditii.php',
            'politica-confidentialitate' => 'politica-confidentialitate.php',
            'politica-cookies' => 'politica-cookies.php',
            'cariere' => 'cariere.php',
            'blog' => 'blog.php',
            'about' => 'about.php',
            'contact' => 'contact.php',
        ];

        if (!isset($map[$slug])) {
            return null;
        }

        $path = $root . DIRECTORY_SEPARATOR . $map[$slug];
        return is_readable($path) ? $path : null;
    }
}

if (!function_exists('site_admin_static_page_defaults')) {
    function site_admin_static_page_defaults(string $slug): array
    {
        $file = site_admin_static_page_file($slug);
        if ($file === null) {
            return [];
        }

        $varName = match ($slug) {
            'about' => 'aboutDefaults',
            'contact' => 'contactDefaults',
            default => 'defaults',
        };

        $data = site_admin_extract_php_array($file, $varName);
        if ($data === []) {
            return [];
        }

        return [
            'title' => (string) ($data['title'] ?? ''),
            'meta_description' => (string) ($data['description'] ?? ''),
            'hero_label' => (string) ($data['hero_label'] ?? ''),
            'hero_title' => (string) ($data['hero_title'] ?? ''),
            'hero_subtitle' => (string) ($data['hero_subtitle'] ?? ''),
            'body_html' => (string) ($data['body_html'] ?? ''),
            'sections_json' => site_admin_json_pretty($data['sections'] ?? []),
            'faq_json' => site_admin_json_pretty($data['faq'] ?? []),
            'cta_json' => site_admin_json_pretty($data['cta'] ?? []),
        ];
    }
}

if (!function_exists('site_admin_block_page_defaults')) {
    function site_admin_block_page_defaults(string $slug): array
    {
        $meta = site_defaults_page_meta($slug);
        $blocks = site_defaults_blocks($slug);

        $heroLabel = '';
        $heroTitle = '';
        $heroSubtitle = '';

        if ($slug === 'home') {
            $heroLabel = (string) ($blocks['hero']['eyebrow'] ?? '');
            $heroTitle = preg_replace('/<br\s*\/?>/i', "\n", (string) ($blocks['hero']['title_html'] ?? '')) ?? '';
            $heroSubtitle = (string) ($blocks['hero']['subtitle'] ?? '');
        } elseif ($slug === 'catalog') {
            $heroTitle = (string) ($blocks['hero']['title'] ?? ($meta['hero_title'] ?? ''));
            $heroSubtitle = (string) ($blocks['hero']['subtitle'] ?? ($meta['hero_subtitle'] ?? ''));
        } elseif ($slug === 'about') {
            $about = site_admin_static_page_defaults('about');
            $heroLabel = $about['hero_label'] ?? 'Despre noi';
            $heroTitle = $about['hero_title'] ?? "POVESTEA\nNOASTRĂ";
            $heroSubtitle = $about['hero_subtitle'] ?? '';
        } elseif ($slug === 'contact') {
            $contact = site_admin_static_page_defaults('contact');
            $heroLabel = $contact['hero_label'] ?? '';
            $heroTitle = $contact['hero_title'] ?? '';
            $heroSubtitle = $contact['hero_subtitle'] ?? '';
        }

        $faqJson = '';
        $ctaJson = '';
        if ($slug === 'contact') {
            $contact = site_admin_static_page_defaults('contact');
            $faqJson = $contact['faq_json'] ?? '';
        }
        if ($slug === 'about') {
            $about = site_admin_static_page_defaults('about');
            $ctaJson = $about['cta_json'] ?? '';
        }

        return [
            'title' => (string) ($meta['title'] ?? ''),
            'meta_description' => (string) ($meta['description'] ?? ''),
            'hero_label' => $heroLabel,
            'hero_title' => $heroTitle,
            'hero_subtitle' => $heroSubtitle,
            'body_html' => '',
            'sections_json' => site_admin_json_pretty($blocks),
            'faq_json' => $faqJson,
            'cta_json' => $ctaJson,
        ];
    }
}

if (!function_exists('site_admin_form_defaults')) {
    function site_admin_form_defaults(string $slug): array
    {
        if (in_array($slug, ['home', 'global', 'catalog', 'about', 'contact'], true)) {
            return site_admin_block_page_defaults($slug);
        }

        return site_admin_static_page_defaults($slug);
    }
}

if (!function_exists('site_admin_merge_page_for_form')) {
    function site_admin_merge_page_for_form(array $dbRow): array
    {
        $slug = (string) ($dbRow['slug'] ?? '');
        $defaults = site_admin_form_defaults($slug);
        $merged = $dbRow;

        foreach (['title', 'meta_description', 'hero_label', 'hero_title', 'hero_subtitle', 'body_html'] as $field) {
            if (trim((string) ($merged[$field] ?? '')) === '' && trim((string) ($defaults[$field] ?? '')) !== '') {
                $merged[$field] = $defaults[$field];
            }
        }

        foreach (['sections_json', 'faq_json', 'cta_json'] as $field) {
            if (trim((string) ($merged[$field] ?? '')) === '' && trim((string) ($defaults[$field] ?? '')) !== '') {
                $merged[$field] = $defaults[$field];
            }
        }

        return $merged;
    }
}

if (!function_exists('site_admin_parsed_blocks')) {
    function site_admin_parsed_blocks(array $formPage, string $slug): array
    {
        $defaults = site_defaults_blocks($slug);
        $raw = trim((string) ($formPage['sections_json'] ?? ''));
        if ($raw === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || array_is_list($decoded)) {
            return $defaults;
        }

        return site_content_deep_merge($defaults, $decoded);
    }
}

if (!function_exists('site_admin_page_profile')) {
    /**
     * @return array<string,mixed>
     */
    function site_admin_page_profile(string $slug): array
    {
        $staticBanner = [
            'show_banner' => true,
            'banner_heading' => 'Banner pagină (bara verde de sus)',
            'banner_hint' => 'Textul afișat în bannerul verde de la începutul paginii.',
            'label_hero_label' => 'Etichetă mică (deasupra titlului)',
            'label_hero_title' => 'Titlu mare în banner',
            'label_hero_subtitle' => 'Text scurt sub titlu',
        ];

        return match ($slug) {
            'global' => [
                'page_name' => 'Header & Footer',
                'intro' => 'Texte comune pe TOT site-ul: banda de sus, căutarea din header, telefonul, meniul și footer-ul. Nu există banner verde pe această pagină.',
                'show_banner' => false,
                'show_body' => false,
                'show_sections_json' => true,
                'sections_json_label' => 'Avansat — JSON complet (header/footer)',
                'sections_json_collapsed' => true,
                'show_faq' => false,
                'show_cta' => false,
                'structured' => 'global',
            ],
            'home' => [
                'page_name' => 'Pagina Acasă',
                'intro' => 'Prima pagină pe care o văd clienții (index.php). Mai jos editezi secțiunea principală; restul conținutului (categorii, produse, mărci, „De ce noi”) este în blocurile JSON.',
                'show_banner' => true,
                'banner_heading' => 'Secțiunea principală (stânga sus)',
                'banner_hint' => '„Găsește rapid”, titlul mare, textul scurt, placeholder-ul căutării VIN.',
                'label_hero_label' => 'Text mic deasupra titlului (ex: Găsește rapid)',
                'label_hero_title' => 'Titlu mare (linie nouă = rând nou; HTML: <span>text</span>)',
                'label_hero_subtitle' => 'Paragraf scurt sub titlu',
                'show_body' => false,
                'show_sections_json' => true,
                'sections_json_label' => 'Restul homepage-ului (categorii, produse, mărci, beneficii…)',
                'sections_json_collapsed' => false,
                'show_faq' => false,
                'show_cta' => false,
                'structured' => null,
            ],
            'catalog' => [
                'page_name' => 'Catalog',
                'intro' => 'Pagina catalog.php — bannerul de sus și câmpul de căutare.',
                'show_banner' => true,
                'banner_heading' => 'Banner catalog (sus)',
                'banner_hint' => 'Titlul „CATALOG PIESE AUTO” și subtitlul. Căutarea se editează în JSON (hero.search_placeholder).',
                'label_hero_label' => 'Etichetă (lăsați gol dacă nu se folosește)',
                'label_hero_title' => 'Titlu banner (ex: CATALOG PIESE AUTO)',
                'label_hero_subtitle' => 'Subtitlu banner',
                'show_body' => false,
                'show_sections_json' => true,
                'sections_json_label' => 'Căutare catalog (placeholder, buton)',
                'sections_json_collapsed' => false,
                'show_faq' => false,
                'show_cta' => false,
                'structured' => null,
            ],
            'about' => array_merge($staticBanner, [
                'page_name' => 'Despre noi',
                'intro' => 'Pagina about.php — banner verde + statistici, poveste, timeline (în JSON).',
                'show_body' => false,
                'show_sections_json' => true,
                'sections_json_label' => 'Statistici, poveste, timeline, carduri (JSON)',
                'sections_json_collapsed' => false,
                'show_faq' => false,
                'show_cta' => true,
                'cta_label' => 'Buton apel final (jos pe pagină)',
                'structured' => null,
            ]),
            'contact' => array_merge($staticBanner, [
                'page_name' => 'Contact',
                'intro' => 'Pagina contact.php — banner, formular, bandă info, FAQ lateral.',
                'banner_heading' => 'Banner contact (ex: Hai să / VORBIM)',
                'show_body' => false,
                'show_sections_json' => true,
                'sections_json_label' => 'Formular, bandă info, carduri contact (JSON)',
                'sections_json_collapsed' => false,
                'show_faq' => true,
                'faq_label' => 'Întrebări frecvente (coloana dreaptă)',
                'show_cta' => true,
                'cta_label' => 'Bloc „Contactează-ne rapid” (jos)',
                'structured' => null,
            ]),
            'intrebari-frecvente' => array_merge($staticBanner, [
                'page_name' => 'Întrebări frecvente',
                'intro' => 'Pagina cu lista de întrebări — conținutul principal este în FAQ.',
                'show_body' => false,
                'show_sections_json' => true,
                'sections_json_label' => 'Introducere pagină (secțiuni JSON)',
                'show_faq' => true,
                'faq_label' => 'Lista întrebări & răspunsuri',
                'show_cta' => true,
                'structured' => null,
            ]),
            default => array_merge($staticBanner, [
                'page_name' => ucfirst(str_replace('-', ' ', $slug)),
                'intro' => 'Pagină informativă cu banner verde și secțiuni de text.',
                'show_body' => true,
                'body_label' => 'Conținut liber (HTML) — opțional, dacă nu folosiți secțiuni JSON',
                'show_sections_json' => true,
                'sections_json_label' => 'Secțiuni pagină (paragrafe, pași, carduri)',
                'show_faq' => in_array($slug, ['cum-comand', 'livrare-plata', 'retur-garantie', 'blog'], true),
                'faq_label' => 'Întrebări frecvente (final pagină)',
                'show_cta' => true,
                'cta_label' => 'Bloc apel acțiune (butoane jos)',
                'structured' => null,
            ]),
        };
    }
}

if (!function_exists('site_page_canonical_meta')) {
    /**
     * Etichete corecte UTF-8 pentru paginile CMS (repară „Acas??” din BD).
     *
     * @return array<string, array{label:string,title:string}>
     */
    function site_page_canonical_meta(): array
    {
        return [
            'home' => ['label' => 'Acasă (index)', 'title' => 'Besoiu Piese Auto'],
            'global' => ['label' => 'Header & Footer', 'title' => 'Setări globale site'],
            'catalog' => ['label' => 'Catalog', 'title' => 'Catalog piese auto'],
            'cum-comand' => ['label' => 'Cum comand', 'title' => 'Cum comand'],
            'livrare-plata' => ['label' => 'Livrare și plată', 'title' => 'Livrare și plată'],
            'retur-garantie' => ['label' => 'Retur și garanție', 'title' => 'Retur și garanție'],
            'intrebari-frecvente' => ['label' => 'Întrebări frecvente', 'title' => 'Întrebări frecvente'],
            'termeni-conditii' => ['label' => 'Termeni și condiții', 'title' => 'Termeni și condiții'],
            'politica-confidentialitate' => ['label' => 'Politica confidențialitate', 'title' => 'Politica confidențialitate'],
            'politica-cookies' => ['label' => 'Politica cookies', 'title' => 'Politica cookies'],
            'cariere' => ['label' => 'Cariere', 'title' => 'Cariere'],
            'blog' => ['label' => 'Blog', 'title' => 'Blog'],
            'about' => ['label' => 'Despre noi', 'title' => 'Despre noi'],
            'contact' => ['label' => 'Contact', 'title' => 'Contact'],
        ];
    }
}

if (!function_exists('site_page_label_looks_broken')) {
    function site_page_label_looks_broken(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        return (bool) preg_match('/\?\?|Ã|Â|Ä|Å|È|Ì|Ò|Ù|ã¢|Äƒ|È›|È™/u', $value);
    }
}

if (!function_exists('site_page_display_label')) {
    function site_page_display_label(string $slug, string $dbLabel): string
    {
        $canonical = site_page_canonical_meta();
        if (isset($canonical[$slug])) {
            return $canonical[$slug]['label'];
        }

        if (site_page_label_looks_broken($dbLabel)) {
            return ucfirst(str_replace('-', ' ', $slug));
        }

        return $dbLabel !== '' ? $dbLabel : $slug;
    }
}

if (!function_exists('site_pages_repair_labels')) {
    /** @return int Număr rânduri actualizate */
    function site_pages_repair_labels(): int
    {
        if (!function_exists('site_content_db')) {
            require_once __DIR__ . '/site-content.php';
        }

        $pdo = site_content_db();
        if (!$pdo instanceof PDO) {
            return 0;
        }

        $canonical = site_page_canonical_meta();
        $fixed = 0;

        try {
            $stmt = $pdo->query('SELECT id, slug, label, title FROM site_pages');
            if ($stmt === false) {
                return 0;
            }

            $upd = $pdo->prepare('UPDATE site_pages SET label = :label, title = :title WHERE id = :id');

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }
                $slug = trim((string) ($row['slug'] ?? ''));
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0 || $slug === '' || !isset($canonical[$slug])) {
                    continue;
                }

                $meta = $canonical[$slug];
                $dbLabel = (string) ($row['label'] ?? '');
                $dbTitle = (string) ($row['title'] ?? '');

                if ($dbLabel === $meta['label'] && $dbTitle === $meta['title'] && !site_page_label_looks_broken($dbLabel)) {
                    continue;
                }

                $upd->execute([
                    ':label' => $meta['label'],
                    ':title' => $meta['title'],
                    ':id' => $id,
                ]);
                $fixed++;
            }
        } catch (Throwable $e) {
            error_log('[site_pages_repair_labels] ' . $e->getMessage());
        }

        return $fixed;
    }
}
