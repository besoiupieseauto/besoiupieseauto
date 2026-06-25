<?php
declare(strict_types=1);

use Evasystem\Controllers\Produse\ProduseService;

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function oldv(array $product, string $key): string { return (string)($product[$key] ?? ''); }
function product_images($value): array {
    $decoded = json_decode((string)$value, true);
    if (is_array($decoded)) return array_values(array_filter($decoded));
    return $value ? [(string)$value] : [];
}
function section_title(string $title): void {
    echo '<div class="flex items-center border-b pb-5 text-base font-medium"><i data-lucide="chevron-down" class="mr-2 size-4"></i>' . h($title) . '</div>';
}
function row_start(string $title, string $help = '', bool $required = false): void {
    echo '<div class="flex flex-col items-start xl:flex-row"><div class="w-full xl:mr-10 xl:w-64"><div class="text-left"><div class="flex items-center"><div class="font-medium">' . h($title) . '</div>';
    if ($required) echo '<div class="ml-3 rounded-full border px-2 py-px text-xs opacity-70">Required</div>';
    echo '</div>';
    if ($help !== '') echo '<div class="mt-3 text-xs leading-relaxed opacity-70">' . h($help) . '</div>';
    echo '</div></div><div class="mt-3 w-full flex-1 xl:mt-0">';
}
function row_end(): void { echo '</div></div>'; }
function select_field(string $name, array $options, string $selected = ''): void {
    echo '<select name="' . h($name) . '" class="bg-(image:--background-image-chevron) bg-[position:calc(100%-theme(spacing.3))_center] bg-[size:theme(spacing.5)] bg-no-repeat relative appearance-none flex h-10 w-full rounded-md border bg-background px-3 py-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-foreground/5 focus-visible:ring-offset-2">';
    echo '<option value="">Select</option>';
    foreach ($options as $value => $label) {
        $isSelected = ((string)$value === (string)$selected) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $isSelected . '>' . h($label) . '</option>';
    }
    echo '</select>';
}
function ph_input(string $name, string $placeholder, string $type = 'text', string $extra = ''): void {
    echo '<input name="' . h($name) . '" type="' . h($type) . '" placeholder="' . h($placeholder) . '" class="h-10 w-full rounded-md border bg-background px-3 py-2" ' . $extra . '>';
}
$curierLivrareOptions = ['Da' => 'Da', 'Nu' => 'Nu'];
$whatsappOptions = ['Da' => 'Da', 'Nu' => 'Nu', 'Foloseste telefon cont' => 'Foloseste telefon cont'];
$badgeConfig = require dirname(__DIR__, 5) . '/config/product-badges.php';
$badgeOptions = ['' => 'Fara badge'];
foreach ($badgeConfig as $value => $badge) {
    $badgeOptions[$value] = (string) ($badge['admin'] ?? $badge['label'] ?? strtoupper($value));
}
$productStatusOptions = require dirname(__DIR__, 5) . '/config/product-status.php';

$id = (string)($_GET['id'] ?? '');
$service = new ProduseService();
$product = $id !== '' ? $service->getIdProduses($id) : null;
if (!$product) {
    echo '<div class="box mt-8 p-6">Produsul nu a fost gasit. <a class="text-primary" href="/admin/product">Inapoi la produse</a></div>';
    return;
}
$productStatusSelected = (string) ($product['status'] ?? '1');
$productStatusSelected = $productStatusSelected === '0' ? '0' : '1';
$images = product_images($product['pImages'] ?? '');
$basePrice = oldv($product, 'pBasePrice') !== '' ? oldv($product, 'pBasePrice') : oldv($product, 'pPrice');

$imageAuditLast = null;
$auditRoot = dirname(__DIR__, 5);
if (is_file($auditRoot . '/admin/src/Services/ProductImageAuditService.php')) {
    require_once $auditRoot . '/admin/src/Services/ProductImageAuditService.php';
    $imageAuditLast = (new \Evasystem\Services\ProductImageAuditService($auditRoot))->loadProductAuditResult($id);
}

