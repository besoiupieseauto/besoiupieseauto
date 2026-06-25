<?php

$hubConfig = [
    'title' => 'Cross-reference OEM',
    'subtitle' => 'Echivalențe OEM (`products_oem`) — încărcare în fundal.',
    'action' => 'crossref',
    'emptyText' => 'Nicio mapare OEM. Rulează import sau migrarea 018.',
    'rowsFrom' => 'oem',
    'columns' => [
        ['key' => 'id', 'label' => 'ID'],
        ['key' => 'oem_code', 'label' => 'Cod OEM'],
        ['key' => 'brand', 'label' => 'Brand'],
        ['key' => 'product_code', 'label' => 'Cod produs'],
        ['key' => 'product_name', 'label' => 'Produs'],
        ['key' => 'source', 'label' => 'Sursă'],
    ],
];

require dirname(__DIR__) . '/_partials/hub-list.php';
