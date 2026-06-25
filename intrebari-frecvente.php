<?php
declare(strict_types=1);

require_once __DIR__ . '/system/static-page.php';
require_once __DIR__ . '/system/site-content.php';

$defaults = [
    'title' => 'Întrebări frecvente',
    'description' => 'Răspunsuri la cele mai frecvente întrebări despre comenzi, livrare, compatibilitate și retur — Besoiu Piese Auto.',
    'hero_label' => 'Suport clienți',
    'hero_title' => 'Întrebări frecvente',
    'hero_subtitle' => 'Tot ce trebuie să știi despre comenzi, livrare, plăți, compatibilitate și retur — într-un singur loc.',
    'sections' => [
        [
            'type' => 'content',
            'title' => 'Răspunsuri rapide',
            'paragraphs' => [
                'Am adunat cele mai frecvente întrebări primite de la clienții Besoiu Piese Auto. Dacă nu găsești răspunsul dorit, sună-ne la <strong>0726 498 573</strong> sau scrie-ne la <strong>contact@besoiupieseauto.ro</strong>.',
            ],
        ],
    ],
    'faq' => [
        ['q' => 'Cum știu că piesa se potrivește mașinii mele?', 'a' => 'Verifică codul OEM, motorizarea și, ideal, trimite-ne seria de șasiu (VIN). Te putem ajuta telefonic înainte de comandă.'],
        ['q' => 'Ce înseamnă „La cerere” la preț?', 'a' => 'Produsul nu are preț afișat automat. Contactează-ne pentru disponibilitate și ofertă personalizată.'],
        ['q' => 'Cât durează livrarea?', 'a' => 'De regulă 24–48 de ore lucrătoare, în funcție de stoc și localitate.'],
        ['q' => 'Ce metode de plată acceptați?', 'a' => 'Plata principală este ramburs la livrare. Alte opțiuni pot fi discutate la confirmarea comenzii.'],
        ['q' => 'Pot anula o comandă?', 'a' => 'Da, dacă nu a fost deja expediată. Sună cât mai repede la 0726 498 573.'],
        ['q' => 'Cum returnez un produs?', 'a' => 'Contactează-ne în 14 zile de la recepție. Produsul trebuie să fie neutilizat și, pe cât posibil, în ambalajul original.'],
        ['q' => 'Emiteți factură?', 'a' => 'Da, pentru persoane fizice și juridice, pe baza datelor furnizate la comandă.'],
        ['q' => 'Unde vă află sediul?', 'a' => 'Timișoara, Str. Stan Vidrighin nr. 14, jud. Timiș.'],
    ],
    'cta' => [
        'title' => 'Nu ai găsit răspunsul?',
        'subtitle' => 'Echipa noastră te ajută să găsești piesa potrivită.',
        'primary' => ['label' => 'Contact', 'href' => 'contact.php'],
        'secondary' => ['label' => 'Catalog', 'href' => 'catalog.php'],
    ],
];

render_static_page(site_content_page('intrebari-frecvente', $defaults));
