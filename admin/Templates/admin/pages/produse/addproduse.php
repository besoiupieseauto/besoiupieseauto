<?php
declare(strict_types=1);

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
$curierLivrareOptions = ['Da' => 'Da', 'Nu' => 'Nu'];
$whatsappOptions = ['Da' => 'Da', 'Nu' => 'Nu', 'Foloseste telefon cont' => 'Foloseste telefon cont'];
$badgeConfig = require dirname(__DIR__, 5) . '/config/product-badges.php';
$badgeOptions = ['' => 'Fara badge'];
foreach ($badgeConfig as $value => $badge) {
    $badgeOptions[$value] = (string) ($badge['admin'] ?? $badge['label'] ?? strtoupper($value));
}
$productStatusOptions = require dirname(__DIR__, 5) . '/config/product-status.php';
?><div class="-mt-5">
    <div class="mt-8 flex items-center">
        <h2 class="mr-auto text-lg font-medium">Add Product</h2>
        <a href="/admin/product" class="inline-flex h-10 items-center rounded-lg border px-4 text-sm hover:bg-foreground/5"><i data-lucide="arrow-left" class="mr-2 size-4"></i>Inapoi</a>
    </div>

    <form id="productForm" class="mt-5 grid grid-cols-11 gap-x-6 pb-20" enctype="multipart/form-data">
        <input type="hidden" name="type_product" value="add">
        <input type="hidden" name="pImages_keep" value="[]">

        <div class="col-span-11 2xl:col-span-9">
            <div class="alert flex border items-center rounded-xl p-4 bg-(--color)/20 border-(--color)/60 text-(--color) [--color:var(--color-primary)] mb-8">
                <i data-lucide="info" class="mr-2 size-4"></i>
                <span>Adauga produsul cu toate imaginile si datele necesare.</span>
            </div>
            <div class="alert flex border items-center rounded-xl p-4 bg-emerald-50 border-emerald-200 text-emerald-800 mb-8">
                <i data-lucide="badge-percent" class="mr-2 size-4"></i>
                <span>Pretul introdus mai jos este tratat ca pret de baza. La salvare, sistemul aplica automat regula activa de adaos comercial care se potriveste produsului.</span>
            </div>

            <div class="box relative p-5 before:absolute before:inset-0 before:mx-3 before:-mb-3 before:border before:border-foreground/10 before:bg-background/30 before:shadow-[0px_3px_5px_#0000000b] before:z-[-1] before:rounded-xl after:absolute after:inset-0 after:border after:border-foreground/10 after:bg-background after:shadow-[0px_3px_5px_#0000000b] after:rounded-xl after:z-[-1] after:backdrop-blur-md">
                <div class="rounded-lg border p-5">
                    <?php section_title('Upload Product'); ?>
                    <div class="mt-5 flex flex-col gap-5">
                        <?php row_start('Product Photos', 'Selecteaza mai multe imagini. Le poti sterge inainte de salvare.', true); ?>
                            <div class="rounded-xl border-2 border-dashed p-4">
                                <div id="previewGrid" class="grid grid-cols-2 gap-4 md:grid-cols-5"></div>
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
                        <?php row_start('Denumire produs', 'Scrie denumirea piesei sau produsului.', true); ?><input name="pName" required class="h-10 w-full rounded-md border bg-background px-3 py-2" placeholder="Ex: furtun incalzire"><?php row_end(); ?>
                        <?php row_start('Masina compatibila'); ?><input name="pCar" class="h-10 w-full rounded-md border bg-background px-3 py-2" placeholder="Ex: BMW F10"><?php row_end(); ?>
                        <?php row_start('Cod piesa'); ?><input name="pCode" class="h-10 w-full rounded-md border bg-background px-3 py-2" placeholder="Cod OEM / intern"><?php row_end(); ?>
                        <?php row_start('Brand produs'); ?><input name="pBrand" class="h-10 w-full rounded-md border bg-background px-3 py-2" placeholder="Ex: Bosch"><?php row_end(); ?>
                        <?php row_start('Categorie'); ?><input name="pCategory" class="h-10 w-full rounded-md border bg-background px-3 py-2" placeholder="Ex: Motor"><?php row_end(); ?>
                        <?php row_start('Subcategorie'); ?><input name="pSubcategory" class="h-10 w-full rounded-md border bg-background px-3 py-2" placeholder="Ex: Turbina"><?php row_end(); ?>
                    </div>
                </div>
            </div>

            <div class="box relative mt-8 p-5 before:absolute before:inset-0 before:mx-3 before:-mb-3 before:border before:border-foreground/10 before:bg-background/30 before:shadow-[0px_3px_5px_#0000000b] before:z-[-1] before:rounded-xl after:absolute after:inset-0 after:border after:border-foreground/10 after:bg-background after:shadow-[0px_3px_5px_#0000000b] after:rounded-xl after:z-[-1] after:backdrop-blur-md">
                <div class="rounded-lg border p-5">
                    <?php section_title('Descriere produs'); ?>
                    <div class="mt-5 flex flex-col gap-5">
                        <?php row_start('Descriere', 'Generată automat la import; aceeași variantă pe site și export.'); ?>
                            <?php require __DIR__ . '/dual_description_fields.php'; product_desc_render_field(); ?>
                        <?php row_end(); ?>
                    </div>
                </div>
            </div>

            <div class="box relative mt-8 p-5 before:absolute before:inset-0 before:mx-3 before:-mb-3 before:border before:border-foreground/10 before:bg-background/30 before:shadow-[0px_3px_5px_#0000000b] before:z-[-1] before:rounded-xl after:absolute after:inset-0 after:border after:border-foreground/10 after:bg-background after:shadow-[0px_3px_5px_#0000000b] after:rounded-xl after:z-[-1] after:backdrop-blur-md">
                <div class="rounded-lg border p-5">
                    <?php section_title('Product Management'); ?>
                    <div class="mt-5 flex flex-col gap-5">
                        <div id="markupPreviewCard" class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-4 text-sm text-sky-900">
                            Completeaza pretul de baza, categoria sau brandul ca sa vezi previzualizarea adaosului comercial.
                        </div>
                        <?php row_start('Pret baza', 'Pretul initial in lei, inainte de adaosul comercial.', true); ?><input name="pBasePrice" required type="number" step="0.01" class="h-10 w-full rounded-md border bg-background px-3 py-2" placeholder="Pret de baza in lei"><?php row_end(); ?>
                        <?php row_start('Stoc'); ?><input name="pStock" type="number" step="1" min="0" class="h-10 w-full rounded-md border bg-background px-3 py-2" placeholder="Ex: 3"><?php row_end(); ?>
                        <?php row_start('Status produs', 'Activ = vizibil in magazin. Inactiv = ascuns (nu apare pe site).'); ?><?php select_field('status', $productStatusOptions, '1'); ?><?php row_end(); ?>
                        <?php row_start('Badge produs', 'Eticheta afisata in coltul dreapta-sus al cartelei de pe site.'); ?><?php select_field('pBadge', $badgeOptions); ?><?php row_end(); ?>
                        <?php row_start('Livrare curier', 'Da = livrare prin curier disponibila. Nu = doar ridicare (caroserie, bare, piese voluminoase).'); ?><?php select_field('pCurierLivrare', $curierLivrareOptions, 'Da'); ?><?php row_end(); ?>
                        <?php row_start('WhatsApp'); ?><?php select_field('pWhatsapp', $whatsappOptions); ?><?php row_end(); ?>
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

