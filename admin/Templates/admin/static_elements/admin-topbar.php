<?php
/** @var string $breadcrumbSection */
/** @var string $breadcrumbTitle */
$breadcrumbSection = $breadcrumbSection ?? 'Admin';
$breadcrumbTitle = $breadcrumbTitle ?? 'Panou';
$accountName = $accountName ?? ($_SESSION['user_name'] ?? $_SESSION['user_login'] ?? 'Administrator');

use Besoiu\Core\AdminUrl;
use Besoiu\Core\Auth\AdminWorkspace;
use Besoiu\Core\Auth\AdminWorkspaceCatalog;

$apiLiveArm = ['armed' => false, 'minutes_left' => 0];
$apiLiveStrict = true;
if (!empty($_SESSION['user_id'])) {
    $guardFile = dirname(__DIR__, 4) . '/system/api_automation_guard.php';
    if (is_file($guardFile)) {
        require_once $guardFile;
        $apiLiveArm = besoiu_api_arm_state();
        $apiLiveStrict = besoiu_api_strict_manual();
    }
}

$wsCurrent = null;
$wsAllowed = [];
$wsAll = AdminWorkspaceCatalog::all();
$wsCurrentMeta = null;

if (!empty($_SESSION['user_id']) && class_exists(AdminWorkspace::class)) {
    $wsCurrent = AdminWorkspace::getCurrent();
    $wsAllowed = AdminWorkspace::allowedWorkspacesForSession();
    if ($wsCurrent !== null) {
        $wsCurrentMeta = AdminWorkspaceCatalog::get($wsCurrent);
    }
}

