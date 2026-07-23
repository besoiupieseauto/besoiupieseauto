<div>
    <div id="users-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>
    <div class="mt-10 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-medium">Utilizatori admin</h2>
            <p class="mt-1 text-sm opacity-70">Conturi multi-user (`users_connect`) cu roluri și permisiuni din <code>role_nav</code>.</p>
        </div>
        <a href="/admin/reg" class="box h-10 inline-flex items-center rounded-md border px-4 text-sm">+ Cont nou</a>
    </div>
    <div class="mt-5 overflow-auto box p-5">
        <div id="users-status" class="mb-3 text-xs opacity-60">—</div>
        <table class="w-full text-sm">
            <thead class="border-b bg-foreground/5">
            <tr>
                <th class="px-3 py-2 text-left">ID</th>
                <th class="px-3 py-2 text-left">Login / Email</th>
                <th class="px-3 py-2 text-left">Nume</th>
                <th class="px-3 py-2 text-center">Rol</th>
                <th class="px-3 py-2 text-center">Status</th>
            </tr>
            </thead>
            <tbody id="users-body"></tbody>
        </table>
    </div>
</div>
<script>
(function () {
    'use strict';
    const tbody = document.getElementById('users-body');
    const statusEl = document.getElementById('users-status');

    const roleLabels = {
        super_ambassador: 'Super ambassador',
        manager: 'Manager',
        regional_ambassador: 'Regional',
        executive: 'Executive',
        operator: 'Operator'
    };

    async function load() {
        tbody.innerHTML = BpaAsync.skeletonRows(5, 5);
        try {
            const result = await BpaAsync.fetchJson('/admin/api/admin_hub_endpoint.php?action=users&per_page=100');
            const rows = result.data || [];
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="px-3 py-6 text-center opacity-70">Niciun utilizator.</td></tr>';
            } else {
                tbody.innerHTML = rows.map((u) => {
                    const role = u.role || '-';
                    const roleLabel = roleLabels[role] || role;
                    return `
                    <tr class="border-b">
                        <td class="px-3 py-2">${BpaAsync.escapeHtml(String(u.id || u.randomn_id || ''))}</td>
                        <td class="px-3 py-2">${BpaAsync.escapeHtml(u.login || u.email || '')}</td>
                        <td class="px-3 py-2">${BpaAsync.escapeHtml(u.fullname || u.nikname || u.name || '')}</td>
                        <td class="px-3 py-2 text-center"><span class="rounded-full border px-2 py-0.5 text-xs">${BpaAsync.escapeHtml(roleLabel)}</span></td>
                        <td class="px-3 py-2 text-center">${BpaAsync.escapeHtml(String(u.status ?? '-'))}</td>
                    </tr>`;
                }).join('');
            }
            statusEl.textContent = rows.length + ' utilizatori';
        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-3 py-6 text-center text-danger">' +
                BpaAsync.escapeHtml(error.message) + '</td></tr>';
        }
    }

    BpaAsync.defer(load);
})();
</script>
