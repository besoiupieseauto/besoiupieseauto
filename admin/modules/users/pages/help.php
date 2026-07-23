<?php

use Besoiu\Core\Module\ModuleGate;

?>
<div class="-mt-5">
    <div class="mt-10">
        <h2 class="text-lg font-medium">Ajutor — panou admin</h2>
        <p class="mt-1 text-sm opacity-70">Resurse rapide pentru utilizarea platformei Besoiu Piese Auto. Funcționalitățile suplimentare se activează din <a class="text-primary hover:underline" href="/admin/settings">Setări → Module</a>.</p>
    </div>

    <div class="mt-5 grid grid-cols-12 gap-4">
        <div class="col-span-12 md:col-span-6 box p-5">
            <h3 class="text-sm font-bold uppercase tracking-wide opacity-70 mb-3">Secțiuni disponibile</h3>
            <ul class="space-y-2 text-sm">
                <li><a class="text-primary hover:underline" href="/admin/dashboard">Dashboard</a> — sumar activitate</li>
                <?php if (ModuleGate::slugAllowed('users')): ?>
                <li><a class="text-primary hover:underline" href="/admin/users">Utilizatori</a> — conturi admin</li>
                <?php endif; ?>
                <?php if (ModuleGate::slugAllowed('furnizori')): ?>
                <li><a class="text-primary hover:underline" href="/admin/suppliers">Furnizori</a> — listă furnizori + panoul global <em>Adaugă logică</em></li>
                <?php endif; ?>
                <?php if (ModuleGate::slugAllowed('clienti')): ?>
                <li><a class="text-primary hover:underline" href="/admin/clienti">Clienți</a> — baza de clienți</li>
                <?php endif; ?>
                <?php if (ModuleGate::slugAllowed('supplier-search')): ?>
                <li><a class="text-primary hover:underline" href="/admin/supplier-search">Căutare furnizori</a> — comparare prețuri</li>
                <?php endif; ?>
                <?php if (ModuleGate::slugAllowed('scraper')): ?>
                <li><a class="text-primary hover:underline" href="/admin/scraper">Scraper imagini</a> — planuri și joburi</li>
                <?php endif; ?>
                <?php if (ModuleGate::slugAllowed('scraper-web')): ?>
                <li><a class="text-primary hover:underline" href="/admin/scraper-web">Scraper web</a> — import din surse web</li>
                <?php endif; ?>
                <li><a class="text-primary hover:underline" href="/admin/settings">Setări</a> — module, permisiuni, sistem</li>
            </ul>
        </div>
        <div class="col-span-12 md:col-span-6 box p-5">
            <?php if (ModuleGate::slugAllowed('furnizori')): ?>
            <h3 class="text-sm font-bold uppercase tracking-wide opacity-70 mb-3">Formare preț furnizori</h3>
            <ol class="list-decimal list-inside space-y-2 text-sm opacity-80 mb-4">
                <li>Meniu stânga → <strong>Furnizori</strong> → <strong>Adaugă logică</strong></li>
                <li>Configurează ordinea de scanare, furnizorii omiși și verificările brand / stoc / preț</li>
                <li>Pe fiecare card: <strong>Formare preț</strong> deschide adaosul local al furnizorului</li>
            </ol>
            <?php endif; ?>
            <h3 class="text-sm font-bold uppercase tracking-wide opacity-70 mb-3">Suport</h3>
            <p class="text-sm opacity-80 mb-3">Pentru probleme tehnice sau acces restricționat, contactează echipa de suport.</p>
            <div class="flex flex-col gap-2 text-sm">
                <a class="text-primary hover:underline" href="mailto:contact@besoiupieseauto.ro">contact@besoiupieseauto.ro</a>
                <a class="text-primary hover:underline" href="tel:+40726498573">0726 498 573</a>
                <a class="text-primary hover:underline" href="<?= htmlspecialchars(\Besoiu\Core\AdminUrl::publicSiteUrl('/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Deschide site-ul public</a>
            </div>
        </div>
    </div>
</div>