$productFormValues = [
    'pName' => oldv($product, 'pName'),
    'pCar' => oldv($product, 'pCar'),
    'pCode' => oldv($product, 'pCode'),
    'pBrand' => oldv($product, 'pBrand'),
    'pCategory' => oldv($product, 'pCategory'),
    'pSubcategory' => oldv($product, 'pSubcategory'),
    'pNote' => oldv($product, 'pNote'),
    'pNote' => oldv($product, 'pNote') !== '' ? oldv($product, 'pNote') : (oldv($product, 'pNoteWebsite') !== '' ? oldv($product, 'pNoteWebsite') : oldv($product, 'pNoteMarketplace')),
    'pBasePrice' => $basePrice,
    'pPriceDisplay' => oldv($product, 'pPrice'),
    'pStock' => oldv($product, 'pStock'),
];
?>
<div class="-mt-5">
    <div class="mt-8 flex items-center">
        <h2 class="mr-auto text-lg font-medium">Edit Product</h2>
        <a href="/admin/product" class="inline-flex h-10 items-center rounded-lg border px-4 text-sm hover:bg-foreground/5"><i data-lucide="arrow-left" class="mr-2 size-4"></i>Inapoi</a>
    </div>

    <div class="alert mt-5 flex border items-center rounded-xl p-4 bg-emerald-50 border-emerald-200 text-emerald-800">
        <i data-lucide="badge-percent" class="mr-2 size-4"></i>
        <span>
            Editezi pretul de baza, iar pretul final se recalculeaza automat la salvare.
            <?php if (oldv($product, 'pMarkupRuleName') !== ''): ?>
                Regula curenta: <strong><?= h(oldv($product, 'pMarkupRuleName')) ?></strong>, pret final salvat: <strong><?= h(oldv($product, 'pPrice')) ?> lei</strong>.
            <?php endif; ?>
        </span>
    </div>

    <form id="productForm" class="mt-5 grid grid-cols-11 gap-x-6 pb-20" enctype="multipart/form-data">
        <input type="hidden" name="type_product" value="edit">
        <input type="hidden" name="id" value="<?= h($id) ?>">
        <input type="hidden" name="pImages_keep" id="pImagesKeep" value="<?= h(json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">

        <div class="col-span-11 2xl:col-span-9">
            <div class="box relative p-5 before:absolute before:inset-0 before:mx-3 before:-mb-3 before:border before:border-foreground/10 before:bg-background/30 before:shadow-[0px_3px_5px_#0000000b] before:z-[-1] before:rounded-xl after:absolute after:inset-0 after:border after:border-foreground/10 after:bg-background after:shadow-[0px_3px_5px_#0000000b] after:rounded-xl after:z-[-1] after:backdrop-blur-md">
                <div class="rounded-lg border p-5">
                    <?php section_title('Upload Product'); ?>
                    <div class="mt-5 flex flex-col gap-5">
                        <?php row_start('Product Photos', 'Sterge imaginile vechi sau adauga imagini noi.', true); ?>
                            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3">
                                <button type="button" id="auditSingleProductImage" class="inline-flex h-9 items-center gap-2 rounded-lg border border-violet-300 bg-white px-3 text-sm font-medium text-violet-800 hover:bg-violet-100">
                                    <i data-lucide="scan-eye" class="size-4"></i>
                                    Audit + Pipeline
                                </button>
                                <button type="button" id="findImagePlanProduct" class="inline-flex h-9 items-center gap-2 rounded-lg border border-teal-300 bg-white px-3 text-sm font-medium text-teal-800 hover:bg-teal-50">
                                    <i data-lucide="image-search" class="size-4"></i>
                                    Caută imagine Plan 1→3
                                </button>
                                <span class="text-xs text-violet-900 opacity-80">Același pipeline ca în Scraper — Autodoc, ePiesa, TecDoc.</span>
                            </div>
                            <?php if (is_array($imageAuditLast) && ($imageAuditLast['verdict'] ?? '') !== ''): ?>
                                <div class="mb-4 rounded-xl border px-4 py-3 text-sm <?= in_array((string)($imageAuditLast['verdict'] ?? ''), ['mismatch', 'error', 'no_image'], true) ? 'border-red-200 bg-red-50 text-red-900' : (((string)($imageAuditLast['verdict'] ?? '') === 'match') ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900') ?>">
                                    <strong>Ultimul audit:</strong>
                                    <?= h((string)($imageAuditLast['verdict'] ?? '')) ?>
                                    · <?= (int)($imageAuditLast['match_score'] ?? 0) ?>/100
                                    — <?= h((string)($imageAuditLast['summary_ro'] ?? '')) ?>
                                </div>
                            <?php endif; ?>
                            <div class="rounded-xl border-2 border-dashed p-4">
                                <div id="productPhotosGrid" class="besoiu-product-photos-grid">
                                    <?php foreach ($images as $image): ?>
                                        <div class="besoiu-product-photo-tile existing-image" data-image="<?= h($image) ?>">
                                            <img src="<?= h($image) ?>" alt="">
                                            <button type="button" class="besoiu-product-photo-remove remove-existing" aria-label="Sterge imagine">x</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <label class="relative mt-4 flex cursor-pointer items-center justify-center rounded-lg border border-dashed px-4 py-6 text-sm">
                                    <i data-lucide="image" class="mr-2 size-4"></i>
                                    <span class="text-primary mr-1">Upload a file</span> or drag and drop
                                    <input id="imageInput" name="pImages[]" type="file" accept="image/*" multiple class="absolute inset-0 opacity-0">
                                </label>
                            </div>
                        <?php row_end(); ?>
                    </div>
                </div>
            </div>

            <div class="box relative mt-8 p-5 before:absolute before:inset-0 before:mx-3 before:-mb-3 before:border before:border-foreground/10 before:bg-background/30 before:shadow-[0px_3px_5px_#0000000b] before:z-[-1] before:rounded-xl after:absolute after:inset-0 after:border after:border-foreground/10 after:bg-background after:shadow-[0px_3px_5px_#0000000b] after:rounded-xl after:z-[-1] after:backdrop-blur-md">
                <div class="rounded-lg border p-5">
                    <?php section_title('Product Information'); ?>
                    <div class="mt-5 flex flex-col gap-5">
                        <?php row_start('Denumire produs', 'Scrie denumirea piesei sau produsului.', true); ?><?php ph_input('pName', 'Ex: furtun incalzire', 'text', 'required'); ?><?php row_end(); ?>
                        <?php row_start('Masina compatibila'); ?><?php ph_input('pCar', 'Ex: BMW F10'); ?><?php row_end(); ?>
                        <?php row_start('Cod piesa'); ?><?php ph_input('pCode', 'Cod OEM / intern'); ?><?php row_end(); ?>
                        <?php row_start('Brand produs'); ?><?php ph_input('pBrand', 'Ex: Bosch'); ?><?php row_end(); ?>
                        <?php row_start('Categorie'); ?><?php ph_input('pCategory', 'Ex: Motor'); ?><?php row_end(); ?>
                        <?php row_start('Subcategorie'); ?><?php ph_input('pSubcategory', 'Ex: Turbina'); ?><?php row_end(); ?>
                    </div>
                </div>
            </div>

            <div class="box relative mt-8 p-5 before:absolute before:inset-0 before:mx-3 before:-mb-3 before:border before:border-foreground/10 before:bg-background/30 before:shadow-[0px_3px_5px_#0000000b] before:z-[-1] before:rounded-xl after:absolute after:inset-0 after:border after:border-foreground/10 after:bg-background after:shadow-[0px_3px_5px_#0000000b] after:rounded-xl after:z-[-1] after:backdrop-blur-md">
                <div class="rounded-lg border p-5">
                    <?php section_title('Descriere produs'); ?>
                    <div class="mt-5 flex flex-col gap-5">
                        <?php row_start('Descriere', 'Generată automat la import; aceeași variantă pe site și export.'); ?>
                            <?php require __DIR__ . '/dual_description_fields.php'; product_desc_render_field($productFormValues['pNote']); ?>
                        <?php row_end(); ?>
                    </div>
                </div>
            </div>

            <div class="box relative mt-8 p-5 before:absolute before:inset-0 before:mx-3 before:-mb-3 before:border before:border-foreground/10 before:bg-background/30 before:shadow-[0px_3px_5px_#0000000b] before:z-[-1] before:rounded-xl after:absolute after:inset-0 after:border after:border-foreground/10 after:bg-background after:shadow-[0px_3px_5px_#0000000b] after:rounded-xl after:z-[-1] after:backdrop-blur-md">
                <div class="rounded-lg border p-5">
                    <?php section_title('Product Management'); ?>
                    <div class="mt-5 flex flex-col gap-5">
                        <div id="markupPreviewCard" class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-4 text-sm text-sky-900">
                            Se incarca previzualizarea regulii de adaos pentru produsul curent.
                        </div>
                        <?php row_start('Pret baza', 'Pretul initial in lei, inainte de adaosul comercial.', true); ?><?php ph_input('pBasePrice', 'Pret de baza in lei', 'number', 'required step="0.01"'); ?><?php row_end(); ?>
                        <?php row_start('Pret final salvat', 'Camp informativ, calculat automat din regula activa.'); ?><input id="pPriceDisplay" readonly placeholder="Se calculeaza automat" class="h-10 w-full rounded-md border bg-slate-50 px-3 py-2 text-slate-600"><?php row_end(); ?>
                        <?php row_start('Stoc'); ?><?php ph_input('pStock', 'Ex: 3', 'number', 'step="1" min="0"'); ?><?php row_end(); ?>
                        <?php row_start('Status produs', 'Activ = vizibil in magazin. Inactiv = ascuns (nu apare pe site).'); ?><?php select_field('status', $productStatusOptions, $productStatusSelected); ?><?php row_end(); ?>
                        <?php row_start('Badge produs', 'Eticheta afisata in coltul dreapta-sus al cartelei de pe site.'); ?><?php select_field('pBadge', $badgeOptions, oldv($product, 'pBadge')); ?><?php row_end(); ?>
                        <?php row_start('Livrare curier', 'Da = livrare prin curier disponibila. Nu = doar ridicare (caroserie, bare, piese voluminoase).'); ?><?php select_field('pCurierLivrare', $curierLivrareOptions, oldv($product, 'pCurierLivrare') !== '' ? oldv($product, 'pCurierLivrare') : 'Da'); ?><?php row_end(); ?>
                        <?php row_start('WhatsApp'); ?><?php select_field('pWhatsapp', $whatsappOptions, oldv($product, 'pWhatsapp')); ?><?php row_end(); ?>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex flex-col justify-end gap-4 md:flex-row">
                <a href="/admin/product" class="box inline-flex h-10 w-full items-center justify-center rounded-lg border px-4 py-3 text-sm md:w-64">Cancel</a>
                <button class="box inline-flex h-10 w-full items-center justify-center rounded-lg border px-4 py-3 text-sm bg-(--color)/20 border-(--color)/60 text-(--color) [--color:var(--color-primary)] md:w-64" type="submit">Save</button>
            </div>
        </div>

        <div class="col-span-2 hidden 2xl:block">
            <div class="sticky top-0 pt-5">
                <ul class="before:bg-foreground/10 relative before:absolute before:left-0 before:z-[-1] before:h-full before:w-[2px]">
                    <li class="active mb-4 border-l-2 border-primary py-1 pl-5 font-medium text-primary">Upload Product</li>
                    <li class="mb-4 border-l-2 border-transparent py-1 pl-5 opacity-70">Product Information</li>
                    <li class="mb-4 border-l-2 border-transparent py-1 pl-5 opacity-70">Descrieri duale</li>
                    <li class="mb-4 border-l-2 border-transparent py-1 pl-5 opacity-70">Product Management</li>
                </ul>
            </div>
        </div>
    </form>
