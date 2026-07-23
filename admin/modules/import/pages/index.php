<?php
declare(strict_types=1);

use Besoiu\Core\AdminUrl;
use Besoiu\Modules\Import\BesoiupieseimportBridge;
use Besoiu\Modules\Import\ImportHubService;

$hub = new ImportHubService();
$data = $hub->dashboard();
$bridge = BesoiupieseimportBridge::status();
$apiUrl = AdminUrl::moduleApi('import', 'import_bridge_endpoint.php');
$css = '/admin/modules/Import/assets/import-module.css';
$js = '/admin/modules/Import/assets/import-module.js';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>">

<div class="import-mod" style="max-width:1100px">
    <header class="import-mod__hero">
        <div>
            <h1 class="text-xl font-semibold"><?= htmlspecialchars((string) $data['name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-sm opacity-80 mt-1"><?= htmlspecialchars($hub->healthLine(), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="import-mod__status <?= $bridge['available'] ? 'is-ok' : 'is-warn' ?>">
            <?php if ($bridge['available']): ?>
                <span class="import-mod__dot"></span> besoiupieseimport conectat
            <?php else ?>
                <span class="import-mod__dot is-warn"></span> besoiupieseimport indisponibil
            <?php endif; ?>
        </div>
    </header>

    <section class="import-mod__section">
        <h2 class="import-mod__section-title">Procesare & vizualizare</h2>
        <div class="import-mod__grid">
            <?php foreach ($data['features'] as $f): ?>
                <a class="import-mod__card" href="<?= htmlspecialchars((string) $f['href'], ENT_QUOTES, 'UTF-8') ?>">
                    <strong><?= htmlspecialchars((string) $f['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars((string) $f['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($bridge['available']): ?>
    <section class="import-mod__section">
        <h2 class="import-mod__section-title">Surse date (besoiupieseimport)</h2>
        <div class="import-mod__paths">
            <div class="import-mod__path-item <?= !empty($bridge['paths']['matc']) ? 'ok' : '' ?>">
                <span>Fisierile Matc</span>
                <code><?= htmlspecialchars($bridge['import_root'] . '/Fisierile Matc', ENT_QUOTES, 'UTF-8') ?></code>
            </div>
            <div class="import-mod__path-item <?= !empty($bridge['paths']['poze']) ? 'ok' : '' ?>">
                <span>Poze</span>
                <code><?= htmlspecialchars($bridge['import_root'] . '/Poze', ENT_QUOTES, 'UTF-8') ?></code>
            </div>
            <div class="import-mod__path-item <?= !empty($bridge['paths']['scraper']) ? 'ok' : '' ?>">
                <span>Scraper Hub</span>
                <a href="/admin/scraper-import" class="import-mod__link">Deschide în admin →</a>
            </div>
        </div>
        <div class="import-mod__actions mt-3">
            <button type="button" id="import-sync-feeds" class="import-mod__btn">Sincronizează feed-uri furnizori</button>
            <span id="import-sync-out" class="text-sm opacity-70"></span>
        </div>
    </section>
    <?php endif; ?>

    <p class="text-xs opacity-60 mt-4">API bridge: <code><?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?></code></p>
</div>

<script>
window.IMPORT_BRIDGE_API = <?= json_encode($apiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars($js, ENT_QUOTES, 'UTF-8') ?>"></script>
