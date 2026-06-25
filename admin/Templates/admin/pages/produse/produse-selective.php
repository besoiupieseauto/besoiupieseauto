<?php
declare(strict_types=1);

use Evasystem\Controllers\Produse\ProduseService;

$service = new ProduseService();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$search = trim((string) ($_GET['q'] ?? ''));
$paged = $service->getVitrinaPickerPaginated($page, $perPage, $search);
$produse = $paged['items'];
$vitrinaCount = $service->countVitrinaProducts();
$total = (int) ($paged['total'] ?? 0);
$totalPages = (int) ($paged['total_pages'] ?? 1);
$currentPage = (int) ($paged['page'] ?? 1);

function vitrina_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function vitrina_first_image(array $product): string
{
    $decoded = json_decode((string) ($product['pImages'] ?? ''), true);
    if (is_array($decoded) && isset($decoded[0])) {
        return (string) $decoded[0];
    }
    return '/admin/dist/images/fakers/preview-12.jpg';
}
?>
<div class="-mt-5 admin-content">
    <div class="admin-panel">
        <div class="admin-panel__head flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="mt-0 text-lg font-medium">Vetrina homepage — produse recomandate</h2>
                <p class="text-sm text-foreground/70 mt-1">
                    Bifează până la <strong>10 produse</strong> (ulei Castrol, antigel, lichid frână etc.) care apar în grila de vitrină <strong>sub caruselul hero</strong> pe homepage. Prețurile se fixează manual la editare produs.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="/admin/product" class="inline-flex h-10 items-center rounded-lg border px-4 text-sm font-medium hover:bg-foreground/[.03]">
                    Lista produse
                </a>
                <span class="inline-flex h-10 items-center rounded-lg bg-emerald-100 px-4 text-sm font-medium text-emerald-900" id="vitrinaCountBadge">
                    Pe vitrină: <?= vitrina_h((string) $vitrinaCount) ?>
                </span>
            </div>
        </div>

        <form method="get" action="/admin/produse-selective" class="mt-4 flex flex-wrap gap-2">
            <input type="text" name="q" value="<?= vitrina_h($search) ?>" placeholder="Caută după nume, cod, brand..." class="h-10 w-64 rounded-md border bg-background px-3 text-sm">
            <button type="submit" class="h-10 rounded-md border px-4 text-sm font-medium hover:bg-foreground/[.03]">Caută</button>
        </form>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th class="py-2 pr-3">Vitrină</th>
                        <th class="py-2 pr-3">Produs</th>
                        <th class="py-2 pr-3">Cod</th>
                        <th class="py-2 pr-3">Brand</th>
                        <th class="py-2 pr-3">Preț</th>
                        <th class="py-2">Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($produse as $product): ?>
                    <?php
                        $id = (string) ($product['randomn_id'] ?? $product['id'] ?? '');
                        $onVitrina = (int) ($product['pVitrina'] ?? 0) === 1;
                    ?>
                    <tr class="border-b border-foreground/10" data-product-id="<?= vitrina_h($id) ?>">
                        <td class="py-3 pr-3">
                            <label class="inline-flex cursor-pointer items-center gap-2">
                                <input type="checkbox" class="vitrina-toggle" data-id="<?= vitrina_h($id) ?>" <?= $onVitrina ? 'checked' : '' ?>>
                                <span class="text-xs <?= $onVitrina ? 'text-emerald-700 font-medium' : 'text-foreground/60' ?>">
                                    <?= $onVitrina ? 'Da' : 'Nu' ?>
                                </span>
                            </label>
                        </td>
                        <td class="py-3 pr-3">
                            <div class="flex items-center gap-2">
                                <img src="<?= vitrina_h(vitrina_first_image($product)) ?>" alt="" class="h-10 w-10 rounded object-cover">
                                <span><?= vitrina_h($product['pName'] ?? '') ?></span>
                            </div>
                        </td>
                        <td class="py-3 pr-3"><?= vitrina_h($product['pCode'] ?? '') ?></td>
                        <td class="py-3 pr-3"><?= vitrina_h($product['pBrand'] ?? '') ?></td>
                        <td class="py-3 pr-3"><?= vitrina_h($product['pPrice'] ?? '') ?></td>
                        <td class="py-3">
                            <a href="/admin/editproduse?id=<?= vitrina_h($id) ?>" class="text-primary hover:underline">Editează</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($produse === []): ?>
                    <tr><td colspan="6" class="py-6 text-center text-foreground/60">Niciun produs găsit.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-4 flex flex-wrap gap-2" aria-label="Paginare">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php
                    $qs = http_build_query(array_filter(['page' => $p > 1 ? $p : null, 'q' => $search !== '' ? $search : null]));
                    $href = '/admin/produse-selective' . ($qs !== '' ? '?' . $qs : '');
                ?>
                <a href="<?= vitrina_h($href) ?>" class="inline-flex h-9 min-w-9 items-center justify-center rounded border px-2 text-sm <?= $p === $currentPage ? 'bg-primary/10 border-primary/40' : 'hover:bg-foreground/[.03]' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    'use strict';
    const endpoint = '/admin/crudproduse';
    const countBadge = document.getElementById('vitrinaCountBadge');

    document.querySelectorAll('.vitrina-toggle').forEach(function (checkbox) {
        checkbox.addEventListener('change', async function () {
            const id = checkbox.dataset.id || '';
            const enabled = checkbox.checked;
            const label = checkbox.parentElement?.querySelector('span');
            checkbox.disabled = true;
            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type_product: 'toggle_vitrina', id: id, enabled: enabled })
                });
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Eroare');
                }
                if (label) {
                    label.textContent = enabled ? 'Da' : 'Nu';
                    label.className = 'text-xs ' + (enabled ? 'text-emerald-700 font-medium' : 'text-foreground/60');
                }
                if (countBadge && typeof data.vitrina_count === 'number') {
                    countBadge.textContent = 'Pe vitrină: ' + data.vitrina_count;
                }
            } catch (err) {
                checkbox.checked = !enabled;
                alert(err.message || 'Nu am putut actualiza vitrina.');
            } finally {
                checkbox.disabled = false;
            }
        });
    });
})();
</script>
