<?php
declare(strict_types=1);

use Evasystem\Controllers\Categorii\CategoriiService;

$service = new CategoriiService();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$activeType = trim((string) ($_GET['type'] ?? ''));
$searchQ = trim((string) ($_GET['q'] ?? ''));
$paged = $service->getPaginated($page, $perPage, array_filter([
    'type' => $activeType,
    'q' => $searchQ,
]));
$categorii = $paged['items'];
$total = (int) ($paged['total'] ?? count($categorii));
$totalPages = (int) ($paged['total_pages'] ?? 1);
$currentPage = (int) ($paged['page'] ?? 1);
$allCategorii = $service->getAll();
$grandTotal = count($allCategorii);
$tecdocStructureImportEnabled = $service->isTecdocStructureImportEnabled();
$tecdocStructureImportNotice = $service->tecdocStructureImportBlockedMessage();

function h_cat($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$types = ['categorie' => 'Categorie', 'subcategorie' => 'Subcategorie', 'marca' => 'Marcă', 'model' => 'Model', 'motorizare' => 'Motorizare'];
?>
<style>
.categorii-admin-page ~ #catModal.categorii-overlay-modal:not(.is-open),
.categorii-admin-page ~ #tecdocModal.categorii-overlay-modal:not(.is-open),
#catModal.categorii-overlay-modal[hidden],
#tecdocModal.categorii-overlay-modal[hidden] {
  display: none !important;
  visibility: hidden !important;
  pointer-events: none !important;
}
</style>
<div class="-mt-5 categorii-admin-page" data-page-title="Categorii">
    <div>
        <h2 class="mt-10 text-lg font-medium">Gestiune Categorii Website</h2>
        <p class="mt-1 text-sm text-foreground/60">Administrează categoriile, mărcile, modelele și motorizările care apar pe site.</p>

        <div id="tecdoc-structure-deferred-banner" class="mt-5 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900" data-tm021="reference-only">
            <strong>TecDoc — doar referință (tm_021):</strong>
            <?= h_cat($tecdocStructureImportNotice) ?>
        </div>

        <div class="mt-5 grid grid-cols-12 gap-x-6 gap-y-4">
            <!-- Toolbar -->
            <div class="col-span-12 flex flex-wrap items-center gap-2">
                <button type="button" onclick="openAddModal()" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">
                    <i data-lucide="plus" class="h-4 w-4"></i> Adaugă Categorie
                </button>
                <button type="button" id="btn-tecdoc-reference" data-action="open-tecdoc-reference" onclick="openTecdocModal()" class="inline-flex items-center gap-2 rounded-lg border border-blue-500/30 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50">
                    <i data-lucide="book-open" class="h-4 w-4"></i> Referință TecDoc
                </button>
                <button type="button" onclick="importDefaults()" class="inline-flex items-center gap-2 rounded-lg border border-primary/30 px-4 py-2 text-sm font-medium text-primary hover:bg-primary/5">
                    <i data-lucide="download" class="h-4 w-4"></i> Importă Categorii Implicite
                </button>
                <div class="ml-auto flex items-center gap-2">
                    <input id="searchBox" type="text" placeholder="Caută..." value="<?= h_cat($searchQ) ?>" class="h-10 w-56 rounded-md border bg-background px-3 py-2 text-sm">
                </div>
            </div>

            <!-- Taburi -->
            <?php
            $stats = ['categorie' => 0, 'subcategorie' => 0, 'marca' => 0, 'model' => 0, 'motorizare' => 0];
            foreach ($allCategorii as $c) { $t = $c['type'] ?? 'categorie'; if (isset($stats[$t])) $stats[$t]++; else $stats['categorie']++; }
            $tabQuery = $searchQ !== '' ? '&q=' . rawurlencode($searchQ) : '';
            ?>
            <div class="col-span-12" style="display:flex;gap:0;border-bottom:2px solid #e5e7eb;margin-bottom:-8px;">
                <a href="?page=1<?= $tabQuery ?>" class="cat-tab <?= $activeType === '' ? 'active' : '' ?>" data-tab="" style="padding:10px 20px;font-size:14px;font-weight:600;border:none;background:none;cursor:pointer;border-bottom:3px solid <?= $activeType === '' ? '#2563eb' : 'transparent' ?>;color:<?= $activeType === '' ? '#2563eb' : '#6b7280' ?>;margin-bottom:-2px;text-decoration:none;">
                    Toate <span style="font-size:12px;opacity:.7;">(<?= $grandTotal ?>)</span>
                </a>
                <?php foreach ($stats as $sType => $sCount): ?>
                    <a href="?page=1&type=<?= rawurlencode($sType) ?><?= $tabQuery ?>" class="cat-tab <?= $activeType === $sType ? 'active' : '' ?>" data-tab="<?= h_cat($sType) ?>" style="padding:10px 20px;font-size:14px;font-weight:600;border:none;background:none;cursor:pointer;border-bottom:3px solid <?= $activeType === $sType ? '#2563eb' : 'transparent' ?>;color:<?= $activeType === $sType ? '#2563eb' : '#6b7280' ?>;margin-bottom:-2px;text-decoration:none;">
                        <?= h_cat($types[$sType] ?? $sType) ?> <span style="font-size:12px;opacity:.7;">(<?= $sCount ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Tabel -->
            <div class="col-span-12 mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm" id="categoriiTable">
                    <thead class="border-b text-foreground/60">
                        <tr>
                            <th class="px-3 py-2 w-12">#</th>
                            <th class="px-3 py-2">Label</th>
                            <th class="px-3 py-2">Slug</th>
                            <th class="px-3 py-2">Tip</th>
                            <th class="px-3 py-2">Icon</th>
                            <th class="px-3 py-2 text-center">Ordine</th>
                            <th class="px-3 py-2 text-center">Parent</th>
                            <th class="px-3 py-2 text-center">Activ</th>
                            <th class="px-3 py-2 text-center">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorii as $idx => $cat): ?>
                            <tr class="cat-row border-b hover:bg-foreground/5" data-id="<?= (int)$cat['id'] ?>" data-type="<?= h_cat($cat['type'] ?? 'categorie') ?>" data-search="<?= h_cat(strtolower($cat['label'] . ' ' . $cat['slug'])) ?>">
                                <td class="px-3 py-2 text-foreground/50"><?= (($currentPage - 1) * $perPage) + $idx + 1 ?></td>
                                <td class="px-3 py-2 font-medium"><?= h_cat($cat['label']) ?></td>
                                <td class="px-3 py-2 text-foreground/60 font-mono text-xs"><?= h_cat($cat['slug']) ?></td>
                                <td class="px-3 py-2">
                                    <?php
                                    $badgeColors = match($cat['type'] ?? '') {
                                        'subcategorie' => 'background:#ecfdf5;color:#047857;',
                                        'marca' => 'background:#eff6ff;color:#1d4ed8;',
                                        'model' => 'background:#f5f3ff;color:#6d28d9;',
                                        'motorizare' => 'background:#fff7ed;color:#c2410c;',
                                        default => 'background:#f0fdf4;color:#15803d;',
                                    };
                                    ?>
                                    <span style="display:inline-block;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:600;<?= $badgeColors ?>">
                                        <?= h_cat($types[$cat['type'] ?? 'categorie'] ?? $cat['type']) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <?php if (!empty($cat['icon'])): ?>
                                        <img src="/<?= h_cat($cat['icon']) ?>" alt="" class="h-5 w-5 inline-block">
                                    <?php else: ?>
                                        <span class="text-foreground/30">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-center"><?= (int)($cat['sort_order'] ?? 0) ?></td>
                                <td class="px-3 py-2 text-center"><?= $cat['parent_id'] ? (int)$cat['parent_id'] : '—' ?></td>
                                <td class="px-3 py-2 text-center">
                                    <button onclick="toggleCat(<?= (int)$cat['id'] ?>, <?= (int)$cat['is_active'] === 1 ? 0 : 1 ?>)" style="display:inline-flex;align-items:center;width:40px;height:24px;border-radius:999px;padding:2px;cursor:pointer;border:none;transition:background .2s;background:<?= (int)$cat['is_active'] === 1 ? '#22c55e' : '#d1d5db' ?>;">
                                        <span style="display:block;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform .2s;transform:translateX(<?= (int)$cat['is_active'] === 1 ? '18px' : '0' ?>);"></span>
                                    </button>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button onclick='editCat(<?= json_encode($cat, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="text-blue-600 hover:text-blue-800 mr-2" title="Editează">
                                        <i data-lucide="pencil" class="h-4 w-4 inline-block"></i>
                                    </button>
                                    <button onclick="deleteCat(<?= (int)$cat['id'] ?>)" class="text-red-600 hover:text-red-800" title="Șterge">
                                        <i data-lucide="trash-2" class="h-4 w-4 inline-block"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($total === 0): ?>
                    <div class="py-12 text-center text-foreground/50">
                        <p class="text-lg">Nu există categorii pentru filtrele curente.</p>
                        <p class="mt-1 text-sm">Apasă „Importă Categorii Implicite" pentru a încărca cele 8 categorii standard.</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($totalPages > 1): ?>
            <?php
            $pageBase = '?page=';
            $pageSuffix = ($activeType !== '' ? '&type=' . rawurlencode($activeType) : '') . ($searchQ !== '' ? '&q=' . rawurlencode($searchQ) : '');
            ?>
            <div class="col-span-12 mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs opacity-60"><?= (($currentPage - 1) * $perPage + 1) ?>–<?= min($currentPage * $perPage, $total) ?> din <?= $total ?></div>
                <div class="flex flex-wrap items-center gap-1">
                    <?php if ($currentPage > 1): ?><a class="box h-9 min-w-9 rounded-md border px-3 py-2 text-sm text-center" href="<?= $pageBase . ($currentPage - 1) . $pageSuffix ?>">‹</a><?php endif; ?>
                    <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                        <a class="box h-9 min-w-9 rounded-md border px-3 py-2 text-sm text-center <?= $p === $currentPage ? 'bg-primary text-white' : '' ?>" href="<?= $pageBase . $p . $pageSuffix ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($currentPage < $totalPages): ?><a class="box h-9 min-w-9 rounded-md border px-3 py-2 text-sm text-center" href="<?= $pageBase . ($currentPage + 1) . $pageSuffix ?>">›</a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══ Modal Add/Edit Categorie ═══ -->
