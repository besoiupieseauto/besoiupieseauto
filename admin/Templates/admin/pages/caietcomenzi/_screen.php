<?php
declare(strict_types=1);

use Besoiu\Core\Auth\AdminCsrf;

/** @var string $caietScreen */
$caietScreen = $caietScreen ?? 'comenzi-tm';
$caietLoc = $caietLoc ?? '';
$caietSource = $caietSource ?? 'interna';
$caietTitles = [
    'comenzi-tm' => 'Comenzi Timișoara',
    'comenzi-utvin' => 'Comenzi UTVIN',
    'comenzi-ext' => 'Comenzi externe',
    'clienti' => 'Clienți',
    'facturi' => 'Facturi',
    'incasari' => 'Încasări',
    'utilizatori' => 'Utilizatori',
    'pieseauto' => 'Pieseauto',
    'search' => 'Supplier Search',
    'api' => 'API Credentials',
];
$caietTitle = $caietTitles[$caietScreen] ?? 'Caiet comenzi';
$caietCsrf = AdminCsrf::token();
?>
<div class="admin-content" data-page-title="<?= htmlspecialchars($caietTitle, ENT_QUOTES, 'UTF-8') ?>">
    <h2 class="mt-0 text-lg font-medium"><?= htmlspecialchars($caietTitle, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if ($caietScreen === 'search'): ?>
        <div class="caiet-panel">
            <?php
            $supplierSearchPage = dirname(__DIR__, 5) . '/modules/supplier_search/pages/supplier-search.php';
            if (is_file($supplierSearchPage)) {
                require $supplierSearchPage;
            }
            ?>
        </div>
    <?php else: ?>
    <div class="caiet-panel">
        <div class="caiet-toolbar" id="caiet-toolbar"></div>
        <div id="caiet-extra"></div>
        <div id="caiet-list" style="overflow:auto">
            <table class="caiet-table">
                <thead id="caiet-head"></thead>
                <tbody id="caiet-body"><tr><td>Se încarcă…</td></tr></tbody>
            </table>
        </div>
        <div id="caiet-invoice" class="inv-hidden"></div>
    </div>
    <?php endif; ?>
</div>
<div id="caiet-modal" class="caiet-modal hidden"></div>
<?php if ($caietScreen !== 'search'): ?>
<script>
(function () {
  const SCREEN = <?= json_encode($caietScreen, JSON_UNESCAPED_UNICODE) ?>;
  const LOC = <?= json_encode($caietLoc, JSON_UNESCAPED_UNICODE) ?>;
  const SOURCE = <?= json_encode($caietSource, JSON_UNESCAPED_UNICODE) ?>;
  const CSRF = <?= json_encode($caietCsrf, JSON_UNESCAPED_UNICODE) ?>;
  const ENDPOINT = '/admin/api/caiet_comenzi_endpoint.php';
  const STATUS = {1:['Comandat','#f0ad4e'],2:['Sosit','#5bc0de'],3:['Cash','#5cb85c'],4:['Avans','#d9534f'],5:['Retur','#6545f0'],6:['Card','#111'],7:['FD','#dc7d0d'],8:['Anulat','#d9534f'],9:['OP','#ff9800'],10:['Avans FD','#dc7d0d'],11:['Avans Cash','#5cb85c'],12:['Avans Card','#111'],13:['Avans OP','#ff9800']};
  const STATUS_EXT = {1:['Comandat','#f0ad4e'],2:['Sosit','#5bc0de'],3:['Expediat','#337ab7'],4:['Achitat','#5cb85c'],5:['Avans','#d9534f'],6:['Retur','#5cb85c'],7:['Anulat','#d9534f']};
  const SUPPLIERS = ['ET','MA','IC','AN','AT','BA','AB','SZ','AP','AD','Stoc'];
  const COLORS = [['7CFC00','Azi','#7CFC00'],['ADD8E6','Maine','#ADD8E6'],['F5A000','2 zile','#F5A000'],['FF0000','+3 zile','#FF0000'],['FFFFFF','Sosit','#9EA5AF']];
  const body = document.getElementById('caiet-body');
  const head = document.getElementById('caiet-head');
  const toolbar = document.getElementById('caiet-toolbar');
  const extra = document.getElementById('caiet-extra');
  const modal = document.getElementById('caiet-modal');
  let rows = [];
  let draftLines = [];
  let invoiceLines = [];
  let invoiceClient = {id: 0, company: ''};

  function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }
  async function api(type, payload) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-Admin-CSRF': (document.querySelector('meta[name="csrf-token"]') || {}).content || CSRF},
      body: JSON.stringify(Object.assign({type_product: type}, payload || {}))
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Eroare.');
    return result.data;
  }
  function formValues(form) {
    const payload = {};
    new FormData(form).forEach((value, key) => { if (String(value).trim() !== '') payload[key] = value; });
    return payload;
  }
  function closeModal() { modal.classList.add('hidden'); modal.innerHTML = ''; }
  function mountModal() {
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
  }
  function openForm(title, fields, row, onSubmit) {
    const inputs = fields.map(field => {
      const value = row && row[field.name] != null ? row[field.name] : (field.value || '');
      if (field.type === 'select') {
        const options = field.options.map(opt => '<option value="' + esc(opt[0]) + '"' + (String(value) === String(opt[0]) ? ' selected' : '') + '>' + esc(opt[1]) + '</option>').join('');
        return '<label>' + esc(field.label) + '<select name="' + esc(field.name) + '">' + options + '</select></label>';
      }
      return '<label>' + esc(field.label) + '<input name="' + esc(field.name) + '" value="' + esc(value) + '"></label>';
    }).join('');
    sheet(title, '<form class="caiet-grid">' + inputs + '<div class="caiet-actions"><button type="button" data-close>Anulează</button><button type="submit" class="lg-btn lg-btn-success">Salvează</button></div></form>', 'form');
    modal.querySelector('form').addEventListener('submit', async (event) => {
      event.preventDefault();
      try { await onSubmit(formValues(event.target)); closeModal(); await load(); }
      catch (error) { alert(error.message); }
    });
  }

  async function load() {
    try {
      if (SCREEN === 'clienti') await loadClienti();
      else if (SCREEN === 'facturi') await loadFacturi();
      else if (SCREEN === 'incasari') await loadIncasari();
      else if (SCREEN === 'utilizatori') await loadUsers();
      else if (SCREEN === 'api') await loadApi();
      else if (SCREEN === 'pieseauto') await loadPieseauto();
      else await loadComenzi();
    } catch (error) {
      body.innerHTML = '<tr><td>' + esc(error.message) + '</td></tr>';
    }
  }

  function loadClienti() {
    head.innerHTML = '<tr><th>ID</th><th>Nume</th><th>Telefon</th><th>Firmă</th><th>CIF</th><th>Mașină</th><th>Comenzi</th><th></th></tr>';
    toolbar.innerHTML = '<input id="q" type="search" placeholder="Nume, telefon, CIF"><button type="button" id="go">Caută</button><button type="button" id="new">Client nou</button>';
    document.getElementById('go').onclick = () => renderClienti();
    document.getElementById('new').onclick = () => openForm('Client nou', clientFields(), null, payload => api('clienti_save', payload));
    return renderClienti();
  }
  function clientFields() {
    return [
      {name:'nume', label:'Nume / Contact'},
      {name:'telefon', label:'Telefon'},
      {name:'adresa', label:'Adresa'},
      {name:'companie', label:'Societate'},
      {name:'cif', label:'CUI / CNP'},
      {name:'marca', label:'Marca masina'},
      {name:'sasiu', label:'Serie sasiu'},
      {name:'nr_inmat', label:'Nr. inmatriculare'},
      {name:'regcom', label:'Reg. Com.'},
      {name:'cont_banca', label:'Cont bancar'},
      {name:'nume_banca', label:'Banca'},
      {name:'idlocalitate', label:'ID localitate'}
    ];
  }
  async function renderClienti() {
    rows = await api('clienti_list', {search: document.getElementById('q').value, limit: 100});
    body.innerHTML = rows.map(row => '<tr><td>' + esc(row.idclienti) + '</td><td>' + esc(row.nume) + '</td><td>' + esc(row.telefon) + '</td><td>' + esc(row.companie) + '</td><td>' + esc(row.cif) + '</td><td>' + esc([row.marca, row.nr_inmat].filter(Boolean).join(' ')) + '</td><td>' + esc((Number(row.total_orders_internal)||0) + (Number(row.total_orders_external)||0)) + '</td><td><button data-edit="' + esc(row.idclienti) + '">Edit</button> <button data-del="' + esc(row.idclienti) + '">Șterge</button></td></tr>').join('') || '<tr><td colspan="8">Niciun client.</td></tr>';
    body.querySelectorAll('[data-edit]').forEach(btn => btn.onclick = () => {
      const row = rows.find(item => String(item.idclienti) === btn.dataset.edit);
      openForm('Edit client', clientFields(), row, payload => api('clienti_save', Object.assign({idclienti: row.idclienti}, payload)));
    });
    body.querySelectorAll('[data-del]').forEach(btn => btn.onclick = async () => {
      if (!confirm('Ștergi clientul?')) return;
      await api('clienti_delete', {idclienti: btn.dataset.del});
      await renderClienti();
    });
  }

  function loadFacturi() {
    head.innerHTML = '<tr><th>ID</th><th>Serie</th><th>Client</th><th>Data</th><th>Tip</th><th>Valid</th><th></th></tr>';
    toolbar.innerHTML = '<input id="q" type="search" placeholder="Serie, client, ID"><select id="tip"><option value="">Toate</option><option value="interna">Internă</option><option value="externa">Externă</option></select><button type="button" id="go">Caută</button><button type="button" id="new">Factură nouă</button>';
    document.getElementById('go').onclick = () => renderFacturi();
    document.getElementById('new').onclick = () => openInvoiceCreate();
    document.getElementById('caiet-invoice').classList.add('inv-hidden');
    document.getElementById('caiet-list').classList.remove('inv-hidden');
    document.getElementById('caiet-toolbar').classList.remove('inv-hidden');
    return renderFacturi();
  }
  function facturaFields() {
    return [
      {name:'CustomerID', label:'ID client'},
      {name:'seria', label:'Serie'},
      {name:'OrderDate', label:'Data'},
      {name:'RequiredDate', label:'Scadență'},
      {name:'tip_comanda', label:'Tip', type:'select', options:[['0','Internă'],['2','Externă']]},
      {name:'valid', label:'Valid', type:'select', options:[['Da','Da'],['Nu','Nu']]}
    ];
  }
  async function renderFacturi() {
    rows = await api('facturi_list', {search: document.getElementById('q').value, tip_comanda: document.getElementById('tip').value, limit: 100});
    body.innerHTML = rows.map(row => '<tr><td>' + esc(row.OrderID) + '</td><td>' + esc(row.seria) + '</td><td>' + esc(row.client_name) + '<br>' + esc(row.client_phone) + '</td><td>' + esc(row.OrderDate) + '</td><td>' + (String(row.tip_comanda) === '2' ? 'Externă' : 'Internă') + '</td><td>' + esc(row.valid) + '</td><td><button data-see="' + esc(row.OrderID) + '">Linii</button> <button data-edit="' + esc(row.OrderID) + '">Edit</button> <button data-del="' + esc(row.OrderID) + '">Șterge</button></td></tr>').join('') || '<tr><td colspan="7">Nicio factură.</td></tr>';
    body.querySelectorAll('[data-see]').forEach(btn => btn.onclick = () => showFactura(btn.dataset.see));
    body.querySelectorAll('[data-edit]').forEach(btn => btn.onclick = () => {
      const row = rows.find(item => String(item.OrderID) === btn.dataset.edit);
      openForm('Edit factură', facturaFields(), row, payload => api('facturi_save', Object.assign({OrderID: row.OrderID}, payload)));
    });
    body.querySelectorAll('[data-del]').forEach(btn => btn.onclick = async () => {
      if (!confirm('Ștergi factura și liniile ei?')) return;
      await api('facturi_delete', {OrderID: btn.dataset.del});
      await renderFacturi();
    });
  }
  async function showFactura(id) {
    const data = await api('facturi_details', {OrderID: id});
    const lines = (data.lines || []).map(line => '<tr><td>' + esc(line.ProductId) + '</td><td>' + esc(line.Quantity) + '</td><td>' + esc(line.UnitPrice) + '</td><td>' + esc(line.tva) + '</td><td>' + esc(line.total) + '</td></tr>').join('');
    sheet('Factura ' + data.OrderID, '<p><strong>' + esc(data.client_name) + '</strong> — total ' + esc(data.grand_total) + ' RON</p><table class="caiet-lines"><thead><tr><th>Produs</th><th>Cant.</th><th>Preț</th><th>TVA</th><th>Total</th><th></th></tr></thead><tbody>' + ((data.lines || []).map(line => '<tr><td>' + esc(line.ProductId) + '</td><td><input data-qty="' + esc(line.ProductId) + '" value="' + esc(line.Quantity) + '" style="width:70px"></td><td><input data-price="' + esc(line.ProductId) + '" value="' + esc(line.UnitPrice) + '" style="width:80px"></td><td>' + esc(line.tva) + '</td><td>' + esc(line.total) + '</td><td><button type="button" data-save-line="' + esc(line.ProductId) + '">Salvează</button> <button type="button" data-drop-line="' + esc(line.ProductId) + '">Șterge</button></td></tr>').join('') || '<tr><td colspan="6">Fără linii.</td></tr>') + '</tbody></table><div class="caiet-actions"><button type="button" id="inv-print">Tipărește</button><button type="button" data-close>Închide</button></div>', true);
    document.getElementById('inv-print').onclick = () => printInvoice(id);
    modal.querySelectorAll('[data-save-line]').forEach(btn => btn.onclick = async () => {
      await api('facturi_line_save', {OrderID: id, idprodus: btn.dataset.saveLine, cantitate: modal.querySelector('[data-qty="' + btn.dataset.saveLine + '"]').value, pret: modal.querySelector('[data-price="' + btn.dataset.saveLine + '"]').value});
      await showFactura(id);
    });
    modal.querySelectorAll('[data-drop-line]').forEach(btn => btn.onclick = async () => {
      await api('facturi_line_delete', {OrderID: id, idprodus: btn.dataset.dropLine});
      await showFactura(id);
    });
  }
  async function openInvoiceCreate() {
    const meta = await api('facturi_meta', {});
    invoiceLines = [];
    invoiceClient = {id: 0, company: ''};
    const agents = (meta.agents || []).map(agent => '<option value="' + esc(agent.EmployeeId) + '">' + esc(((agent.FirstName || '') + ' ' + (agent.LastName || '')).trim()) + '</option>').join('');
    const payments = (meta.payments || []).map(pay => '<option value="' + esc(pay.id_plata) + '"' + (String(pay.id_plata) === '1' ? ' selected' : '') + '>' + esc(pay.denumire) + '</option>').join('');
    const box = document.getElementById('caiet-invoice');
    box.classList.remove('inv-hidden');
    document.getElementById('caiet-list').classList.add('inv-hidden');
    document.getElementById('caiet-toolbar').classList.add('inv-hidden');
    document.getElementById('caiet-extra').classList.add('inv-hidden');
    box.innerHTML = '<div class="inv-card"><div class="inv-head"><h4>✎ Factura noua</h4></div><div class="inv-body">'
      + '<div class="inv-grid">'
      + '<label for="inv-client">Client</label><div class="inv-client"><input id="inv-client" placeholder="Selecteaza un client" autocomplete="off"><button type="button" id="inv-client-add" title="Client nou">+</button><ul id="inv-suggest" class="inv-suggest inv-hidden"></ul></div>'
      + '<label for="inv-phone">Telefon</label><input id="inv-phone" placeholder="Telefon" readonly>'
      + '<label for="inv-cui">CUI</label><input id="inv-cui" placeholder="CUI" readonly>'
      + '<label for="inv-agent">Agent</label><select id="inv-agent">' + agents + '</select>'
      + '<label for="inv-date">Data</label><input id="inv-date" value="' + esc(meta.date || '') + '" readonly>'
      + '<label for="inv-due">Data scadenta</label><input id="inv-due" value="' + esc(meta.due || '') + '" readonly>'
      + '<label for="inv-pay">Tip incasare</label><select id="inv-pay">' + payments + '</select>'
      + '</div>'
      + '<div class="inv-actions"><button type="button" id="inv-add">⌕ Adauga produs</button><button type="button" id="inv-save" class="lg-btn-success">Salvare</button></div>'
      + '<table class="inv-table"><thead><tr><th>Nr. Crt.</th><th>PRODUS</th><th>UM</th><th>CANT.</th><th>PRET UNIT.</th><th>VALOARE.</th><th>TVA</th><th>Cota TVA</th><th></th></tr></thead><tbody id="inv-lines"></tbody></table>'
      + '</div></div>';
    window.__invCounties = meta.counties || [];
    renderInvoiceLines();
    const clientInput = document.getElementById('inv-client');
    let timer = 0;
    clientInput.oninput = () => {
      clearTimeout(timer);
      timer = setTimeout(() => searchInvoiceClients(clientInput.value), 250);
    };
    document.getElementById('inv-client-add').onclick = () => openInvoiceClient();
    document.getElementById('inv-add').onclick = () => openInvoiceProducts();
    document.getElementById('inv-save').onclick = () => askInvoiceType();
  }
  async function searchInvoiceClients(query) {
    const list = document.getElementById('inv-suggest');
    if (!list) return;
    if (query.trim().length < 1) {
      list.classList.add('inv-hidden');
      return;
    }
    const found = await api('clienti_list', {search: query, limit: 12});
    list.innerHTML = found.map(client => '<li data-id="' + esc(client.idclienti) + '">' + esc(client.nume) + (client.telefon ? ' — ' + esc(client.telefon) : '') + '</li>').join('') || '<li>Niciun client</li>';
    list.classList.remove('inv-hidden');
    list.querySelectorAll('li[data-id]').forEach(item => item.onclick = () => {
      const client = found.find(row => String(row.idclienti) === item.dataset.id);
      if (!client) return;
      invoiceClient = {id: client.idclienti, company: client.companie || ''};
      document.getElementById('inv-client').value = client.nume || '';
      document.getElementById('inv-phone').value = client.telefon || '';
      document.getElementById('inv-cui').value = client.cif || '';
      applyPaymentRule();
      list.classList.add('inv-hidden');
    });
  }
  function applyPaymentRule() {
    const pay = document.getElementById('inv-pay');
    if (!pay) return;
    const chitanta = pay.querySelector('option[value="1"]');
    if (!chitanta) return;
    const company = String(invoiceClient.company || '').trim() !== '';
    chitanta.hidden = !company && invoiceClient.id;
    if (!company && invoiceClient.id && pay.value === '1') pay.value = '2';
    if (!invoiceClient.id) chitanta.hidden = false;
  }
  function renderInvoiceLines() {
    const body = document.getElementById('inv-lines');
    if (!body) return;
    let subtotal = 0;
    let vat = 0;
    const rowsHtml = invoiceLines.map((line, index) => {
      const value = Number(line.cantitate) * Number(line.pret);
      const rowVat = value * (Number(line.tva || 21) / 100);
      subtotal += value;
      vat += rowVat;
      return '<tr><td>' + (index + 1) + '</td><td>' + esc(line.denumire) + '</td><td>buc</td><td>' + esc(line.cantitate) + '</td><td>' + Number(line.pret).toFixed(2) + '</td><td>' + value.toFixed(2) + '</td><td>' + rowVat.toFixed(2) + '</td><td>' + esc(line.tva || 21) + '</td><td><button type="button" data-drop="' + index + '">Șterge</button></td></tr>';
    }).join('');
    body.innerHTML = (rowsHtml || '<tr><td colspan="9">No items found</td></tr>')
      + '<tr><td colspan="5" class="inv-right"><strong>SUBTOTAL</strong></td><td>' + subtotal.toFixed(2) + '</td><td>' + vat.toFixed(2) + '</td><td colspan="2"></td></tr>'
      + '<tr><td colspan="7" class="inv-right"><strong>TOTAL</strong></td><td colspan="2">' + (subtotal + vat).toFixed(2) + ' lei</td></tr>';
    body.querySelectorAll('[data-drop]').forEach(btn => btn.onclick = () => {
      invoiceLines.splice(Number(btn.dataset.drop), 1);
      renderInvoiceLines();
    });
  }
  function openInvoiceProducts() {
    sheet('Cauta produs', '<div class="inv-inline"><input id="inv-q" placeholder="Cauta produs" style="flex:1;height:34px;border:1px solid #ccc;padding:4px 8px"><button type="button" id="inv-q-go">Cauta</button><button type="button" id="inv-prod-new">+ Produs nou</button></div><div id="inv-prod-results" style="margin-top:12px"></div>', true);
    const run = () => searchInvoiceProducts(document.getElementById('inv-q').value);
    document.getElementById('inv-q-go').onclick = run;
    document.getElementById('inv-q').onkeydown = event => { if (event.key === 'Enter') { event.preventDefault(); run(); } };
    document.getElementById('inv-prod-new').onclick = () => openInvoiceNewProduct();
    run();
  }
  async function searchInvoiceProducts(query) {
    const box = document.getElementById('inv-prod-results');
    if (!box) return;
    const term = query.trim();
    if (term.length === 1) return;
    const page = await api('produse_list', {search: term, limit: 20});
    const items = page.items || [];
    const rowsHtml = items.map(product => '<tr><td>' + esc(product.denumire) + '</td><td>' + esc(product.cod_produs) + '</td><td><input data-qty="' + esc(product.idprodus) + '" value="1" style="width:70px;text-align:right"></td><td><input data-price="' + esc(product.idprodus) + '" value="' + esc(product.pret) + '" style="width:90px;text-align:right"></td><td><button type="button" data-add="' + esc(product.idprodus) + '">+</button></td></tr>').join('');
    box.innerHTML = '<table class="inv-table"><thead><tr><th>Produs</th><th>Cod Produs</th><th>Cant.</th><th>Pret</th><th>Adauga</th></tr></thead><tbody>' + rowsHtml
      + '<tr><td>CHELTUIELI TRANSPORT</td><td>-</td><td><input data-qty="32066" value="1" style="width:70px;text-align:right"></td><td><input data-price="32066" value="30" style="width:90px;text-align:right"></td><td><button type="button" data-add="32066" data-name="CHELTUIELI TRANSPORT">+</button></td></tr></tbody></table>';
    box.querySelectorAll('[data-add]').forEach(btn => btn.onclick = () => {
      const id = btn.dataset.add;
      const product = items.find(item => String(item.idprodus) === String(id));
      const qty = box.querySelector('[data-qty="' + id + '"]');
      const price = box.querySelector('[data-price="' + id + '"]');
      invoiceLines.push({
        idprodus: Number(id),
        denumire: product ? product.denumire : (btn.dataset.name || 'Produs'),
        cantitate: Number(qty && qty.value ? qty.value : 1),
        pret: Number(price && price.value ? price.value : 0),
        tva: product && product.TVA !== null && product.TVA !== '' ? Number(product.TVA) : 21
      });
      renderInvoiceLines();
    });
  }
  function openInvoiceNewProduct() {
    sheet('Produs nou', '<form class="inv-form" id="inv-prod-form"><label>Produs<textarea id="np-name" required></textarea></label><label>Cod produs<input id="np-code" required></label><label>Pret<input id="np-price" type="number" step="0.01" required></label><div class="caiet-actions"><button type="submit" class="lg-btn-success">Salveaza</button></div></form>', 'form');
    document.getElementById('inv-prod-form').onsubmit = async event => {
      event.preventDefault();
      await api('produse_save', {denumire: document.getElementById('np-name').value, cod_produs: document.getElementById('np-code').value, pret: document.getElementById('np-price').value, TVA: 21, um: 'buc'});
      openInvoiceProducts();
    };
  }
  function openInvoiceClient() {
    const counties = (window.__invCounties || []).map(county => '<option value="' + esc(county) + '">' + esc(county) + '</option>').join('');
    sheet('Client nou', '<form class="inv-form" id="inv-client-form">'
      + '<label>Societate<input id="nc-company" placeholder="Denumire"></label>'
      + '<label>CUI / CNP<div class="inv-inline"><input id="nc-cif" placeholder="Cui/CNP"><button type="button" id="nc-anaf">Cauta ANAF</button></div></label>'
      + '<label>J<input id="nc-reg" placeholder="Reg.Com"></label>'
      + '<label>Cont bancar<input id="nc-iban" placeholder="Cont bancar"></label>'
      + '<label>Banca<input id="nc-bank" placeholder="Banca"></label>'
      + '<label>Nume / Contact<input id="nc-name" placeholder="Denumire client" required></label>'
      + '<label>Telefon<input id="nc-phone" placeholder="Telefon"></label>'
      + '<label>Judet<select id="nc-county"><option value="">-- Judet --</option>' + counties + '</select></label>'
      + '<label>Localitate<select id="nc-city"><option value="">Localitate</option></select></label>'
      + '<label>Adresa<textarea id="nc-address" placeholder="Str., nr., ..." required></textarea></label>'
      + '<label>Marca masina<input id="nc-marca" placeholder="Marca masina"></label>'
      + '<label>Serie sasiu<input id="nc-vin" placeholder="Serie Sasiu"></label>'
      + '<label>Nr. inmatriculare<input id="nc-plate" placeholder="Nr. inmatriculare"></label>'
      + '<div class="caiet-actions"><button type="submit" class="lg-btn-success">Salvare</button></div></form>', 'form');
    document.getElementById('nc-county').onchange = async () => {
      const county = document.getElementById('nc-county').value;
      const city = document.getElementById('nc-city');
      city.innerHTML = '<option value="">Localitate</option>';
      if (!county) return;
      const places = await api('facturi_localitati', {judet: county});
      city.innerHTML += places.map(place => '<option value="' + esc(place.idlocatie) + '">' + esc(place.localitate) + '</option>').join('');
    };
    document.getElementById('nc-anaf').onclick = async () => {
      const data = await api('facturi_anaf', {cui: document.getElementById('nc-cif').value});
      if (data.companie) document.getElementById('nc-company').value = data.companie;
      if (data.adresa) document.getElementById('nc-address').value = data.adresa;
      if (data.telefon) document.getElementById('nc-phone').value = data.telefon;
      if (data.regcom) document.getElementById('nc-reg').value = data.regcom;
      if (data.cont_banca) document.getElementById('nc-iban').value = data.cont_banca;
    };
    document.getElementById('inv-client-form').onsubmit = async event => {
      event.preventDefault();
      const saved = await api('clienti_save', {
        nume: document.getElementById('nc-name').value,
        telefon: document.getElementById('nc-phone').value,
        adresa: document.getElementById('nc-address').value,
        companie: document.getElementById('nc-company').value,
        cif: document.getElementById('nc-cif').value,
        regcom: document.getElementById('nc-reg').value,
        cont_banca: document.getElementById('nc-iban').value,
        nume_banca: document.getElementById('nc-bank').value,
        idlocalitate: document.getElementById('nc-city').value,
        marca: document.getElementById('nc-marca').value,
        sasiu: document.getElementById('nc-vin').value,
        nr_inmat: document.getElementById('nc-plate').value
      });
      invoiceClient = {id: saved.idclienti, company: saved.companie || ''};
      document.getElementById('inv-client').value = saved.nume || '';
      document.getElementById('inv-phone').value = saved.telefon || '';
      document.getElementById('inv-cui').value = saved.cif || '';
      applyPaymentRule();
      closeModal();
    };
  }
  function askInvoiceType() {
    if (!invoiceClient.id) {
      alert('Selecteaza un client.');
      return;
    }
    sheet('Selectează tip factură', '<p>Alege cum vrei să creezi factura:</p><div class="caiet-actions" style="flex-direction:column"><button type="button" id="inv-internal" class="lg-btn lg-btn-info">Factura Internă</button><button type="button" id="inv-smart" class="lg-btn lg-btn-info">Factura SmartBill</button></div>');
    document.getElementById('inv-internal').onclick = () => saveInvoice('internal');
    document.getElementById('inv-smart').onclick = () => saveInvoice('smartbill');
  }
  async function saveInvoice(kind) {
    try {
      const saved = await api('facturi_create', {
        id_client: invoiceClient.id,
        agent: document.getElementById('inv-agent').value,
        data_factura: document.getElementById('inv-date').value,
        data_scadenta: document.getElementById('inv-due').value,
        tip_incasare: document.getElementById('inv-pay').value,
        tip_factura: kind,
        lines: invoiceLines
      });
      closeModal();
      alert('Factura ' + saved.seria + ' ' + saved.OrderID + ' a fost creată.');
      await loadFacturi();
    } catch (error) {
      alert(error.message || 'Factura nu a putut fi salvată.');
    }
  }

  function loadIncasari() {
    const title = document.querySelector('.admin-content > h2');
    if (title) title.style.display = 'none';
    document.querySelector('.caiet-table').classList.add('inc-table');
    head.innerHTML = '<tr><th>Data comanda</th><th>Utilizator</th><th>Client</th><th>Suma incasata</th><th>Magazin</th><th>Metoda incasare</th></tr>';
    toolbar.innerHTML = '<div class="inc-head"><h4>⌕ Incasari</h4></div>'
      + '<div class="inc-date"><button type="button" id="inc-prev">‹</button><input id="inc-date" readonly><button type="button" id="inc-next">›</button></div>'
      + '<select id="inc-loc"><option value="">Locație</option><option value="1">TM</option><option value="2">UTVIN</option></select>'
      + '<select id="inc-method"><option value="">Metodă</option><option value="3">Cash</option><option value="6">Card</option><option value="9">OP</option><option value="7">FD</option><option value="5">Return</option><option value="4">Avans</option></select>'
      + '<button type="button" id="inc-add" class="inc-add">Adauga Cheltuieli</button><button type="button" id="inc-cash">Casa zilei</button>';
    const date = document.getElementById('inc-date');
    date.value = roStamp(new Date());
    document.getElementById('inc-prev').onclick = () => shiftIncasari(-1);
    document.getElementById('inc-next').onclick = () => shiftIncasari(1);
    document.getElementById('inc-loc').onchange = () => renderIncasari(1);
    document.getElementById('inc-method').onchange = () => renderIncasari(1);
    document.getElementById('inc-add').onclick = () => openExpense();
    document.getElementById('inc-cash').onclick = () => openDailyCash();
    return renderIncasari(1);
  }
  function roStamp(date) {
    const pad = value => String(value).padStart(2, '0');
    return pad(date.getDate()) + '/' + pad(date.getMonth() + 1) + '/' + date.getFullYear();
  }
  function isoFromRo(value) {
    const parts = String(value || '').split('/');
    if (parts.length !== 3) return today();
    return parts[2] + '-' + parts[1] + '-' + parts[0];
  }
  function shiftIncasari(delta) {
    const input = document.getElementById('inc-date');
    const parts = input.value.split('/');
    const date = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
    date.setDate(date.getDate() + delta);
    input.value = roStamp(date);
    renderIncasari(1);
  }
  function incBadge(value) {
    return Math.round(Number(value || 0)).toLocaleString('ro-RO');
  }
  async function renderIncasari(page) {
    const data = await api('incasari_list', {
      order_date: isoFromRo(document.getElementById('inc-date').value),
      location: document.getElementById('inc-loc').value,
      method: document.getElementById('inc-method').value,
      page: page || 1
    });
    const list = data.rows || [];
    body.innerHTML = list.map(row => '<tr class="' + (row.missing_order ? 'inc-red' : '') + '"><td>' + esc(row.order_date) + '</td><td><div class="inc-user"><span>' + esc(row.user_name) + '</span><span>' + esc(row.receipt_date) + '</span><span>' + esc(row.receipt_time) + '</span></div></td><td class="inc-client">' + esc(row.client_name) + '</td><td class="inc-sum">' + Number(row.suma || 0).toFixed(2) + '</td><td>' + esc(row.store_name) + '</td><td>' + esc(row.method_name) + '</td></tr>').join('') || '<tr><td colspan="6">Nu există înregistrări pentru data selectată.</td></tr>';
    const totals = data.totals || {};
    const pages = Number(data.pages || 1);
    const current = Number(data.page || 1);
    let pager = '';
    if (pages > 1) {
      pager = '<div class="inc-pager">' + Array.from({length: pages}, (_, index) => {
        const number = index + 1;
        return '<button type="button" data-page="' + number + '"' + (number === current ? ' class="is-on"' : '') + '>' + number + '</button>';
      }).join('') + '</div>';
    }
    extra.innerHTML = '<div class="inc-badges"><span class="info">Total zi: ' + incBadge(totals.zi) + '</span><span class="ok">Cash: ' + incBadge(totals.cash) + '</span><span class="ok">OP: ' + incBadge(totals.op) + '</span><span class="ok">Card: ' + incBadge(totals.card) + '</span><span class="ok">FD: ' + incBadge(totals.fd) + '</span><span class="ok">Retur: ' + incBadge(totals.retur) + '</span><span class="ok">Avans: ' + incBadge(totals.avans) + '</span></div>' + pager;
    extra.querySelectorAll('[data-page]').forEach(btn => btn.onclick = () => renderIncasari(Number(btn.dataset.page)));
  }
  function openExpense() {
    sheet('Start Of Day Cash', '<form class="inv-form" id="exp-form"><label>Text<textarea id="exp-text" rows="3" required></textarea></label><label>Price (RON)<input id="exp-price" type="number" step="0.01" required></label><label>Location<select id="exp-loc"><option value="TM">TM</option><option value="UTVIN">UTVIN</option></select></label><div class="caiet-actions"><button type="submit" class="lg-btn-success">Save</button><button type="button" data-close>Close</button></div></form>', 'form');
    document.getElementById('exp-form').onsubmit = async event => {
      event.preventDefault();
      try {
        await api('incasari_expense', {text: document.getElementById('exp-text').value, amount: document.getElementById('exp-price').value, location: document.getElementById('exp-loc').value});
        closeModal();
        await renderIncasari(1);
      } catch (error) {
        alert(error.message || 'Nu am putut salva cheltuiala.');
      }
    };
  }

  async function loadUsers() {
    head.innerHTML = '<tr><th>ID</th><th>Utilizator</th><th>Email</th><th>Nume</th><th>Telefon</th><th>Rol</th><th>Activ</th><th></th></tr>';
    toolbar.innerHTML = '<a class="caiet-btn" href="/admin/users">Utilizatori Besoiu</a><button type="button" id="new-user">Utilizator caiet</button><button type="button" id="go">Reîncarcă</button>';
    document.getElementById('go').onclick = () => renderUsers();
    document.getElementById('new-user').onclick = () => openUser(null);
    return renderUsers();
  }
  async function renderUsers() {
    rows = await api('utilizatori_list', {});
    body.innerHTML = rows.map(row => '<tr><td>' + esc(row.Id) + '</td><td>' + esc(row.username) + '</td><td>' + esc(row.email) + '</td><td>' + esc(row.nume_complet) + '</td><td>' + esc(row.telefon) + '</td><td>' + esc(row.rol) + '</td><td>' + esc(row.active) + '</td><td><button type="button" data-user="' + esc(row.Id) + '">Edit</button> <button type="button" data-user-del="' + esc(row.Id) + '">Șterge</button></td></tr>').join('') || '<tr><td colspan="8">Niciun utilizator.</td></tr>';
    body.querySelectorAll('[data-user]').forEach(btn => btn.onclick = () => openUser(rows.find(row => String(row.Id) === btn.dataset.user) || null));
    body.querySelectorAll('[data-user-del]').forEach(btn => btn.onclick = async () => {
      if (!confirm('Ștergi utilizatorul caiet ' + btn.dataset.userDel + '?')) return;
      await api('utilizatori_delete', {Id: btn.dataset.userDel});
      await renderUsers();
    });
  }
  async function loadApi() {
    head.innerHTML = '';
    toolbar.innerHTML = '<button type="button" id="go">Reîncarcă</button><button type="button" id="save-api" class="lg-btn lg-btn-success">Salvare</button>';
    document.getElementById('go').onclick = () => renderApi();
    document.getElementById('save-api').onclick = saveApi;
    return renderApi();
  }
  async function renderApi() {
    rows = await api('api_list', {});
    const groups = {};
    rows.forEach(row => { (groups[row.service_name] ||= []).push(row); });
    extra.innerHTML = Object.entries(groups).map(([service, fields]) => {
      const inputs = fields.map(field => {
        const value = field.data_value || '';
        const wide = String(value).length > 80 || String(service).indexOf('template') !== -1;
        const control = wide
          ? '<textarea data-service="' + esc(service) + '" data-key="' + esc(field.data_key) + '" rows="4">' + esc(value) + '</textarea>'
          : '<input data-service="' + esc(service) + '" data-key="' + esc(field.data_key) + '" value="' + esc(value) + '">';
        return '<label class="' + (wide ? 'wide' : '') + '">' + esc(String(field.data_key).replaceAll('_', ' ')) + control + '</label>';
      }).join('');
      return '<section class="caiet-sheet" style="width:100%;margin:0 0 12px"><div class="caiet-sheet__head"><h3>' + esc(service) + '</h3></div><div class="caiet-sheet__body caiet-api-fields">' + inputs + '</div></section>';
    }).join('') || '<p>Nicio credențială în baza de date.</p>';
    body.innerHTML = '';
  }
  async function saveApi() {
    const items = Array.from(document.querySelectorAll('[data-service][data-key]')).map(field => ({
      service_name: field.dataset.service,
      data_key: field.dataset.key,
      data_value: field.value
    }));
    try {
      const result = await api('api_save', {items});
      alert('Salvate: ' + (result.saved || 0));
    } catch (error) {
      alert(error.message);
    }
  }
  let pieseOrders = [];
  let piesePicked = [];
  async function loadPieseauto() {
    head.innerHTML = '<tr><th></th><th>Comandă</th><th>Cumpărător</th><th>Telefon</th><th>Oraș</th><th>Mașină</th><th>Total</th><th>Data</th></tr>';
    toolbar.innerHTML = '<select id="st"><option value="new">New</option><option value="in_progress">Processing</option><option value="finalized">Finalized</option><option value="cancelled">Cancelled</option></select><button type="button" id="go">Alege contul</button><button type="button" id="imp" class="lg-btn lg-btn-success" disabled>Import selectate</button>';
    document.getElementById('go').onclick = () => {
      sheet('Select PieseAuto Account', '<p>Alege contul din care se încarcă comenzile.</p><div class="caiet-actions"><button type="button" id="pa-1" class="lg-btn lg-btn-success">BesoiuPieseAuto</button><button type="button" id="pa-2">PieseAutoBesoiu</button></div>');
      document.getElementById('pa-1').onclick = () => renderPieseauto(1);
      document.getElementById('pa-2').onclick = () => renderPieseauto(2);
    };
    document.getElementById('go').click();
    document.getElementById('st').onchange = () => paintPieseauto();
    document.getElementById('imp').onclick = openPieseProducts;
    return Promise.resolve();
  }
  async function renderPieseauto(account) {
    closeModal();
    body.innerHTML = '<tr><td colspan="8">Se încarcă...</td></tr>';
    const data = await api('pieseauto_fetch', {account: account});
    pieseOrders = Array.isArray(data.orders) ? data.orders : [];
    if (!pieseOrders.length && data.message) extra.textContent = data.message;
    paintPieseauto();
  }
  function paintPieseauto() {
    const status = document.getElementById('st').value;
    const rows = pieseOrders.filter(order => String(order.order_status || '') === status);
    body.innerHTML = rows.map(order => {
      const car = order.car_info ? [order.car_info.car_maker, order.car_info.car_model, order.car_info.car_year].filter(Boolean).join(' ') : '';
      const total = (Number(order.items_cost_ron || 0) + Number(order.shipping_cost_ron || 0)).toFixed(2);
      const date = order.order_date ? new Date(Number(order.order_date) * 1000).toLocaleString('ro-RO') : '';
      return '<tr><td><input type="checkbox" class="pa-check" value="' + esc(order.order_id) + '"></td><td>' + esc(order.order_id) + '</td><td>' + esc(order.buyer_name) + '</td><td>' + esc(order.buyer_phone) + '</td><td>' + esc(order.shipping_city) + '</td><td>' + esc(car) + '</td><td>' + esc(total) + '</td><td>' + esc(date) + '</td></tr>';
    }).join('') || '<tr><td colspan="8">Nicio comandă ' + esc(status) + '.</td></tr>';
    body.querySelectorAll('.pa-check').forEach(box => box.onchange = () => {
      document.getElementById('imp').disabled = !body.querySelector('.pa-check:checked');
    });
  }
  function openPieseProducts() {
    const ids = [...body.querySelectorAll('.pa-check:checked')].map(box => String(box.value));
    piesePicked = pieseOrders.filter(order => ids.includes(String(order.order_id)));
    if (!piesePicked.length) return;
    const supplierOpts = SUPPLIERS.map(name => '<option value="' + name + '">' + name + '</option>').join('');
    const colorOpts = COLORS.map(item => '<option value="' + item[0] + '">' + item[1] + '</option>').join('');
    const html = piesePicked.map(order => {
      const items = (order.items || []).map(item => '<label class="pa-line"><input type="checkbox" class="pa-prod" data-order="' + esc(order.order_id) + '" data-prod="' + esc(item.product_id) + '" checked> <span>' + esc(item.title) + '</span> — ' + esc(item.unit_price_ron) + ' RON</label><div class="pa-edit"><input data-field="title" data-order="' + esc(order.order_id) + '" data-prod="' + esc(item.product_id) + '" value="' + esc(item.title) + '"><input data-field="price" data-order="' + esc(order.order_id) + '" data-prod="' + esc(item.product_id) + '" value="' + esc(item.unit_price_ron) + '"><input data-field="code" data-order="' + esc(order.order_id) + '" data-prod="' + esc(item.product_id) + '" value="' + esc(item.product_id) + '"><select data-field="furnizor" data-order="' + esc(order.order_id) + '" data-prod="' + esc(item.product_id) + '">' + supplierOpts + '</select><select data-field="disponibilitate" data-order="' + esc(order.order_id) + '" data-prod="' + esc(item.product_id) + '">' + colorOpts + '</select></div>').join('');
      return '<h4>Comanda #' + esc(order.order_id) + ' — ' + esc(order.buyer_name) + '</h4>' + items + '<label class="pa-line"><input type="checkbox" class="pa-transport" data-order="' + esc(order.order_id) + '" checked> Transport Service — <input class="pa-transport-price" data-order="' + esc(order.order_id) + '" value="' + esc(order.shipping_cost_ron || 30) + '" style="width:80px"> RON</label>';
    }).join('<hr>') + '<div class="caiet-actions"><button type="button" id="pa-next" class="lg-btn lg-btn-success">Mai departe: destinația</button></div>';
    sheet('Selectează produsele', html, true);
    document.getElementById('pa-next').onclick = () => {
      const chosen = [...modal.querySelectorAll('.pa-prod:checked')];
      if (!chosen.length) { alert('Alege cel puțin un produs.'); return; }
      const read = (orderId, prodId, field) => {
        const el = modal.querySelector('[data-field="' + field + '"][data-order="' + orderId + '"][data-prod="' + prodId + '"]');
        return el ? el.value : '';
      };
      const orders = piesePicked.map(order => {
        const selected = chosen.filter(box => String(box.dataset.order) === String(order.order_id)).map(box => String(box.dataset.prod));
        const items = (order.items || []).filter(item => selected.includes(String(item.product_id))).map(item => Object.assign({}, item, {
          title: read(order.order_id, item.product_id, 'title') || item.title,
          unit_price_ron: read(order.order_id, item.product_id, 'price') || item.unit_price_ron,
          product_id: read(order.order_id, item.product_id, 'code') || item.product_id,
          furnizor: read(order.order_id, item.product_id, 'furnizor') || 'ET',
          disponibilitate: read(order.order_id, item.product_id, 'disponibilitate') || '7CFC00'
        }));
        const transport = modal.querySelector('.pa-transport[data-order="' + order.order_id + '"]');
        if (transport && transport.checked) {
          const price = modal.querySelector('.pa-transport-price[data-order="' + order.order_id + '"]');
          items.push({ product_id: 'transport_' + order.order_id, title: 'Transport Service', unit_price_ron: price ? price.value : 30, quantity: 1, furnizor: '__', disponibilitate: 'FFFFFF' });
        }
        return Object.assign({}, order, { items: items });
      }).filter(order => (order.items || []).length);
      sheet('Selectează destinația', '<p>Unde se adaugă comenzile selectate?</p><div class="caiet-actions"><button type="button" class="lg-btn lg-btn-success" data-dest="UTVIN">UTVIN</button><button type="button" class="lg-btn" data-dest="TM">TM</button><button type="button" class="lg-btn" data-dest="External">Externe</button></div>');
      modal.querySelectorAll('[data-dest]').forEach(btn => btn.onclick = () => importPieseauto(btn.getAttribute('data-dest'), orders));
    };
  }
  async function importPieseauto(destination, orders) {
    const result = await api('pieseauto_import', { orders: orders, destination: destination });
    closeModal();
    const target = destination === 'UTVIN' ? '/admin/caiet-comenzi-utvin' : (destination === 'TM' ? '/admin/caiet-comenzi-tm' : '/admin/caiet-comenzi-ext');
    alert('Importate: ' + (result.imported || 0));
    window.location.href = target;
  }

  function colorName(hex) {
    const key = String(hex || 'FFFFFF').replace('#', '').toUpperCase();
    const found = COLORS.find(item => item[0].toUpperCase() === key);
    return found ? found[1] : (key === 'FFFFFF' ? 'Sosit' : key);
  }
  function statusMap() {
    return SOURCE === 'externa' ? STATUS_EXT : STATUS;
  }
  function statusName(code) {
    return (statusMap()[code] || ['Status ' + code, '#777'])[0];
  }
  function statusColor(code) {
    return (statusMap()[code] || ['', '#777'])[1];
  }
  function sheet(title, body, wide) {
    mountModal();
    const size = wide === 'form' ? ' caiet-sheet--form' : (wide ? '' : ' caiet-sheet--sm');
    modal.innerHTML = '<div class="caiet-sheet' + size + '"><div class="caiet-sheet__head"><h3>' + esc(title) + '</h3><button type="button" class="caiet-sheet__close" data-close>&times;</button></div><div class="caiet-sheet__body">' + body + '</div></div>';
    modal.classList.remove('hidden');
    modal.querySelectorAll('[data-close]').forEach(btn => { btn.onclick = closeModal; });
  }
  function loadComenzi() {
    head.innerHTML = SOURCE === 'externa'
      ? '<tr class="info"><th>Data</th><th>Client</th><th>Adresa</th><th>Produs</th><th>Cod</th><th>Furnizor</th><th>Cant.</th><th>Pret</th><th>Total</th><th>AWB</th><th>Status</th><th>Notita</th><th>Actiune</th></tr>'
      : '<tr class="info"><th>Data</th><th>Client</th><th>Marca</th><th>Total</th><th>Status</th><th>Notita</th><th>Actiune</th></tr>';
    const statusOpts = Object.entries(statusMap()).map(([id, pair]) => '<option value="' + id + '">' + esc(pair[0]) + '</option>').join('');
    toolbar.innerHTML = '<button type="button" id="prev">←</button><input id="day" type="date"><button type="button" id="next">→</button><button type="button" id="all">Toate zilele</button><select id="st"><option value="">Status</option>' + statusOpts + '</select><input id="q" type="search" placeholder="Nume, telefon, marca, adresa, cod"><button type="button" id="go">Cauta</button><a class="lg-btn lg-btn-success" href="/admin/caiet-pieseauto">Import comenzi pieseauto.ro</a><button type="button" id="new" class="lg-btn lg-btn-success">+ Comanda noua</button>';
    const day = document.getElementById('day');
    day.value = '';
    document.getElementById('prev').onclick = () => shiftDay(-1);
    document.getElementById('next').onclick = () => shiftDay(1);
    document.getElementById('all').onclick = () => { day.value = ''; renderComenzi(); };
    document.getElementById('go').onclick = () => renderComenzi();
    document.getElementById('st').onchange = () => renderComenzi();
    document.getElementById('new').onclick = () => openNewOrder(null);
    return renderComenzi();
  }
  function shiftDay(delta) {
    const day = document.getElementById('day');
    const date = new Date(day.value || today());
    date.setDate(date.getDate() + delta);
    day.value = date.toISOString().slice(0, 10);
    renderComenzi();
  }
  async function renderComenzi() {
    const status = document.getElementById('st').value;
    rows = await api('comenzi_list', {search: document.getElementById('q').value, source_type: SOURCE, location: SOURCE === 'externa' ? '' : LOC, status: status, order_date: document.getElementById('day').value, limit: 80});
    const dayTotal = rows.reduce((sum, row) => sum + Number(row.total_amount || 0), 0);
    extra.innerHTML = '<div class="total-wrapper"><span class="total-zi">Total zi: ' + dayTotal.toFixed(2) + '</span><span class="total-cautare">Total cautare: ' + dayTotal.toFixed(2) + '</span></div>';
    body.innerHTML = (SOURCE === 'externa' ? rows.map(renderExternalOrder) : rows.map(row => {
      const stare = Number(row.status || 0);
      const statusBtn = '<button type="button" class="lg-btn" style="background:' + statusColor(stare) + ';color:#fff;border-color:' + statusColor(stare) + '" data-status="' + esc(row.order_id) + '" data-source="' + esc(row.source_type) + '" data-stare="' + stare + '">' + esc(statusName(stare)) + '</button>';
      const note = row.observations ? '<button type="button" class="lg-btn lg-btn-danger" title="' + esc(row.observations) + '" data-note="' + esc(row.observations) + '">i</button>' : '<b>__</b>';
      const phone = row.client_phone ? '<span class="order-phone">' + esc(row.client_phone) + '</span>' : '';
      return '<tr><td>' + esc(row.order_date) + '</td><td><b>' + esc(row.client_name) + '</b>' + phone + '</td><td>' + esc(row.marca) + '</td><td><button type="button" data-total="' + esc(row.order_id) + '" data-source="' + esc(row.source_type) + '" data-amount="' + esc(row.total_amount) + '"><b>' + Number(row.total_amount || 0).toFixed(2) + '</b></button></td><td>' + statusBtn + '</td><td>' + note + '</td><td>' + actionButtons(row, stare) + '</td></tr>';
    })).join('') || '<tr><td colspan="' + (SOURCE === 'externa' ? 13 : 7) + '">' + (document.getElementById('day').value ? 'Nicio comandă în ziua aleasă.' : 'Nicio comandă în baza de date.') + '</td></tr>';
    body.querySelectorAll('[data-status]').forEach(btn => btn.onclick = () => openStatus(btn.dataset.status, btn.dataset.source, btn.dataset.stare));
    body.querySelectorAll('[data-total]').forEach(btn => btn.onclick = () => openTotal(btn.dataset.total, btn.dataset.source, btn.dataset.amount));
    body.querySelectorAll('[data-note]').forEach(btn => btn.onclick = () => sheet('Observații comandă', '<p style="margin:0;white-space:pre-wrap">' + esc(btn.getAttribute('title') || '') + '</p><div class="caiet-actions"><button type="button" data-close>Închide</button></div>'));
    body.querySelectorAll('[data-lines]').forEach(btn => btn.onclick = () => openLines(btn.dataset.lines, btn.dataset.source, btn.dataset.stare));
    body.querySelectorAll('[data-loc]').forEach(btn => btn.onclick = () => openLocation(btn.dataset.loc));
    body.querySelectorAll('[data-sms]').forEach(btn => btn.onclick = () => openSms(btn.dataset.sms, btn.dataset.name, btn.dataset.phone));
    body.querySelectorAll('[data-wa]').forEach(btn => btn.onclick = () => openWhatsapp(btn.dataset.wa));
    body.querySelectorAll('[data-awb]').forEach(btn => btn.onclick = () => openAwb(btn.dataset.awb));
    body.querySelectorAll('[data-copy]').forEach(btn => btn.onclick = () => openNewOrder(rows.find(row => String(row.order_id) === btn.dataset.copy) || null));
    body.querySelectorAll('[data-del]').forEach(btn => btn.onclick = async () => {
      if (!confirm('Ștergi comanda ' + btn.dataset.del + '?')) return;
      await api('comenzi_delete', {order_id: btn.dataset.del, source_type: btn.dataset.source});
      await renderComenzi();
    });
    body.querySelectorAll('[data-factura]').forEach(btn => btn.onclick = () => showFactura(btn.dataset.factura));
    body.querySelectorAll('[data-factureaza]').forEach(btn => btn.onclick = () => openFactureaza(btn.dataset.factureaza));
  }
  function orderStamp(row) {
    const raw = String(row.created_at || row.order_date || '');
    const date = raw.slice(0, 10).split('-');
    const shown = date.length === 3 ? date[2] + '/' + date[1] + '/' + date[0] : esc(row.order_date || '');
    const time = raw.length >= 16 ? raw.slice(11, 16) : '';
    return '<div class="order-stamp"><span>' + esc(row.user_name || '') + '</span><span>' + shown + '</span><span>' + esc(time) + '</span></div>';
  }
  function invoiceButton(row) {
    const invoiceId = Number(row.invoice_id || 0);
    if (invoiceId > 0) {
      return '<button type="button" class="lg-btn lg-btn-info" title="Tipărește factura" data-factura="' + invoiceId + '">Factură</button>';
    }
    return '<button type="button" class="lg-btn lg-btn-default" title="Facturează" data-factureaza="' + esc(row.order_id) + '">Facturează</button>';
  }
  function awbCell(row) {
    const awb = String(row.awb || '').trim();
    if (awb === '' || awb === '___') {
      return '<button type="button" class="lg-btn lg-btn-info" data-awb="' + esc(row.order_id) + '">Emitere AWB</button>';
    }
    const track = /^\d+$/.test(awb)
      ? 'https://www.fancourier.ro/awb-tracking/?tracking=' + encodeURIComponent(awb)
      : 'https://sameday.ro/#awb=' + encodeURIComponent(awb);
    let html = '<strong>' + esc(awb) + '</strong><a class="awb-track" href="' + track + '" target="_blank" rel="noopener">Urmărire colet</a>';
    const swap = String(row.swap_awb || '').trim();
    if (swap !== '' && swap !== '___') {
      html += '<strong>' + esc(swap) + '</strong>';
    }
    return html;
  }
  function renderExternalOrder(row) {
    const stare = Number(row.status || 0);
    const products = Array.isArray(row.products) && row.products.length ? row.products : [{denumire:'', cod_produs:'', furnizor:'', cantitate:'', pret:'', culoare:''}];
    const span = products.length;
    const statusBtn = '<button type="button" class="lg-btn" style="background:' + statusColor(stare) + ';color:#fff;border-color:' + statusColor(stare) + '" data-status="' + esc(row.order_id) + '" data-source="externa" data-stare="' + stare + '">' + esc(statusName(stare)) + '</button>';
    const note = row.observations ? '<button type="button" class="lg-btn lg-btn-danger" title="' + esc(row.observations) + '" data-note="' + esc(row.observations) + '">i</button>' : '<b>—</b>';
    const phone = '<span class="order-phone">' + esc(row.client_phone || '') + '</span>';
    const company = row.company ? '<small class="order-company">' + esc(row.company) + '</small>' : '';
    const meta = '<td rowspan="' + span + '">' + orderStamp(row) + '</td>'
      + '<td rowspan="' + span + '"><b>' + esc(row.client_name) + '</b>' + company + phone + '</td>'
      + '<td rowspan="' + span + '">' + esc(row.address_city || '') + '</td>';
    const tail = '<td rowspan="' + span + '"><button type="button" data-total="' + esc(row.order_id) + '" data-source="externa" data-amount="' + esc(row.total_amount) + '"><b>' + Number(row.total_amount || 0).toFixed(2) + '</b></button></td>'
      + '<td rowspan="' + span + '" class="awb-cell">' + awbCell(row) + '</td>'
      + '<td rowspan="' + span + '">' + statusBtn + '</td>'
      + '<td rowspan="' + span + '">' + note + '</td>'
      + '<td rowspan="' + span + '">' + actionButtons(row, stare) + '</td>';
    return products.map((product, index) => {
      const color = String(product.culoare || '').replace('#', '');
      const codeBg = color && color.toUpperCase() !== 'FFFFFF' ? '#' + color : '#7CFC00';
      const line = '<td>' + esc(product.denumire || '') + '</td>'
        + '<td class="code-cell" style="background:' + codeBg + '"><strong>' + esc(product.cod_produs || '') + '</strong></td>'
        + '<td><strong>' + esc(product.furnizor || '—') + '</strong></td>'
        + '<td>' + esc(product.cantitate === '' || product.cantitate == null ? '' : product.cantitate) + '</td>'
        + '<td>' + (product.pret === '' || product.pret == null ? '' : Number(product.pret).toFixed(2)) + '</td>';
      return '<tr>' + (index === 0 ? meta : '') + line + (index === 0 ? tail : '') + '</tr>';
    }).join('');
  }
  function openFactureaza(orderId) {
    const row = rows.find(item => String(item.order_id) === String(orderId));
    if (!row) return;
    sheet('Facturează comanda ' + orderId, '<p><strong>' + esc(row.client_name) + '</strong> — ' + Number(row.total_amount || 0).toFixed(2) + ' RON</p><div class="caiet-actions" style="flex-direction:column"><button type="button" id="bill-internal" class="lg-btn lg-btn-info">Factură internă</button><button type="button" id="bill-smart" class="lg-btn lg-btn-info">Factură SmartBill</button></div>', true);
    const run = async kind => {
      const saved = await api('facturi_from_order', {order_id: orderId, source_type: SOURCE, tip_factura: kind, tip_incasare: 1});
      closeModal();
      await printInvoice(saved.OrderID);
      await renderComenzi();
    };
    document.getElementById('bill-internal').onclick = () => run('internal');
    document.getElementById('bill-smart').onclick = () => run('smartbill');
  }
  function actionButtons(row, stare) {
    const id = esc(row.order_id);
    const source = esc(row.source_type);
    let html = '<div class="order-act">';
    if (SOURCE === 'externa') {
      if (stare === 3 || stare === 5) {
        html += '<button type="button" class="lg-btn lg-btn-success" title="SMS" data-sms="' + id + '" data-name="' + esc(row.client_name) + '" data-phone="' + esc(row.client_phone) + '">SMS</button>';
      }
      if (stare !== 3) {
        html += '<button type="button" class="lg-btn lg-btn-default" title="Editare" data-lines="' + id + '" data-source="' + source + '" data-stare="' + stare + '">Edit</button>';
      }
      if (stare === 1) {
        html += '<button type="button" class="lg-btn lg-btn-warning" title="Sterge" data-del="' + id + '" data-source="' + source + '">Del</button>';
      }
      html += '<button type="button" class="lg-btn lg-btn-success" title="Trimite WhatsApp" data-wa="' + id + '">WA</button>';
      html += invoiceButton(row);
      if (stare === 3 || stare === 4 || stare === 5 || stare === 6) {
        html += '<button type="button" class="lg-btn lg-btn-default" title="Duplicate" data-copy="' + id + '">Copy</button>';
      }
      return html + '</div>';
    }
    if (stare === 2 || stare === 4) {
      html += '<button type="button" class="lg-btn lg-btn-success" title="SMS" data-sms="' + id + '" data-name="' + esc(row.client_name) + '" data-phone="' + esc(row.client_phone) + '">SMS</button>';
    }
    if (stare === 1 || stare === 2 || stare === 4) {
      html += '<button type="button" class="lg-btn lg-btn-default" title="Editare" data-lines="' + id + '" data-source="' + source + '" data-stare="' + stare + '">Edit</button>';
    }
    if (stare === 1) {
      html += '<button type="button" class="lg-btn lg-btn-warning" title="Sterge" data-del="' + id + '" data-source="' + source + '">Del</button>';
    }
    html += invoiceButton(row);
    if (stare === 2 || stare === 4) {
      html += '<button type="button" class="lg-btn lg-btn-success" title="Trimite WhatsApp" data-wa="' + id + '">WA</button>';
    }
    if (SOURCE !== 'externa') {
      html += '<button type="button" class="lg-btn lg-btn-default" title="Magazin" data-loc="' + id + '">Mag</button>';
    }
    if ([8,5,3,6,7,4,9].includes(stare)) {
      html += '<button type="button" class="lg-btn lg-btn-default" title="Duplicate" data-copy="' + id + '">Copy</button>';
    }
    html += '<button type="button" class="lg-btn lg-btn-info" title="Linii" data-lines="' + id + '" data-source="' + source + '" data-stare="' + stare + '">Linii</button>';
    return html + '</div>';
  }
  function openStatus(orderId, source, stare) {
    const group = (title, ids) => '<p><b><u>' + title + '</u></b></p>' + ids.map(id => '<label class="st-btn" style="background:' + statusColor(id) + '"><input type="radio" name="stare" value="' + id + '"' + (String(stare) === String(id) ? ' checked' : '') + '>' + esc(statusName(id)) + '</label>').join('');
    const body = SOURCE === 'externa'
      ? group('Status comanda', [1,2,3,4,5,6,7])
      : group('Status comanda', [1,2,8,5]) + group('Incasare comanda', [3,6,7,4,9,10,11,12,13]);
    sheet('Editare status comanda', body + '<div class="caiet-actions"><button type="button" id="save-status" class="lg-btn lg-btn-success">Actualizare date</button></div>');
    document.getElementById('save-status').onclick = async () => {
      const picked = modal.querySelector('input[name="stare"]:checked');
      if (!picked) return;
      await api('comenzi_status', {order_id: orderId, source_type: source, new_status: picked.value});
      closeModal();
      await renderComenzi();
    };
  }
  function openLocation(orderId) {
    sheet('Magazin', '<label><input type="radio" name="location" value="1" checked> Timisoara</label><br><label><input type="radio" name="location" value="2"> Utvin</label><div class="caiet-actions"><button type="button" id="save-loc" class="lg-btn lg-btn-success">Actualizare date</button></div>');
    document.getElementById('save-loc').onclick = async () => {
      const picked = modal.querySelector('input[name="location"]:checked');
      await api('comenzi_location', {order_id: orderId, location: picked.value, source_type: 'interna'});
      closeModal();
      await renderComenzi();
    };
  }
  function openTotal(orderId, source, amount) {
    sheet('Editare total comanda', '<label>Total actual<input id="old-total" value="' + esc(Number(amount || 0).toFixed(2)) + '" readonly></label><label>Transport<input id="transport" type="number" step="0.01" value="0"></label><div class="caiet-actions"><button type="button" id="save-total" class="lg-btn lg-btn-success">Actualizare date</button></div>');
    document.getElementById('save-total').onclick = async () => {
      await api('comenzi_total', {order_id: orderId, source_type: source, transport: document.getElementById('transport').value});
      closeModal();
      await renderComenzi();
    };
  }
  function openSms(orderId, name, phone) {
    sheet('Trimite SMS', '<label>Nume client<input value="' + esc(name) + '" readonly></label><label>Telefon<input id="sms-phone" value="' + esc(phone) + '"></label><label>Mesaj<textarea id="sms-msg" style="width:100%;height:90px"></textarea></label><div class="caiet-actions"><button type="button" data-close>Închide</button><button type="button" id="send-sms" class="lg-btn lg-btn-success">Trimite SMS</button></div>');
    modal.querySelector('[data-close]').onclick = closeModal;
    api('sms_prepare', {order_id: orderId, source_type: SOURCE}).then(data => {
      const box = document.getElementById('sms-msg');
      const phone = document.getElementById('sms-phone');
      if (box && !box.value) box.value = data.default_message || '';
      if (phone && data.client_phone) phone.value = data.client_phone;
    }).catch(() => {});
    document.getElementById('send-sms').onclick = async () => {
      try {
        const result = await api('sms_send', {order_id: orderId, source_type: SOURCE, phone: document.getElementById('sms-phone').value, message: document.getElementById('sms-msg').value});
        closeModal();
        alert(result.message || 'SMS trimis.');
      } catch (error) {
        alert(error.message);
      }
    };
  }
  async function openLines(orderId, source, stare) {
    const data = await api('comenzi_details', {order_id: orderId, source_type: source});
    const canEdit = Number(stare) < 3;
    const lines = (data.lines || []).map(line => '<tr><td>' + esc(line.idprodus) + '</td><td><input data-qty="' + esc(line.iddetaliu) + '" value="' + esc(line.cantitate) + '" style="width:70px"' + (canEdit ? '' : ' readonly') + '></td><td><input data-price="' + esc(line.iddetaliu) + '" value="' + esc(line.pret) + '" style="width:80px"' + (canEdit ? '' : ' readonly') + '></td><td>' + (canEdit ? '<button type="button" class="st-btn" style="background:#' + esc(line.culoare || 'FFFFFF') + ';color:#111" data-color="' + esc(line.iddetaliu) + '">' + esc(colorName(line.culoare)) + '</button>' : esc(colorName(line.culoare))) + '</td><td>' + (canEdit ? '<button type="button" data-sup="' + esc(line.iddetaliu) + '">' + esc(line.furnizor || '__') + '</button>' : esc(line.furnizor || '')) + '</td><td>' + (canEdit ? '<button type="button" data-save-line="' + esc(line.iddetaliu) + '" data-prod="' + esc(line.idprodus) + '">Salvează</button> <button type="button" data-drop-line="' + esc(line.iddetaliu) + '">Șterge</button>' : '') + '</td></tr>').join('');
    sheet('Linii comanda ' + orderId, '<table class="caiet-lines"><thead><tr><th>ID produs</th><th>Cant.</th><th>Pret</th><th>Culoare</th><th>Furnizor</th><th></th></tr></thead><tbody>' + (lines || '<tr><td colspan="6">Fără linii.</td></tr>') + '</tbody></table>' + (canEdit ? '<div class="caiet-actions"><button type="button" id="add-line">Adaugă produs</button></div>' : ''), true);
    const add = document.getElementById('add-line');
    if (add) add.onclick = () => openOrderProduct(orderId, source, stare);
    modal.querySelectorAll('[data-save-line]').forEach(btn => btn.onclick = async () => {
      await api('comenzi_line_save', {order_id: orderId, source_type: source, iddetaliu: btn.dataset.saveLine, idprodus: btn.dataset.prod, cantitate: modal.querySelector('[data-qty="' + btn.dataset.saveLine + '"]').value, pret: modal.querySelector('[data-price="' + btn.dataset.saveLine + '"]').value});
      await openLines(orderId, source, stare);
    });
    modal.querySelectorAll('[data-drop-line]').forEach(btn => btn.onclick = async () => {
      await api('comenzi_line_delete', {order_id: orderId, source_type: source, iddetaliu: btn.dataset.dropLine});
      await openLines(orderId, source, stare);
    });
    modal.querySelectorAll('[data-color]').forEach(btn => btn.onclick = () => openColor(orderId, source, btn.dataset.color, stare));
    modal.querySelectorAll('[data-sup]').forEach(btn => btn.onclick = () => openSupplier(orderId, source, btn.dataset.sup, stare));
  }
  function openColor(orderId, source, lineId, stare) {
    const options = COLORS.map(item => '<label class="st-btn" style="background:' + item[2] + ';color:' + (item[0] === 'FFFFFF' ? '#111' : '#fff') + '"><input type="radio" name="color" value="' + item[0] + '">' + item[1] + '</label>').join('');
    sheet('Editare status piesa', options);
    modal.querySelectorAll('input[name="color"]').forEach(input => input.onchange = async () => {
      await api('comenzi_line', {order_id: orderId, source_type: source, iddetaliu: lineId, field: 'culoare', value: input.value});
      await openLines(orderId, source, stare);
    });
  }
  function openSupplier(orderId, source, lineId, stare) {
    const options = SUPPLIERS.map(name => '<label class="st-btn" style="background:#e5e5e5;color:#111;display:block"><input type="radio" name="supplier" value="' + name + '">' + name + '</label>').join('');
    sheet('Furnizor piesa', options);
    modal.querySelectorAll('input[name="supplier"]').forEach(input => input.onchange = async () => {
      await api('comenzi_line', {order_id: orderId, source_type: source, iddetaliu: lineId, field: 'furnizor', value: input.value});
      await openLines(orderId, source, stare);
    });
  }
  function openNewOrder(seed) {
    const locDefault = SOURCE === 'externa' ? '3' : (LOC === 'utvin' ? '2' : '1');
    const now = new Date();
    const time = now.toLocaleTimeString('ro-RO', {hour:'2-digit', minute:'2-digit'});
    draftLines = [];
    sheet('Comanda noua',
      '<div class="order-form">' +
      '<div class="order-line"><label for="client_search">Client</label><div class="order-ctrl caiet-suggest"><div class="caiet-input-plus"><input id="client_search" placeholder="Cauta si click pe client din lista" autocomplete="off" value="' + esc(seed && seed.client_name || '') + '"><button type="button" id="add-client" title="Client nou">+</button></div><ul id="client-list" hidden></ul><input type="hidden" id="id_client" value="' + esc(seed && seed.client_id || '') + '"></div><label for="telefon">Telefon</label><div class="order-ctrl"><input id="telefon" readonly value="' + esc(seed && seed.client_phone || '') + '"></div><label for="adresa">Adresa</label><div class="order-ctrl"><input id="adresa" readonly></div></div>' +
      '<div class="order-line"><label for="data">Data</label><div class="order-ctrl order-when"><input id="data" type="date" value="' + esc((document.getElementById('day') || {}).value || today()) + '"><input id="created_time" type="text" readonly value="' + esc(time) + '"></div><label for="marca">Marca masina</label><div class="order-ctrl"><input id="marca" value="' + esc(seed && seed.marca || '') + '"></div><label for="idstare">Stare</label><div class="order-ctrl"><select id="idstare"><option value="1">Comandat</option><option value="2">Sosit</option><option value="3">Expediat</option><option value="4">Achitat</option></select></div></div>' +
      '<div class="order-split"><div class="order-block"><span class="order-k">Magazin</span><div class="order-radios"><label><input type="radio" name="locatie_mgz" value="1"' + (locDefault === '1' ? ' checked' : '') + '> Timisoara</label><label><input type="radio" name="locatie_mgz" value="2"' + (locDefault === '2' ? ' checked' : '') + '> Utvin</label><label><input type="radio" name="locatie_mgz" value="3"' + (locDefault === '3' ? ' checked' : '') + '> Externe</label></div></div><div class="order-block"><label for="observations">Observații comandă</label><div class="order-ctrl"><textarea id="observations" placeholder="Adăugați observații sau comentarii despre comandă..."></textarea></div></div></div>' +
      '<div class="order-actions"><button type="button" id="add-product" class="lg-btn lg-btn-default">Adauga produs</button><button type="button" id="save-order" class="lg-btn lg-btn-success">Salvare</button></div>' +
      '</div>' +
      '<table class="caiet-lines"><thead><tr><th>Nr. Crt.</th><th>PRODUS</th><th>COD PRODUS</th><th>CANT.</th><th>PRET</th><th>VALOARE.</th><th></th></tr></thead><tbody id="draft-lines"></tbody></table>',
      true);
    paintDraft();
    const search = document.getElementById('client_search');
    let timer = 0;
    search.oninput = () => {
      document.getElementById('id_client').value = '';
      clearTimeout(timer);
      timer = setTimeout(() => suggestClients(search.value), 200);
    };
    document.getElementById('add-client').onclick = () => openClientNou();
    document.getElementById('add-product').onclick = () => openProductSearch();
    document.getElementById('save-order').onclick = saveNewOrder;
  }
  async function suggestClients(query) {
    const list = document.getElementById('client-list');
    if (!list || query.trim().length < 1) { if (list) list.hidden = true; return; }
    const found = await api('clienti_list', {search: query, limit: 8});
    list.innerHTML = (found || []).map((row, index) => '<li data-i="' + index + '">' + esc(row.nume) + ' · ' + esc(row.telefon || '') + '</li>').join('');
    list.hidden = !found.length;
    list.querySelectorAll('li').forEach(item => item.onclick = () => {
      const row = found[Number(item.dataset.i)];
      document.getElementById('id_client').value = row.idclienti;
      document.getElementById('client_search').value = row.nume || '';
      document.getElementById('telefon').value = row.telefon || '';
      document.getElementById('adresa').value = row.adresa || '';
      document.getElementById('marca').value = row.marca || document.getElementById('marca').value;
      list.hidden = true;
    });
  }
  function openClientNou() {
    const orderHtml = modal.innerHTML;
    sheet('Client nou', '<label>Societate<input id="cl-comp"></label><label>CUI / CNP<input id="cl-cif"></label><label>Nume / Contact<input id="cl-nume" required></label><label>Telefon<input id="cl-tel"></label><label>Adresa<textarea id="cl-adr" style="width:100%;height:70px"></textarea></label><label>Marca masina<input id="cl-marca"></label><label>Serie sasiu<input id="cl-sasiu"></label><label>Nr. inmatriculare<input id="cl-nr"></label><div class="caiet-actions"><button type="button" id="cl-back" class="lg-btn">Închide</button><button type="button" id="cl-save" class="lg-btn lg-btn-success">Salvare</button></div>');
    document.getElementById('cl-back').onclick = () => { modal.innerHTML = orderHtml; bindOrderDraft(); };
    document.getElementById('cl-save').onclick = async () => {
      const saved = await api('clienti_save', {nume: document.getElementById('cl-nume').value, telefon: document.getElementById('cl-tel').value, adresa: document.getElementById('cl-adr').value, companie: document.getElementById('cl-comp').value, cif: document.getElementById('cl-cif').value, marca: document.getElementById('cl-marca').value, sasiu: document.getElementById('cl-sasiu').value, nr_inmat: document.getElementById('cl-nr').value});
      modal.innerHTML = orderHtml;
      bindOrderDraft();
      document.getElementById('id_client').value = saved.idclienti || '';
      document.getElementById('client_search').value = saved.nume || '';
      document.getElementById('telefon').value = saved.telefon || '';
      document.getElementById('adresa').value = saved.adresa || '';
      document.getElementById('marca').value = saved.marca || '';
    };
  }
  function bindOrderDraft() {
    const search = document.getElementById('client_search');
    if (!search) return;
    let timer = 0;
    search.oninput = () => { document.getElementById('id_client').value = ''; clearTimeout(timer); timer = setTimeout(() => suggestClients(search.value), 200); };
    const addClient = document.getElementById('add-client');
    const addProduct = document.getElementById('add-product');
    const save = document.getElementById('save-order');
    if (addClient) addClient.onclick = () => openClientNou();
    if (addProduct) addProduct.onclick = () => openProductSearch();
    if (save) save.onclick = saveNewOrder;
    modal.querySelector('[data-close]').onclick = closeModal;
    paintDraft();
  }
  function openProductSearch() {
    const orderHtml = modal.innerHTML;
    sheet('Cauta produs', '<div class="caiet-input-plus"><input id="prod-q" placeholder="Cauta produs"><button type="button" id="prod-go">Cauta</button></div><div id="prod-rows"></div><div class="caiet-actions"><button type="button" id="prod-back" class="lg-btn">Închide</button></div>', true);
    const run = async () => {
      const page = await api('produse_list', {search: document.getElementById('prod-q').value, limit: 20});
      const items = page.items || [];
      document.getElementById('prod-rows').innerHTML = '<table class="caiet-lines"><thead><tr><th>Produs</th><th>Cod</th><th>Pret</th><th></th></tr></thead><tbody>' + (items.map((item, index) => '<tr><td>' + esc(item.denumire) + '</td><td>' + esc(item.cod_produs) + '</td><td>' + esc(item.pret) + '</td><td><button type="button" data-pick="' + index + '">Adauga</button></td></tr>').join('') || '<tr><td colspan="4">Niciun produs.</td></tr>') + '</tbody></table>';
      document.querySelectorAll('[data-pick]').forEach(btn => btn.onclick = () => {
        const item = items[Number(btn.dataset.pick)];
        draftLines.push({idprodus: item.idprodus, denumire: item.denumire || '', cod: item.cod_produs || '', cantitate: 1, pret: Number(item.pret || 0), furnizor: '__'});
        modal.innerHTML = orderHtml;
        bindOrderDraft();
      });
    };
    document.getElementById('prod-go').onclick = run;
    document.getElementById('prod-q').onkeyup = event => { if (event.key === 'Enter') run(); };
    document.getElementById('prod-back').onclick = () => { modal.innerHTML = orderHtml; bindOrderDraft(); };
    run();
  }
  function paintDraft() {
    const target = document.getElementById('draft-lines');
    if (!target) return;
    let total = 0;
    const rowsHtml = draftLines.map((line, index) => {
      const value = Number(line.cantitate) * Number(line.pret);
      total += value;
      return '<tr><td>' + (index + 1) + '</td><td>' + esc(line.denumire) + '</td><td>' + esc(line.cod) + '</td><td><input data-qty="' + index + '" type="number" min="1" value="' + esc(line.cantitate) + '" style="width:70px"></td><td>' + Number(line.pret).toFixed(2) + '</td><td>' + value.toFixed(2) + '</td><td><button type="button" data-drop="' + index + '">x</button></td></tr>';
    }).join('');
    target.innerHTML = rowsHtml + '<tr><td colspan="5" style="text-align:right"><strong>TOTAL</strong></td><td>' + total.toFixed(2) + '</td><td></td></tr>';
    target.querySelectorAll('[data-qty]').forEach(input => input.onchange = () => { draftLines[Number(input.dataset.qty)].cantitate = Math.max(1, Number(input.value) || 1); paintDraft(); });
    target.querySelectorAll('[data-drop]').forEach(btn => btn.onclick = () => { draftLines.splice(Number(btn.dataset.drop), 1); paintDraft(); });
  }
  async function saveNewOrder() {
    try {
      const clientId = document.getElementById('id_client').value;
      const loc = (modal.querySelector('input[name="locatie_mgz"]:checked') || {}).value || '1';
      await api('comenzi_create', {idclient: clientId, data: document.getElementById('data').value, idstare: document.getElementById('idstare').value, locatie_mgz: loc, observations: document.getElementById('observations').value, source_type: loc === '3' ? 'externa' : SOURCE, lines: draftLines});
      closeModal();
      await renderComenzi();
    } catch (error) {
      alert(error.message);
    }
  }

  async function printInvoice(id) {
    const data = await api('facturi_print', {OrderID: id});
    const page = window.open('', '_blank');
    if (page) {
      page.document.open();
      page.document.write(data.html || '');
      page.document.close();
    }
  }
  async function openWhatsapp(orderId) {
    const data = await api('whatsapp', {order_id: orderId, source_type: SOURCE});
    if (data.url) window.open(data.url, '_blank', 'noopener');
  }
  async function openAwb(orderId) {
    const data = await api('awb_form', {order_id: orderId, source_type: 'externa'});
    const order = data.order || {};
    const counties = (data.counties || []).map(name => '<option' + (name === order.judet ? ' selected' : '') + '>' + esc(name) + '</option>').join('');
    sheet('Emitere AWB', '<label>Curier<select id="awb-courier"><option value="fancourier">FanCourier</option><option value="sameday">Sameday</option></select></label><label>Nume<input id="awb-name" value="' + esc(order.nume || '') + '"></label><label>Telefon<input id="awb-phone" value="' + esc(order.telefon || '') + '"></label><label>Județ<select id="awb-county">' + counties + '</select></label><label>Localitate<input id="awb-city" value="' + esc(order.localitate || '') + '"></label><label>Adresă<input id="awb-address" value="' + esc(order.adresa || '') + '"></label><label>Ramburs<input id="awb-cod" value="' + esc(order.total || 0) + '"></label><label>Greutate<input id="awb-weight" value="1"></label><label>Serviciu<select id="awb-service"><option>Standard</option><option>Cont Colector</option></select></label><label><input id="awb-open" type="checkbox"> Deschidere la livrare</label><div class="caiet-actions"><button type="button" id="awb-manual">Salvează AWB manual</button><button type="button" id="awb-go" class="lg-btn lg-btn-success">Generează AWB</button></div>', 'form');
    document.getElementById('awb-go').onclick = async () => {
      try {
        const saved = await api('awb_create', {order_id: orderId, courier: document.getElementById('awb-courier').value, nume: document.getElementById('awb-name').value, telefon: document.getElementById('awb-phone').value, judet: document.getElementById('awb-county').value, localitate: document.getElementById('awb-city').value, adresa: document.getElementById('awb-address').value, ramburs: document.getElementById('awb-cod').value, greutate: document.getElementById('awb-weight').value, tipserviciu: document.getElementById('awb-service').value, deschidere: document.getElementById('awb-open').checked ? 1 : 0, contfan: LOC === '2' ? 'Utvin' : 'Timisoara'});
        closeModal();
        alert('AWB ' + (saved.awb_number || ''));
        await renderComenzi();
      } catch (error) { alert(error.message); }
    };
    document.getElementById('awb-manual').onclick = async () => {
      const value = prompt('Număr AWB', order.awb || '');
      if (!value) return;
      await api('awb_update', {order_id: orderId, awb: value});
      closeModal();
      await renderComenzi();
    };
  }
  async function openDailyCash() {
    const data = await api('incasari_daily', {});
    sheet('Casa zilei', '<label>Sumă<input id="cash-amount" value="' + esc(data.amount || data.suma || 0) + '"></label><div class="caiet-actions"><button type="button" id="cash-save" class="lg-btn lg-btn-success">Salvează</button></div>');
    document.getElementById('cash-save').onclick = async () => {
      await api('incasari_daily_save', {amount: document.getElementById('cash-amount').value});
      closeModal();
    };
  }
  function openUser(row) {
    const menus = ['produse','clienti','comenzi_externe','facturi','incasari','ultilizatori','pieseauto','searching','apicredentials'];
    const checks = menus.map(key => '<label><input type="checkbox" data-perm="' + key + '" checked> ' + key + '</label>').join(' ');
    sheet(row ? 'Edit utilizator' : 'Utilizator nou', '<label>Username<input id="u-user" value="' + esc(row && row.username || '') + '"></label><label>Email<input id="u-email" value="' + esc(row && row.email || '') + '"></label><label>Nume<input id="u-nume" value="' + esc(row && row.nume || '') + '"></label><label>Prenume<input id="u-prenume" value="' + esc(row && row.prenume || '') + '"></label><label>Telefon<input id="u-tel" value="' + esc(row && row.telefon || '') + '"></label><label>Rol<input id="u-rol" value="' + esc(row && row.rol || 'user') + '"></label><label>Parolă<input id="u-pass" type="password" placeholder="' + (row ? 'lasă gol dacă nu schimbi' : 'minim 8') + '"></label><label><input id="u-active" type="checkbox" checked> Activ</label><div>' + checks + '</div><div class="caiet-actions"><button type="button" id="u-save" class="lg-btn lg-btn-success">Salvează</button></div>', 'form');
    document.getElementById('u-save').onclick = async () => {
      const permissions = {};
      modal.querySelectorAll('[data-perm]').forEach(box => { permissions[box.dataset.perm] = box.checked ? 1 : 0; });
      try {
        await api('utilizatori_save', {Id: row && row.Id, username: document.getElementById('u-user').value, email: document.getElementById('u-email').value, nume: document.getElementById('u-nume').value, prenume: document.getElementById('u-prenume').value, telefon: document.getElementById('u-tel').value, rol: document.getElementById('u-rol').value, password: document.getElementById('u-pass').value, active: document.getElementById('u-active').checked ? 1 : 0, permissions});
        closeModal();
        await renderUsers();
      } catch (error) { alert(error.message); }
    };
  }
  function openOrderProduct(orderId, source, stare) {
    sheet('Adaugă produs', '<label>ID produs<input id="line-prod"></label><label>Cantitate<input id="line-qty" value="1"></label><label>Preț<input id="line-price" value="0"></label><label>Furnizor<select id="line-sup">' + SUPPLIERS.map(name => '<option>' + name + '</option>').join('') + '</select></label><div class="caiet-actions"><button type="button" id="line-add" class="lg-btn lg-btn-success">Adaugă</button></div>', 'form');
    document.getElementById('line-add').onclick = async () => {
      try {
        await api('comenzi_line_save', {order_id: orderId, source_type: source, idprodus: document.getElementById('line-prod').value, cantitate: document.getElementById('line-qty').value, pret: document.getElementById('line-price').value, furnizor: document.getElementById('line-sup').value});
        await openLines(orderId, source, stare);
      } catch (error) { alert(error.message); }
    };
  }
  function today() {
    return new Date().toISOString().slice(0, 10);
  }

  load();
})();
</script>
<?php endif; ?>
<?php if ($caietScreen === 'search'): ?>
<section class="caiet-panel" id="caiet-extra-tools" style="margin-top:12px">
    <h3>Liste, promoții, Autototal exclus</h3>
    <div class="caiet-toolbar">
        <input id="wl-name" placeholder="Nume listă">
        <input id="wl-phone" placeholder="Telefon">
        <input id="wl-vin" placeholder="VIN">
        <button type="button" id="wl-save" class="lg-btn lg-btn-success">Salvează wishlist din coș</button>
    </div>
    <div id="wl-list"></div>
    <div class="caiet-toolbar">
        <input id="pr-supplier" placeholder="furnizor">
        <input id="pr-brand" placeholder="marcă">
        <button type="button" id="pr-save">Adaugă promoție</button>
    </div>
    <div id="pr-list"></div>
    <div id="ex-list"></div>
