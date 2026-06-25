<?php

use Evasystem\Core\Comenzi\ComenziModel;
use Evasystem\Controllers\Website\WebsiteService;
use Evasystem\Services\ComunicareHubService;

require_once dirname(__DIR__, 4) . '/system/site-admin-form.php';

$__besoiuNavNewOrders = 0;
$__besoiuNavAbandonedCarts = 0;
$__besoiuNavUnreadMessages = 0;
if (!empty($_SESSION['user_id'])) {
    try {
        $__besoiuNavNewOrders = (int) ((new ComenziModel())->getDashboardStats()['new_orders'] ?? 0);
    } catch (Throwable) {
        $__besoiuNavNewOrders = 0;
    }
    if (class_exists(\Evasystem\Services\CartAbandonmentService::class)) {
        try {
            $__besoiuNavAbandonedCarts = \Evasystem\Services\CartAbandonmentService::countOpen();
        } catch (Throwable) {
            $__besoiuNavAbandonedCarts = 0;
        }
    }
    if (class_exists(ComunicareHubService::class)) {
        try {
            $__besoiuNavUnreadMessages = (new ComunicareHubService())->countUnreadMessages();
        } catch (Throwable) {
            $__besoiuNavUnreadMessages = 0;
        }
    }
}
$__besoiuNavSitePages = [];
if (!empty($_SESSION['user_id'])) {
    try {
        $__besoiuNavSitePages = (new WebsiteService())->getAll();
    } catch (Throwable) {
        $__besoiuNavSitePages = [];
    }
}
?>
<div class="side-menu__nav w-full h-full z-20 px-4 overflow-y-auto overflow-x-hidden pb-3 [&:-webkit-scrollbar]:w-0 scroll-smooth [&_.simplebar-scrollbar]:before:!bg-background/70 [-webkit-mask-image:_linear-gradient(to_top,_rgba(0,_0,_0,_0),_black_30px),_linear-gradient(to_bottom,_rgba(0,_0,_0,_0),_black_30px)] [-webkit-mask-composite:_destination-in]">
    <ul class="scrollable">

        <li class="side-menu__group-label">
            ADMIN PANEL
        </li>

        <li data-besoiu-section="dashboard">
            <a href="/admin/dashboard" class="side-menu__link">
                <i data-lucide="layout-dashboard" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Dashboard</div>
            </a>
        </li>

        <li data-besoiu-section="furnizori">
            <a href="/admin/suppliers?tab=compare" class="side-menu__link">
                <i data-lucide="git-compare" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Comparare furnizori</div>
            </a>
        </li>

        <li data-besoiu-section="furnizori">
            <a href="/admin/suppliers" class="side-menu__link">
                <i data-lucide="truck" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Lista furnizori</div>
            </a>
        </li>

        <li data-besoiu-section="produse">
            <a href="/admin/product" class="side-menu__link">
                <i data-lucide="package" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Lista produse</div>
            </a>
        </li>

        <li data-besoiu-section="produse">
            <a href="/admin/vitrina" class="side-menu__link">
                <i data-lucide="layout-grid" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Vitrina homepage</div>
            </a>
        </li>

        <li data-besoiu-section="produse">
            <a href="/admin/scanned" class="side-menu__link">
                <i data-lucide="radar" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Produse scanate</div>
            </a>
        </li>

        <li data-besoiu-section="produse">
            <a href="/admin/addproduse" class="side-menu__link">
                <i data-lucide="plus-circle" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Adaugă produs</div>
            </a>
        </li>

        <li data-besoiu-section="produse">
            <a href="/admin/categorii" class="side-menu__link">
                <i data-lucide="folder-tree" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Categorii</div>
            </a>
        </li>

        <li data-besoiu-section="produse">
            <a href="/admin/adaoscomercial" class="side-menu__link">
                <i data-lucide="badge-percent" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Adaos comercial</div>
            </a>
        </li>

        <li data-besoiu-section="produse">
            <a href="/admin/import" class="side-menu__link">
                <i data-lucide="file-up" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Import CSV / Excel</div>
            </a>
        </li>

        <li data-besoiu-section="produse">
            <a href="/admin/importreview" class="side-menu__link">
                <i data-lucide="list-checks" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Coadă import</div>
            </a>
        </li>

        <li data-besoiu-section="comenzi">
            <a href="/admin/orders" class="side-menu__link">
                <i data-lucide="shopping-cart" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Comenzi</div>
                <?php if ($__besoiuNavNewOrders > 0): ?>
                <div class="side-menu__link__badge"><?= (int) $__besoiuNavNewOrders ?></div>
                <?php endif; ?>
            </a>
        </li>

        <li data-besoiu-section="comenzi">
            <a href="/admin/order-create" class="side-menu__link">
                <i data-lucide="plus-circle" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Comandă nouă</div>
            </a>
        </li>

        <li data-besoiu-section="comenzi">
            <a href="/admin/abandoned-carts" class="side-menu__link">
                <i data-lucide="shopping-bag" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Coș abandonat</div>
                <?php if ($__besoiuNavAbandonedCarts > 0): ?>
                <div class="side-menu__link__badge"><?= (int) $__besoiuNavAbandonedCarts ?></div>
                <?php endif; ?>
            </a>
        </li>

        <li data-besoiu-section="comenzi">
            <a href="/admin/supplier-search" class="side-menu__link">
                <i data-lucide="search-check" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Supplier Search</div>
            </a>
        </li>

        <li data-besoiu-section="comenzi">
            <a href="/admin/facturi" class="side-menu__link">
                <i data-lucide="receipt-text" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Facturi</div>
            </a>
        </li>

        <li data-besoiu-section="comenzi">
            <a href="/admin/livrare" class="side-menu__link">
                <i data-lucide="truck" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Livrare / AWB</div>
            </a>
        </li>

        <li data-besoiu-section="clienti">
            <a href="/admin/clienti" class="side-menu__link">
                <i data-lucide="users" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Clienți</div>
            </a>
        </li>

        <li class="side-menu__group-label bpa-com-nav-label">COMUNICARE &amp; SOCIALIZARE</li>

        <li data-besoiu-section="comunicare">
            <a href="/admin/comunicare" class="side-menu__link">
                <i data-lucide="radio" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Hub comunicare</div>
            </a>
        </li>

        <li data-besoiu-section="comunicare">
            <a href="/admin/messages" class="side-menu__link">
                <i data-lucide="messages-square" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Mesagerie / Chat</div>
                <?php if ($__besoiuNavUnreadMessages > 0): ?>
                <div class="side-menu__link__badge"><?= (int) $__besoiuNavUnreadMessages ?></div>
                <?php endif; ?>
            </a>
        </li>

        <li data-besoiu-section="comunicare">
            <a href="/admin/reply-templates" class="side-menu__link">
                <i data-lucide="file-text" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Template-uri răspuns</div>
            </a>
        </li>

        <li data-besoiu-section="comunicare">
            <a href="/admin/reply-templates?tab=quick" class="side-menu__link">
                <i data-lucide="zap" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Răspunsuri rapide</div>
            </a>
        </li>

        <li data-besoiu-section="comunicare">
            <a href="/admin/comunicare-canale" class="side-menu__link">
                <i data-lucide="share-2" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Canale comunicare</div>
            </a>
        </li>

        <li data-besoiu-section="comunicare">
            <a href="/admin/comunicare-leads" class="side-menu__link">
                <i data-lucide="user-plus" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Lead-uri contact</div>
            </a>
        </li>

        <li data-besoiu-section="comunicare">
            <a href="/admin/comunicare-broadcast" class="side-menu__link">
                <i data-lucide="megaphone" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Broadcast mesaje</div>
            </a>
        </li>

        <li data-besoiu-section="comunicare">
            <a href="/admin/comunicare-archive" class="side-menu__link">
                <i data-lucide="archive" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Arhivă conversații</div>
            </a>
        </li>

        <li class="side-menu__group-label">AUTOMATIZARE</li>

        <li data-besoiu-section="automatizare">
            <a href="/admin/bots" class="side-menu__link">
                <i data-lucide="bot" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Roboți AI</div>
            </a>
        </li>

        <li data-besoiu-section="automatizare">
            <a href="/admin/marketplace" class="side-menu__link">
                <i data-lucide="store" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Marketplace</div>
            </a>
        </li>

        <li data-besoiu-section="automatizare">
            <a href="/admin/export" class="side-menu__link">
                <i data-lucide="file-down" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Export</div>
            </a>
        </li>

        <li data-besoiu-section="automatizare">
            <a href="<?= htmlspecialchars(\Evasystem\Core\AdminUrl::navPath('cron'), ENT_QUOTES, 'UTF-8') ?>" class="side-menu__link" id="side-nav-cron-sync" data-admin-nav="cron">
                <i data-lucide="refresh-cw" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Cron Sync</div>
            </a>
        </li>

        <li class="side-menu__group-label">ANALIZĂ</li>

        <li data-besoiu-section="analiza">
            <a href="/admin/search-logs" class="side-menu__link">
                <i data-lucide="search-check" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Search Logs</div>
            </a>
        </li>

        <li data-besoiu-section="analiza">
            <a href="/admin/cross-reference" class="side-menu__link">
                <i data-lucide="git-compare" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Echivalențe OEM</div>
            </a>
        </li>

        <li data-besoiu-section="analiza">
            <a href="/admin/reports" class="side-menu__link">
                <i data-lucide="bar-chart-3" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Rapoarte</div>
            </a>
        </li>

        <li class="side-menu__group-label">WEB SITE</li>

        <li data-besoiu-section="website">
            <a href="javascript:void(0)" role="button" data-submenu-toggle="1" aria-expanded="false" class="side-menu__link">
                <i data-lucide="globe" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Web site</div>
                <i data-lucide="chevron-down" class="side-menu__link__chevron transition [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
            </a>
            <ul class="hidden" data-besoiu-open="0">
                <?php foreach ($__besoiuNavSitePages as $__navPage): ?>
                    <?php
                    $__navSlug = (string) ($__navPage['slug'] ?? '');
                    if ($__navSlug === '') {
                        continue;
                    }
                    $__navLabel = site_page_display_label($__navSlug, (string) ($__navPage['label'] ?? $__navSlug));
                    $__navActive = (int) ($__navPage['is_active'] ?? 1) === 1;
                    $__navHref = $__navSlug === 'global'
                        ? '/admin/website?tab=global&mode=form'
                        : '/admin/website?tab=' . rawurlencode($__navSlug);
                    ?>
                <li>
                    <a href="<?= htmlspecialchars($__navHref, ENT_QUOTES, 'UTF-8') ?>" class="side-menu__link<?= $__navActive ? '' : ' opacity-50' ?>">
                        <i data-lucide="file-text" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                        <div class="side-menu__link__title"><?= htmlspecialchars($__navLabel, ENT_QUOTES, 'UTF-8') ?></div>
                    </a>
                </li>
                <?php endforeach; ?>
                <li>
                    <a href="/admin/website?view=pages" class="side-menu__link">
                        <i data-lucide="folder-plus" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                        <div class="side-menu__link__title">Gestionează pagini</div>
                    </a>
                </li>
                <li>
                    <a href="/admin/blog" class="side-menu__link">
                        <i data-lucide="newspaper" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                        <div class="side-menu__link__title">Blog</div>
                    </a>
                </li>
                <li>
                    <a href="/admin/addblog" class="side-menu__link">
                        <i data-lucide="pen-line" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                        <div class="side-menu__link__title">Articol nou</div>
                    </a>
                </li>
            </ul>
        </li>

        <li class="side-menu__group-label">SISTEM</li>

        <li data-besoiu-section="utilizatori">
            <a href="/admin/users" class="side-menu__link">
                <i data-lucide="user-cog" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Utilizatori admin</div>
            </a>
        </li>

        <li data-besoiu-section="sistem">
            <a href="/admin/alerts" class="side-menu__link">
                <i data-lucide="bell-ring" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Alerte</div>
            </a>
        </li>

        <li data-besoiu-section="automatizare">
            <a href="/admin/scraper" class="side-menu__link">
                <i data-lucide="radar" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Scraper</div>
            </a>
        </li>

        <li data-besoiu-section="sistem">
            <a href="/admin/backup" class="side-menu__link">
                <i data-lucide="database-backup" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Backup</div>
            </a>
        </li>

        <li data-besoiu-section="sistem">
            <a href="/admin/settings" class="side-menu__link">
                <i data-lucide="settings" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title">Setări</div>
            </a>
        </li>

    </ul>
