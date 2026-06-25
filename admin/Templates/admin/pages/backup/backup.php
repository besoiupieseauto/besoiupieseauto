<div>
    <div id="backup-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="admin-panel mt-8">
        <div class="admin-panel__head">
            <div>
                <h2>Backup bază de date</h2>
                <p class="mt-1 text-sm opacity-70">Export SQL local în <code>admin/storage/backups/</code> — retenție 7 zile.</p>
            </div>
            <div class="ms-auto flex gap-2">
                <button type="button" id="backup-run" class="box rounded-md border bg-primary px-4 py-2 text-sm text-white">Rulează backup acum</button>
                <button type="button" id="backup-refresh" class="box rounded-md border px-4 py-2 text-sm">Reîncarcă</button>
            </div>
        </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 md:col-span-4">
            <div class="box p-5">
                <div class="text-xs uppercase opacity-70">Ultimul backup</div>
                <div id="backup-latest" class="mt-2 text-lg font-medium">—</div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-4">
            <div class="box p-5">
                <div class="text-xs uppercase opacity-70">Total fișiere</div>
                <div id="backup-count" class="mt-2 text-lg font-medium">0</div>
            </div>
        </div>
        <div class="col-span-12 md:col-span-4">
            <div class="box p-5">
                <div class="text-xs uppercase opacity-70">Spațiu utilizat</div>
                <div id="backup-size" class="mt-2 text-lg font-medium">0 B</div>
            </div>
        </div>
    </div>

        <h3 class="mt-6 text-base font-medium">Istoric backup-uri</h3>
        <div class="admin-table-wrap mt-4">
            <table class="w-full text-sm">
                <thead>
                <tr>
                    <th class="px-3 py-2 text-left">Fișier</th>
                    <th class="px-3 py-2 text-left">Data</th>
                    <th class="px-3 py-2 text-right">Mărime</th>
                    <th class="px-3 py-2 text-right">Acțiune</th>
                </tr>
                </thead>
                <tbody id="backup-table">
                <tr><td colspan="4" class="px-3 py-6 text-center opacity-70">Se încarcă...</td></tr>
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-xs opacity-60">
            Pentru backup zilnic automat, programează în Windows Task Scheduler:
            <code>admin/scripts/run_daily_backup.bat</code> la 03:00 (vezi <code>admin/docs/CRON_WINDOWS_SETUP.md</code>).
        </p>
    </div>
</div>

<script>
(function () {
    'use strict';

    const ENDPOINT = '/admin/api/backup_endpoint.php';

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[c]));
    }

    function showToast(message, isError) {
        const toast = document.getElementById('backup-toast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.remove('hidden');
        toast.classList.toggle('text-danger', Boolean(isError));
        setTimeout(() => toast.classList.add('hidden'), 4000);
    }

    function formatBytes(bytes) {
        const num = Number(bytes || 0);
        if (num >= 1048576) return (num / 1048576).toFixed(2) + ' MB';
        if (num >= 1024) return (num / 1024).toFixed(2) + ' KB';
        return num + ' B';
    }

    async function loadBackups() {
        const response = await fetch(ENDPOINT);
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Eroare la încărcare.');
        }
        render(result);
    }

    function render(result) {
        const stats = result.stats || {};
        const items = result.data || [];

        document.getElementById('backup-latest').textContent = stats.latest
            ? stats.latest.created_at + ' (' + stats.latest.filename + ')'
            : 'Niciun backup';
        document.getElementById('backup-count').textContent = String(stats.count || 0);
        document.getElementById('backup-size').textContent = formatBytes(stats.total_bytes || 0);

        const table = document.getElementById('backup-table');
        if (!items.length) {
            table.innerHTML = '<tr><td colspan="4" class="px-3 py-6 text-center opacity-70">Niciun backup încă. Apasă „Rulează backup acum”.</td></tr>';
            return;
        }

        table.innerHTML = items.map((item) => `
            <tr class="border-b">
                <td class="px-3 py-2 font-medium">${escapeHtml(item.filename)}</td>
                <td class="px-3 py-2">${escapeHtml(item.created_at)}</td>
                <td class="px-3 py-2 text-right">${escapeHtml(item.size_human || formatBytes(item.size_bytes))}</td>
                <td class="px-3 py-2 text-right">
                    <a class="rounded border px-3 py-1 text-xs" href="${ENDPOINT}?download=${encodeURIComponent(item.filename)}">Descarcă</a>
                </td>
            </tr>
        `).join('');
    }

    async function runBackup() {
        const button = document.getElementById('backup-run');
        if (button) {
            button.disabled = true;
            button.textContent = 'Se rulează...';
        }
        try {
            const response = await fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type_product: 'run' })
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Backup eșuat.');
            }
            showToast(result.message || 'Backup creat.', false);
            render(result);
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = 'Rulează backup acum';
            }
        }
    }

    document.getElementById('backup-refresh')?.addEventListener('click', () => {
        loadBackups().catch((error) => showToast(error.message, true));
    });

    document.getElementById('backup-run')?.addEventListener('click', () => {
        runBackup().catch((error) => showToast(error.message, true));
    });

    loadBackups().catch((error) => showToast(error.message, true));
})();
</script>