<script>
(function () {
    const form = document.getElementById('productForm');
    const input = document.getElementById('imageInput');
    const grid = document.getElementById('previewGrid');
    const markupCard = document.getElementById('markupPreviewCard');
    const selectedFiles = [];
    const previewFields = ['pName', 'pCode', 'pCar', 'pBrand', 'pCategory', 'pSubcategory', 'pStock', 'pBasePrice'];
    let previewTimer = null;

    function syncInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        input.files = dt.files;
    }

    function renderPreviews() {
        grid.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const url = URL.createObjectURL(file);
            const item = document.createElement('div');
            item.className = 'image-fit relative h-28 overflow-hidden rounded-xl border';
            item.innerHTML = `<img src="${url}" class="h-full w-full object-cover" alt=""><button type="button" class="absolute right-1 top-1 flex size-7 items-center justify-center rounded-full bg-danger text-white" data-index="${index}">x</button>`;
            grid.appendChild(item);
        });
    }

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
            renderMarkupPreview('Completeaza pretul de baza, categoria sau brandul ca sa vezi previzualizarea adaosului comercial.', true);
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
        const response = await fetch('/admin/crudproduse', {method: 'POST', body: new FormData(form)});
        const result = await response.json();
        alert(result.message || (result.success ? 'Produs adaugat.' : 'Eroare la salvare.'));
        if (result.success) window.location.href = '/admin/product';
    });
})();
</script>
