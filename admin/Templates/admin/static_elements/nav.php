<?php

use Besoiu\Core\Comenzi\ComenziModel;
use Besoiu\Core\Module\ModuleGate;
use Besoiu\Services\WebsiteService;
use Besoiu\Services\ComunicareHubService;

require_once dirname(__DIR__, 4) . '/system/site-admin-form.php';

$__besoiuNavNewOrders = 0;
$__besoiuNavAbandonedCarts = 0;
$__besoiuNavUnreadMessages = 0;
if (!empty($_SESSION['user_id'])) {
    if (ModuleGate::slugAllowed('orders') || ModuleGate::slugAllowed('comenzi')) {
        try {
            $__besoiuNavNewOrders = (int) ((new ComenziModel())->getDashboardStats()['new_orders'] ?? 0);
        } catch (Throwable) {
            $__besoiuNavNewOrders = 0;
        }
    }
    if (ModuleGate::slugAllowed('abandoned-carts') && class_exists(\Besoiu\Services\CartAbandonmentService::class)) {
        try {
            $__besoiuNavAbandonedCarts = \Besoiu\Services\CartAbandonmentService::countOpen();
        } catch (Throwable) {
            $__besoiuNavAbandonedCarts = 0;
        }
    }
    if (ModuleGate::slugAllowed('comunicare') && class_exists(ComunicareHubService::class)) {
        try {
            $__besoiuNavUnreadMessages = (new ComunicareHubService())->countUnreadMessages();
        } catch (Throwable) {
            $__besoiuNavUnreadMessages = 0;
        }
    }
}
$__besoiuNavSitePages = [];
if (!empty($_SESSION['user_id']) && ModuleGate::slugAllowed('website')) {
    try {
        $__besoiuNavSitePages = (new WebsiteService())->getAll();
    } catch (Throwable) {
        $__besoiuNavSitePages = [];
    }
}
?>
<div class="side-menu__nav w-full h-full z-20 px-4 overflow-y-auto overflow-x-hidden pb-3 [&:-webkit-scrollbar]:w-0 scroll-smooth [&_.simplebar-scrollbar]:before:!bg-background/70 [-webkit-mask-image:_linear-gradient(to_top,_rgba(0,_0,_0,_0),_black_30px),_linear-gradient(to_bottom,_rgba(0,_0,_0,_0),_black_30px)] [-webkit-mask-composite:_destination-in]">
    <ul class="scrollable">

        <?php
        use Besoiu\Services\AdminNavRegistryService;
        $__navDbRegistry = new AdminNavRegistryService();
        if ($__navDbRegistry->isReady()) {
            $__navDbRegistry->ensureBootstrapped(false);
            require __DIR__ . '/nav_renderer.php';
        } else {
            require __DIR__ . '/nav_legacy.php';
        }
        unset($__navDbRegistry);
        ?>

    </ul>
</div>
<?php
use Besoiu\Core\Auth\AdminPermissionCatalog;
use Besoiu\Core\Auth\AdminWorkspace;
use Besoiu\Core\Auth\AdminWorkspaceCatalog;

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
    // Filtrarea pe workspace se face acum pe server (AdminNavJsonRegistry::buildTree).
    'serverFiltered' => true,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="<?= htmlspecialchars(\Besoiu\Core\AdminUrl::publicAsset('js/admin-workspace-nav.js'), ENT_QUOTES, 'UTF-8') ?>?v=20260714-nav-db" defer></script>
<?php endif;

if (!empty($_SESSION['user_id'])):
?>
<script src="<?= htmlspecialchars(\Besoiu\Core\AdminUrl::publicAsset('js/admin-nav-personalize.js'), ENT_QUOTES, 'UTF-8') ?>?v=20260714-nav-pref" defer></script>
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
<script src="<?= htmlspecialchars(\Besoiu\Core\AdminUrl::publicAsset('js/admin-nav-permissions.js'), ENT_QUOTES, 'UTF-8') ?>?v=20260619b" defer></script>
<?php endif; ?>
