<?php
declare(strict_types=1);

function blog_form_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<div class="-mt-5">
    <div>
        <h2 class="mt-10 text-lg font-medium">Blog — articol nou</h2>
        <p class="mt-1 text-sm text-foreground/60">Completează detaliile articolului.</p>

        <form id="blog-form" class="mt-5 box grid grid-cols-12 gap-4 p-5">
            <div class="col-span-12 md:col-span-8">
                <label class="mb-1 block text-sm font-medium">Titlu *</label>
                <input type="text" name="title" required class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
            </div>
            <div class="col-span-12 md:col-span-4">
                <label class="mb-1 block text-sm font-medium">Slug</label>
                <input type="text" name="slug" placeholder="auto-generat" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
            </div>
            <div class="col-span-12 md:col-span-4">
                <label class="mb-1 block text-sm font-medium">Tag / categorie</label>
                <input type="text" name="tag" value="Articole" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
            </div>
            <div class="col-span-12 md:col-span-4">
                <label class="mb-1 block text-sm font-medium">Imagine (URL)</label>
                <input type="text" name="featured_image" placeholder="assets/images/..." class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
            </div>
            <div class="col-span-12 md:col-span-4 flex items-end">
                <label class="inline-flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="is_published" value="1"> Publică imediat
                </label>
            </div>
            <div class="col-span-12">
                <label class="mb-1 block text-sm font-medium">Rezumat (afișat în listă)</label>
                <textarea name="excerpt" rows="3" class="w-full rounded-md border bg-background px-3 py-2 text-sm"></textarea>
            </div>
            <div class="col-span-12">
                <label class="mb-1 block text-sm font-medium">Conținut articol (HTML permis)</label>
                <textarea name="body_html" rows="12" class="w-full rounded-md border bg-background px-3 py-2 text-sm font-mono"></textarea>
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
    payload.type_product = 'add';
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
