<?php
declare(strict_types=1);

require_once __DIR__ . '/system/static-page.php';
require_once __DIR__ . '/system/site-content.php';

$defaults = [
    'title' => 'Retur și garanție',
    'description' => 'Politica de retur și garanție Besoiu Piese Auto — drepturile tale ca client.',
    'hero_label' => 'Suport clienți',
    'hero_title' => 'Retur și garanție',
    'hero_subtitle' => 'Cumpără cu încredere. Explicăm clar condițiile de retur, garanție și procedura de reclamație.',
    'sections' => [
        [
            'type' => 'content',
            'title' => 'Dreptul de retur',
            'paragraphs' => [
                'Conform legislației aplicabile comerțului online, ai dreptul să returnezi produsele în termen de <strong>14 zile calendaristice</strong> de la recepție, fără a invoca un motiv, pentru produsele care nu au fost montate, utilizate sau deteriorate.',
                'Pentru a iniția un retur, contactează-ne la <strong>contact@besoiupieseauto.ro</strong> sau la telefon <strong>0726 498 573</strong>, menționând numărul comenzii și motivul solicitării.',
            ],
            'list' => [
                'Produsul trebuie returnat în ambalajul original, dacă este posibil',
                'Accesoriile și documentele trebuie incluse complet',
                'Costurile de retur pot fi suportate de client, exceptând cazurile de produs neconform',
            ],
        ],
        [
            'type' => 'cards',
            'title' => 'Garanție',
            'items' => [
                ['title' => 'Produse noi', 'text' => 'Beneficiază de garanția legală de conformitate, conform termenelor aplicabile.'],
                ['title' => 'Produse aftermarket', 'text' => 'Garanția este acordată conform documentației producătorului și tipului de piesă.'],
                ['title' => 'Verificare la montaj', 'text' => 'Recomandăm montajul într-un service autorizat și păstrarea dovezii montajului.'],
                ['title' => 'Reclamații', 'text' => 'Anunță orice problemă în termen rezonabil de la identificare, cu dovezi foto/video.'],
            ],
        ],
        [
            'type' => 'content',
            'title' => 'Produse excluse de la retur',
            'paragraphs' => [
                'Nu pot fi returnate produsele confecționate după specificații clare, produsele sigilate care au fost desigilate și nu pot fi recondiționate, precum și piesele montate, utilizate sau deteriorate din vina clientului.',
            ],
        ],
    ],
    'faq' => [
        ['q' => 'Cât durează rambursarea?', 'a' => 'De regulă în 14 zile de la recepția și verificarea produsului returnat.'],
        ['q' => 'Pot returna o piesă comandată greșit?', 'a' => 'Da, dacă produsul este neutilizat și în starea originală, în termenul legal de 14 zile.'],
        ['q' => 'Ce fac dacă piesa este defectă?', 'a' => 'Contactează-ne imediat. Vom analiza cazul și propune soluție de înlocuire, reparare sau retur.'],
    ],
    'cta' => [
        'title' => 'Ai nevoie să inițiezi un retur?',
        'subtitle' => 'Contactează echipa noastră și te ghidăm pas cu pas.',
        'primary' => ['label' => 'Contactează-ne', 'href' => 'contact.php'],
    ],
];

render_static_page(site_content_page('retur-garantie', $defaults));
