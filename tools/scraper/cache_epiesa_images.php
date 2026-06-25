<?php

declare(strict_types=1);



$root = dirname(__DIR__, 2);

require_once $root . '/lib/Scraper/EpiesaCatalog.php';



$updated = EpiesaCatalog::refreshAllImages();

$total = count(EpiesaCatalog::listProducts());



echo "Imagini ePiesa actualizate: {$updated}\n";

echo "Produse în catalog: {$total}\n";


