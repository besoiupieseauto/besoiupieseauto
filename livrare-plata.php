<?php
declare(strict_types=1);

require_once __DIR__ . '/system/static-page.php';
require_once __DIR__ . '/system/site-content.php';

$defaults = [
    'title' => 'Livrare și plată',
    'description' => 'Informații despre livrarea pieselor auto și metodele de plată acceptate — Besoiu Piese Auto.',
    'hero_label' => 'Suport clienți',
    'hero_title' => 'Livrare și plată',
    'hero_subtitle' => 'Livrăm rapid în toată România. Plata se face simplu, cu opțiuni sigure pentru fiecare comandă.',
    'sections' => [
        [
            'type' => 'cards',
            'title' => 'Metode de livrare',
            'items' => [
                ['title' => 'Curier național', 'text' => 'Livrare în 24–48h în majoritatea localităților din România, prin servicii de curierat.'],
                ['title' => 'Ridicare locală', 'text' => 'Poți ridica comanda din Timișoara, după confirmarea disponibilității.'],
                ['title' => 'Ambalare sigură', 'text' => 'Piesele sunt ambalate corespunzător pentru transport, mai ales componentele sensibile.'],
                ['title' => 'Urmărire comandă', 'text' => 'Primești informații despre expediere și AWB după procesarea comenzii.'],
            ],
        ],
        [
            'type' => 'content',
            'title' => 'Metode de plată',
            'paragraphs' => [
                'Plata se poate face <strong>ramburs la livrare</strong>, metodă preferată de majoritatea clienților noștri. Pentru comenzile eligibile, putem oferi și alte opțiuni, comunicate la confirmarea comenzii.',
                'Toate prețurile afișate pe site sunt exprimate în <strong>RON (lei)</strong>, cu TVA inclus acolo unde este cazul, conform legislației aplicabile.',
            ],
            'list' => [
                'Plata ramburs la primirea coletului',
                'Facturare pentru persoane fizice și juridice',
                'Confirmare telefonică pentru comenzi cu valoare mare',
            ],
        ],
        [
            'type' => 'content',
            'title' => 'Costuri de transport',
            'paragraphs' => [
                'Costul livrării depinde de greutatea coletului, destinație și serviciul de curierat selectat. Valoarea exactă este comunicată înainte de expediere sau afișată în procesul de comandă, când este disponibilă.',
            ],
        ],
    ],
    'faq' => [
        ['q' => 'Cât durează livrarea?', 'a' => 'De regulă 24–48 de ore lucrătoare, în funcție de disponibilitatea produsului și localitate.'],
        ['q' => 'Livrați în toată țara?', 'a' => 'Da, livrăm în România prin curier. Pentru ridicare locală, sediul este în Timișoara.'],
        ['q' => 'Pot plăti cu cardul?', 'a' => 'Momentan plata principală acceptată este ramburs. Alte opțiuni pot fi discutate la confirmarea comenzii.'],
    ],
    'cta' => [
        'title' => 'Ai întrebări despre livrare?',
        'subtitle' => 'Sună-ne și îți spunem termenul estimat pentru piesa dorită.',
        'primary' => ['label' => '0726 498 573', 'href' => 'tel:+40726498573'],
        'secondary' => ['label' => 'Contact', 'href' => 'contact.php'],
    ],
];

render_static_page(site_content_page('livrare-plata', $defaults));