</div>
<?php
use Evasystem\Core\Auth\AdminPermissionCatalog;
use Evasystem\Core\Auth\AdminWorkspace;
use Evasystem\Core\Auth\AdminWorkspaceCatalog;

$__navRole = (string) ($_SESSION['role'] ?? 'guest');
$__navDelegated = !empty($_SESSION['admin_permissions_delegated']) && $__navRole !== 'super_ambassador';

$__wsCurrent = AdminWorkspace::getCurrent();
if ($__wsCurrent !== null && !empty($_SESSION['user_id'])):
    $__wsMeta = AdminWorkspaceCatalog::get($__wsCurrent);
?>
<script>
window.BESOIU_WORKSPACE_CTX = <?= json_encode([
    'workspace' => $__wsCurrent,
    'workspaceLabel' => $__wsMeta['label'] ?? $__wsCurrent,
    'pathToWorkspace' => AdminWorkspaceCatalog::navPathWorkspaceMap(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="<?= htmlspecialchars(\Evasystem\Core\AdminUrl::publicAsset('js/admin-workspace-nav.js'), ENT_QUOTES, 'UTF-8') ?>?v=20260624f" defer></script>
<?php endif;

if ($__navDelegated):
    $__navPerms = AdminPermissionCatalog::normalizePermissions($_SESSION['admin_permissions'] ?? null, $__navRole);
?>
<script>
window.BESOIU_NAV_CTX = <?= json_encode([
    'role' => $__navRole,
    'permissions' => $__navPerms,
    'sections' => AdminPermissionCatalog::sections(),
    'features' => AdminPermissionCatalog::allFeatures(),
    'modules' => AdminPermissionCatalog::modules(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="<?= htmlspecialchars(\Evasystem\Core\AdminUrl::publicAsset('js/admin-nav-permissions.js'), ENT_QUOTES, 'UTF-8') ?>?v=20260619b" defer></script>
<?php endif; ?>
