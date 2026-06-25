<?php
declare(strict_types=1);

if (!function_exists('site_defaults_blocks')) {
    function site_defaults_blocks(string $slug): array
    {
        return match ($slug) {
            'home' => [
                'filterbar' => [
                    'categories_btn' => 'Categorii piese',
                    'search_btn' => 'CAUTĂ PIESĂ',
                ],
                'hero' => [
                    'eyebrow' => 'Găsește rapid',
                    'title_html' => 'Piesa potrivită<br>pentru <span>mașina</span> ta',
                    'subtitle' => 'Caută după VIN, cod OEM sau selectează marca și modelul. Verificăm compatibilitatea înainte de comandă.',
                    'search_placeholder' => 'Introdu VIN, OEM sau cod piesă...',
                    'search_button' => 'VERIFICĂ COMPATIBILITATEA',
                    'benefits' => [
                        ['text' => 'Compatibilitate verificată', 'icon' => 'img/icons/21_scut_compatibilitate.svg'],
                        ['text' => 'Livrare rapidă 24–48h', 'icon' => 'img/icons/09_livrare_rapida.svg'],
                        ['text' => 'Suport telefonic dedicat', 'icon' => 'img/icons/12_telefon.svg'],
                        ['text' => 'Peste 10k produse', 'icon' => 'img/icons/22_cutie_produse.svg'],
                    ],
                    'popup_product' => [
                        'title' => 'Disc frână ventilat Brembo Max',
                        'price' => '450 RON',
                        'stock' => 'În stoc',
                        'image' => 'assets/images/products/1.jpg',
                        'url' => '/catalog',
                    ],
                    'promo_slides' => [],
                    'promo_interval_ms' => 5000,
                ],
                'special_products' => [
                    'title_html' => 'Produse <span>speciale</span>',
                    'subtitle' => 'Uleiuri, lichide și consumabile esențiale pentru mașina ta.',
                    'button' => 'VEZI TOATE PRODUSELE SPECIALE',
                    'empty' => 'Produsele speciale vor apărea aici în curând.',
                ],
                'categories' => [
                    'title_html' => 'Categorii <span>populare</span>',
                    'button' => 'VEZI TOATE CATEGORIILE',
                ],
                'products' => [
                    'title_html' => 'Produse <span>recomandate</span>',
                    'view_all' => 'Vezi toate produsele',
                    'loading' => 'Se încarcă piesele...',
                    'empty' => 'Nu au fost găsite produse după filtrele selectate.',
                    'trust' => [
                        ['title' => 'Livrare rapidă', 'subtitle' => '24–48h în toată țara', 'icon' => 'img/icons/09_livrare_rapida.svg'],
                        ['title' => 'Retur simplu', 'subtitle' => '14 zile fără griji', 'icon' => 'img/icons/10_retur_simplu.svg'],
                        ['title' => 'Plată ramburs', 'subtitle' => 'la livrare', 'icon' => 'img/icons/26_plata_card.svg'],
                        ['title' => 'Verificare compatibilitate', 'subtitle' => 'înainte de comandă', 'icon' => 'img/icons/11_verificare_compatibilitate.svg'],
                        ['title' => 'Suport telefonic', 'subtitle' => '0726 498 573', 'subtitle_href' => 'tel:+40726498573', 'icon' => 'img/icons/27_casti_suport.svg'],
                    ],
                ],
                'brands' => [
                    'title_html' => 'Mărci <span>disponibile</span>',
                    'button' => 'VEZI TOATE MĂRCILE',
                    'items' => [
                        ['name' => 'BOSCH', 'color' => '#e11d48'],
                        ['name' => 'MANN', 'color' => '#65a30d'],
                        ['name' => 'febi', 'color' => '#ef4444'],
                        ['name' => 'SACHS', 'color' => '#2563eb'],
                        ['name' => 'Valeo', 'color' => '#65a30d'],
                        ['name' => 'TRW', 'color' => '#dc2626'],
                        ['name' => 'HELLA', 'color' => '#1d4ed8'],
                        ['name' => 'MAHLE', 'color' => '#1e3a8a'],
                    ],
                ],
                'why' => [
                    'title_html' => 'DE CE SĂ ALEGI<br>BESOIU PIESE AUTO?',
                    'list' => [
                        'Piese originale și aftermarket de calitate',
                        'Verificare compatibilitate după VIN sau model',
                        'Livrare rapidă 24–48h în toată țara',
                        'Prețuri corecte și oferte avantajoase',
                        'Suport telefonic dedicat',
                    ],
                    'help_title' => 'AI NEVOIE DE AJUTOR?',
                    'help_text' => 'Consultă asistentul nostru AI pe WhatsApp — răspuns automat bazat pe catalog, stoc și istoricul comenzilor tale.',
                    'phone' => '0726 498 573',
                    'phone_href' => 'tel:+40726498573',
                    'whatsapp_btn' => 'Consultă pe WhatsApp',
                    'whatsapp_prefill' => 'Bună! Am nevoie de consultanță pentru piese auto.',
                    'whatsapp_hint' => 'Sau deschide chat-ul AI din colțul dreapta-jos pentru răspuns instant.',
                    'clients_plus' => '+97',
                    'clients_text_html' => 'Peste 10.000+<br>clienți mulțumiți',
                    'car_image' => 'img/car1.png',
                ],
            ],
            'global' => [
                'topbar' => [
                    ['text' => 'Livrare rapidă 24–48h', 'icon' => 'img/icons/09_livrare_rapida.svg'],
                    ['text' => 'Retur simplu 14 zile', 'icon' => 'img/icons/10_retur_simplu.svg'],
                    ['text' => 'Verificare compatibilitate', 'icon' => 'img/icons/11_verificare_compatibilitate.svg'],
                ],
                'header' => [
                    'logo' => 'img/logo.png',
                    'search_placeholder' => 'Caută după cod piesă, OEM, VIN...',
                    'search_button' => 'CAUTĂ',
                    'phone' => '0726 498 573',
                    'phone_href' => 'tel:+40726498573',
                    'phone_label' => 'Sună acum',
                    'account_title' => 'Contul meu',
                    'account_guest' => 'Autentificare',
                    'cart_label' => 'Coș',
                ],
                'nav' => [
                    ['href' => '/', 'label' => 'Acasă', 'page' => '/'],
                    ['href' => '/catalog', 'label' => 'Magazin', 'page' => '/catalog'],
                    ['href' => '/despre', 'label' => 'Despre noi', 'page' => 'about.php'],
                    ['href' => '/contact', 'label' => 'Contact', 'page' => 'contact.php'],
                    ['href' => '/cart', 'label' => 'Coș cumpărături', 'page' => 'cart.php'],
                    ['href' => '/cont', 'label' => 'Contul meu', 'page' => 'cont.php'],
                ],
                'nav_support' => [
                    ['href' => '/cum-comand', 'label' => 'Cum comand'],
                    ['href' => '/livrare-plata', 'label' => 'Livrare și plată'],
                    ['href' => '/intrebari-frecvente', 'label' => 'Întrebări frecvente'],
                ],
                'footer' => [
                    'description' => 'Magazin online de piese auto pentru toate mărcile. Piese originale și aftermarket la prețuri corecte.',
                    'copyright' => 'Besoiu Piese Auto. Toate drepturile rezervate.',
                    'tagline' => 'Creat cu pasiune pentru mașina ta.',
                    'phone' => '0726 498 573',
                    'phone_href' => 'tel:+40726498573',
                    'email' => 'contact@besoiupieseauto.ro',
                    'address' => 'Timișoara, Str. Stan Vidrighin nr. 14, jud. Timiș',
                    'social' => [
                        ['label' => 'Facebook', 'href' => 'https://www.facebook.com/besoiupieseauto', 'icon' => 'img/icons/32_facebook.svg'],
                        ['label' => 'Instagram', 'href' => 'https://www.instagram.com/besoiupieseauto', 'icon' => 'img/icons/33_instagram.svg'],
                        ['label' => 'YouTube', 'href' => 'https://www.youtube.com/@besoiupieseauto', 'icon' => 'img/icons/34_youtube.svg'],
                        ['label' => 'TikTok', 'href' => 'https://www.tiktok.com/@besoiupieseauto', 'icon' => 'img/icons/35_tiktok.svg'],
                    ],
                ],
            ],
            'catalog' => [
                'hero' => [
                    'title' => 'CATALOG PIESE AUTO',
                    'subtitle' => 'Peste 10.000 de piese auto pentru toate mărcile. Caută după VIN, cod OEM sau filtrează după categorie.',
                    'search_placeholder' => 'Caută piesa de care ai nevoie...',
                    'search_button' => 'CAUTĂ',
                ],
            ],
            'about' => [
                'stats' => [
                    ['icon' => 'fa-box-open', 'value' => '15.000+', 'label' => 'Piese în catalog'],
                    ['icon' => 'fa-cart-shopping', 'value' => '8.500+', 'label' => 'Comenzi livrate'],
                    ['icon' => 'fa-bolt', 'value' => '24h', 'label' => 'Timp de răspuns'],
                    ['icon' => 'fa-star', 'value' => '4.9', 'label' => 'Rating clienți', 'suffix_icon' => 'fa-star'],
                ],
                'story' => [
                    'label' => 'Povestea noastră',
                    'paragraph1' => 'BESOIU PIESE AUTO a pornit în 2020 dintr-o pasiune sinceră pentru mașini și din dorința de a oferi șoferilor din România piese auto de calitate, la prețuri corecte și cu livrare rapidă.',
                    'paragraph2' => 'Am început ca o echipă mică, cu multă muncă și determinare. Astăzi, datorită încrederii voastre, deservim mii de clienți din toată țara și colaborăm cu cei mai importanți furnizori de piese auto din Europa.',
                    'pullquote' => 'Fiecare piesă vândută trece prin verificare de compatibilitate — siguranța ta e prioritatea noastră.',
                ],
                'timeline_label' => 'Drumul nostru',
                'timeline' => [
                    ['year' => '2020', 'title' => 'Înființare', 'text' => 'Am pus bazele BESOIU PIESE AUTO cu un obiectiv clar: calitate și încredere pentru fiecare client.'],
                    ['year' => '2022', 'title' => '1.000 comenzi', 'text' => 'Primul nostru mare milestone — am depășit 1.000 de comenzi livrate cu succes în toată România.'],
                    ['year' => '2024', 'title' => '10.000 piese', 'text' => 'Am extins catalogul la peste 10.000 de piese auto pentru mărci și modele diverse.'],
                    ['year' => '2026', 'title' => 'Expansiune națională', 'text' => 'Ne extindem în toată România, cu depozite și parteneri în toate regiunile importante.'],
                ],
                'why' => [
                    'title_html' => 'De ce să <span>ne alegi</span>',
                    'cards' => [
                        ['icon' => 'fa-truck-fast', 'title' => 'Livrare rapidă', 'text' => 'Livrăm oriunde în România în 24-48h prin curier rapid. Tu primești, noi ne mișcăm.'],
                        ['icon' => 'fa-shield-halved', 'title' => 'Piese de calitate', 'text' => 'Colaborăm doar cu furnizori de încredere și verificăm fiecare piesă înainte de livrare.'],
                        ['icon' => 'fa-headset', 'title' => 'Suport dedicat', 'text' => 'Specialiști auto disponibili telefonic pentru consultanță și recomandări corecte.'],
                    ],
                ],
                'trust' => [
                    'title' => 'Echipa noastră e formată din pasionați auto cu experiență reală',
                    'checks' => [
                        'Piese verificate înainte de expediere',
                        'Suport tehnic de la specialiști auto',
                        'Garanție reală, nu doar pe hârtie',
                        'Compatibilitate verificată după VIN',
                    ],
                    'brands' => [
                        ['name' => 'BOSCH', 'color' => '#e11d48'],
                        ['name' => 'MANN', 'color' => '#65a30d'],
                        ['name' => 'SACHS', 'color' => '#2563eb'],
                        ['name' => 'Valeo', 'color' => '#65a30d'],
                        ['name' => 'TRW', 'color' => '#dc2626'],
                    ],
                ],
            ],
            'contact' => [
                'strip' => [
                    ['icon' => 'fa-location-dot', 'title' => 'Timișoara, Str. Stan Vidrighin nr. 14', 'subtitle' => 'jud. Timiș'],
                    ['icon' => 'fa-phone', 'title' => '0726 498 573', 'subtitle' => 'Luni – Vineri'],
                    ['icon' => 'fa-envelope', 'title' => 'contact@besoiupieseauto.ro', 'subtitle' => 'Răspundem în 2h'],
                    ['icon' => 'fa-clock', 'title' => 'L-V 09:00–18:00', 'subtitle' => 'Zile lucrătoare'],
                ],
                'float_cards' => [
                    ['icon' => 'fa-phone', 'title' => '0726 498 573', 'subtitle' => 'Sună acum'],
                    ['icon' => 'fa-envelope', 'title' => 'contact@besoiupieseauto.ro', 'subtitle' => 'Email'],
                    ['icon' => 'fa-location-dot', 'title' => 'Timișoara, România', 'subtitle' => 'Str. Stan Vidrighin nr. 14'],
                ],
                'form' => [
                    'title' => 'Trimite-ne un mesaj',
                    'subtitle' => 'Completează formularul și revenim în maxim 2 ore',
                    'submit' => 'TRIMITE MESAJUL',
                    'privacy' => 'Datele tale sunt protejate și nu vor fi partajate.',
                ],
                'cta' => [
                    'title_html' => 'CONTACTEAZĂ-NE<br>RAPID',
                    'cards' => [
                        ['icon' => 'fa-phone', 'title' => 'Telefonic', 'text' => 'Sună la 0726 498 573', 'link_label' => 'Sună acum', 'link_href' => 'tel:+40726498573'],
                        ['icon' => 'fa-envelope', 'title' => 'Email', 'text' => 'Scrie la contact@besoiupieseauto.ro', 'link_label' => 'Trimite email', 'link_href' => 'mailto:contact@besoiupieseauto.ro'],
                        ['icon' => 'fa-location-dot', 'title' => 'Vizitează-ne', 'text' => 'Timișoara, Str. Stan Vidrighin 14', 'link_label' => 'Vezi pe hartă', 'link_href' => 'https://maps.google.com/?q=Timișoara+Stan+Vidrighin+14'],
                    ],
                ],
            ],
            default => [],
        };
    }
}

if (!function_exists('site_defaults_page_meta')) {
    function site_defaults_page_meta(string $slug): array
    {
        return match ($slug) {
            'home' => [
                'title' => 'Besoiu Piese Auto',
                'description' => 'Magazin online de piese auto — compatibilitate verificată, livrare rapidă 24-48h, peste 10.000 de produse.',
                'hero_label' => '',
                'hero_title' => '',
                'hero_subtitle' => '',
            ],
            'global' => [
                'title' => 'Setări globale site',
                'description' => 'Header, footer și elemente comune.',
            ],
            'catalog' => [
                'title' => 'Catalog piese auto',
                'description' => 'Catalog complet de piese auto — caută după VIN, OEM sau categorie.',
                'hero_label' => '',
                'hero_title' => 'CATALOG PIESE AUTO',
                'hero_subtitle' => 'Peste 10.000 de piese auto pentru toate mărcile.',
            ],
            default => [],
        };
    }
}
