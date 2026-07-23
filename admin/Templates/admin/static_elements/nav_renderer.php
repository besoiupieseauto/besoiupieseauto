<?php

declare(strict_types=1);

/**
 * Render meniu sidebar din registry JSON (app/Backend/storage/navigation/registry.json).
 *
 * @var int $__besoiuNavNewOrders
 * @var int $__besoiuNavAbandonedCarts
 * @var int $__besoiuNavUnreadMessages
 * @var list<array<string, mixed>> $__besoiuNavSitePages
 */

use Besoiu\Core\AdminUrl;
use Besoiu\Core\Auth\AdminSectionDashboardCatalog;
use Besoiu\Services\AdminNavRegistryService;

$__navRegistry = new AdminNavRegistryService();
$__navTree = $__navRegistry->listTreeForRender([
    'new_orders' => (int) ($__besoiuNavNewOrders ?? 0),
    'abandoned_carts' => (int) ($__besoiuNavAbandonedCarts ?? 0),
    'unread_messages' => (int) ($__besoiuNavUnreadMessages ?? 0),
]);

$__navLastGroupLabel = null;
foreach ($__navTree as $__navBlock):
    $__section = $__navBlock['section'];
    $__items = $__navBlock['items'];
    if ($__items === []) {
        continue;
    }
    $__besoiuSection = (string) ($__section['besoiu_section'] ?? 'sistem');
    $__groupLabel = trim((string) ($__section['group_label'] ?? ''));

    if ($__groupLabel !== '' && $__groupLabel !== $__navLastGroupLabel):
        $__navLastGroupLabel = $__groupLabel;
?>
        <li class="side-menu__group-label<?= $__besoiuSection === 'comunicare' ? ' bpa-com-nav-label' : '' ?>">
            <?= htmlspecialchars($__groupLabel, ENT_QUOTES, 'UTF-8') ?>
        </li>
<?php
    endif;

    $__sectionKey = (string) ($__section['section_key'] ?? $__section['id'] ?? '');
    foreach ($__items as $__item):
        $__itemType = (string) ($__item['item_type'] ?? 'link');
        $__itemKey = (string) ($__item['item_key'] ?? '');
        $__itemId = (string) ($__item['id'] ?? ($__sectionKey . '::' . $__itemKey));
        $__modId = (string) ($__item['module_id'] ?? $__section['module_id'] ?? '');
        $__navPersonalizeAttr = ' data-nav-item-id="' . htmlspecialchars($__itemId, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-nav-section-key="' . htmlspecialchars($__sectionKey, ENT_QUOTES, 'UTF-8') . '"';

        if ($__itemType === 'submenu' && $__itemKey === 'website-submenu'):
?>
        <li data-besoiu-section="<?= htmlspecialchars($__besoiuSection, ENT_QUOTES, 'UTF-8') ?>"<?= $__navPersonalizeAttr ?>>
            <a href="javascript:void(0)" role="button" data-submenu-toggle="1" aria-expanded="false" class="side-menu__link">
                <i data-lucide="globe" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title"><?= htmlspecialchars((string) $__item['label'], ENT_QUOTES, 'UTF-8') ?></div>
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
                <?php if (\Besoiu\Core\Module\ModuleGate::enabled('blog')): ?>
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
                <?php endif; ?>
            </ul>
        </li>
<?php
            continue;
        endif;

        $__href = (string) ($__item['url'] ?? '#');
        if ($__itemKey === 'cron') {
            $__href = AdminUrl::navPath('cron');
        }

        // Item de modul: link direct la pagina reală a modulului (nu la dashboard-shell gol),
        // doar când registry-ul are încă ciotul spre /admin/dashboard.
        if ($__modId !== '' && str_contains($__href, '/admin/dashboard')) {
            $__moduleOptionalMap = \Besoiu\Core\Module\ModuleGate::optionalMap();
            $__moduleMeta = $__moduleOptionalMap[$__modId] ?? null;
            if (is_array($__moduleMeta)) {
                foreach (($__moduleMeta['paths'] ?? []) as $__modulerPath) {
                    $__modulerPath = (string) $__modulerPath;
                    if ($__modulerPath === ''
                        || str_contains($__modulerPath, '/public/')
                        || str_ends_with($__modulerPath, '.php')
                        || preg_match('#/(add|profile)#i', $__modulerPath) === 1
                    ) {
                        continue;
                    }
                    $__href = $__modulerPath;
                    break;
                }
            }
        }

        $__global = (int) ($__item['is_global'] ?? 0) === 1 ? ' data-besoiu-global-nav="1"' : '';
        $__target = (int) ($__item['open_new_tab'] ?? 0) === 1 ? ' target="_blank" rel="noopener"' : '';
        $__alertKey = trim((string) ($__item['alert_key'] ?? ''));
        $__alertAttr = $__alertKey !== '' ? ' data-besoiu-alert-nav="' . htmlspecialchars($__alertKey, ENT_QUOTES, 'UTF-8') . '"' : '';
        $__cronId = $__itemKey === 'cron' ? ' id="side-nav-cron-sync" data-admin-nav="cron"' : '';
        $__badge = (int) ($__item['badge_count'] ?? 0);

        // Link-ul „dashboard” al unei secțiuni afișează numele secțiunii (nu textul generic „Dashboard”),
        // altfel secțiunile fără group_label apar toate identice.
        $__linkLabel = (string) ($__item['label'] ?? '');
        $__linkIcon = (string) ($__item['icon'] ?? 'circle');
        if ($__itemKey === 'dashboard') {
            $__sectionLabel = trim((string) ($__section['label'] ?? ''));
            if ($__sectionLabel !== '') {
                $__linkLabel = $__sectionLabel;
            }
            if ($__sectionKey !== '' && AdminSectionDashboardCatalog::isValid($__sectionKey)) {
                $__sectionMeta = AdminSectionDashboardCatalog::meta($__sectionKey);
                if (!empty($__sectionMeta['icon'])) {
                    $__linkIcon = (string) $__sectionMeta['icon'];
                }
            }
        }
?>
        <li data-besoiu-section="<?= htmlspecialchars($__besoiuSection, ENT_QUOTES, 'UTF-8') ?>"<?= $__navPersonalizeAttr ?><?= $__modId !== '' ? ' data-besoiu-ext-module="' . htmlspecialchars($__modId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
            <a href="<?= htmlspecialchars($__href, ENT_QUOTES, 'UTF-8') ?>" class="side-menu__link"<?= $__global . $__target . $__alertAttr . $__cronId ?>>
                <i data-lucide="<?= htmlspecialchars($__linkIcon, ENT_QUOTES, 'UTF-8') ?>" class="side-menu__link__icon [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                <div class="side-menu__link__title"><?= htmlspecialchars($__linkLabel, ENT_QUOTES, 'UTF-8') ?></div>
                <?php if ($__badge > 0): ?>
                <div class="side-menu__link__badge"><?= (int) $__badge ?></div>
                <?php endif; ?>
                <?php if ($__alertKey !== ''): ?>
                <span class="besoiu-nav-alert-dot hidden" data-nav-alert="<?= htmlspecialchars($__alertKey, ENT_QUOTES, 'UTF-8') ?>"></span>
                <?php endif; ?>
            </a>
        </li>
<?php
    endforeach;
endforeach;

unset($__navRegistry, $__navTree, $__navBlock, $__section, $__items, $__item);