<div id="catModal" class="categorii-overlay-modal" aria-hidden="true" hidden style="display:none">
    <div class="categorii-overlay-modal__panel" style="width:100%;max-width:560px;background:#fff;border-radius:16px;padding:28px 32px;box-shadow:0 25px 60px rgba(0,0,0,.25);margin:auto;position:relative;top:50%;transform:translateY(-50%);">
        <h3 id="modalTitle" style="font-size:18px;font-weight:700;margin-bottom:18px;color:#1e293b;">Adaugă Categorie</h3>
        <form id="catForm" onsubmit="return saveCat(event)">
            <input type="hidden" id="cat_id" value="">
            <input type="hidden" id="cat_icon_path" value="">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">Label *</label>
                    <input type="text" id="cat_label" required style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">Slug</label>
                    <input type="text" id="cat_slug" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;" placeholder="auto-generat">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">Tip</label>
                    <select id="cat_type" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;">
                        <?php foreach ($types as $val => $lbl): ?>
                            <option value="<?= $val ?>"><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">Icon</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <label style="padding:7px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;cursor:pointer;background:#f9fafb;white-space:nowrap;">
                            Alege fișier
                            <input type="file" id="cat_icon_file" accept=".svg,.png,.jpg,.webp" style="display:none;" onchange="previewIcon(this)">
                        </label>
                        <img id="cat_icon_preview" src="" alt="" style="width:32px;height:32px;display:none;border-radius:4px;object-fit:contain;border:1px solid #e5e7eb;">
                        <span id="cat_icon_name" style="font-size:12px;color:#6b7280;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">Parent ID</label>
                    <input type="number" id="cat_parent" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;" placeholder="gol = root">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">Ordine sortare</label>
                    <input type="number" id="cat_sort" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;" value="0">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">TecDoc ID</label>
                    <input type="number" id="cat_tecdoc" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;" placeholder="opțional">
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                        <input type="checkbox" id="cat_active" checked style="width:18px;height:18px;border-radius:4px;"> Activ
                    </label>
                </div>
            </div>
            <div style="margin-top:14px;">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">Meta (JSON, opțional)</label>
                <textarea id="cat_meta" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;height:60px;resize:vertical;" placeholder='{"count": 1245}'></textarea>
            </div>
            <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" onclick="closeModal()" style="padding:9px 20px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;cursor:pointer;">Anulează</button>
                <button type="submit" style="padding:9px 20px;border:none;border-radius:8px;font-size:14px;font-weight:600;background:#1d4fd8;color:#fff;cursor:pointer;">Salvează</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Modal Referință TecDoc (consultare — import amânat tm_021) ═══ -->
