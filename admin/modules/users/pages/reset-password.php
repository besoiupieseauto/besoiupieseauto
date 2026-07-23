<?php

$userId = (int) ($_SESSION['user_id'] ?? 0);
$accountLogin = htmlspecialchars((string) ($_SESSION['user_login'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="-mt-5">
    <div class="mt-10">
        <h2 class="text-lg font-medium">Resetare parolă</h2>
        <p class="mt-1 text-sm opacity-70">Schimbă parola contului tău de administrator<?= $accountLogin !== '' ? ' (' . $accountLogin . ')' : '' ?>.</p>
    </div>

    <div class="mt-5 box p-5 max-w-xl">
        <form id="admin-reset-password-form" class="grid grid-cols-12 gap-4">
            <input type="hidden" name="type_product" value="add">
            <input type="hidden" name="ridusers" value="<?= $userId > 0 ? (string) $userId : '' ?>">
            <input type="hidden" name="login" value="<?= $accountLogin ?>">

            <div class="col-span-12">
                <label class="mb-1 block text-sm font-medium">Parolă nouă</label>
                <input type="password" name="password" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" minlength="8" required placeholder="Min. 8 caractere, literă mare, mică și cifră">
            </div>
            <div class="col-span-12">
                <label class="mb-1 block text-sm font-medium">Confirmă parola nouă</label>
                <input type="password" id="admin-reset-password-confirm" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" minlength="8" required>
            </div>
            <div class="col-span-12 flex items-center gap-3">
                <button type="submit" class="inline-flex h-10 items-center rounded-md bg-primary px-5 text-sm font-medium text-white">Salvează parola</button>
                <span id="admin-reset-password-status" class="text-sm opacity-70"></span>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';
    var form = document.getElementById('admin-reset-password-form');
    if (!form) return;

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        var status = document.getElementById('admin-reset-password-status');
        var pwd = form.querySelector('[name="password"]');
        var confirm = document.getElementById('admin-reset-password-confirm');
        if (!pwd || !confirm || pwd.value !== confirm.value) {
            status.textContent = 'Parolele nu coincid.';
            status.style.color = '#dc2626';
            return;
        }

        status.textContent = 'Se salvează...';
        status.style.color = '';

        try {
            var payload = Object.fromEntries(new FormData(form).entries());
            var response = await fetch('/admin/addusersadd', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var result = await response.json();
            status.textContent = result.message || (result.success ? 'Parola a fost actualizată.' : 'Eroare.');
            status.style.color = result.success ? '#059669' : '#dc2626';
            if (result.success) {
                pwd.value = '';
                confirm.value = '';
            }
        } catch (error) {
            status.textContent = 'Eroare de rețea.';
            status.style.color = '#dc2626';
        }
    });
})();
</script>
