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
        <div style="overflow:auto">
            <table class="caiet-table">
                <thead id="caiet-head"></thead>
                <tbody id="caiet-body"><tr><td>Se încarcă…</td></tr></tbody>
            </table>
        </div>
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
  const SUPPLIERS = ['ET','MA','IC','AN','AT','BA','AB','SZ','AP','AD','Stoc'];
  const COLORS = [['7CFC00','Azi','#7CFC00'],['ADD8E6','Maine','#ADD8E6'],['F5A000','2 zile','#F5A000'],['FF0000','+3 zile','#FF0000'],['FFFFFF','Sosit','#9EA5AF']];
  const body = document.getElementById('caiet-body');
  const head = document.getElementById('caiet-head');
  const toolbar = document.getElementById('caiet-toolbar');
  const extra = document.getElementById('caiet-extra');
  const modal = document.getElementById('caiet-modal');
  let rows = [];
  let draftLines = [];

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
      {name:'nr_inmat', label:'Nr. inmatriculare'}
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
    document.getElementById('new').onclick = () => openForm('Factură nouă', facturaFields(), {OrderDate: today(), valid: 'Da', tip_comanda: '0'}, payload => api('facturi_save', payload));
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
    sheet('Factura ' + data.OrderID, '<p><strong>' + esc(data.client_name) + '</strong> — total ' + esc(data.grand_total) + ' RON</p><table class="caiet-lines"><thead><tr><th>Produs</th><th>Cant.</th><th>Preț</th><th>TVA</th><th>Total</th></tr></thead><tbody>' + (lines || '<tr><td colspan="5">Fără linii.</td></tr>') + '</tbody></table><div class="caiet-actions"><button type="button" data-close>Închide</button></div>', true);
  }

  function loadIncasari() {
    head.innerHTML = '<tr><th>ID</th><th>Data</th><th>Client</th><th>Comandă</th><th>Sumă</th><th>Notă</th><th>Locație</th><th></th></tr>';
    toolbar.innerHTML = '<input id="q" type="search" placeholder="Client sau notă"><input id="from" type="date"><input id="to" type="date"><button type="button" id="go">Caută</button><button type="button" id="new">Încasare nouă</button><label>Casa zilei <input id="cash" type="number" step="0.01"></label><button type="button" id="cash-save">Salvează casa</button>';
    document.getElementById('go').onclick = () => renderIncasari();
    document.getElementById('new').onclick = () => openForm('Încasare nouă', incasareFields(), {data: today()}, payload => api('incasari_save', payload));
    document.getElementById('cash-save').onclick = async () => {
      await api('incasari_daily_save', {amount: document.getElementById('cash').value});
      alert('Casa zilei a fost salvată.');
    };
    return renderIncasari();
  }
  function incasareFields() {
    return [
      {name:'idclient', label:'ID client'},
      {name:'idcmd', label:'ID comandă'},
      {name:'suma', label:'Sumă'},
      {name:'data', label:'Data'},
      {name:'cstmtext', label:'Notă'},
      {name:'locatie_mgz', label:'Locație'},
      {name:'idstare', label:'Stare'}
    ];
  }
  async function renderIncasari() {
    const daily = await api('incasari_daily', {});
    const cash = document.getElementById('cash');
    if (cash && document.activeElement !== cash) cash.value = daily.amount ?? 0;
    rows = await api('incasari_list', {search: document.getElementById('q').value, date_from: document.getElementById('from').value, date_to: document.getElementById('to').value, limit: 100});
    body.innerHTML = rows.map(row => '<tr><td>' + esc(row.id) + '</td><td>' + esc(row.data) + ' ' + esc(row.data_time) + '</td><td>' + esc(row.client_name) + '</td><td>' + esc(row.idcmd) + '</td><td>' + Number(row.suma || 0).toFixed(2) + '</td><td>' + esc(row.cstmtext) + '</td><td>' + esc(row.locatie_mgz) + '</td><td><button data-edit="' + esc(row.id) + '">Edit</button> <button data-del="' + esc(row.id) + '">Șterge</button></td></tr>').join('') || '<tr><td colspan="8">Nicio încasare.</td></tr>';
    body.querySelectorAll('[data-edit]').forEach(btn => btn.onclick = () => {
      const row = rows.find(item => String(item.id) === btn.dataset.edit);
      openForm('Edit încasare', incasareFields(), row, payload => api('incasari_save', Object.assign({id: row.id}, payload)));
    });
    body.querySelectorAll('[data-del]').forEach(btn => btn.onclick = async () => {
      if (!confirm('Ștergi încasarea?')) return;
      await api('incasari_delete', {id: btn.dataset.del});
      await renderIncasari();
    });
  }

  async function loadUsers() {
    head.innerHTML = '<tr><th>ID</th><th>Utilizator</th><th>Email</th><th>Nume</th><th>Telefon</th><th>Rol</th><th>Activ</th><th></th></tr>';
    toolbar.innerHTML = '<a class="caiet-btn" href="/admin/users">Utilizatori Besoiu</a><button type="button" id="go">Reîncarcă</button>';
    document.getElementById('go').onclick = () => renderUsers();
    return renderUsers();
  }
  async function renderUsers() {
    rows = await api('utilizatori_list', {});
    body.innerHTML = rows.map(row => '<tr><td>' + esc(row.Id) + '</td><td>' + esc(row.username) + '</td><td>' + esc(row.email) + '</td><td>' + esc(row.nume_complet) + '</td><td>' + esc(row.telefon) + '</td><td>' + esc(row.rol) + '</td><td>' + esc(row.active) + '</td><td><a href="/admin/users">Deschide în Besoiu</a></td></tr>').join('') || '<tr><td colspan="8">Niciun utilizator.</td></tr>';
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
  function statusName(code) {
    return (STATUS[code] || ['Status ' + code, '#777'])[0];
  }
  function statusColor(code) {
    return (STATUS[code] || ['', '#777'])[1];
  }
  function sheet(title, body, wide) {
    mountModal();
    const size = wide === 'form' ? ' caiet-sheet--form' : (wide ? '' : ' caiet-sheet--sm');
    modal.innerHTML = '<div class="caiet-sheet' + size + '"><div class="caiet-sheet__head"><h3>' + esc(title) + '</h3><button type="button" class="caiet-sheet__close" data-close>&times;</button></div><div class="caiet-sheet__body">' + body + '</div></div>';
    modal.classList.remove('hidden');
    modal.querySelectorAll('[data-close]').forEach(btn => { btn.onclick = closeModal; });
  }
  function loadComenzi() {
    head.innerHTML = '<tr class="info"><th>Data</th><th>Client</th><th>Marca</th><th>Total</th><th>Status</th><th>Notita</th><th>Actiune</th></tr>';
    const statusOpts = Object.entries(STATUS).map(([id, pair]) => '<option value="' + id + '">' + esc(pair[0]) + '</option>').join('');
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
    body.innerHTML = rows.map(row => {
      const stare = Number(row.status || 0);
      const statusBtn = '<button type="button" class="lg-btn" style="background:' + statusColor(stare) + ';color:#fff;border-color:' + statusColor(stare) + '" data-status="' + esc(row.order_id) + '" data-source="' + esc(row.source_type) + '" data-stare="' + stare + '">' + esc(statusName(stare)) + '</button>';
      const note = row.observations ? '<button type="button" class="lg-btn lg-btn-danger" title="' + esc(row.observations) + '" data-note="' + esc(row.observations) + '">i</button>' : '<b>__</b>';
      return '<tr><td>' + esc(row.order_date) + '</td><td><b>' + esc(row.client_name) + '</b><br>' + esc(row.client_phone) + '</td><td>' + esc(row.marca) + '</td><td><button type="button" data-total="' + esc(row.order_id) + '" data-source="' + esc(row.source_type) + '" data-amount="' + esc(row.total_amount) + '"><b>' + Number(row.total_amount || 0).toFixed(2) + '</b></button></td><td>' + statusBtn + '</td><td>' + note + '</td><td>' + actionButtons(row, stare) + '</td></tr>';
    }).join('') || '<tr><td colspan="7">' + (document.getElementById('day').value ? 'Nicio comandă în ziua aleasă.' : 'Nicio comandă în baza de date.') + '</td></tr>';
    body.querySelectorAll('[data-status]').forEach(btn => btn.onclick = () => openStatus(btn.dataset.status, btn.dataset.source, btn.dataset.stare));
    body.querySelectorAll('[data-total]').forEach(btn => btn.onclick = () => openTotal(btn.dataset.total, btn.dataset.source, btn.dataset.amount));
    body.querySelectorAll('[data-note]').forEach(btn => btn.onclick = () => sheet('Observații comandă', '<p style="margin:0;white-space:pre-wrap">' + esc(btn.getAttribute('title') || '') + '</p><div class="caiet-actions"><button type="button" data-close>Închide</button></div>'));
    body.querySelectorAll('[data-lines]').forEach(btn => btn.onclick = () => openLines(btn.dataset.lines, btn.dataset.source, btn.dataset.stare));
    body.querySelectorAll('[data-loc]').forEach(btn => btn.onclick = () => openLocation(btn.dataset.loc));
    body.querySelectorAll('[data-sms]').forEach(btn => btn.onclick = () => openSms(btn.dataset.sms, btn.dataset.name, btn.dataset.phone));
    body.querySelectorAll('[data-copy]').forEach(btn => btn.onclick = () => openNewOrder(rows.find(row => String(row.order_id) === btn.dataset.copy) || null));
    body.querySelectorAll('[data-del]').forEach(btn => btn.onclick = async () => {
      if (!confirm('Ștergi comanda ' + btn.dataset.del + '?')) return;
      await api('comenzi_delete', {order_id: btn.dataset.del, source_type: btn.dataset.source});
      await renderComenzi();
    });
  }
  function actionButtons(row, stare) {
    const id = esc(row.order_id);
    const source = esc(row.source_type);
    let html = '<div class="order-act">';
    if (stare === 2 || stare === 4) {
      html += '<button type="button" class="lg-btn lg-btn-success" title="SMS" data-sms="' + id + '" data-name="' + esc(row.client_name) + '" data-phone="' + esc(row.client_phone) + '">SMS</button>';
    }
    if (stare === 1 || stare === 2 || stare === 4) {
      html += '<button type="button" class="lg-btn lg-btn-default" title="Editare" data-lines="' + id + '" data-source="' + source + '" data-stare="' + stare + '">Edit</button>';
    }
    if (stare === 1) {
      html += '<button type="button" class="lg-btn lg-btn-warning" title="Sterge" data-del="' + id + '" data-source="' + source + '">Del</button>';
    }
    html += '<a class="lg-btn lg-btn-default" title="Factureaza" href="/admin/caiet-facturi">Fact</a>';
    if (stare === 2 || stare === 4) {
      html += '<a class="lg-btn lg-btn-success" title="Trimite WhatsApp" href="/admin/caiet-clienti">WA</a>';
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
    sheet('Editare status comanda', group('Status comanda', [1,2,8,5]) + group('Incasare comanda', [3,6,7,4,9,10,11,12,13]) + '<div class="caiet-actions"><button type="button" id="save-status" class="lg-btn lg-btn-success">Actualizare date</button></div>');
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
    document.getElementById('send-sms').onclick = () => {
      alert('Formularul SMS este deschis pentru comanda ' + orderId + '. Trimiterea către gateway nu este legată în Besoiu.');
    };
  }
  async function openLines(orderId, source, stare) {
    const data = await api('comenzi_details', {order_id: orderId, source_type: source});
    const canEdit = Number(stare) < 3;
    const lines = (data.lines || []).map(line => '<tr><td>' + esc(line.idprodus) + '</td><td>' + esc(line.cantitate) + '</td><td>' + esc(line.pret) + '</td><td>' + (canEdit ? '<button type="button" class="st-btn" style="background:#' + esc(line.culoare || 'FFFFFF') + ';color:#111" data-color="' + esc(line.iddetaliu) + '">' + esc(colorName(line.culoare)) + '</button>' : esc(colorName(line.culoare))) + '</td><td>' + (canEdit ? '<button type="button" data-sup="' + esc(line.iddetaliu) + '">' + esc(line.furnizor || '__') + '</button>' : esc(line.furnizor || '')) + '</td></tr>').join('');
    sheet('Linii comanda ' + orderId, '<table class="caiet-lines"><thead><tr><th>ID produs</th><th>Cant.</th><th>Pret</th><th>Culoare</th><th>Furnizor</th></tr></thead><tbody>' + (lines || '<tr><td colspan="5">Fără linii.</td></tr>') + '</tbody></table>', true);
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

  function today() {
    return new Date().toISOString().slice(0, 10);
  }

  load();
})();
</script>
<?php endif; ?>