<div id="tecdocModal" class="categorii-overlay-modal" data-tm021="reference-only" aria-hidden="true" hidden style="display:none">
    <div class="categorii-overlay-modal__panel" style="width:100%;max-width:700px;background:#fff;border-radius:16px;padding:28px 32px;box-shadow:0 25px 60px rgba(0,0,0,.25);margin:auto;position:relative;top:50%;transform:translateY(-50%);max-height:85vh;overflow-y:auto;">
        <h3 style="font-size:18px;font-weight:700;margin-bottom:6px;color:#1e293b;">Referință catalog TecDoc</h3>
        <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">Consultă mărci, modele, motorizări și categorii din catalogul TecDoc. Importul în baza site-ului este amânat.</p>
        <div id="tecdoc-reference-notice" data-tm021="deferred-import" style="margin-bottom:18px;padding:12px 14px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;font-size:13px;line-height:1.5;">
            <?= h_cat($tecdocStructureImportNotice) ?>
        </div>

        <!-- Step 1: Selectare vehicul -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">Marcă</label>
                <select id="td_marca" onchange="tdLoadModels()" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;">
                    <option value="0">Alege marca...</option>
                    <option value="2">ALFA ROMEO</option><option value="5">AUDI</option><option value="16">BMW</option>
                    <option value="21">CITROEN</option><option value="139">DACIA</option><option value="35">FIAT</option>
                    <option value="36">FORD</option><option value="45">HONDA</option><option value="183">HYUNDAI</option>
                    <option value="184">KIA</option><option value="72">MAZDA</option><option value="74">MERCEDES-BENZ</option>
                    <option value="80">NISSAN</option><option value="84">OPEL</option><option value="88">PEUGEOT</option>
                    <option value="92">PORSCHE</option><option value="93">RENAULT</option><option value="104">SEAT</option>
                    <option value="106">SKODA</option><option value="109">SUZUKI</option><option value="111">TOYOTA</option>
                    <option value="120">VOLVO</option><option value="121">VW</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">Model</label>
                <select id="td_model" onchange="tdLoadMotor()" disabled style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;">
                    <option value="0">Alege modelul...</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151;">Motorizare</label>
                <select id="td_motor" disabled style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;">
                    <option value="0">Alege motorizarea...</option>
                </select>
            </div>
        </div>

        <div style="margin-bottom:16px;">
            <button type="button" onclick="tdLoadCategories()" id="td_load_btn" style="padding:9px 20px;border:none;border-radius:8px;font-size:14px;font-weight:600;background:#2563eb;color:#fff;cursor:pointer;">Consultă categorii TecDoc</button>
            <span id="td_status" style="margin-left:12px;font-size:13px;color:#6b7280;"></span>
        </div>

        <!-- Lista categorii (doar referință) -->
        <div id="td_categories_list" style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;max-height:340px;overflow-y:auto;display:none;">
            <div style="margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
                <label style="font-size:13px;font-weight:600;color:#374151;">Categorii disponibile (consultare):</label>
            </div>
            <div id="td_cat_items"></div>
        </div>

        <div style="margin-top:20px;display:flex;justify-content:flex-end;gap:10px;">
            <button type="button" onclick="closeTecdocModal()" style="padding:9px 20px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#fff;cursor:pointer;">Închide</button>
        </div>
    </div>
