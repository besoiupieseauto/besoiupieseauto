<?php
declare(strict_types=1);

use Evasystem\Controllers\Website\WebsiteService;

require_once dirname(__DIR__, 5) . '/system/site-defaults.php';
require_once dirname(__DIR__, 5) . '/system/site-admin-form.php';
require_once dirname(__DIR__, 5) . '/system/site-live-cms.php';

function ws_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$service = new WebsiteService();
$pages = $service->getAll();
$view = trim((string) ($_GET['view'] ?? ''));
$activeSlug = trim((string) ($_GET['tab'] ?? ''));

if ($view === 'pages') {
    require __DIR__ . '/website-manage.php';
    return;
}

if ($activeSlug === '') {
    $activeSlug = 'home';
}

$activePage = null;

foreach ($pages as $page) {
    if ((string) ($page['slug'] ?? '') === $activeSlug) {
        $activePage = $page;
        break;
    }
}
if ($activePage === null && $pages !== []) {
    $activePage = $pages[0];
    $activeSlug = (string) ($activePage['slug'] ?? '');
}

$formPage = $activePage ? site_admin_merge_page_for_form($activePage) : [];
$profile = site_admin_page_profile($activeSlug);
$globalBlocks = $activeSlug === 'global' ? site_admin_parsed_blocks($formPage, 'global') : [];
$liveRegistry = site_live_pages_registry();
$canLiveEdit = $activeSlug !== '' && !empty($liveRegistry[$activeSlug]['live']);
$liveMeta = $liveRegistry[$activeSlug] ?? null;
$viewMode = trim((string) ($_GET['mode'] ?? ''));

if ($viewMode === '') {
    if ($activeSlug === 'global') {
        $viewMode = 'form';
    } elseif ($canLiveEdit) {
        $viewMode = 'live';
    } else {
        $viewMode = 'form';
    }
}

if ($viewMode === 'live' && $canLiveEdit && $liveMeta !== null):
    $frameSrc = site_live_frame_url($activeSlug);
