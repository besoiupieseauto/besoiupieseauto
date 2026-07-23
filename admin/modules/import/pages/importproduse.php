<?php
declare(strict_types=1);

$legacy = dirname(__DIR__, 3) . '/Templates/admin/pages/import/import.php';
if (is_file($legacy)) {
    require $legacy;
    return;
}
echo '<div class="box p-6"><p>Import produse / importproduse — template legacy lipsă. Hub: <a href="/admin/import-system">/admin/import-system</a></p></div>';
