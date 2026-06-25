<?php
// config/roles.php
return [
    'super_ambassador' => [
        'label'   => 'Ambasador Suprem',
        'scopes'  => ['global','regions','departments','finance','users','settings'],
        'nav'     => [
            'Firme'      => '/public/firms',
            'Echipa'     => '/public/team',
            'Clienți'    => '/public/clients',
            'Produse'    => '/public/products',
            'Task-uri'   => '/public/todolist',
            'Rapoarte'   => '/public/analytic',
            'Setări'     => '/public/settings',
            'Utilizatori'=> '/public/users',
        ],
        'widgets' => ['kpi_global','sales_overview','regions_map','top_categories','recent_orders','quick_actions','howto'],
    ],
    'regional_ambassador' => [
        'label'   => 'Ambasador Regional',
        'scopes'  => ['regions','departments','users'],
        'nav'     => [
            'Firme (Regiune)'  => '/public/firms?scope=region',
            'Echipa (Regiune)' => '/public/team?scope=region',
            'Clienți (Regiune)'=> '/public/clients?scope=region',
            'Task-uri'         => '/public/todolist?scope=region',
            'Rapoarte (Regiune)'=> '/public/analytic?scope=region',
        ],
        'widgets' => ['kpi_region','sales_overview','regions_map','recent_orders','quick_actions','howto'],
    ],
    'manager' => [
        'label'   => 'Manager de Departament',
        'scopes'  => ['departments'],
        'nav'     => [
            'Echipa departament' => '/public/team?scope=dept',
            'Task-uri'           => '/public/todolist?scope=dept',
            'Produse'            => '/public/products?scope=dept',
            'Rapoarte'           => '/public/analytic?scope=dept',
        ],
        'widgets' => ['kpi_department','sales_overview','top_categories','recent_orders','quick_actions','howto'],
    ],
    'executive' => [
        'label'   => 'Executiv',
        'scopes'  => ['personal'],
        'nav'     => [
            'Task-urile mele' => '/public/todolist?scope=me',
            'Clienții mei'    => '/public/clients?scope=me',
            'Profil'          => '/public/profile',
        ],
        'widgets' => ['kpi_personal','quick_actions','howto','recent_orders'],
    ],
    'guest' => [
        'label'   => 'Vizitator',
        'scopes'  => [],
        'nav'     => [
            'Autentificare' => '/public/login',
            'Înregistrare'  => '/public/reg',
        ],
        'widgets' => ['howto'],
    ],
];
