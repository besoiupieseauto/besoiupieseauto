<?php

$accountName = htmlspecialchars((string) ($_SESSION['user_name'] ?? ''), ENT_QUOTES, 'UTF-8');
$accountLogin = htmlspecialchars((string) ($_SESSION['user_login'] ?? ''), ENT_QUOTES, 'UTF-8');
$accountRole = htmlspecialchars((string) ($_SESSION['role'] ?? 'guest'), ENT_QUOTES, 'UTF-8');
$userId = (int) ($_SESSION['user_id'] ?? 0);
?>
<div class="-mt-5">
    <div class="mt-10">
        <h2 class="text-lg font-medium">Profil administrator</h2>
        <p class="mt-1 text-sm opacity-70">Datele contului tău de acces în panoul admin.</p>
    </div>

    <div class="mt-5 box p-5 grid grid-cols-12 gap-4 max-w-3xl">
        <div class="col-span-12 md:col-span-6">
            <label class="mb-1 block text-sm font-medium">Nume afișat</label>
            <input type="text" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" value="<?= $accountName ?>" readonly>
        </div>
        <div class="col-span-12 md:col-span-6">
            <label class="mb-1 block text-sm font-medium">Login / email</label>
            <input type="text" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" value="<?= $accountLogin ?>" readonly>
        </div>
        <div class="col-span-12 md:col-span-6">
            <label class="mb-1 block text-sm font-medium">Rol</label>
            <input type="text" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" value="<?= $accountRole ?>" readonly>
        </div>
        <div class="col-span-12 md:col-span-6">
            <label class="mb-1 block text-sm font-medium">ID sesiune</label>
            <input type="text" class="h-10 w-full rounded-md border bg-background px-3 py-2 text-sm" value="<?= $userId > 0 ? (string) $userId : '—' ?>" readonly>
        </div>
        <div class="col-span-12 flex flex-wrap gap-3 pt-2">
            <a href="/admin/reset-password" class="inline-flex h-10 items-center rounded-md border px-4 text-sm font-medium">Schimbă parola</a>
            <a href="/admin/users" class="inline-flex h-10 items-center rounded-md border px-4 text-sm font-medium">Utilizatori admin</a>
        </div>
    </div>
</div>
