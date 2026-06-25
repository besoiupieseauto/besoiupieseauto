<?php
declare(strict_types=1);

/** @var WebsiteService $service */
/** @var array<int, array<string, mixed>> $pages */

if (!function_exists('ws_h')) {
    function ws_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$liveRegistry = site_live_pages_registry();
$builtinSlugs = site_live_builtin_slugs();
?>
<div class="-mt-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="mt-10 text-lg font-medium">Pagini site</h2>
            <p class="mt-1 text-sm text-foreground/60">Adaugă, editează, dezactivează sau șterge paginile publice.</p>
        </div>
        <button type="button" id="wsAddPageBtn" class="inline-flex items-center gap-2 rounded-lg bg-[#1abc9c] px-4 py-2 text-sm font-medium text-white hover:bg-[#16a085]">
            <i data-lucide="plus" class="h-4 w-4"></i> Pagină nouă
        </button>
    </div>

    <div class="mt-5 overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="border-b bg-muted/40 text-left text-xs uppercase tracking-wide text-foreground/60">
                <tr>
                    <th class="px-4 py-3">Pagină</th>
                    <th class="px-4 py-3">Adresă</th>
                    <th class="px-4 py-3">Stare</th>
                    <th class="px-4 py-3 text-right">Acțiuni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page): ?>
                    <?php
                    $id = (int) ($page['id'] ?? 0);
                    $slug = (string) ($page['slug'] ?? '');
                    $label = site_page_display_label($slug, (string) ($page['label'] ?? $slug));
                    $active = (int) ($page['is_active'] ?? 1) === 1;
                    $isLive = !empty($liveRegistry[$slug]['live']);
                    $isBuiltin = in_array($slug, $builtinSlugs, true) || $slug === 'global';
                    $isProtected = in_array($slug, ['home', 'global'], true);
                    $canDelete = !$isProtected && !$isBuiltin;
                    $editUrl = $isLive
                        ? '/admin/website?tab=' . rawurlencode($slug)
                        : '/admin/website?tab=' . rawurlencode($slug) . '&mode=form';
                    $publicUrl = $slug === 'home' ? '/' : '/' . rawurlencode($slug);
                    if ($slug === 'about') {
                        $publicUrl = '/despre';
                    }
                    ?>
                    <tr class="border-b last:border-0" data-page-id="<?= $id ?>" data-page-slug="<?= ws_h($slug) ?>">
                        <td class="px-4 py-3 font-medium"><?= ws_h($label) ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-foreground/70"><?= ws_h($slug) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($active): ?>
                                <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Activă</span>
                            <?php else: ?>
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Dezactivată</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="<?= ws_h($editUrl) ?>" class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs hover:bg-muted">
                                    <i data-lucide="pen-line" class="h-3.5 w-3.5"></i> Editează
                                </a>
                                <?php if ($slug !== 'global'): ?>
                                <a href="<?= ws_h($publicUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs hover:bg-muted">
                                    <i data-lucide="external-link" class="h-3.5 w-3.5"></i> Vezi
                                </a>
                                <?php endif; ?>
                                <?php if ($slug !== 'global'): ?>
                                <button type="button" class="ws-toggle-page inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-xs hover:bg-muted" data-id="<?= $id ?>">
                                    <?= $active ? 'Dezactivează' : 'Activează' ?>
                                </button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                <button type="button" class="ws-delete-page inline-flex items-center gap-1 rounded-md border border-red-200 px-2.5 py-1.5 text-xs text-red-700 hover:bg-red-50" data-id="<?= $id ?>" data-label="<?= ws_h($label) ?>">
                                    Șterge
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="wsAddPageModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/40 p-4" aria-hidden="true">
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <h3 class="text-base font-semibold">Pagină nouă</h3>
            <p class="mt-1 text-sm text-foreground/60">Pagina va fi editabilă în constructorul live.</p>
            <form id="wsAddPageForm" class="mt-4 space-y-3">
                <div>
                    <label class="mb-1 block text-sm font-medium">Nume pagină</label>
                    <input type="text" name="label" required class="h-10 w-full rounded-md border px-3 text-sm" placeholder="Ex: Promoții vară">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Adresă (slug)</label>
                    <input type="text" name="slug" class="h-10 w-full rounded-md border px-3 text-sm font-mono" placeholder="promotii-vara">
                    <p class="mt-1 text-xs text-foreground/50">Lăsați gol pentru generare automată din nume.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Titlu pagină (SEO)</label>
                    <input type="text" name="title" class="h-10 w-full rounded-md border px-3 text-sm" placeholder="Opțional">
                </div>
                <p id="wsAddPageStatus" class="text-sm"></p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" id="wsAddPageCancel" class="rounded-md border px-4 py-2 text-sm hover:bg-muted">Anulează</button>
                    <button type="submit" class="rounded-md bg-[#1abc9c] px-4 py-2 text-sm font-medium text-white">Creează</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('wsAddPageModal');
    const form = document.getElementById('wsAddPageForm');
    const statusEl = document.getElementById('wsAddPageStatus');

    function postAction(type, payload) {
        return fetch('/admin/crudwebsite', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ type_product: type }, payload || {})),
        }).then(function (r) { return r.json(); });
    }

    document.getElementById('wsAddPageBtn')?.addEventListener('click', function () {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        form?.reset();
        if (statusEl) statusEl.textContent = '';
    });

    function closeModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('wsAddPageCancel')?.addEventListener('click', closeModal);
    modal?.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    form?.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (statusEl) {
            statusEl.textContent = 'Se creează...';
            statusEl.style.color = '';
        }
        const data = Object.fromEntries(new FormData(form).entries());
        try {
            const result = await postAction('create', data);
            if (result.success && result.slug) {
                window.location.href = '/admin/website?tab=' + encodeURIComponent(result.slug);
                return;
            }
            if (statusEl) {
                statusEl.textContent = result.message || 'Eroare.';
                statusEl.style.color = '#dc2626';
            }
        } catch (err) {
            if (statusEl) {
                statusEl.textContent = 'Eroare de rețea.';
                statusEl.style.color = '#dc2626';
            }
        }
    });

    document.querySelectorAll('.ws-toggle-page').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const id = parseInt(btn.getAttribute('data-id') || '0', 10);
            if (!id) return;
            btn.disabled = true;
            try {
                const result = await postAction('toggle_active', { id: id });
                if (result.success) {
                    window.location.reload();
                    return;
                }
                alert(result.message || 'Eroare.');
            } catch (e) {
                alert('Eroare de rețea.');
            } finally {
                btn.disabled = false;
            }
        });
    });

    document.querySelectorAll('.ws-delete-page').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const id = parseInt(btn.getAttribute('data-id') || '0', 10);
            const label = btn.getAttribute('data-label') || 'pagina';
            if (!id) return;
            if (!confirm('Ștergi definitiv pagina „' + label + '”?')) return;
            btn.disabled = true;
            try {
                const result = await postAction('delete', { id: id });
                if (result.success) {
                    window.location.reload();
                    return;
                }
                alert(result.message || 'Eroare.');
            } catch (e) {
                alert('Eroare de rețea.');
            } finally {
                btn.disabled = false;
            }
        });
    });
})();
</script>
