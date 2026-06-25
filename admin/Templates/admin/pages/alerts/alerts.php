<?php

$hubConfig = [
    'title' => 'Alerte & Red Flags',
    'subtitle' => 'Alerte configurate + semnale din dashboard.',
    'action' => 'alerts',
    'emptyText' => 'Nicio alertă înregistrată.',
    'columns' => [
        ['key' => 'id', 'label' => 'ID'],
        ['key' => 'name', 'label' => 'Nume'],
        ['key' => 'email', 'label' => 'Email'],
        ['key' => 'phone', 'label' => 'Telefon'],
        ['key' => 'status', 'label' => 'Status'],
    ],
];

require dirname(__DIR__) . '/_partials/hub-list.php';