?>
<div class="-mt-5">
    <style>
        .ws-live-wrap { display: flex; flex-direction: column; gap: .75rem; min-height: calc(100vh - 200px); }
        .ws-live-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .75rem 1rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; }
        .ws-live-frame-wrap { flex: 1; min-height: 520px; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #f8fafc; position: relative; }
        .ws-live-frame { width: 100%; height: 100%; min-height: 520px; border: 0; display: block; background: #fff; }
        .ws-live-frame-error { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem; padding: 2rem; text-align: center; background: #f8fafc; }
        .ws-live-frame-error[hidden] { display: none !important; }
        .ws-live-status { margin-left: auto; font-size: .85rem; color: #64748b; }
        .ws-live-status.is-ok { color: #16a34a; }
    </style>
    <h2 class="mt-10 text-lg font-medium">Constructor site — <?= ws_h($liveMeta['label'] ?? $activeSlug) ?></h2>
    <div class="ws-live-wrap">
        <div class="ws-live-toolbar">
            <select class="h-9 rounded-md border bg-background px-2 text-sm" id="wsPageSelect" style="max-width:240px">
                <?php foreach ($pages as $page): ?>
                    <?php $slug = (string) ($page['slug'] ?? ''); ?>
                    <?php if (empty($liveRegistry[$slug]['live'])) continue; ?>
                    <option value="<?= ws_h($slug) ?>"<?= $slug === $activeSlug ? ' selected' : '' ?>><?= ws_h(site_page_display_label($slug, (string) ($page['label'] ?? $slug))) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-[#1abc9c] px-4 py-1.5 text-sm font-medium text-white" id="wsSaveTrigger">
                <i data-lucide="save" class="h-4 w-4"></i> Salvează
            </button>
            <a class="inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm hover:bg-muted" href="<?= ws_h($frameSrc) ?>" target="_blank" rel="noopener">
                <i data-lucide="external-link" class="h-4 w-4"></i> Tab nou
            </a>
            <a class="inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm hover:bg-muted" href="/admin/website?tab=<?= ws_h(urlencode($activeSlug)) ?>&amp;mode=form">
                <i data-lucide="settings" class="h-4 w-4"></i> Setări SEO
            </a>
            <a class="inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm hover:bg-muted" href="/admin/website?view=pages">
                <i data-lucide="files" class="h-4 w-4"></i> Gestionează pagini
            </a>
            <span class="ws-live-status" id="wsLiveStatus">Click pe texte · panou dreapta = blocuri</span>
        </div>
        <div class="ws-live-frame-wrap">
            <iframe id="wsLiveFrame" class="ws-live-frame" title="Editor live <?= ws_h($activeSlug) ?>" src="<?= ws_h($frameSrc) ?>"></iframe>
            <div id="wsFrameError" class="ws-live-frame-error" hidden>
                <p><strong>Previzualizarea nu s-a încărcat în iframe.</strong></p>
                <p class="text-sm opacity-80">Deschide pagina într-un tab nou, editează acolo și salvează cu Ctrl+S.</p>
                <a class="inline-flex items-center gap-2 rounded-lg bg-[#1abc9c] px-4 py-2 text-sm font-medium text-white" href="<?= ws_h($frameSrc) ?>" target="_blank" rel="noopener">Deschide editorul în tab nou</a>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const frame = document.getElementById('wsLiveFrame');
    const statusEl = document.getElementById('wsLiveStatus');
    const selectEl = document.getElementById('wsPageSelect');
    const saveBtn = document.getElementById('wsSaveTrigger');
    const frameError = document.getElementById('wsFrameError');

    function checkFrameLoaded() {
        if (!frame || !frameError) return;
        let ok = false;
        let blockedTest = false;
        try {
            const doc = frame.contentDocument;
            const winLoc = frame.contentWindow?.location?.href || '';
            if (winLoc.includes('besoiupieseauto.ro.test')) {
                blockedTest = true;
            }
            ok = !!(doc && doc.body && doc.body.innerHTML.trim() !== '');
        } catch (e) {
            ok = false;
        }
        if (!ok) {
            frame.style.display = 'none';
            frameError.hidden = false;
            if (statusEl) {
                statusEl.textContent = blockedTest
                    ? 'Redirect greșit către .test — actualizează fișierele pe server.'
                    : 'Iframe blocat — folosește tab nou.';
                statusEl.className = 'ws-live-status';
            }
        }
    }

    frame?.addEventListener('load', function () {
        window.setTimeout(checkFrameLoaded, 400);
    });
    window.setTimeout(checkFrameLoaded, 2500);

    selectEl?.addEventListener('change', function () {
        window.location.href = '/admin/website?tab=' + encodeURIComponent(selectEl.value);
    });

    saveBtn?.addEventListener('click', function () {
        try {
            frame?.contentWindow?.document.getElementById('bpaCmsSave')?.click();
        } catch (e) {
            if (statusEl) statusEl.textContent = 'Salvează din bara paginii.';
        }
    });

    window.addEventListener('message', function (e) {
        if (!e.data || e.data.type !== 'bpaCmsSaved') return;
        if (statusEl) {
            statusEl.textContent = 'Conținut salvat.';
            statusEl.className = 'ws-live-status is-ok';
        }
    });
})();
</script>
<?php return; endif; ?>

<?php if ($viewMode !== 'form'): ?>
<?php
    $firstLive = 'home';
    foreach ($pages as $page) {
        $s = (string) ($page['slug'] ?? '');
        if (!empty($liveRegistry[$s]['live'])) {
            $firstLive = $s;
            break;
        }
    }
    header('Location: /admin/website?tab=' . rawurlencode($firstLive));
    exit;
endif; ?>

<div class="-mt-5">
    <div>
        <div class="mt-10 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-medium">Setări avansate — <?= ws_h($profile['page_name'] ?? $activeSlug) ?></h2>
                <p class="mt-1 text-sm text-foreground/60">SEO și JSON. Pentru editare vizuală folosește constructorul live.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($canLiveEdit): ?>
                <a href="/admin/website?tab=<?= ws_h(urlencode($activeSlug)) ?>" class="inline-flex items-center gap-2 rounded-lg bg-[#1abc9c] px-4 py-2 text-sm font-medium text-white">
                    <i data-lucide="layout-template" class="h-4 w-4"></i> Înapoi la editor live
                </a>
                <?php endif; ?>
                <a href="/admin/website?view=pages" class="inline-flex items-center gap-1 rounded-lg border px-4 py-2 text-sm hover:bg-muted">
                    <i data-lucide="files" class="h-4 w-4"></i> Gestionează pagini
                </a>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-12 gap-x-6 gap-y-4">
            <div class="col-span-12 overflow-x-auto">
                <div style="display:flex;gap:0;border-bottom:2px solid #e5e7eb;flex-wrap:wrap;">
                    <?php foreach ($pages as $page): ?>
                        <?php $slug = (string) ($page['slug'] ?? ''); ?>
                        <?php
                        $tabHref = empty($liveRegistry[$slug]['live'])
                            ? '/admin/website?tab=' . rawurlencode($slug) . '&mode=form'
                            : '/admin/website?tab=' . rawurlencode($slug) . '&mode=form';
                        ?>
                        <a href="<?= ws_h($tabHref) ?>"
                           class="site-tab<?= $slug === $activeSlug ? ' active' : '' ?>"
                           style="padding:10px 16px;font-size:13px;font-weight:600;text-decoration:none;border-bottom:3px solid <?= $slug === $activeSlug ? '#2563eb' : 'transparent' ?>;color:<?= $slug === $activeSlug ? '#2563eb' : '#6b7280' ?>;margin-bottom:-2px;white-space:nowrap;">
                            <?= ws_h($page['label'] ?? $slug) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($activePage): ?>
            <div class="col-span-12 mt-4">
                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <strong><?= ws_h($profile['page_name'] ?? $activeSlug) ?></strong><br>
                    <?= ws_h($profile['intro'] ?? '') ?>
                </div>

                <form id="website-page-form" class="box p-5 grid grid-cols-12 gap-4" data-page-slug="<?= ws_h($activeSlug) ?>">
                    <input type="hidden" name="id" value="<?= ws_h((string) ($formPage['id'] ?? '')) ?>">

                    <?php if (!($profile['show_banner'] ?? false)): ?>
                        <input type="hidden" name="hero_label" value="">
                        <input type="hidden" name="hero_title" value="">
                        <input type="hidden" name="hero_subtitle" value="">
                    <?php endif; ?>
                    <?php if (!($profile['show_body'] ?? false)): ?>
                        <input type="hidden" name="body_html" value="">
                    <?php endif; ?>
                    <?php if (!($profile['show_faq'] ?? false)): ?>
                        <input type="hidden" name="faq_json" value="<?= ws_h($formPage['faq_json'] ?? '') ?>">
                    <?php endif; ?>
                    <?php if (!($profile['show_cta'] ?? false)): ?>
                        <input type="hidden" name="cta_json" value="<?= ws_h($formPage['cta_json'] ?? '') ?>">
                    <?php endif; ?>

                    <div class="col-span-12 border-b pb-4">
                        <h3 class="mb-3 text-sm font-bold uppercase tracking-wide opacity-70">SEO & identificare</h3>
                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12 md:col-span-6">
                                <label class="mb-1 block text-sm font-medium">Titlu pagină (tab browser / Google)</label>
                                <input type="text" name="title" value="<?= ws_h($formPage['title'] ?? '') ?>" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
                            </div>
                            <div class="col-span-12 md:col-span-6">
                                <label class="mb-1 block text-sm font-medium">Adresă internă (slug)</label>
                                <input type="text" value="<?= ws_h($formPage['slug'] ?? '') ?>" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm opacity-70" readonly>
                            </div>
                            <div class="col-span-12">
                                <label class="mb-1 block text-sm font-medium">Descriere scurtă Google (meta description)</label>
                                <textarea name="meta_description" rows="2" class="w-full rounded-md border bg-background px-3 py-2 text-sm"><?= ws_h($formPage['meta_description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <?php if (($profile['structured'] ?? null) === 'global'): ?>
                    <div class="col-span-12 border-b pb-4">
                        <h3 class="mb-1 text-sm font-bold uppercase tracking-wide opacity-70">Banda verde de sus (topbar)</h3>
                        <p class="mb-3 text-xs opacity-60">Cele 3 mesaje mici de deasupra header-ului pe fiecare pagină.</p>
                        <div class="grid grid-cols-12 gap-3">
                            <?php foreach (($globalBlocks['topbar'] ?? []) as $i => $item): ?>
                            <div class="col-span-12 md:col-span-4">
                                <label class="mb-1 block text-xs font-medium">Mesaj <?= (int) $i + 1 ?></label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="topbar.<?= (int) $i ?>.text"
                                       value="<?= ws_h($item['text'] ?? '') ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-span-12 border-b pb-4">
                        <h3 class="mb-1 text-sm font-bold uppercase tracking-wide opacity-70">Header — căutare & telefon</h3>
                        <p class="mb-3 text-xs opacity-60">Bara principală cu logo, căutare și număr de telefon.</p>
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12 md:col-span-6">
                                <label class="mb-1 block text-sm font-medium">Placeholder căutare</label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="header.search_placeholder"
                                       value="<?= ws_h($globalBlocks['header']['search_placeholder'] ?? '') ?>">
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <label class="mb-1 block text-sm font-medium">Text buton căutare</label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="header.search_button"
                                       value="<?= ws_h($globalBlocks['header']['search_button'] ?? '') ?>">
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <label class="mb-1 block text-sm font-medium">Telefon afișat</label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="header.phone"
                                       value="<?= ws_h($globalBlocks['header']['phone'] ?? '') ?>">
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="mb-1 block text-sm font-medium">Sub telefon (ex: Sună acum)</label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="header.phone_label"
                                       value="<?= ws_h($globalBlocks['header']['phone_label'] ?? '') ?>">
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="mb-1 block text-sm font-medium">Etichetă coș</label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="header.cart_label"
                                       value="<?= ws_h($globalBlocks['header']['cart_label'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 border-b pb-4">
                        <h3 class="mb-1 text-sm font-bold uppercase tracking-wide opacity-70">Footer (subsol site)</h3>
                        <p class="mb-3 text-xs opacity-60">Textul din partea de jos a fiecărei pagini.</p>
                        <div class="grid grid-cols-12 gap-3">
                            <div class="col-span-12">
                                <label class="mb-1 block text-sm font-medium">Descriere scurtă lângă logo</label>
                                <textarea class="global-field w-full rounded-md border bg-background px-3 py-2 text-sm" rows="2"
                                          data-global-path="footer.description"><?= ws_h($globalBlocks['footer']['description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="mb-1 block text-sm font-medium">Telefon footer</label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="footer.phone"
                                       value="<?= ws_h($globalBlocks['footer']['phone'] ?? '') ?>">
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="mb-1 block text-sm font-medium">Email footer</label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="footer.email"
                                       value="<?= ws_h($globalBlocks['footer']['email'] ?? '') ?>">
                            </div>
                            <div class="col-span-12 md:col-span-4">
                                <label class="mb-1 block text-sm font-medium">Adresă footer</label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="footer.address"
                                       value="<?= ws_h($globalBlocks['footer']['address'] ?? '') ?>">
                            </div>
                            <div class="col-span-12 md:col-span-6">
                                <label class="mb-1 block text-sm font-medium">Copyright (fără © și an)</label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="footer.copyright"
                                       value="<?= ws_h($globalBlocks['footer']['copyright'] ?? '') ?>">
                            </div>
                            <div class="col-span-12 md:col-span-6">
                                <label class="mb-1 block text-sm font-medium">Tagline dreapta jos</label>
                                <input type="text" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="footer.tagline"
                                       value="<?= ws_h($globalBlocks['footer']['tagline'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 border-b pb-4" id="website-footer-social-admin">
                        <h3 class="mb-1 text-sm font-bold uppercase tracking-wide opacity-70">Footer — rețele sociale</h3>
                        <p class="mb-3 text-xs opacity-60">Link-urile iconițelor din subsol (Facebook, Instagram etc.). Lăsați gol pentru a ascunde rețeaua respectivă.</p>
                        <div class="grid grid-cols-12 gap-3">
                            <?php
                            $footerSocialItems = is_array($globalBlocks['footer']['social'] ?? null)
                                ? $globalBlocks['footer']['social']
                                : [];
                            foreach ($footerSocialItems as $socialIndex => $socialItem):
                                if (!is_array($socialItem)) {
                                    continue;
                                }
                                $socialLabel = trim((string) ($socialItem['label'] ?? 'Social'));
                                $socialIcon = trim((string) ($socialItem['icon'] ?? ''));
                                $socialHref = trim((string) ($socialItem['href'] ?? ''));
                                $idx = (int) $socialIndex;
                            ?>
                            <div class="col-span-12 md:col-span-6">
                                <label class="mb-1 block text-sm font-medium"><?= ws_h($socialLabel) ?> — URL</label>
                                <input type="url" class="global-field h-10 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                       data-global-path="footer.social.<?= $idx ?>.href"
                                       placeholder="https://"
                                       value="<?= ws_h($socialHref) ?>">
                                <input type="hidden" class="global-field"
                                       data-global-path="footer.social.<?= $idx ?>.label"
                                       value="<?= ws_h($socialLabel) ?>">
                                <input type="hidden" class="global-field"
                                       data-global-path="footer.social.<?= $idx ?>.icon"
                                       value="<?= ws_h($socialIcon) ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($profile['show_banner'] ?? false): ?>
                    <div class="col-span-12 border-b pb-4">
                        <h3 class="mb-1 text-sm font-bold uppercase tracking-wide opacity-70"><?= ws_h($profile['banner_heading'] ?? 'Banner pagină') ?></h3>
                        <?php if (!empty($profile['banner_hint'])): ?>
                        <p class="mb-3 text-xs opacity-60"><?= ws_h($profile['banner_hint']) ?></p>
                        <?php endif; ?>
                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12 md:col-span-4">
                                <label class="mb-1 block text-sm font-medium"><?= ws_h($profile['label_hero_label'] ?? 'Etichetă mică') ?></label>
                                <input type="text" name="hero_label" value="<?= ws_h($formPage['hero_label'] ?? '') ?>" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
                            </div>
                            <div class="col-span-12 md:col-span-8">
                                <label class="mb-1 block text-sm font-medium"><?= ws_h($profile['label_hero_title'] ?? 'Titlu banner') ?></label>
                                <textarea name="hero_title" rows="2" class="w-full rounded-md border bg-background px-3 py-2 text-sm" placeholder="Pentru 2 rânduri (ex: POVESTEA + NOASTRĂ) pune text pe linii separate"><?= ws_h($formPage['hero_title'] ?? '') ?></textarea>
                            </div>
                            <div class="col-span-12">
                                <label class="mb-1 block text-sm font-medium"><?= ws_h($profile['label_hero_subtitle'] ?? 'Text sub titlu') ?></label>
                                <textarea name="hero_subtitle" rows="2" class="w-full rounded-md border bg-background px-3 py-2 text-sm"><?= ws_h($formPage['hero_subtitle'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($profile['show_body'] ?? false): ?>
                    <div class="col-span-12 border-b pb-4">
                        <label class="mb-1 block text-sm font-medium"><?= ws_h($profile['body_label'] ?? 'Conținut HTML') ?></label>
                        <textarea name="body_html" rows="6" class="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"><?= ws_h($formPage['body_html'] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if ($profile['show_sections_json'] ?? true): ?>
                    <div class="col-span-12 border-b pb-4">
                        <details <?= !empty($profile['sections_json_collapsed']) ? '' : 'open' ?>>
                            <summary class="cursor-pointer text-sm font-bold uppercase tracking-wide opacity-70">
                                <?= ws_h($profile['sections_json_label'] ?? 'Secțiuni conținut (JSON)') ?>
                            </summary>
                            <p class="mb-2 mt-3 text-xs opacity-60">
                                <?php if ($activeSlug === 'home'): ?>
                                    Editează aici: beneficii hero, titluri categorii/produse/mărci, trust bar, secțiunea „De ce noi”.
                                <?php elseif ($activeSlug === 'global'): ?>
                                    Doar pentru utilizatori avansați. Modificările de mai sus sunt suficiente în mod normal.
                                <?php else: ?>
                                    Paragrafe, pași, carduri și alte blocuri structurate. Lăsați gol dacă folosiți doar bannerul de sus.
                                <?php endif; ?>
                            </p>
                            <textarea name="sections_json" rows="14" id="website-sections-json" class="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"><?= ws_h($formPage['sections_json'] ?? '') ?></textarea>
                        </details>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="sections_json" id="website-sections-json" value="<?= ws_h($formPage['sections_json'] ?? '') ?>">
                    <?php endif; ?>

                    <?php if ($profile['show_faq'] ?? false): ?>
                    <div class="col-span-12 md:col-span-6">
                        <label class="mb-1 block text-sm font-medium"><?= ws_h($profile['faq_label'] ?? 'Întrebări frecvente (JSON)') ?></label>
                        <p class="mb-2 text-xs opacity-60">Format: [{"q":"Întrebare","a":"Răspuns"}]</p>
                        <textarea name="faq_json" rows="10" class="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"><?= ws_h($formPage['faq_json'] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if ($profile['show_cta'] ?? false): ?>
                    <div class="col-span-12 md:col-span-6">
                        <label class="mb-1 block text-sm font-medium"><?= ws_h($profile['cta_label'] ?? 'Bloc apel acțiune (JSON)') ?></label>
                        <p class="mb-2 text-xs opacity-60">Format: {"title":"...","subtitle":"...","primary":{"label":"...","href":"..."}}</p>
                        <textarea name="cta_json" rows="10" class="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"><?= ws_h($formPage['cta_json'] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <div class="col-span-12 flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary/90">
                            <i data-lucide="save" class="h-4 w-4"></i> Salvează pagina
                        </button>
                        <span id="website-save-status" class="text-sm opacity-70"></span>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="col-span-12 box p-8 text-center opacity-70">
                Nu există pagini configurate. Rulează migrarea <code>008_create_web_site_cms.sql</code>.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('website-page-form');
    if (!form) return;

    function setNestedValue(obj, path, value) {
        const parts = path.split('.');
        let current = obj;
        for (let i = 0; i < parts.length - 1; i++) {
            const key = parts[i];
            const nextKey = parts[i + 1];
            const index = /^\d+$/.test(nextKey) ? parseInt(nextKey, 10) : null;
            if (!(key in current)) {
                current[key] = index !== null ? [] : {};
            }
            current = current[key];
        }
        current[parts[parts.length - 1]] = value;
    }

    function applyGlobalFieldsToJson() {
        const textarea = document.getElementById('website-sections-json');
        if (!textarea || form.dataset.pageSlug !== 'global') return;

        let data = {};
        try {
            data = JSON.parse(textarea.value || '{}');
        } catch (e) {
            data = {};
        }

        form.querySelectorAll('.global-field').forEach(function (input) {
            const path = input.getAttribute('data-global-path');
            if (!path) return;
            setNestedValue(data, path, input.value);
        });

        if (data.header && data.header.phone) {
            data.header.phone_href = normalizeTelHref(data.header.phone, data.header.phone_href);
        }
        if (data.footer && data.footer.phone) {
            data.footer.phone_href = normalizeTelHref(data.footer.phone, data.footer.phone_href);
        }
        if (data.why && data.why.phone) {
            data.why.phone_href = normalizeTelHref(data.why.phone, data.why.phone_href);
        }

        textarea.value = JSON.stringify(data, null, 2);
    }

    function normalizeTelHref(displayPhone, phoneHref) {
        var source = (phoneHref || displayPhone || '').trim();
        if (!source) return '';
        source = source.replace(/^tel:/i, '');
        var digits = source.replace(/\D+/g, '');
        if (!digits) return '';
        if (digits.indexOf('00') === 0) digits = digits.slice(2);
        if (digits.charAt(0) === '0') digits = '40' + digits.slice(1);
        else if (digits.length === 9 && digits.charAt(0) === '7') digits = '40' + digits;
        return 'tel:+' + digits;
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        applyGlobalFieldsToJson();

        const status = document.getElementById('website-save-status');
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.type_product = 'save';

        status.textContent = 'Se salvează...';
        try {
            const response = await fetch('/admin/crudwebsite', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            status.textContent = result.message || (result.success ? 'Salvat.' : 'Eroare.');
            status.style.color = result.success ? '#059669' : '#dc2626';
        } catch (error) {
            status.textContent = 'Eroare de rețea.';
            status.style.color = '#dc2626';
        }
    });
})();
</script>
