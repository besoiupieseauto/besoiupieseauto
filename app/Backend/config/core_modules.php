<?php

declare(strict_types=1);

/**
 * Module CORE = „HDD de sistem” / OS-ul Adminului.
 * NU stau în admin/modules/ și NU se dezactivează din state.json.
 *
 * Import + AI sunt OPTIONAL (vezi modules/Import, modules/Ai).
 *
 * @return list<array{id: string, name: string, note: string, why_core: string}>
 */
return [
    [
        'id' => 'auth',
        'name' => 'Auth / RBAC',
        'note' => 'Permission, Roles, Workspace, login',
        'why_core' => 'Fără auth nu există Admin securizat.',
    ],
    [
        'id' => 'router',
        'name' => 'Router / HTTP',
        'note' => 'HttpApplication, Router, ApiBootstrap, ModuleRegistry boot',
        'why_core' => 'Front-controller — totul trece pe aici.',
    ],
    [
        'id' => 'produse',
        'name' => 'Produse',
        'note' => 'Catalog magazin, vitrină, CRUD produse',
        'why_core' => 'Business principal: fără produse nu există magazin.',
    ],
    [
        'id' => 'furnizori',
        'name' => 'Furnizori',
        'note' => 'Modul opțional modules/furnizori/ — UI B2B; importul folosește fallback DB dacă modulul e oprit',
        'why_core' => 'Business: surse preț/stoc — poate fi dezactivat ca modul plug-in.',
    ],
    [
        'id' => 'categorii',
        'name' => 'Categorii',
        'note' => 'Taxonomie CMS + facete',
        'why_core' => 'Catalog și taxonomie magazin.',
    ],
    [
        'id' => 'comenzi',
        'name' => 'Comenzi',
        'note' => 'Checkout site → admin, clienți, facturi, livrare',
        'why_core' => 'Conversie vânzare — flux comercial.',
    ],
    [
        'id' => 'settings',
        'name' => 'Settings',
        'note' => 'Config aplicație, module, env UI',
        'why_core' => 'Config central + gestionare module.',
    ],
    [
        'id' => 'dashboard',
        'name' => 'Dashboard shell',
        'note' => 'Shell UI; metricile pot veni din module opționale',
        'why_core' => 'Punct de intrare admin după login.',
    ],
    [
        'id' => 'users',
        'name' => 'Users admin',
        'note' => 'Modul modules/users/ — conturi operatori / roluri / login',
        'why_core' => 'Cine operează Adminul.',
    ],
    [
        'id' => 'alerts',
        'name' => 'Alerte ops',
        'note' => 'Ops alerts, red flags',
        'why_core' => 'Monitorizare operațională minimă.',
    ],
];
