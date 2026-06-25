<?php
declare(strict_types=1);

use Evasystem\Controllers\Produse\ProduseService;

require_once __DIR__ . '/_produse-list-helpers.php';

$clientRoot = dirname(__DIR__, 5);
require_once $clientRoot . '/system/home-vitrina-render.php';

$vitrinaPreviewCssVersion = (string) max(
    (int) @filemtime($clientRoot . '/assets/css/product-cards.css'),
    (int) @filemtime($clientRoot . '/assets/css/home-scraper-products.css')
);

$service = new ProduseService();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$search = trim((string) ($_GET['q'] ?? ''));

$vitrinaCount = $service->countVitrinaProducts();
$vitrinaOnSite = $service->getVitrinaProducts(max(50, $vitrinaCount));

$vitrinaHomeMax = $service->vitrinaHomepageMax();
$homeVitrinaProducts = [];
foreach (array_slice($vitrinaOnSite, 0, $vitrinaHomeMax) as $row) {
    if (is_array($row)) {
        $homeVitrinaProducts[] = produse_vitrina_db_row_for_preview($row);
    }
}
$homeVitrinaCount = count($homeVitrinaProducts);

$paged = $service->getVitrinaAdminPickerPaginated($page, $perPage, $search);
$produse = $paged['items'] ?? [];
$total = (int) ($paged['total'] ?? 0);
$totalPages = max(1, (int) ($paged['total_pages'] ?? 1));
$currentPage = (int) ($paged['page'] ?? $page);
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$produseSectionActive = 'vitrina';
$produseNavVitrinaCount = $vitrinaCount;
$produseNavScraperCount = 0;

$buildVitrinaQuery = static function (array $overrides = []) use ($search): string {
    $params = [];
    if ($search !== '') {
        $params['q'] = $search;
    }
    $params = array_merge($params, $overrides);

    return http_build_query($params);
};
?>
<link rel="stylesheet" href="/assets/css/product-cards.css?v=<?= produse_list_h($vitrinaPreviewCssVersion) ?>">
<link rel="stylesheet" href="/assets/css/home-scraper-products.css?v=<?= produse_list_h($vitrinaPreviewCssVersion) ?>">