</section>
<script>
(function () {
  const CSRF = <?= json_encode($caietCsrf, JSON_UNESCAPED_UNICODE) ?>;
  async function api(type, payload) {
    const response = await fetch('/admin/api/caiet_comenzi_endpoint.php', {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-Admin-CSRF': CSRF},
      body: JSON.stringify(Object.assign({type_product: type}, payload || {}))
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Eroare.');
    return result.data;
  }
  function esc(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
  async function load() {
    const lists = await api('wishlist_list', {});
    document.getElementById('wl-list').innerHTML = lists.map(row => '<p>' + esc(row.name) + ' ' + esc(row.phone || '') + ' <button type="button" data-offer="' + esc(row.id) + '">WhatsApp</button> <button type="button" data-del="' + esc(row.id) + '">Șterge</button></p>').join('') || '<p>Nicio listă salvată.</p>';
    document.querySelectorAll('[data-offer]').forEach(btn => btn.onclick = async () => {
      const offer = await api('wishlist_offer', {id: btn.dataset.offer});
      if (offer.url) window.open(offer.url, '_blank', 'noopener'); else alert(offer.text || '');
    });
    document.querySelectorAll('[data-del]').forEach(btn => btn.onclick = async () => { await api('wishlist_delete', {id: btn.dataset.del}); await load(); });
    const promos = await api('promo_list', {});
    document.getElementById('pr-list').innerHTML = promos.map(row => '<p>' + esc(row.supplier) + ' / ' + esc(row.brand) + ' <button type="button" data-promo="' + esc(row.id) + '">Șterge</button></p>').join('') || '<p>Nicio promoție.</p>';
    document.querySelectorAll('[data-promo]').forEach(btn => btn.onclick = async () => { await api('promo_delete', {id: btn.dataset.promo}); await load(); });
    const excluded = await api('excluded_list', {});
    document.getElementById('ex-list').innerHTML = '<h4>Autototal exclus</h4>' + (excluded.map(row => '<p>' + esc(row.product_code) + ' ' + esc(row.product_name) + ' x' + esc(row.qty) + ' <button type="button" data-ex="' + esc(row.id) + '">Șterge</button></p>').join('') || '<p>Niciun produs exclus.</p>');
    document.querySelectorAll('[data-ex]').forEach(btn => btn.onclick = async () => { await api('excluded_delete', {id: btn.dataset.ex}); await load(); });
  }
  document.getElementById('wl-save').onclick = async () => {
    try { await api('wishlist_save', {name: document.getElementById('wl-name').value, phone: document.getElementById('wl-phone').value, vin: document.getElementById('wl-vin').value}); await load(); }
    catch (error) { alert(error.message); }
  };
  document.getElementById('pr-save').onclick = async () => {
    try { await api('promo_save', {supplier: document.getElementById('pr-supplier').value, brand: document.getElementById('pr-brand').value}); await load(); }
    catch (error) { alert(error.message); }
  };
  load().catch(error => { document.getElementById('wl-list').textContent = error.message; });
})();
</script>
<?php endif; ?>
