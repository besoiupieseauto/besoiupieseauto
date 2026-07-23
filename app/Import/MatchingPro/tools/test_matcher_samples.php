<?php
declare(strict_types=1);

require dirname(__DIR__) . '/api/bootstrap.php';
require dirname(__DIR__) . '/api/lib/ImportShowcaseTypeMatcher.php';

$tests = [
    'Termostat lichid racire PIERBURG 704879000',
    'Antigel KROON OIL SP 12 EVO 1L',
    'Lichid frana DOT 4 500ml',
    'ULEI MOTOR MAZDA 0W20 5L',
    'FILTRU ULEI MANN',
    'VARTA Baterie litiu CR1632',
    'Suport baterie',
    'Vas expansiune lichid racire',
];

foreach ($tests as $name) {
    $hits = [];
    foreach (['ulei', 'lichid', 'baterie'] as $type) {
        $m = ImportShowcaseTypeMatcher::matchKeyword(['name' => $name], [$type], 72, true);
        if ($m !== null) {
            $hits[] = $type;
        }
    }
    echo $name . ' => ' . ($hits === [] ? 'NONE' : implode(', ', $hits)) . PHP_EOL;
}
