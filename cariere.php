<?php
declare(strict_types=1);

require_once __DIR__ . '/system/static-page.php';
require_once __DIR__ . '/system/site-content.php';

$defaults = [
    'title' => 'Cariere',
    'description' => 'Oportunități de carieră la Besoiu Piese Auto — Timișoara. Alătură-te echipei noastre.',
    'hero_label' => 'Informații',
    'hero_title' => 'Cariere',
    'hero_subtitle' => 'Construim un magazin online de piese auto orientat spre calitate, viteză și suport real pentru clienți. Căutăm oameni motivați care vor să crească odată cu noi.',
    'sections' => [
        [
            'type' => 'content',
            'title' => 'De ce Besoiu Piese Auto',
            'paragraphs' => [
                'Suntem o echipă din Timișoara, cu focus pe piese auto originale și aftermarket, livrare rapidă în România și consultanță telefonică dedicată. Oferim un mediu de lucru practic, orientat spre rezultate și spre nevoile reale ale clienților.',
            ],
        ],
        [
            'type' => 'cards',
            'title' => 'Ce oferim',
            'items' => [
                ['title' => 'Mediu dinamic', 'text' => 'Proiecte reale în e-commerce auto, cu impact direct asupra experienței clienților.'],
                ['title' => 'Echipă compactă', 'text' => 'Decizii rapide, responsabilitate clară și colaborare directă între departamente.'],
                ['title' => 'Dezvoltare', 'text' => 'Posibilitatea de a învăța procese comerciale, logistice și tehnice din domeniul auto.'],
                ['title' => 'Locație', 'text' => 'Bază operațională în Timișoara, jud. Timiș.'],
            ],
        ],
        [
            'type' => 'jobs',
            'title' => 'Posturi deschise',
            'items' => [
                ['title' => 'Consultant piese auto', 'location' => 'Timișoara', 'type' => 'Full-time'],
                ['title' => 'Operator procesare comenzi', 'location' => 'Timișoara', 'type' => 'Full-time'],
                ['title' => 'Specialist marketing digital', 'location' => 'Remote / Timișoara', 'type' => 'Part-time'],
            ],
        ],
        [
            'type' => 'content',
            'title' => 'Cum aplici',
            'paragraphs' => [
                'Trimite CV-ul și scrisoarea de intenție la <strong>contact@besoiupieseauto.ro</strong>, cu subiectul „Candidatură — [numele postului]”. Revenim cu un răspuns în maximum 5 zile lucrătoare.',
            ],
        ],
    ],
    'cta' => [
        'title' => 'Nu găsești postul potrivit?',
        'subtitle' => 'Trimite-ne candidatura spontană — păstrăm profilurile relevante pentru viitoarele oportunități.',
        'primary' => ['label' => 'Trimite candidatura', 'href' => 'contact.php'],
    ],
];

render_static_page(site_content_page('cariere', $defaults));
