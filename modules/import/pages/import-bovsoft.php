<?php
declare(strict_types=1);

use Besoiu\Modules\Import\BesoiupieseimportBridge;

$bridge = BesoiupieseimportBridge::status();
$bovsoftUrl = $bridge['urls']['bovsoft'] ?? '';
$localUrl = '/admin/public/bovsoft-import/Procesare%20fisier%20import%20Base.html';
$embedUrl = $bridge['available'] ? $bovsoftUrl : $localUrl;
$css = '/admin/modules/Import/assets/import-module.css';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>">

<div class="import-mod import-mod--wide">
    <header class="import-mod__hero">
        <div>
            <a href="/admin/import-system" class="import-mod__back">← Hub Import</a>
            <h1 class="text-xl font-semibold mt-2">Procesare Base TecDoc (Bovsoft)</h1>
            <p class="text-sm opacity-80 mt-1">
                Tool complet de import: upload Base CSV, liste preț furnizori, aplicare reguli 10 pași,
                export XLSX/CSV cu imagini și prețuri calculate.
            </p>
        </div>
    </header>

    <?php if (!$bridge['available']): ?>
        <div class="import-mod__alert">
            besoiupieseimport indisponibil — se folosește copia locală din <code>admin/public/bovsoft-import/</code>.
            Actualizează calea în <code>admin/config/besoiupieseimport_sync.php</code>.
        </div>
    <?php endif; ?>

    <div class="import-mod__iframe-wrap">
        <iframe
            id="bovsoft-iframe"
            src="<?= htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8') ?>"
            title="Procesare Base TecDoc"
            class="import-mod__iframe"
            loading="lazy"
        ></iframe>
    </div>

    <div class="import-mod__iframe-actions">
        <a href="<?= htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="import-mod__btn import-mod__btn--ghost">
            Deschide în tab nou
        </a>
        <a href="/admin/import-cards" class="import-mod__btn">Vizualizare produse cu filtrare →</a>
    </div>
</div>