</div>

<script>
const CRUD_URL = '/admin/crudcategorii';
const API_URL = '/admin/api/categorii_endpoint.php';
const TECDOC_URL = '/tecdoc_proxy.php';
const TECDOC_STRUCTURE_IMPORT_ENABLED = <?= $tecdocStructureImportEnabled ? 'true' : 'false' ?>;
const TECDOC_STRUCTURE_IMPORT_NOTICE = <?= json_encode($tecdocStructureImportNotice, JSON_UNESCAPED_UNICODE) ?>;

/* ═══ Icon Preview ═══ */
function previewIcon(input) {
    const preview = document.getElementById('cat_icon_preview');
    const nameEl = document.getElementById('cat_icon_name');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        nameEl.textContent = file.name;
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

/* ═══ Modal Add/Edit ═══ */
function setCategoriiModalOpen(modalId, open) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.toggle('is-open', !!open);
    modal.hidden = !open;
    modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    modal.style.display = open ? 'block' : 'none';
    const anyOpen = document.querySelector('#tecdocModal.is-open, #catModal.is-open');
    document.body.classList.toggle('categorii-modal-open', !!anyOpen);
}

(function () {
    ['catModal', 'tecdocModal'].forEach(function (modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        modal.style.display = 'none';
    });
})();