$wsSwitchBase = AdminUrl::path('workspace-switch');
$wsPortalUrl = AdminUrl::path('workspace') . '?force=1';
?>
<header class="top-bar group -mt-2 besoiu-topbar [&.scrolled]:sticky [&.scrolled]:inset-x-0 [&.scrolled]:top-0 [&.scrolled]:z-[999] [&.scrolled]:mt-0">
    <div class="besoiu-topbar__inner flex h-16 items-center gap-5">
        <div class="open-mobile-menu besoiu-topbar__menu-btn mr-auto flex size-9 cursor-pointer items-center justify-center rounded-xl border xl:hidden">
            <i data-lucide="menu" class="size-5 stroke-[1.5] [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
        </div>
        <nav class="besoiu-breadcrumb mr-auto hidden xl:flex" aria-label="Breadcrumb">
            <ol class="besoiu-breadcrumb__list">
                <li><a href="/admin/dashboard">Besoiu Admin</a></li>
                <li><span><?= htmlspecialchars($breadcrumbSection, ENT_QUOTES, 'UTF-8') ?></span></li>
                <li aria-current="page"><strong><?= htmlspecialchars($breadcrumbTitle, ENT_QUOTES, 'UTF-8') ?></strong></li>
            </ol>
        </nav>
        <div class="besoiu-topbar__title-mobile xl:hidden font-bold text-sm truncate">
            <?= htmlspecialchars($breadcrumbTitle, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="ml-auto flex items-center gap-3">
            <?php if ($wsCurrent !== null && $wsCurrentMeta !== null && count($wsAllowed) > 0): ?>
            <div class="besoiu-ws-switch" data-besoiu-ws-switch>
                <?php if (count($wsAllowed) === 1): ?>
                <span class="besoiu-topbar__workspace besoiu-ws-switch__label-only" title="Departament activ">
                    <i data-lucide="layers"></i>
                    <span><?= htmlspecialchars((string) ($wsCurrentMeta['label'] ?? $wsCurrent), ENT_QUOTES, 'UTF-8') ?></span>
                </span>
                <?php else: ?>
                <button
                    type="button"
                    class="besoiu-topbar__workspace besoiu-ws-switch__trigger"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-label="Schimbă departamentul"
                >
                    <i data-lucide="layers"></i>
                    <span class="besoiu-ws-switch__trigger-text"><?= htmlspecialchars((string) ($wsCurrentMeta['label'] ?? $wsCurrent), ENT_QUOTES, 'UTF-8') ?></span>
                    <i data-lucide="chevron-down" class="besoiu-ws-switch__chev"></i>
                </button>
                <div class="besoiu-ws-switch__menu" role="listbox" hidden>
                    <p class="besoiu-ws-switch__menu-title">Schimbă departamentul</p>
                    <?php foreach ($wsAllowed as $wsId):
                        $meta = $wsAll[$wsId] ?? null;
                        if ($meta === null) {
                            continue;
                        }
                        $label = htmlspecialchars((string) ($meta['label'] ?? $wsId), ENT_QUOTES, 'UTF-8');
                        $accent = htmlspecialchars((string) ($meta['accent'] ?? '#1abc9c'), ENT_QUOTES, 'UTF-8');
                        $isActive = $wsId === $wsCurrent;
                        $href = htmlspecialchars($wsSwitchBase . '?to=' . rawurlencode($wsId), ENT_QUOTES, 'UTF-8');
                    ?>
                    <a
                        href="<?= $href ?>"
                        class="besoiu-ws-switch__item<?= $isActive ? ' is-active' : '' ?>"
                        role="option"
                        aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                        style="--ws-a: <?= $accent ?>"
                    >
                        <span class="besoiu-ws-switch__dot" aria-hidden="true"></span>
                        <span class="besoiu-ws-switch__item-label"><?= $label ?></span>
                        <?php if ($isActive): ?>
                        <i data-lucide="check" class="besoiu-ws-switch__check"></i>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    <a href="<?= htmlspecialchars($wsPortalUrl, ENT_QUOTES, 'UTF-8') ?>" class="besoiu-ws-switch__portal">
                        <i data-lucide="layout-grid"></i>
                        Vezi portalul departamente
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['user_id']) && $apiLiveStrict): ?>
            <div
                id="bpa-api-live-arm"
                class="bpa-api-live<?= !empty($apiLiveArm['armed']) ? ' is-armed' : ' is-disarmed' ?>"
                data-api="<?= htmlspecialchars(AdminUrl::api('settings_endpoint.php'), ENT_QUOTES, 'UTF-8') ?>"
                data-csrf="<?= htmlspecialchars(\Besoiu\Core\Auth\AdminCsrf::token(), ENT_QUOTES, 'UTF-8') ?>"
                data-armed="<?= !empty($apiLiveArm['armed']) ? '1' : '0' ?>"
                data-minutes-left="<?= (int) ($apiLiveArm['minutes_left'] ?? 0) ?>"
                data-permanent="<?= !empty($apiLiveArm['permanent']) ? '1' : '0' ?>"
                title="Control consum API plătit — temporar, mereu sau oprit complet"
            >
                <span id="bpa-api-live-status" class="bpa-api-live__status"><?php
                    if (!empty($apiLiveArm['armed'])) {
                        if (!empty($apiLiveArm['permanent'])) {
                            echo 'API mereu activ';
                        } else {
                            $ml = (int) ($apiLiveArm['minutes_left'] ?? 0);
                            echo $ml >= 60
                                ? ('API activ ~' . (int) round($ml / 60) . ' h')
                                : ('API activ ~' . $ml . ' min');
                        }
                    } else {
                        echo 'API oprit complet';
                    }
                ?></span>
                <select id="bpa-api-live-minutes" class="bpa-api-live__select" aria-label="Durată armare">
                    <option value="30" selected>30m</option>
                    <option value="240">4h</option>
                    <option value="0">Mereu</option>
                </select>
                <button type="button" id="bpa-api-live-arm-btn" class="bpa-api-live__btn bpa-api-live__btn--arm"<?= !empty($apiLiveArm['armed']) ? ' hidden' : '' ?>>Armez</button>
                <button type="button" id="bpa-api-live-disarm-btn" class="bpa-api-live__btn bpa-api-live__btn--disarm"<?= empty($apiLiveArm['armed']) ? ' hidden' : '' ?> title="Oprește complet apelurile API plătite">Oprit complet</button>
            </div>
            <?php endif; ?>
            <div class="besoiu-notif-wrap" id="besoiu-notif-wrap">
                <button type="button" class="besoiu-topbar__icon-btn besoiu-topbar__alerts" title="Notificări" id="besoiu-topbar-alerts-btn" aria-expanded="false" aria-controls="besoiu-notif-panel">
                    <i data-lucide="bell-ring" class="size-4 stroke-[1.5]"></i>
                    <span id="besoiu-alert-badge" class="besoiu-alert-badge hidden" aria-live="polite">0</span>
                </button>
                <div id="besoiu-notif-panel" class="besoiu-notif-panel hidden" role="dialog" aria-label="Notificări" hidden>
                    <div class="besoiu-notif-panel__head">
                        <strong>Notificări</strong>
                        <span id="besoiu-notif-count" class="besoiu-notif-panel__count">0</span>
                    </div>
                    <ul id="besoiu-notif-list" class="besoiu-notif-panel__list">
                        <li class="besoiu-notif-panel__empty">Se încarcă…</li>
                    </ul>
                    <div class="besoiu-notif-panel__foot">
                        <a href="/admin/alerts" class="besoiu-notif-panel__all">Vezi toate alertele →</a>
                        <a href="/admin/system-errors" class="besoiu-notif-panel__all besoiu-notif-panel__all--muted">Jurnal erori</a>
                    </div>
                </div>
            </div>
            <a href="/admin/settings" class="besoiu-topbar__icon-btn" title="Setări">
                <i data-lucide="settings" class="size-4 stroke-[1.5]"></i>
            </a>
            <button type="button" class="besoiu-topbar__icon-btn besoiu-topbar__marker" data-bpa-dom-marker-toggle title="Marker DOM — click pe element pentru instrucțiuni" aria-pressed="false">
                <i data-lucide="crosshair" class="size-4 stroke-[1.5]"></i>
            </button>
            <div class="besoiu-topbar__user hidden sm:flex items-center gap-2 pl-3 border-l-2 border-[var(--b26-line)]">
                <span class="besoiu-topbar__user-name text-sm font-bold"><?= htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8') ?></span>
                <a href="/admin/logout" class="besoiu-topbar__icon-btn" title="Deconectare" data-admin-action="logout">
                    <i data-lucide="log-out" class="size-4 stroke-[1.5]"></i>
                </a>
            </div>
        </div>
    </div>
</header>

