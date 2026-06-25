<?php

$hubConfig = [
    'title' => 'Rapoarte',
    'subtitle' => 'Rapoarte salvate + KPI dashboard (fundal).',
    'action' => 'reports',
    'emptyText' => 'Nu există rapoarte salvate.',
    'columns' => [
        ['key' => 'id', 'label' => 'ID'],
        ['key' => 'name', 'label' => 'Nume'],
        ['key' => 'email', 'label' => 'Email'],
        ['key' => 'status', 'label' => 'Status'],
    ],
];

require dirname(__DIR__) . '/_partials/hub-list.php';