document.addEventListener('DOMContentLoaded', function () {
    setCategoriiModalOpen('catModal', false);
    setCategoriiModalOpen('tecdocModal', false);
    ['catModal', 'tecdocModal'].forEach(function (modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                setCategoriiModalOpen(modalId, false);
            }
        });
    });
});

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Adaugă Categorie';
    document.getElementById('catForm').reset();
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_icon_path').value = '';
    document.getElementById('cat_active').checked = true;
    document.getElementById('cat_icon_preview').style.display = 'none';
    document.getElementById('cat_icon_name').textContent = '';
    setCategoriiModalOpen('catModal', true);
}

function closeModal() {
    setCategoriiModalOpen('catModal', false);
}

function editCat(cat) {
    document.getElementById('modalTitle').textContent = 'Editează Categorie';
    document.getElementById('cat_id').value = cat.id || '';
    document.getElementById('cat_label').value = cat.label || '';
    document.getElementById('cat_slug').value = cat.slug || '';
    document.getElementById('cat_type').value = cat.type || 'categorie';
    document.getElementById('cat_icon_path').value = cat.icon || '';
    document.getElementById('cat_parent').value = cat.parent_id || '';
    document.getElementById('cat_sort').value = cat.sort_order || '0';
    document.getElementById('cat_tecdoc').value = cat.tecdoc_id || '';
    document.getElementById('cat_active').checked = parseInt(cat.is_active) === 1;
    document.getElementById('cat_meta').value = cat.meta || '';

    const preview = document.getElementById('cat_icon_preview');
    const nameEl = document.getElementById('cat_icon_name');
    if (cat.icon) {
        preview.src = '/' + cat.icon;
        preview.style.display = 'block';
        nameEl.textContent = cat.icon.split('/').pop();
    } else {
        preview.style.display = 'none';
        nameEl.textContent = '';
    }

    setCategoriiModalOpen('catModal', true);
}

