<?php
declare(strict_types=1);

$importEmbedInline = defined('BESOIU_IMPORT_EMBED_INLINE') && BESOIU_IMPORT_EMBED_INLINE;
if (!$importEmbedInline):
?><!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Pro simplu</title>
<?php endif; ?>
<style>
    .ip-simple { --primary:#0f766e; --primary2:#14b8a6; --border:#dbe3ea; --muted:#64748b; --bg:#f8fafc; font-family:"Segoe UI",system-ui,sans-serif; color:#0f172a; }
    .ip-simple * { box-sizing:border-box; }
    .ip-simple .wrap { width:100%; max-width:none; margin:0; }
    .ip-simple .hero { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; padding:20px 22px; border-radius:14px; background:linear-gradient(120deg,#0f766e,#0b4d42); color:#fff; margin-bottom:16px; }
    .ip-simple h1 { margin:0 0 4px; font-size:24px; }
    .ip-simple .hero p { margin:0; opacity:.9; }
    .ip-simple .tabs { display:flex; gap:8px; margin-bottom:14px; }
    .ip-simple .tab { border:1px solid var(--border); background:#fff; color:#0f172a; border-radius:10px; padding:10px 14px; font-weight:700; cursor:pointer; }
    .ip-simple .tab.active { background:var(--primary); color:#fff; border-color:var(--primary); }
    .ip-simple .panel { background:#fff; border:1px solid var(--border); border-radius:14px; padding:16px; box-shadow:0 8px 24px rgba(15,23,42,.05); }
    .ip-simple .toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:12px; }
    .ip-simple .btn { border:0; border-radius:10px; padding:10px 14px; background:var(--primary2); color:#fff; font-weight:800; cursor:pointer; }
    .ip-simple .btn.secondary { background:#fff; color:var(--primary); border:1px solid #99f6e4; }
    .ip-simple .btn.danger { background:#dc2626; }
    .ip-simple .btn:disabled { opacity:.55; cursor:not-allowed; }
    .ip-simple select, .ip-simple input[type="number"] { height:40px; border:1px solid var(--border); border-radius:10px; padding:0 10px; }
    .ip-simple table { width:100%; border-collapse:collapse; font-size:14px; }
    .ip-simple th, .ip-simple td { border-bottom:1px solid #e2e8f0; padding:10px 12px; text-align:left; vertical-align:top; }
    .ip-simple th { background:#f1f5f9; font-size:12px; text-transform:uppercase; color:#475569; }
    .ip-simple tr.selected { background:#ecfdf5; }
    .ip-simple code { background:#f1f5f9; padding:2px 5px; border-radius:6px; }
    .ip-simple .hint { color:var(--muted); font-size:13px; }
    .ip-simple .status { margin:10px 0; padding:10px 12px; border-radius:10px; background:#f0fdfa; color:#115e59; }
    .ip-simple .status.error { background:#fef2f2; color:#991b1b; }
    .ip-simple .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:12px; margin-top:12px; }
    .ip-simple .card { border:1px solid var(--border); border-radius:14px; overflow:hidden; background:#fff; }
    .ip-simple .card-head { padding:12px 14px; background:#f8fafc; border-bottom:1px solid var(--border); }
    .ip-simple .card-head strong { display:block; font-size:15px; }
    .ip-simple .card-body { padding:12px 14px; display:grid; gap:6px; font-size:13px; }
    .ip-simple .pill { display:inline-flex; border-radius:999px; padding:3px 8px; font-size:12px; font-weight:800; background:#dcfce7; color:#166534; }
    .ip-simple .pill.warn { background:#fef3c7; color:#92400e; }
    .ip-simple .pill.err { background:#fee2e2; color:#991b1b; }
    .ip-modal { position:fixed; inset:0; z-index:2147483000; background:#fff; display:flex; flex-direction:column; }
    .ip-modal-head { display:flex; gap:10px; align-items:center; padding:12px 16px; border-bottom:1px solid var(--border,#dbe3ea); background:#f8fafc; }
    .ip-modal-head input { margin-left:auto; height:40px; min-width:260px; border:1px solid #cbd5e1; border-radius:9px; padding:0 12px; }
    .ip-modal-body { flex:1; min-height:0; overflow:auto; }
    .ip-modal table { width:max-content; min-width:100%; border-collapse:collapse; }
    .ip-modal th { position:sticky; top:0; z-index:2; background:#0f766e; color:#fff; }
    .ip-modal th, .ip-modal td { white-space:nowrap; padding:10px 14px; border-bottom:1px solid #e2e8f0; text-align:left; }
</style>
<div class="ip-simple" id="ipSimple">
    <div class="wrap">
        <section class="hero">
            <div>
                <h1>Import Pro simplu</h1>
                <p>Fișiere furnizori → mapare brand + cod → cartele produse cu prețul din fișier.</p>
            </div>
            <button type="button" class="btn secondary" id="reloadFiles">Reîncarcă fișiere</button>
        </section>

        <nav class="tabs" aria-label="Pași import">
            <button type="button" class="tab active" data-tab="files">1. Fișiere furnizori</button>
            <button type="button" class="tab" data-tab="map">2. Mapare cartele</button>
        </nav>

        <section class="panel" id="tab-files">
            <div class="toolbar">
                <strong>Fișiere încărcate de furnizori</strong>
                <span class="hint" id="filesHint">Se încarcă...</span>
            </div>
            <div class="status" id="filesStatus" hidden></div>
            <table>
                <thead><tr><th>Furnizor</th><th>Fișier</th><th>Mărime</th><th>Modificat</th><th>Acțiuni</th></tr></thead>
                <tbody id="filesBody"><tr><td colspan="5">Se încarcă...</td></tr></tbody>
            </table>
        </section>

        <section class="panel" id="tab-map" hidden>
            <div class="toolbar">
                <label>Fișier
                    <select id="mapFileSelect"></select>
                </label>
                <label>Lot
                    <input type="number" id="mapLimit" min="1" max="50" value="20">
                </label>
                <button type="button" class="btn" id="startMap">Mapează</button>
                <button type="button" class="btn secondary" id="stopMap" disabled>Oprește</button>
                <button type="button" class="btn secondary" id="queueCards" disabled>Trimite în coadă</button>
            </div>
            <div class="status" id="mapStatus">Alege un fișier și apasă Mapează.</div>
            <div class="grid" id="cardsGrid"></div>
        </section>
    </div>
</div>
<script>
(function(){
    const root = document.getElementById('ipSimple');
    const P = window.IMPORT_PRO_CONFIG || {};
    const embeddedQuery = window.location.search || '';
    function importAbsoluteUrl(url){ return new URL(url, window.location.href).toString(); }
    function importApi(path){
        const raw = String(path || '').replace(/^api\//,'');
        const proxy = P.apiProxy || '';
        if (proxy) {
            const u = new URL(importAbsoluteUrl(proxy));
            u.searchParams.set('script', raw);
            if (embeddedQuery) new URLSearchParams(embeddedQuery).forEach((v,k)=>u.searchParams.append(k,v));
            return u.toString();
        }
        const base = String(P.webBase || '');
        if (base) return importAbsoluteUrl(base.replace(/\/?$/,'/') + 'api/' + raw);
        return new URL('api/' + raw, window.location.href).toString();
    }
    const API = {
        files: importApi('files.php'),
        preview: importApi('preview-file.php'),
        map: importApi('simple-map.php'),
        stage: importApi('stage-cards.php')
    };
    const state = { files: [], selected: null, cards: [], stop: false, mapping: false };
    const $ = id => document.getElementById(id);
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const size = f => f.size_mb != null ? (Number(f.size_mb).toFixed(2) + ' MB') : ((Number(f.size||0)/1048576).toFixed(2) + ' MB');
    async function json(url, opts){
        const res = await fetch(url, { credentials:'same-origin', cache:'no-store', ...(opts||{}) });
        const text = await res.text();
        let data; try { data = JSON.parse(text); } catch(e) { throw new Error('Răspuns JSON invalid'); }
        if (!res.ok || data.success === false) throw new Error(data.error || data.message || 'Eroare');
        return data;
    }
    function showTab(name){
        document.querySelectorAll('.ip-simple .tab').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
        $('tab-files').hidden = name !== 'files';
        $('tab-map').hidden = name !== 'map';
    }
    function fileKey(f){ return String(f.supplier||'') + '/' + String(f.filename||''); }
    function selectFile(supplier, filename){
        state.selected = state.files.find(f => f.supplier === supplier && f.filename === filename) || null;
        renderFiles();
        renderFileSelect();
        showTab('map');
    }
    function renderFiles(){
        const body = $('filesBody');
        if (!state.files.length) {
            body.innerHTML = '<tr><td colspan="5">Nu sunt fișiere în folderele furnizorilor.</td></tr>';
            return;
        }
        body.innerHTML = state.files.map(f => {
            const selected = state.selected && fileKey(state.selected) === fileKey(f);
            return '<tr class="'+(selected?'selected':'')+'">'
                + '<td><code>'+esc(f.supplier)+'</code><br><span class="hint">'+esc(f.supplier_name || f.supplier_code || '')+'</span></td>'
                + '<td>'+esc(f.filename)+'</td>'
                + '<td>'+esc(size(f))+'</td>'
                + '<td>'+esc(f.modified_at || '')+'</td>'
                + '<td><button class="btn secondary" data-preview="'+esc(fileKey(f))+'">Vezi tabel</button> '
                + '<button class="btn" data-select="'+esc(fileKey(f))+'">Alege pentru mapare</button></td></tr>';
        }).join('');
        body.querySelectorAll('[data-preview]').forEach(btn => btn.addEventListener('click', () => {
            const [supplier, ...rest] = btn.dataset.preview.split('/');
            previewFile(supplier, rest.join('/'));
        }));
        body.querySelectorAll('[data-select]').forEach(btn => btn.addEventListener('click', () => {
            const [supplier, ...rest] = btn.dataset.select.split('/');
            selectFile(supplier, rest.join('/'));
        }));
    }
    function renderFileSelect(){
        const select = $('mapFileSelect');
        select.innerHTML = state.files.map(f => '<option value="'+esc(fileKey(f))+'">'+esc(fileKey(f))+'</option>').join('');
        if (state.selected) select.value = fileKey(state.selected);
    }
    async function loadFiles(){
        $('filesStatus').hidden = true;
        $('filesHint').textContent = 'Se încarcă...';
        try {
            const data = await json(API.files + (API.files.includes('?') ? '&' : '?') + 'empty=1');
            state.files = Array.isArray(data.files) ? data.files : [];
            if (!state.selected && state.files.length) state.selected = state.files[0];
            $('filesHint').textContent = state.files.length + ' fișier(e)';
            renderFiles();
            renderFileSelect();
        } catch(e) {
            $('filesStatus').hidden = false;
            $('filesStatus').className = 'status error';
            $('filesStatus').textContent = e.message;
        }
    }
    function openPreviewModal(title, rows){
        const modal = document.createElement('div');
        modal.className = 'ip-modal';
        const table = rows && rows.length ? '<table><thead><tr>' + rows[0].map(h => '<th>'+esc(h)+'</th>').join('') + '</tr></thead><tbody>'
            + rows.slice(1).map(r => '<tr>'+r.map(c => '<td>'+esc(c)+'</td>').join('')+'</tr>').join('') + '</tbody></table>' : '<p style="padding:16px">Fără rânduri de afișat.</p>';
        modal.innerHTML = '<div class="ip-modal-head"><strong>'+esc(title)+'</strong><input type="search" placeholder="Caută în rândurile afișate"><button class="btn secondary">Închide</button></div><div class="ip-modal-body">'+table+'</div>';
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        const close = () => { modal.remove(); document.body.style.overflow = ''; };
        modal.querySelector('button').addEventListener('click', close);
        modal.querySelector('input').addEventListener('input', e => {
            const q = e.target.value.toLowerCase();
            modal.querySelectorAll('tbody tr').forEach(tr => tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none');
        });
    }
    async function previewFile(supplier, filename){
        try {
            const u = new URL(API.preview);
            u.searchParams.set('supplier', supplier);
            u.searchParams.set('filename', filename);
            const data = await json(u.toString());
            openPreviewModal(supplier + '/' + filename, data.rows || []);
        } catch(e) {
            alert(e.message);
        }
    }
    function cardStatus(card){
        const s = String(card.status || '');
        if (s === 'mapped') return '<span class="pill">Mapat</span>';
        if (s === 'indexing') return '<span class="pill warn">Indexare</span>';
        if (s === 'no_catalog') return '<span class="pill err">Fără catalog</span>';
        return '<span class="pill warn">Cod negăsit</span>';
    }
    function renderCards(){
        $('queueCards').disabled = !state.cards.some(c => c.status === 'mapped');
        $('cardsGrid').innerHTML = state.cards.slice(-160).map(card => '<article class="card">'
            + '<div class="card-head"><strong>'+esc(card.title || card.card_name || card.file_name || 'Cartelă produs')+'</strong>'+cardStatus(card)+'</div>'
            + '<div class="card-body">'
            + '<div>Fișier: <code>'+esc(card.file_brand)+'</code> <code>'+esc(card.file_code)+'</code></div>'
            + '<div>Cartelă: <code>'+esc(card.brand)+'</code> <code>'+esc(card.tecdocSku || card.sku)+'</code></div>'
            + '<div>Denumire: '+esc(card.name || card.matchedName || '')+'</div>'
            + '<div>EAN: '+esc(card.ean || '')+'</div>'
            + '<div>Preț fișier: '+esc(card.priceCsv ?? card.price ?? '')+' · Achiziție: '+esc(card.pricePurchaseNet ?? card.priceNet ?? '')+'</div>'
            + '<div class="hint">'+esc(card.compatText ? String(card.compatText).slice(0,180) : (card.reason || ''))+'</div>'
            + '</div></article>').join('');
    }
    async function mapSelected(){
        if (!state.selected) return alert('Alege un fișier.');
        state.cards = [];
        state.stop = false;
        state.mapping = true;
        $('startMap').disabled = true;
        $('stopMap').disabled = false;
        $('queueCards').disabled = true;
        let offset = 0;
        const limit = Math.max(1, Math.min(50, Number($('mapLimit').value || 20)));
        const totals = { scanned:0, mapped:0, missing:0, no_catalog:0, indexing:0 };
        while (!state.stop) {
            const fd = new FormData();
            fd.append('supplier', state.selected.supplier);
            fd.append('filename', state.selected.filename);
            fd.append('offset', String(offset));
            fd.append('limit', String(limit));
            let data;
            try { data = await json(API.map, { method:'POST', body:fd }); }
            catch(e) { $('mapStatus').className='status error'; $('mapStatus').textContent=e.message; break; }
            offset = Number(data.next_offset || offset);
            const stats = data.stats || {};
            totals.scanned += Number(stats.scanned || 0);
            totals.mapped += Number(stats.mapped || 0);
            totals.missing += Number(stats.missing_code || 0);
            totals.no_catalog += Number(stats.no_catalog || 0);
            totals.indexing += Number(stats.indexing || 0);
            (data.cards || []).forEach(c => state.cards.push(c));
            $('mapStatus').className='status';
            $('mapStatus').textContent='Scanate '+totals.scanned+' · mapate '+totals.mapped+' · cod negăsit '+totals.missing+' · fără catalog '+totals.no_catalog+' · indexare '+totals.indexing;
            renderCards();
            if (data.done) break;
        }
        state.mapping = false;
        $('startMap').disabled = false;
        $('stopMap').disabled = true;
    }
    async function queueMapped(){
        const cards = state.cards.filter(c => c.status === 'mapped');
        if (!cards.length) return;
        let queued = 0;
        for (let i=0; i<cards.length; i+=25) {
            const batch = cards.slice(i, i+25);
            const data = await json(API.stage, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ cards:batch, import_lane:'standard' }) });
            queued += Number(data.queued || 0);
            $('mapStatus').textContent = 'Trimise în coadă: ' + queued + ' / ' + cards.length;
        }
    }
    document.querySelectorAll('.ip-simple .tab').forEach(btn => btn.addEventListener('click', () => showTab(btn.dataset.tab)));
    $('reloadFiles').addEventListener('click', loadFiles);
    $('mapFileSelect').addEventListener('change', e => {
        const [supplier, ...rest] = e.target.value.split('/');
        state.selected = state.files.find(f => f.supplier === supplier && f.filename === rest.join('/')) || null;
        renderFiles();
    });
    $('startMap').addEventListener('click', mapSelected);
    $('stopMap').addEventListener('click', () => { state.stop = true; });
    $('queueCards').addEventListener('click', queueMapped);
    loadFiles();
})();
</script>
<?php if (!$importEmbedInline): ?>
</body>
</html>
<?php endif; ?>
