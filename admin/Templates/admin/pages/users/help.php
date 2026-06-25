<div class="-mt-5">
    <div class="mt-10">
        <h2 class="text-lg font-medium">Ajutor — panou admin</h2>
        <p class="mt-1 text-sm opacity-70">Resurse rapide pentru utilizarea platformei Besoiu Piese Auto.</p>
    </div>

    <div class="mt-5 grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-6 box p-5">
            <h3 class="text-sm font-bold uppercase tracking-wide opacity-70 mb-3">Secțiuni frecvente</h3>
            <ul class="space-y-2 text-sm">
                <li><a class="text-primary hover:underline" href="/admin/dashboard">Dashboard</a> — sumar activitate</li>
                <li><a class="text-primary hover:underline" href="/admin/website">Website CMS</a> — texte site (telefon, header, footer)</li>
                <li><a class="text-primary hover:underline" href="/admin/product">Produse</a> — catalog și stoc</li>
                <li><a class="text-primary hover:underline" href="/admin/orders">Comenzi</a> — comenzi clienți</li>
                <li><a class="text-primary hover:underline" href="/admin/users">Utilizatori</a> — conturi admin</li>
                <li><a class="text-primary hover:underline" href="/admin/suppliers">Furnizori</a> — listă furnizori + panoul global <em>Adaugă logică</em> (ordine scanare, omit, brand/stoc/preț)</li>
                <li><a class="text-primary hover:underline" href="/admin/adaoscomercial">Adaus comercial</a> — reguli de adaos pe produse (categorie, brand, interval preț)</li>
            </ul>
        </div>
        <div class="col-span-12 md:col-span-6 box p-5">
            <h3 class="text-sm font-bold uppercase tracking-wide opacity-70 mb-3">Formare preț furnizori</h3>
            <ol class="list-decimal list-inside space-y-2 text-sm opacity-80 mb-4">
                <li>Meniu stânga → <strong>Furnizori</strong> → <strong>Adaugă logică</strong> (sau bannerul de pe lista furnizori)</li>
                <li>Configurează ordinea de scanare, furnizorii omiși și verificările brand / stoc / preț, apoi <strong>Salvează logica</strong></li>
                <li>Pe fiecare card: <strong>Formare preț</strong> deschide adaosul local al furnizorului (% sau lei, rotunjire)</li>
                <li>Reguli de adaos pe catalog (produse): <a class="text-primary hover:underline" href="/admin/adaoscomercial">Produse → Adaos comercial</a></li>
            </ol>
            <h3 class="text-sm font-bold uppercase tracking-wide opacity-70 mb-3">Suport</h3>
            <p class="text-sm opacity-80 mb-3">Pentru probleme tehnice sau acces restricționat, contactează echipa de suport.</p>
            <div class="flex flex-col gap-2 text-sm">
                <a class="text-primary hover:underline" href="mailto:contact@besoiupieseauto.ro">contact@besoiupieseauto.ro</a>
                <a class="text-primary hover:underline" href="tel:+40726498573">0726 498 573</a>
                <a class="text-primary hover:underline" href="<?= htmlspecialchars(\Evasystem\Core\AdminUrl::publicSiteUrl('/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Deschide site-ul public</a>
            </div>
        </div>
    </div>
</div>