<div class="besoiu-page besoiu-produse-page besoiu-vitrina-page" id="besoiuVitrinaPage" data-page-title="Produse vetrină">
    <h2 class="sr-only">Produse vetrină — homepage</h2>

    <div class="besoiu-dash-hero">
        <div>
            <h1>Produse vetrină — homepage</h1>
            <p class="besoiu-dash-hero__meta mt-2">
                Produse din <strong>magazin</strong> bifate pe vitrină (grilă sub caruselul hero, max. <?= (int) $vitrinaHomeMax ?> afișate).
                Poți adăuga consumabile importate (ulei, lichide, electrice) din lista de mai jos.
            </p>
            <?php if ($vitrinaCount > $vitrinaHomeMax): ?>
                <p class="besoiu-dash-hero__meta mt-1" style="color:#b45309;">
                    Ai <?= (int) $vitrinaCount ?> produse pe vitrină — pe homepage apar doar cele <?= (int) $vitrinaHomeMax ?> cele mai recente.
                </p>
            <?php endif; ?>
        </div>
        <div class="besoiu-dash-hero__actions" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <span class="besoiu-vitrina-count-pill" id="vitrinaCountBadge">Pe vitrină: <?= (int) $vitrinaCount ?> / <?= (int) $vitrinaHomeMax ?></span>
            <?php if ($vitrinaCount > 0): ?>
                <button type="button" class="besoiu-btn-secondary besoiu-vitrina-clear-all-btn" id="vitrinaClearAllBtn" style="border-color:#fca5a5;color:#b91c1c;">
                    Golește vitrina (<?= (int) $vitrinaCount ?>)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php require __DIR__ . '/_produse-section-nav.php'; ?>

    <section class="besoiu-vitrina-preview-panel" aria-labelledby="vitrina-preview-heading">
        <div class="besoiu-vitrina-preview-panel__head">
            <h2 id="vitrina-preview-heading">Previzualizare homepage — Produse recomandate</h2>
            <p>Așa apar pe <strong>index.php</strong> (<?= (int) $homeVitrinaCount ?> din <?= (int) $vitrinaCount ?> pe vitrină).</p>
        </div>
        <?php if ($homeVitrinaProducts === []): ?>
            <div class="besoiu-produse-empty">
                Niciun produs pe vitrină. Bifează consumabile din magazin în tabelul de mai jos sau importă din
                <a href="/admin/import">Import consumabile</a>.
            </div>
        <?php else: ?>
            <div class="_product-grid besoiu-vitrina-home-grid" data-home-vitrina="1" role="list" aria-label="Produse recomandate pe homepage">
                <?php foreach ($homeVitrinaProducts as $product): ?>
                    <?php include __DIR__ . '/_produse-card-vitrina-preview.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($vitrinaOnSite !== []): ?>
    <section class="besoiu-produse-panel besoiu-vitrina-on-panel" style="margin-bottom:1.5rem;">
        <div class="besoiu-vitrina-picker-panel__head" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:flex-start;">
            <div>
                <h2>Toate produsele pe vitrină (<?= (int) $vitrinaCount ?>)</h2>
                <p>Produse active pe homepage — selectează și scoate în masă sau golește tot.</p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <button type="button" class="besoiu-btn-secondary besoiu-vitrina-bulk-btn" data-action="remove-active" id="vitrinaRemoveActiveBtn" disabled>
                    Scoate selectate de pe vitrină
                </button>
                <button type="button" class="besoiu-btn-secondary besoiu-vitrina-clear-all-btn" style="border-color:#fca5a5;color:#b91c1c;">
                    Golește toată vitrina
                </button>
            </div>
        </div>
        <div class="besoiu-vitrina-table-wrap">
            <table class="besoiu-vitrina-table" id="vitrinaActiveTable">
                <thead>
                    <tr>
                        <th class="besoiu-vitrina-table__check">
                            <label class="besoiu-vitrina-check-all">
                                <input type="checkbox" id="vitrinaSelectAllActive" aria-label="Selectează toate de pe vitrină">
                            </label>
                        </th>
                        <th>Produs</th>
                        <th>Categorie</th>
                        <th>Preț</th>
                        <th>Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($vitrinaOnSite as $product): ?>
                    <?php
                        $id = trim((string) ($product['randomn_id'] ?? $product['id'] ?? ''));
                        $image = produse_list_first_image($product);
                        $name = trim((string) ($product['pName'] ?? 'Produs'));
                        $catLabel = trim((string) ($product['pCategory'] ?? ''));
                        $price = trim((string) ($product['pPrice'] ?? ''));
                        if ($price !== '' && !str_contains($price, 'RON')) {
                            $price .= ' RON';
                        }
                    ?>
                    <tr data-product-id="<?= produse_list_h($id) ?>" data-on-vitrina="1">
                        <td class="besoiu-vitrina-table__check">
                            <input type="checkbox" class="vitrina-active-select" value="<?= produse_list_h($id) ?>" aria-label="Selectează produs">
                        </td>
                        <td>
                            <div class="besoiu-vitrina-table__product">
                                <?php if ($image !== ''): ?>
                                    <img src="<?= produse_list_h($image) ?>" alt="" loading="lazy">
                                <?php endif; ?>
                                <span><?= produse_list_h($name) ?></span>
                            </div>
                        </td>
                        <td><?= produse_list_h($catLabel !== '' ? $catLabel : '—') ?></td>
                        <td><?= produse_list_h($price) ?></td>
                        <td>
                            <button type="button" class="besoiu-btn-secondary besoiu-vitrina-preview-btn" data-action="vitrina-off" data-product-id="<?= produse_list_h($id) ?>" style="font-size:12px;padding:4px 10px;">Scoate</button>
                            <a href="/admin/editproduse?id=<?= produse_list_h($id) ?>" class="text-primary hover:underline" style="margin-left:8px;">Editează</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <div class="besoiu-produse-panel besoiu-vitrina-picker-panel">
        <div class="besoiu-vitrina-picker-panel__head">
            <h2>Adaugă consumabile din magazin</h2>
            <p>Consumabile eligibile (ulei, lichide, electrice) din catalogul live — bifează vitrina și badge <strong>RECOMANDAT</strong> (max. <?= (int) $vitrinaHomeMax ?> pe homepage, sub carusel).</p>
        </div>

        <div class="besoiu-produse-panel__toolbar besoiu-vitrina-picker-toolbar">
            <form method="get" action="/admin/vitrina" class="besoiu-toolbar besoiu-toolbar--inline">
                <div class="besoiu-toolbar__search">
                    <input type="text" name="q" value="<?= produse_list_h($search) ?>" placeholder="Caută după nume, cod, brand...">
                    <i data-lucide="search" class="size-4"></i>
                </div>
                <button type="submit" class="besoiu-btn-secondary">Caută</button>
                <span class="besoiu-counter"><?= (int) $total ?> consumabile eligibile în magazin</span>
            </form>
        </div>

        <div class="besoiu-vitrina-bulk-bar" id="vitrinaBulkBar" hidden>
            <span class="besoiu-vitrina-bulk-bar__count"><strong id="vitrinaSelectedCount">0</strong> selectate</span>
            <div class="besoiu-vitrina-bulk-bar__actions">
                <button type="button" class="besoiu-btn-secondary besoiu-vitrina-bulk-btn" data-action="add">Adaugă pe vitrină</button>
                <button type="button" class="besoiu-btn-secondary besoiu-vitrina-bulk-btn" data-action="remove">Scoate de pe vitrină</button>
                <button type="button" class="besoiu-btn-secondary besoiu-vitrina-bulk-btn" data-action="badge-on">Badge RECOMANDAT</button>
                <button type="button" class="besoiu-btn-secondary besoiu-vitrina-bulk-btn" data-action="badge-off">Elimină badge</button>
            </div>
        </div>

        <div class="besoiu-vitrina-table-wrap">
            <table class="besoiu-vitrina-table">
                <thead>
                    <tr>
                        <th class="besoiu-vitrina-table__check">
                            <label class="besoiu-vitrina-check-all">
                                <input type="checkbox" id="vitrinaSelectAll" aria-label="Selectează toate">
                            </label>
                        </th>
                        <th>Produs</th>
                        <th>Categorie</th>
                        <th>Preț</th>
                        <th>Vitrină</th>
                        <th>Badge</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($produse as $product): ?>
                    <?php
                        $id = trim((string) ($product['randomn_id'] ?? $product['id'] ?? ''));
                        $onVitrina = (int) ($product['pVitrina'] ?? 0) === 1;
                        $badge = trim((string) ($product['pBadge'] ?? ''));
                        $hasRecomandat = $badge === 'recomandat';
                        $image = produse_list_first_image($product);
                        $name = trim((string) ($product['pName'] ?? 'Produs'));
                        $catLabel = trim((string) ($product['pCategory'] ?? ''));
                        $price = trim((string) ($product['pPrice'] ?? ''));
                        if ($price !== '' && !str_contains($price, 'RON')) {
                            $price .= ' RON';
                        }
                    ?>
                    <tr data-product-id="<?= produse_list_h($id) ?>" data-on-vitrina="<?= $onVitrina ? '1' : '0' ?>">
                        <td class="besoiu-vitrina-table__check">
                            <input type="checkbox" class="vitrina-row-select" value="<?= produse_list_h($id) ?>" aria-label="Selectează produs">
                        </td>
                        <td>
                            <div class="besoiu-vitrina-table__product">
                                <?php if ($image !== ''): ?>
                                    <img src="<?= produse_list_h($image) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="besoiu-vitrina-table__noimg" aria-hidden="true">—</span>
                                <?php endif; ?>
                                <span><?= produse_list_h($name) ?></span>
                            </div>
                        </td>
                        <td><?= produse_list_h($catLabel !== '' ? $catLabel : 'Consumabil') ?></td>
                        <td><?= produse_list_h($price) ?></td>
                        <td>
                            <label class="besoiu-vitrina-toggle-label">
                                <input type="checkbox" class="vitrina-toggle" data-product-id="<?= produse_list_h($id) ?>" <?= $onVitrina ? 'checked' : '' ?>>
                                <span class="besoiu-vitrina-toggle-label__text<?= $onVitrina ? ' is-on' : '' ?>"><?= $onVitrina ? 'Pe vitrină' : 'Nu' ?></span>
                            </label>
                        </td>
                        <td>
                            <label class="besoiu-vitrina-toggle-label">
                                <input type="checkbox" class="vitrina-badge-toggle" data-product-id="<?= produse_list_h($id) ?>" <?= $hasRecomandat ? 'checked' : '' ?>>
                                <span class="besoiu-vitrina-badge-pill<?= $hasRecomandat ? ' is-on' : '' ?>">RECOMANDAT</span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($produse === []): ?>
                    <tr><td colspan="6" class="besoiu-vitrina-table__empty">Niciun consumabil eligibil în magazin. Importă din <a href="/admin/import">Import consumabile</a>.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="besoiu-pagination" aria-label="Paginare vitrină">
                <span class="besoiu-counter"><?= (($currentPage - 1) * $perPage) + 1 ?>–<?= min($currentPage * $perPage, $total) ?> din <?= $total ?></span>
                <?php if ($currentPage > 1): ?>
                    <a class="besoiu-pagination__btn" href="?<?= produse_list_h($buildVitrinaQuery(['page' => $currentPage - 1])) ?>">‹</a>
                <?php endif; ?>
                <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                    <a class="besoiu-pagination__btn<?= $p === $currentPage ? ' besoiu-pagination__btn--active' : '' ?>"
                       href="?<?= produse_list_h($buildVitrinaQuery(['page' => $p])) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a class="besoiu-pagination__btn" href="?<?= produse_list_h($buildVitrinaQuery(['page' => $currentPage + 1])) ?>">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    'use strict';
    const root = document.getElementById('besoiuVitrinaPage');
    if (!root) return;

    const endpoint = '/admin/crudproduse';
    const countBadge = document.getElementById('vitrinaCountBadge');
    const bulkBar = document.getElementById('vitrinaBulkBar');
    const selectedCountEl = document.getElementById('vitrinaSelectedCount');
    const selectAll = document.getElementById('vitrinaSelectAll');
    const selectAllActive = document.getElementById('vitrinaSelectAllActive');
    const removeActiveBtn = document.getElementById('vitrinaRemoveActiveBtn');

    function productIdFrom(el) {
        if (!el) return '';
        return (el.dataset.productId || el.dataset.id || el.getAttribute('data-product-id') || el.getAttribute('data-id') || el.value || '').trim();
    }

    function selectedIds(selector) {
        return Array.from(root.querySelectorAll(selector + ':checked')).map(productIdFrom).filter(Boolean);
    }

    function selectedPickerIds() {
        return selectedIds('.vitrina-row-select');
    }

    function selectedActiveIds() {
        return selectedIds('.vitrina-active-select');
    }

    function refreshBulkBar() {
        const ids = selectedPickerIds();
        if (selectedCountEl) selectedCountEl.textContent = String(ids.length);
        if (bulkBar) bulkBar.hidden = ids.length === 0;
        if (selectAll) {
            const rows = root.querySelectorAll('.vitrina-row-select');
            selectAll.checked = rows.length > 0 && ids.length === rows.length;
            selectAll.indeterminate = ids.length > 0 && ids.length < rows.length;
        }
    }

    function refreshActiveBulkBar() {
        const ids = selectedActiveIds();
        if (removeActiveBtn) {
            removeActiveBtn.disabled = ids.length === 0;
            removeActiveBtn.textContent = ids.length > 0
                ? ('Scoate selectate de pe vitrină (' + ids.length + ')')
                : 'Scoate selectate de pe vitrină';
        }
        if (selectAllActive) {
            const rows = root.querySelectorAll('.vitrina-active-select');
            selectAllActive.checked = rows.length > 0 && ids.length === rows.length;
            selectAllActive.indeterminate = ids.length > 0 && ids.length < rows.length;
        }
    }

    function updateCountBadge(count) {
        if (countBadge && typeof count === 'number') {
            countBadge.textContent = 'Pe vitrină: ' + count + ' / <?= (int) $vitrinaHomeMax ?>';
        }
    }

    async function postJson(payload) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Eroare');
        }
        return data;
    }

    root.addEventListener('change', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) return;

        if (target.matches('#vitrinaSelectAll')) {
            root.querySelectorAll('.vitrina-row-select').forEach(function (checkbox) {
                checkbox.checked = target.checked;
            });
            refreshBulkBar();
            return;
        }

        if (target.matches('#vitrinaSelectAllActive')) {
            root.querySelectorAll('.vitrina-active-select').forEach(function (checkbox) {
                checkbox.checked = target.checked;
            });
            refreshActiveBulkBar();
            return;
        }

        if (target.matches('.vitrina-row-select')) {
            refreshBulkBar();
            return;
        }

        if (target.matches('.vitrina-active-select')) {
            refreshActiveBulkBar();
            return;
        }

        if (target.matches('.vitrina-toggle')) {
            (async function () {
                const id = productIdFrom(target);
                const enabled = target.checked;
                const label = target.parentElement?.querySelector('.besoiu-vitrina-toggle-label__text');
                const row = target.closest('tr');
                target.disabled = true;
                try {
                    const data = await postJson({ type_product: 'toggle_vitrina', id: id, enabled: enabled });
                    if (label) {
                        label.textContent = enabled ? 'Pe vitrină' : 'Nu';
                        label.classList.toggle('is-on', enabled);
                    }
                    if (row) row.dataset.onVitrina = enabled ? '1' : '0';
                    if (enabled) {
                        const badgeToggle = row?.querySelector('.vitrina-badge-toggle');
                        const badgePill = row?.querySelector('.besoiu-vitrina-badge-pill');
                        if (badgeToggle) badgeToggle.checked = true;
                        if (badgePill) badgePill.classList.add('is-on');
                    }
                    updateCountBadge(data.vitrina_count);
                } catch (err) {
                    target.checked = !enabled;
                    alert(err.message || 'Nu am putut actualiza vitrina.');
                } finally {
                    target.disabled = false;
                }
            })();
            return;
        }

        if (target.matches('.vitrina-badge-toggle')) {
            (async function () {
                const id = productIdFrom(target);
                const enabled = target.checked;
                const pill = target.parentElement?.querySelector('.besoiu-vitrina-badge-pill');
                target.disabled = true;
                try {
                    await postJson({ type_product: 'set_badge', id: id, badge: enabled ? 'recomandat' : '' });
                    if (pill) pill.classList.toggle('is-on', enabled);
                } catch (err) {
                    target.checked = !enabled;
                    alert(err.message || 'Nu am putut actualiza badge-ul.');
                } finally {
                    target.disabled = false;
                }
            })();
        }
    });

    root.addEventListener('click', function (event) {
        const button = event.target instanceof Element ? event.target.closest('button') : null;
        if (!button || !root.contains(button)) return;

        if (button.matches('.besoiu-vitrina-clear-all-btn')) {
            (async function () {
                if (!confirm('Golești toată vitrina și elimini badge-ul RECOMANDAT de pe toate produsele?')) {
                    return;
                }
                button.disabled = true;
                try {
                    const data = await postJson({ type_product: 'clear_vitrina_all', clear_badges: true });
                    updateCountBadge(data.vitrina_count);
                    alert(data.message || 'Vitrina a fost golită.');
                    location.reload();
                } catch (err) {
                    alert(err.message || 'Nu am putut goli vitrina.');
                } finally {
                    button.disabled = false;
                }
            })();
            return;
        }

        if (button.matches('.besoiu-vitrina-bulk-btn')) {
            (async function () {
                const action = button.dataset.action || '';
                let ids = selectedPickerIds();
                if (action === 'remove-active') {
                    ids = selectedActiveIds();
                }
                if (!ids.length) return;
                button.disabled = true;
                try {
                    if (action === 'add' || action === 'remove' || action === 'remove-active') {
                        const data = await postJson({
                            type_product: 'toggle_vitrina_bulk',
                            ids: ids,
                            enabled: action === 'add'
                        });
                        updateCountBadge(data.vitrina_count);
                        alert(data.message || 'Actualizat.');
                        location.reload();
                        return;
                    }
                    if (action === 'badge-on' || action === 'badge-off') {
                        await postJson({
                            type_product: 'set_badge_bulk',
                            ids: ids,
                            badge: action === 'badge-on' ? 'recomandat' : ''
                        });
                        alert('Badge actualizat.');
                        location.reload();
                    }
                } catch (err) {
                    alert(err.message || 'Eroare la acțiunea în masă.');
                } finally {
                    button.disabled = false;
                }
            })();
            return;
        }

        if (button.matches('.besoiu-vitrina-preview-btn[data-action][data-product-id]')) {
            (async function () {
                const id = productIdFrom(button);
                const action = button.dataset.action || '';
                if (!id || !action) return;
                button.disabled = true;
                try {
                    if (action === 'badge-off') {
                        await postJson({ type_product: 'set_badge', id: id, badge: '' });
                    } else if (action === 'vitrina-off') {
                        const data = await postJson({ type_product: 'toggle_vitrina', id: id, enabled: false });
                        updateCountBadge(data.vitrina_count);
                    }
                    location.reload();
                } catch (err) {
                    alert(err.message || 'Nu am putut actualiza produsul.');
                } finally {
                    button.disabled = false;
                }
            })();
        }
    });

    refreshBulkBar();
    refreshActiveBulkBar();
})();
</script>
