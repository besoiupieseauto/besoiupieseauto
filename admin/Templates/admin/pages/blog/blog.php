<?php
declare(strict_types=1);

use Evasystem\Controllers\Blog\BlogService;

function blog_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$service = new BlogService();
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$paged = $service->getPaginated($page, $perPage);
$posts = $paged['items'];
$total = (int) ($paged['total'] ?? count($posts));
$totalPages = (int) ($paged['total_pages'] ?? 1);
$currentPage = (int) ($paged['page'] ?? 1);
?>
<div class="-mt-5">
    <div>
        <h2 class="mt-10 text-lg font-medium">Blog — articole</h2>
        <p class="mt-1 text-sm text-foreground/60">Gestionează articolele publicate pe pagina Blog.</p>

        <div class="mt-5 grid grid-cols-12 gap-x-6 gap-y-4">
            <div class="col-span-12 flex flex-wrap items-center gap-2">
                <a href="/admin/addblog" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">
                    <i data-lucide="plus" class="h-4 w-4"></i> Articol nou
                </a>
                <span class="ml-auto text-sm opacity-70"><?= $total ?> articole · pagina <?= $currentPage ?>/<?= max(1, $totalPages) ?></span>
            </div>

            <div class="col-span-12 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-foreground/60">
                        <tr>
                            <th class="px-3 py-2">Titlu</th>
                            <th class="px-3 py-2">Tag</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Publicat</th>
                            <th class="px-3 py-2 text-right">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($posts === []): ?>
                            <tr><td colspan="5" class="px-3 py-8 text-center opacity-60">Nu există articole. Creează primul articol.</td></tr>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <tr class="border-b">
                                    <td class="px-3 py-3 font-medium"><?= blog_h($post['title'] ?? '') ?></td>
                                    <td class="px-3 py-3"><?= blog_h($post['tag'] ?? '') ?></td>
                                    <td class="px-3 py-3">
                                        <?php if (!empty($post['is_published'])): ?>
                                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Publicat</span>
                                        <?php else: ?>
                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-3 text-xs opacity-70"><?= blog_h($post['published_at'] ?? $post['created_at'] ?? '') ?></td>
                                    <td class="px-3 py-3 text-right">
                                        <a href="/admin/editblog?id=<?= blog_h((string) ($post['id'] ?? '')) ?>" class="text-primary font-semibold">Editează</a>
                                        <button type="button" class="ml-3 text-danger font-semibold" data-delete-blog="<?= blog_h((string) ($post['id'] ?? '')) ?>">Șterge</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="col-span-12 mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs opacity-60">
                    <?= $total ? (($currentPage - 1) * $perPage + 1) . '–' . min($currentPage * $perPage, $total) . ' din ' . $total : '0 articole' ?>
                </div>
                <div class="flex flex-wrap items-center gap-1">
                    <?php if ($currentPage > 1): ?>
                        <a class="box h-9 min-w-9 rounded-md border px-3 py-2 text-sm text-center" href="?page=<?= $currentPage - 1 ?>">‹</a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                        <a class="box h-9 min-w-9 rounded-md border px-3 py-2 text-sm text-center <?= $p === $currentPage ? 'bg-primary text-white' : '' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a class="box h-9 min-w-9 rounded-md border px-3 py-2 text-sm text-center" href="?page=<?= $currentPage + 1 ?>">›</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-delete-blog]').forEach(function (button) {
    button.addEventListener('click', async function () {
        if (!confirm('Ștergi acest articol?')) return;
        const id = button.getAttribute('data-delete-blog');
        const response = await fetch('/admin/crudblog', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type_product: 'delete', id: id }),
        });
        const result = await response.json();
        alert(result.message || 'Gata.');
        if (result.success) location.reload();
    });
});
</script>
