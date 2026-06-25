<?php
declare(strict_types=1);

require_once __DIR__ . '/system/static-page.php';
require_once __DIR__ . '/system/site-content.php';

$defaults = [
    'title' => 'Politica de cookies',
    'description' => 'Politica de cookies Besoiu Piese Auto — ce cookie-uri folosim și cum le poți gestiona.',
    'hero_label' => 'Politici',
    'hero_title' => 'Politica de cookies',
    'hero_subtitle' => 'Explicăm ce tipuri de cookie-uri folosim, de ce sunt necesare și cum poți controla preferințele tale.',
    'sections' => [
        [
            'type' => 'content',
            'title' => '1. Ce sunt cookie-urile',
            'paragraphs' => [
                'Cookie-urile sunt fișiere mici stocate în browserul tău atunci când vizitezi site-ul. Ele ne ajută să asigurăm funcționarea corectă a site-ului și să îmbunătățim experiența ta.',
            ],
        ],
        [
            'type' => 'cards',
            'title' => '2. Tipuri de cookie-uri folosite',
            'items' => [
                ['title' => 'Strict necesare', 'text' => 'Permit funcționarea site-ului, coșul de cumpărături și securitatea sesiunii.'],
                ['title' => 'Preferințe', 'text' => 'Memorează setări precum filtre sau categorii selectate recent.'],
                ['title' => 'Statistice', 'text' => 'Ne ajută să înțelegem cum este utilizat site-ul, în formă agregată.'],
                ['title' => 'Marketing', 'text' => 'Pot fi folosite doar dacă există consimțământ explicit, pentru campanii relevante.'],
            ],
        ],
        [
            'type' => 'content',
            'title' => '3. Cum poți controla cookie-urile',
            'paragraphs' => [
                'Poți șterge sau bloca cookie-urile din setările browserului. Reține că dezactivarea cookie-urilor strict necesare poate afecta funcționalitatea coșului și a procesului de comandă.',
            ],
            'list' => [
                'Chrome: Setări → Confidențialitate și securitate → Cookie-uri',
                'Firefox: Setări → Confidențialitate și securitate',
                'Edge: Setări → Cookie-uri și permisiuni site',
                'Safari: Preferințe → Confidențialitate',
            ],
        ],
        [
            'type' => 'content',
            'title' => '4. Cookie-uri terțe',
            'paragraphs' => [
                'Site-ul poate include servicii terțe, precum hărți, fonturi sau instrumente de analiză, care pot seta propriile cookie-uri. Te încurajăm să consulți politicile acelor furnizori.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '5. Actualizări',
            'paragraphs' => [
                'Putem actualiza periodic această politică. Versiunea curentă este publicată pe această pagină, cu data ultimei modificări: <strong>20.05.2026</strong>.',
            ],
        ],
    ],
    'cta' => [
        'title' => 'Întrebări despre confidențialitate?',
        'subtitle' => 'Consultă și politica de confidențialitate sau contactează-ne direct.',
        'primary' => ['label' => 'Confidențialitate', 'href' => 'politica-confidentialitate.php'],
        'secondary' => ['label' => 'Contact', 'href' => 'contact.php'],
    ],
];

render_static_page(site_content_page('politica-cookies', $defaults));
