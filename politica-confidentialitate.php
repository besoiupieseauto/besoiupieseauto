<?php
declare(strict_types=1);

require_once __DIR__ . '/system/static-page.php';
require_once __DIR__ . '/system/site-content.php';

$defaults = [
    'title' => 'Politica de confidențialitate',
    'description' => 'Politica de confidențialitate Besoiu Piese Auto — cum colectăm și protejăm datele personale.',
    'hero_label' => 'Politici',
    'hero_title' => 'Politica de confidențialitate',
    'hero_subtitle' => 'Respectăm confidențialitatea datelor tale și le prelucrăm în mod responsabil, conform legislației aplicabile.',
    'sections' => [
        [
            'type' => 'content',
            'title' => '1. Operatorul de date',
            'paragraphs' => [
                'Operatorul datelor personale este Besoiu Piese Auto, Timișoara, Str. Stan Vidrighin nr. 14, jud. Timiș. Contact: <strong>contact@besoiupieseauto.ro</strong>.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '2. Ce date colectăm',
            'paragraphs' => [
                'Putem prelucra următoarele categorii de date, în funcție de interacțiunea ta cu site-ul:',
            ],
            'list' => [
                'Nume, telefon, email',
                'Adresă de livrare și facturare',
                'Detalii despre comandă și produse solicitate',
                'Date tehnice de navigare (IP, browser, pagini vizitate)',
                'Mesaje transmise prin formularul de contact',
            ],
        ],
        [
            'type' => 'content',
            'title' => '3. Scopul prelucrării',
            'paragraphs' => [
                'Folosim datele pentru procesarea comenzilor, comunicarea cu tine, livrarea produselor, emiterea documentelor fiscale, suport clienți, securitatea site-ului și îmbunătățirea serviciilor.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '4. Temeiul legal',
            'paragraphs' => [
                'Prelucrăm datele în baza executării contractului, obligațiilor legale, interesului legitim (ex. securitate, prevenirea fraudelor) și, unde este cazul, a consimțământului tău.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '5. Durata stocării',
            'paragraphs' => [
                'Păstrăm datele cât timp este necesar pentru scopurile menționate și conform termenelor legale aplicabile documentelor contabile și fiscale.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '6. Drepturile tale',
            'paragraphs' => [
                'Ai dreptul de acces, rectificare, ștergere, restricționare, opoziție și portabilitate, în condițiile legii. Poți depune reclamație la ANSPDCP.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '7. Securitate',
            'paragraphs' => [
                'Aplicăm măsuri tehnice și organizatorice rezonabile pentru protejarea datelor împotriva accesului neautorizat, pierderii sau alterării.',
            ],
        ],
    ],
    'cta' => [
        'title' => 'Solicitări privind datele personale',
        'subtitle' => 'Scrie-ne la contact@besoiupieseauto.ro pentru exercitarea drepturilor tale.',
        'primary' => ['label' => 'Contact', 'href' => 'contact.php'],
    ],
];

render_static_page(site_content_page('politica-confidentialitate', $defaults));
