<?php
declare(strict_types=1);

require_once __DIR__ . '/system/static-page.php';
require_once __DIR__ . '/system/site-content.php';

$defaults = [
    'title' => 'Cum comand',
    'description' => 'Ghid pas cu pas pentru plasarea comenzii pe Besoiu Piese Auto — simplu, rapid și sigur.',
    'hero_label' => 'Suport clienți',
    'hero_title' => 'Cum comand',
    'hero_subtitle' => 'De la căutarea piesei până la confirmarea comenzii — tot procesul explicat clar, în câțiva pași simpli.',
    'sections' => [
        [
            'type' => 'steps',
            'title' => 'Pașii comenzii',
            'items' => [
                ['title' => 'Caută piesa', 'text' => 'Folosește catalogul, codul OEM, VIN-ul sau filtrele de marcă/model pentru a găsi produsul potrivit.'],
                ['title' => 'Verifică compatibilitatea', 'text' => 'Consultă detaliile produsului sau contactează-ne telefonic dacă ai nevoie de confirmare înainte de comandă.'],
                ['title' => 'Adaugă în coș', 'text' => 'Selectează cantitatea dorită și adaugă piesa în coșul de cumpărături.'],
                ['title' => 'Completează datele', 'text' => 'Introdu numele, telefonul, adresa de livrare și alege metoda de livrare preferată.'],
                ['title' => 'Confirmă comanda', 'text' => 'Finalizează comanda. Vei primi confirmarea, iar echipa noastră te contactează dacă sunt necesare clarificări.'],
            ],
        ],
        [
            'type' => 'cards',
            'title' => 'Informații importante',
            'items' => [
                ['title' => 'Verificare înainte de livrare', 'text' => 'La cerere, verificăm compatibilitatea piesei înainte de expediere.'],
                ['title' => 'Comenzi telefonice', 'text' => 'Poți comanda și telefonic la 0726 498 573, în zilele lucrătoare.'],
                ['title' => 'Produse la cerere', 'text' => 'Piesele fără preț afișat pot fi solicitate — revenim cu ofertă și disponibilitate.'],
                ['title' => 'Factură', 'text' => 'Emitem factură pentru persoane fizice și juridice, conform datelor furnizate la comandă.'],
            ],
        ],
    ],
    'faq' => [
        ['q' => 'Pot modifica comanda după ce am trimis-o?', 'a' => 'Da, dacă nu a fost deja procesată pentru livrare. Contactează-ne cât mai rapid la 0726 498 573.'],
        ['q' => 'Primesc confirmare pe email?', 'a' => 'Da, după finalizarea comenzii primești confirmarea cu detaliile comenzii, dacă ai introdus adresa de email.'],
        ['q' => 'Pot comanda fără cont?', 'a' => 'Da, poți comanda ca vizitator, completând datele necesare în pagina de coș.'],
    ],
    'cta' => [
        'title' => 'Gata să plasezi comanda?',
        'subtitle' => 'Intră în catalog și adaugă piesele de care ai nevoie.',
        'primary' => ['label' => 'Mergi la catalog', 'href' => 'catalog.php'],
        'secondary' => ['label' => 'Sună acum', 'href' => 'tel:+40726498573'],
    ],
];

render_static_page(site_content_page('cum-comand', $defaults));