async function saveCat(e) {
    e.preventDefault();
    const id = document.getElementById('cat_id').value;
    const fileInput = document.getElementById('cat_icon_file');
    const existingIcon = document.getElementById('cat_icon_path').value;

    let iconPath = existingIcon;

    if (fileInput.files && fileInput.files[0]) {
        const formData = new FormData();
        formData.append('icon', fileInput.files[0]);
        formData.append('action', 'upload_icon');
        const uploadRes = await fetch(API_URL + '?action=upload_icon', { method: 'POST', body: formData });
        const uploadJson = await uploadRes.json();
        if (uploadJson.success) {
            iconPath = uploadJson.path;
        } else {
            alert('Eroare upload icon: ' + (uploadJson.message || 'necunoscută'));
            return false;
        }
    }

    const payload = {
        type_product: id ? 'edit' : 'add',
        id: id || undefined,
        label: document.getElementById('cat_label').value,
        slug: document.getElementById('cat_slug').value,
        type: document.getElementById('cat_type').value,
        icon: iconPath,
        parent_id: document.getElementById('cat_parent').value || null,
        sort_order: document.getElementById('cat_sort').value || '0',
        tecdoc_id: document.getElementById('cat_tecdoc').value || null,
        is_active: document.getElementById('cat_active').checked ? 1 : 0,
        meta: document.getElementById('cat_meta').value || null,
    };

    const res = await fetch(CRUD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.success) {
        location.reload();
    } else {
        alert(json.message || 'Eroare la salvare.');
    }
    return false;
}

/* ═══ CRUD actions ═══ */
async function deleteCat(id) {
    if (!confirm('Sigur vrei să ștergi această categorie?')) return;
    const res = await fetch(CRUD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type_product: 'delete', id })
    });
    const json = await res.json();
    if (json.success) location.reload();
    else alert(json.message || 'Eroare la ștergere.');
}

async function toggleCat(id, newValue) {
    const res = await fetch(CRUD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type_product: 'toggle', id, is_active: newValue })
    });
    const json = await res.json();
    if (json.success) location.reload();
    else alert(json.message || 'Eroare.');
}

async function importDefaults() {
    if (!confirm('Importă cele 8 categorii standard (Frâne, Filtre, etc.)?')) return;
    const res = await fetch(CRUD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type_product: 'import_defaults' })
    });
    const json = await res.json();
    alert(json.message || 'Done');
    if (json.success) location.reload();
}

/* ═══ TecDoc Reference Modal (import amânat tm_021) ═══ */
function openTecdocModal() {
    setCategoriiModalOpen('tecdocModal', true);
}
function closeTecdocModal() {
    setCategoriiModalOpen('tecdocModal', false);
}

async function tdLoadModels() {
    const manuId = document.getElementById('td_marca').value;
    const selModel = document.getElementById('td_model');
    const selMotor = document.getElementById('td_motor');
    selModel.innerHTML = '<option value="0">Se încarcă...</option>';
    selMotor.innerHTML = '<option value="0">Alege motorizarea...</option>';
    selMotor.disabled = true;

    if (manuId === '0') { selModel.disabled = true; selModel.innerHTML = '<option value="0">Alege modelul...</option>'; return; }
    selModel.disabled = false;

    try {
        const res = await fetch(TECDOC_URL + '?action=get_models&manuId=' + manuId);
        const data = await res.json();
        selModel.innerHTML = '<option value="0">Alege modelul...</option>';
        if (data && Array.isArray(data.models)) {
            data.models.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.modelId;
                const yf = m.modelYearFrom ? String(m.modelYearFrom).substring(0, 4) : '';
                const yt = m.modelYearTo ? String(m.modelYearTo).substring(0, 4) : 'Prezent';
                opt.textContent = m.modelName + ' (' + yf + ' - ' + yt + ')';
                selModel.appendChild(opt);
            });
        }
    } catch (err) {
        selModel.innerHTML = '<option value="0">Eroare la server</option>';
    }
}

