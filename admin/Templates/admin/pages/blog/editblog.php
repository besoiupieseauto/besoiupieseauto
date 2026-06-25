<?php
declare(strict_types=1);

use Evasystem\Controllers\Blog\BlogService;

function blog_edit_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$id = (int) ($_GET['id'] ?? 0);
$service = new BlogService();
$post = $id > 0 ? $service->getById($id) : null;

if (!$post) {
    echo '<div class="box p-8 text-center">Articol inexistent. <a href="/admin/blog">Înapoi la listă</a></div>';
    return;
}
?>
<div class="-mt-5">
    <div>
        <h2 class="mt-10 text-lg font-medium">Blog — editează articol</h2>
        <p class="mt-1 text-sm text-foreground/60"><?= blog_edit_h($post['title'] ?? '') ?></p>

        <form id="blog-form" class="mt-5 box grid grid-cols-12 gap-4 p-5">
            <input type="hidden" name="id" value="<?= blog_edit_h((string) ($post['id'] ?? '')) ?>">

            <div class="col-span-12 md:col-span-8">
                <label class="mb-1 block text-sm font-medium">Titlu *</label>
                <input type="text" name="title" required value="<?= blog_edit_h($post['title'] ?? '') ?>" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
            </div>
            <div class="col-span-12 md:col-span-4">
                <label class="mb-1 block text-sm font-medium">Slug</label>
                <input type="text" name="slug" value="<?= blog_edit_h($post['slug'] ?? '') ?>" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
            </div>
            <div class="col-span-12 md:col-span-4">
                <label class="mb-1 block text-sm font-medium">Tag / categorie</label>
                <input type="text" name="tag" value="<?= blog_edit_h($post['tag'] ?? 'Articole') ?>" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
            </div>
            <div class="col-span-12 md:col-span-4">
                <label class="mb-1 block text-sm font-medium">Imagine (URL)</label>
                <input type="text" name="featured_image" value="<?= blog_edit_h($post['featured_image'] ?? '') ?>" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
            </div>
            <div class="col-span-12 md:col-span-4 flex items-end">
                <label class="inline-flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="is_published" value="1" <?= !empty($post['is_published']) ? 'checked' : '' ?>> Publicat
                </label>
            </div>
            <div class="col-span-12">
                <label class="mb-1 block text-sm font-medium">Rezumat</label>
                <textarea name="excerpt" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm"><?= blog_edit_h($post['excerpt'] ?? '') ?></textarea>
            </div>
            <div class="col-span-12">
                <label class="mb-1 block text-sm font-medium">Conținut articol</label>
                <textarea name="body_html" rows="12" class="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"><?= blog_edit_h($post['body_html'] ?? '') ?></textarea>
            </div>
            <div class="col-span-12 flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-medium text-white">Salvează</button>
                <a href="/admin/blog" class="inline-flex items-center rounded-lg border px-5 py-2 text-sm font-medium">Înapoi</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('blog-form')?.addEventListener('submit', async function (event) {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(event.target).entries());
    payload.type_product = 'edit';
    payload.is_published = event.target.querySelector('[name=is_published]')?.checked ? 1 : 0;

    const response = await fetch('/admin/crudblog', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    const result = await response.json();
    alert(result.message || 'Gata.');
    if (result.success) window.location.href = '/admin/blog';
});
</script>
