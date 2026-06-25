<?php
declare(strict_types=1);

require_once __DIR__ . '/system/static-page.php';
require_once __DIR__ . '/system/site-content.php';

$defaults = [
    'title' => 'Termeni și condiții',
    'description' => 'Termenii și condițiile de utilizare a site-ului și de comercializare — Besoiu Piese Auto.',
    'hero_label' => 'Politici',
    'hero_title' => 'Termeni și condiții',
    'hero_subtitle' => 'Regulile de utilizare a site-ului besoiupieseauto.ro și condițiile comerciale aplicabile comenzilor.',
    'sections' => [
        [
            'type' => 'content',
            'title' => '1. Informații generale',
            'paragraphs' => [
                'Site-ul <strong>besoiupieseauto.ro</strong> este operat de Besoiu Piese Auto, cu sediul în Timișoara, Str. Stan Vidrighin nr. 14, jud. Timiș. Prin accesarea site-ului și plasarea comenzilor, accepți termenii de mai jos.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '2. Produse și prețuri',
            'paragraphs' => [
                'Imaginile și descrierile produselor au caracter informativ. Ne străduim să menținem informațiile corecte, însă pot apărea erori tehnice sau de stoc. Prețurile sunt exprimate în RON și pot fi actualizate fără notificare prealabilă.',
                'Ne rezervăm dreptul de a refuza sau anula o comandă în caz de eroare evidentă de preț, indisponibilitate sau date incomplete furnizate de client.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '3. Comenzi și contract',
            'paragraphs' => [
                'Plasarea comenzii reprezintă o ofertă de cumpărare. Contractul se consideră încheiat după confirmarea comenzii de către Besoiu Piese Auto, prin email, telefon sau alte mijloace de comunicare.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '4. Livrare',
            'paragraphs' => [
                'Termenele de livrare sunt estimative și pot varia în funcție de disponibilitate, destinație și condiții externe. Detaliile privind livrarea sunt disponibile în pagina <a href="livrare-plata.php">Livrare și plată</a>.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '5. Retur și reclamații',
            'paragraphs' => [
                'Condițiile de retur și garanție sunt detaliate în pagina <a href="retur-garantie.php">Retur și garanție</a>, în conformitate cu legislația aplicabilă consumatorilor din România.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '6. Proprietate intelectuală',
            'paragraphs' => [
                'Conținutul site-ului — texte, grafică, logo, structură — aparține Besoiu Piese Auto sau partenerilor săi și nu poate fi copiat fără acord scris.',
            ],
        ],
        [
            'type' => 'content',
            'title' => '7. Contact',
            'paragraphs' => [
                'Pentru întrebări legate de termeni: <strong>contact@besoiupieseauto.ro</strong>, tel. <strong>0726 498 573</strong>.',
            ],
        ],
    ],
    'cta' => [
        'title' => 'Ai nevoie de clarificări?',
        'primary' => ['label' => 'Contactează-ne', 'href' => 'contact.php'],
    ],
];

render_static_page(site_content_page('termeni-conditii', $defaults));