</div>

<?php require __DIR__ . '/_produse-image-audit.php'; ?>

<script>
(function () {
    const form = document.getElementById('productForm');
    const productId = <?= json_encode($id, JSON_UNESCAPED_UNICODE) ?>;
    const auditBtn = document.getElementById('auditSingleProductImage');
    const findImgBtn = document.getElementById('findImagePlanProduct');
    auditBtn && auditBtn.addEventListener('click', async () => {
        if (!productId || !window.besoiuImageAudit) return;
        auditBtn.disabled = true;
        try {
            await window.besoiuImageAudit.runAudit([productId]);
        } finally {
            auditBtn.disabled = false;
        }
    });
    findImgBtn && findImgBtn.addEventListener('click', async () => {
        if (!productId || !window.besoiuImageAudit) return;
        findImgBtn.disabled = true;
        try {
            await window.besoiuImageAudit.runFindImageOnly([productId]);
        } finally {
            findImgBtn.disabled = false;
        }
    });
})();
</script>

<script>
(function () {
    const form = document.getElementById('productForm');
    const productFormValues = <?= json_encode($productFormValues, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const input = document.getElementById('imageInput');
    const grid = document.getElementById('productPhotosGrid');
    const keep = document.getElementById('pImagesKeep');
    const markupCard = document.getElementById('markupPreviewCard');
    const selectedFiles = [];
    const previewFields = ['pName', 'pCode', 'pCar', 'pBrand', 'pCategory', 'pSubcategory', 'pStock', 'pBasePrice'];
    let previewTimer = null;

    Object.entries(productFormValues).forEach(([name, value]) => {
        if (value === '' || value === null || value === undefined) return;
        if (name === 'pNote') return;
        if (name === 'pPriceDisplay') {
            const display = document.getElementById('pPriceDisplay');
            if (display) display.value = String(value);
            return;
        }
        const field = form.elements.namedItem(name);
        if (field) field.value = String(value);
    });

    function syncKeep() {
        keep.value = JSON.stringify(Array.from(document.querySelectorAll('.existing-image')).map(el => el.dataset.image));
    }

    function syncInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        input.files = dt.files;
    }

    function renderPreviews() {
        grid.querySelectorAll('[data-preview-index]').forEach(el => el.remove());
        selectedFiles.forEach((file, index) => {
            const url = URL.createObjectURL(file);
            const item = document.createElement('div');
            item.className = 'besoiu-product-photo-tile';
            item.dataset.previewIndex = String(index);
            item.innerHTML = `<img src="${url}" alt=""><button type="button" class="besoiu-product-photo-remove" data-index="${index}" aria-label="Sterge imagine">x</button>`;
            grid.appendChild(item);
        });
    }

    document.querySelectorAll('.remove-existing').forEach(button => {
        button.addEventListener('click', () => {
            button.closest('.existing-image').remove();
            syncKeep();
        });
    });

    input.addEventListener('change', () => {
        Array.from(input.files).forEach(file => selectedFiles.push(file));
        syncInput();
        renderPreviews();
    });

    grid.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-index]');
        if (!button) return;
        selectedFiles.splice(Number(button.dataset.index), 1);
        syncInput();
        renderPreviews();
    });

    function renderMarkupPreview(result, isError = false) {
        if (!markupCard) return;
        if (isError) {
            markupCard.className = 'rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900';
            markupCard.textContent = result;
            return;
        }

        const data = result || {};
        const rule = data.rule || null;
        const applied = !!data.applied;
        markupCard.className = applied
            ? 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900'
            : 'rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-800';

        markupCard.innerHTML =
            '<div class="font-medium">' + (rule ? 'Regula potrivita: ' + rule.name : 'Nu exista regula activa potrivita') + '</div>' +
            '<div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3">' +
                '<div><span class="opacity-70">Pret baza:</span> <strong>' + (data.base_price || '0') + ' lei</strong></div>' +
                '<div><span class="opacity-70">Adaos:</span> <strong>' + (applied ? ('+' + (data.delta || '0') + ' lei') : '0 lei') + '</strong></div>' +
                '<div><span class="opacity-70">Pret final:</span> <strong>' + (data.final_price || data.base_price || '0') + ' lei</strong></div>' +
            '</div>';
    }

    async function fetchMarkupPreview() {
        const payload = { type_product: 'simulate_product' };
        previewFields.forEach(name => {
            const field = form.elements.namedItem(name);
            if (field) payload[name] = field.value;
        });

        if (!payload.pBasePrice) {
            renderMarkupPreview('Introduce pretul de baza pentru a calcula previzualizarea.', true);
            return;
        }

        try {
            const response = await fetch('/admin/crudadaoscomercial', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (!result.success) {
                renderMarkupPreview(result.message || 'Nu am putut calcula previzualizarea.', true);
                return;
            }
            renderMarkupPreview(result.data);
        } catch (error) {
            renderMarkupPreview('Previzualizarea nu este disponibila momentan.', true);
        }
    }

    function queueMarkupPreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(fetchMarkupPreview, 250);
    }

    previewFields.forEach(name => {
        const field = form.elements.namedItem(name);
        if (!field) return;
        field.addEventListener('input', queueMarkupPreview);
        field.addEventListener('change', queueMarkupPreview);
    });

    queueMarkupPreview();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        syncKeep();
        const response = await fetch('/admin/crudproduse', {method: 'POST', body: new FormData(form)});
        const result = await response.json();
        alert(result.message || (result.success ? 'Produs salvat.' : 'Eroare la salvare.'));
        if (result.success) window.location.href = '/admin/product';
    });
})();
</script>