async function tdLoadMotor() {
    const modelId = document.getElementById('td_model').value;
    const selMotor = document.getElementById('td_motor');
    selMotor.innerHTML = '<option value="0">Se încarcă...</option>';

    if (modelId === '0') { selMotor.disabled = true; selMotor.innerHTML = '<option value="0">Alege motorizarea...</option>'; return; }
    selMotor.disabled = false;

    try {
        const res = await fetch(TECDOC_URL + '?action=get_vehicles&modelId=' + modelId);
        const data = await res.json();
        selMotor.innerHTML = '<option value="0">Alege motorizarea...</option>';
        if (data && Array.isArray(data.vehicles)) {
            data.vehicles.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.carId;
                opt.textContent = (v.typeName || v.typeEngineName || '') + ' (' + v.powerPs + ' CP / ' + v.powerKw + ' KW) - ' + (v.fuelType || '');
                selMotor.appendChild(opt);
            });
        } else if (data && data.vehicleTypeDetails) {
            const v = data.vehicleTypeDetails;
            const opt = document.createElement('option');
            opt.value = v.carId || modelId;
            opt.textContent = (v.typeEngineName || '') + ' (' + v.powerPs + ' CP / ' + v.powerKw + ' KW) - ' + (v.fuelType || '');
            selMotor.appendChild(opt);
        }
    } catch (err) {
        selMotor.innerHTML = '<option value="0">Eroare</option>';
    }
}

async function tdLoadCategories() {
    const carId = document.getElementById('td_motor').value;
    const statusEl = document.getElementById('td_status');
    const listEl = document.getElementById('td_categories_list');
    const itemsEl = document.getElementById('td_cat_items');

    if (!carId || carId === '0') {
        statusEl.textContent = 'Selectează marca, modelul și motorizarea mai întâi.';
        statusEl.style.color = '#dc2626';
        return;
    }

    statusEl.textContent = 'Se încarcă categoriile din TecDoc...';
    statusEl.style.color = '#6b7280';
    itemsEl.innerHTML = '';
    listEl.style.display = 'none';

    try {
        const res = await fetch(TECDOC_URL + '?action=get_parts&carId=' + carId);
        const data = await res.json();

        if (!data || !data.categories || typeof data.categories !== 'object') {
            statusEl.textContent = 'Nu s-au găsit categorii pentru această motorizare.';
            statusEl.style.color = '#dc2626';
            return;
        }

        const categories = data.categories;
        let html = '';
        let count = 0;

        for (const [id, cat] of Object.entries(categories)) {
            const label = cat.text || cat.assemblyGroupName || cat.name || 'Categorie #' + id;
            const cleanLabel = label.replace(/\s*\([^)]*\)\s*$/, '').trim();
            html += '<div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;border-bottom:1px solid #f3f4f6;">' +
                '<span style="font-size:14px;color:#1e293b;">' + cleanLabel + '</span>' +
                '<span style="font-size:11px;color:#9ca3af;margin-left:auto;">ID: ' + id + '</span>' +
                '</div>';
            count++;

            if (cat.children && typeof cat.children === 'object') {
                for (const [childId, child] of Object.entries(cat.children)) {
                    const childLabel = child.text || child.assemblyGroupName || child.name || 'Sub #' + childId;
                    const cleanChild = childLabel.replace(/\s*\([^)]*\)\s*$/, '').trim();
                    html += '<div style="display:flex;align-items:center;gap:10px;padding:6px 10px 6px 32px;border-bottom:1px solid #f9fafb;">' +
                        '<span style="font-size:13px;color:#4b5563;">↳ ' + cleanChild + '</span>' +
                        '<span style="font-size:11px;color:#9ca3af;margin-left:auto;">ID: ' + childId + '</span>' +
                        '</div>';
                    count++;
                }
            }
        }

        itemsEl.innerHTML = html;
        listEl.style.display = 'block';
        statusEl.textContent = count + ' categorii găsite (doar referință).';
        statusEl.style.color = '#059669';

    } catch (err) {
        statusEl.textContent = 'Eroare la încărcarea categoriilor: ' + err.message;
        statusEl.style.color = '#dc2626';
    }
}

/* ═══ Căutare server-side ═══ */
(function () {
  const searchBox = document.getElementById('searchBox');
  if (!searchBox) return;
  let timer;
  searchBox.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      const q = searchBox.value.trim();
      const params = new URLSearchParams(window.location.search);
      params.set('page', '1');
      if (q) params.set('q', q); else params.delete('q');
      window.location.search = params.toString();
    }, 400);
  });
})();
</script>
