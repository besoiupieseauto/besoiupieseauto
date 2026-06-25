<?php
declare(strict_types=1);

require_once __DIR__ . '/system/static-page.php';
require_once __DIR__ . '/system/site-content.php';

$defaults = [
    'title' => 'Blog',
    'description' => 'Articole, sfaturi și noutăți despre piese auto, întreținere și compatibilitate — Besoiu Piese Auto.',
    'hero_label' => 'Informații',
    'hero_title' => 'Blog Besoiu Piese Auto',
    'hero_subtitle' => 'Ghiduri practice, recomandări tehnice și noutăți din lumea pieselor auto pentru șoferi și service-uri din România.',
    'sections' => [
        [
            'type' => 'content',
            'title' => 'Resurse utile pentru mașina ta',
            'paragraphs' => [
                'Pe blogul Besoiu Piese Auto publicăm articole despre alegerea corectă a pieselor, verificarea compatibilității după VIN și întreținerea preventivă. Scopul nostru este să te ajutăm să comanzi rapid piesa potrivită, fără riscuri și fără timp pierdut.',
            ],
        ],
        [
            'type' => 'blog',
            'title' => 'Articole recente',
            'items' => [
                [
                    'tag' => 'Ghid',
                    'title' => 'Cum verifici compatibilitatea unei piese după VIN',
                    'text' => 'Pași simpli pentru a confirma că piesa comandată se potrivește cu motorizarea și echiparea mașinii tale.',
                ],
                [
                    'tag' => 'Întreținere',
                    'title' => 'Când trebuie schimbate filtrele auto',
                    'text' => 'Recomandări pentru filtru de ulei, aer, polen și combustibil, în funcție de utilizare și condiții de mers.',
                ],
            ],
        ],
        [
            'type' => 'content',
            'title' => 'Vrei un subiect anume?',
            'paragraphs' => [
                'Dacă ai o întrebare tehnică sau vrei un articol despre o anumită piesă, scrie-ne pe <strong>contact@besoiupieseauto.ro</strong>. Echipa noastră răspunde în zilele lucrătoare, de obicei în maxim 2 ore.',
            ],
        ],
    ],
    'cta' => [
        'title' => 'Cauți o piesă acum?',
        'subtitle' => 'Intră în catalog sau sună-ne pentru verificare compatibilitate.',
        'primary' => ['label' => 'Vezi catalogul', 'href' => 'catalog.php'],
        'secondary' => ['label' => 'Contactează-ne', 'href' => 'contact.php'],
    ],
];

$page = site_content_page('blog', $defaults);
$dbBlogItems = site_content_blog_items_for_static_page(20);

if ($dbBlogItems !== [] && is_array($page['sections'] ?? null)) {
    foreach ($page['sections'] as $index => $section) {
        if (($section['type'] ?? '') === 'blog') {
            $page['sections'][$index]['items'] = $dbBlogItems;
        }
    }
}

render_static_page($page);
