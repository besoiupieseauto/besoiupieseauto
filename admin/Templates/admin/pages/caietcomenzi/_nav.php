<?php
declare(strict_types=1);

/** @var string $caietScreen */
$caietScreen = $caietScreen ?? 'comenzi-tm';
$caietLinks = [
    'comenzi-tm' => ['Comenzi TM', '/admin/caiet-comenzi-tm'],
    'comenzi-utvin' => ['Comenzi UTVIN', '/admin/caiet-comenzi-utvin'],
    'comenzi-ext' => ['Comenzi externe', '/admin/caiet-comenzi-ext'],
    'produse' => ['Produse', '/admin/caiet-produse'],
    'clienti' => ['Clienți', '/admin/caiet-clienti'],
    'facturi' => ['Facturi', '/admin/caiet-facturi'],
    'incasari' => ['Încasări', '/admin/caiet-incasari'],
    'utilizatori' => ['Utilizatori', '/admin/caiet-utilizatori'],
    'pieseauto' => ['Pieseauto', '/admin/caiet-pieseauto'],
    'search' => ['Supplier Search', '/admin/caiet-search'],
    'api' => ['API', '/admin/caiet-api'],
];
if (in_array($caietScreen, ['comenzi-tm', 'comenzi-utvin', 'comenzi-ext'], true)) {
    $caietLinks = array_intersect_key($caietLinks, array_flip(['comenzi-tm', 'comenzi-utvin', 'comenzi-ext']));
}
?>
<nav class="besoiu-tabs caiet-nav" aria-label="Caiet comenzi">
    <?php foreach ($caietLinks as $key => [$label, $href]): ?>
        <a class="admin-tab besoiu-tabs__btn caiet-nav__link<?= $caietScreen === $key ? ' is-active admin-tab--active besoiu-tabs__btn--active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
</nav>
<style>
.caiet-nav{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 16px;overflow:visible}
.caiet-nav__link{border:1px solid rgba(15,118,110,.18);border-radius:10px;padding:8px 12px;font-size:.85rem;text-decoration:none;color:#0f766e;background:#fff;white-space:nowrap}
.caiet-nav__link.is-active{background:#0f766e;color:#fff;border-color:#0f766e}
.caiet-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}
.caiet-toolbar input,.caiet-toolbar select,.caiet-toolbar button,.caiet-toolbar a.caiet-btn{height:40px;border:1px solid rgba(15,118,110,.25);border-radius:8px;padding:0 10px;background:#fff;color:inherit;text-decoration:none;display:inline-flex;align-items:center}
.caiet-toolbar button.is-primary{background:#5cb85c;color:#fff;border-color:#4cae4c;border-radius:4px}
.caiet-toolbar .lg-btn-success{background:#5cb85c;color:#fff;border-color:#4cae4c}
.caiet-actions button[type="submit"]{background:#5cb85c;color:#fff;border-color:#4cae4c}
.caiet-table{width:100%;border-collapse:collapse;font-size:.875rem;background:#fff}
.caiet-table th,.caiet-table td{border-bottom:1px solid rgba(15,118,110,.12);padding:8px 10px;text-align:left;vertical-align:top}
.caiet-table th{font-size:.75rem;text-transform:uppercase;letter-spacing:.03em}
.caiet-panel{border:1px solid rgba(15,118,110,.14);border-radius:14px;padding:16px;background:#fff}
.caiet-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:200000;display:flex;align-items:flex-start;justify-content:center;padding:28px 16px;overflow:auto}
.caiet-modal.hidden{display:none}
.caiet-modal form.caiet-grid{background:#fff;border-radius:6px;padding:16px;width:min(640px,100%);display:grid;grid-template-columns:1fr 1fr;gap:10px;box-shadow:0 18px 50px rgba(15,23,42,.28);box-sizing:border-box}
.caiet-modal form.caiet-grid h3,.caiet-modal .caiet-actions{grid-column:1/-1}
.caiet-modal form.caiet-grid label{display:flex;flex-direction:column;gap:4px;font-size:13px;color:#333;min-width:0}
.caiet-modal form.caiet-grid input,.caiet-modal form.caiet-grid select,.caiet-modal form.caiet-grid textarea{height:34px;border:1px solid #ccc;border-radius:4px;padding:0 8px;width:100%;box-sizing:border-box;background:#fff}
.caiet-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:4px}
.caiet-actions button{height:34px;padding:0 14px;border:1px solid #ccc;border-radius:4px;background:#fff;cursor:pointer}
.caiet-sheet{background:#fff;border:1px solid #bce8f1;width:min(980px,100%);max-height:calc(100vh - 56px);overflow:auto;border-radius:6px;box-shadow:0 18px 50px rgba(15,23,42,.28);box-sizing:border-box}
.caiet-sheet--sm{width:min(520px,100%)}
.caiet-sheet--form{width:min(680px,100%)}
.caiet-sheet form.caiet-grid{box-shadow:none;padding:0;width:100%;background:transparent}
.caiet-sheet__head{background:#d9edf7;color:#31708f;border-bottom:1px solid #bce8f1;padding:10px 15px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:2}
.caiet-sheet__head h3{margin:0;font-size:18px;font-weight:500}
.caiet-sheet__body{padding:16px}
.caiet-sheet__body > label{display:flex;flex-direction:column;gap:4px;margin:0 0 10px;font-size:13px;color:#333}
.caiet-sheet__body > label:not(.st-btn):has(input[type="radio"]){flex-direction:row;align-items:center;gap:8px;font-size:14px}
.caiet-sheet__body > label.st-btn{display:inline-flex;flex-direction:row;align-items:center;gap:4px;width:auto;margin:0 6px 6px 0}
.caiet-sheet__body > label input,.caiet-sheet__body > label textarea,.caiet-sheet__body > label select{border:1px solid #ccc;padding:6px 8px;width:100%;box-sizing:border-box;background:#fff;border-radius:4px}
.caiet-sheet__close{border:0;background:transparent;font-size:22px;line-height:1;cursor:pointer;color:#31708f}
.order-form{display:flex;flex-direction:column;gap:12px}
.order-line{display:grid;grid-template-columns:104px minmax(260px,1.7fr) 108px minmax(140px,1fr) 64px minmax(140px,.9fr);gap:8px 10px;align-items:center}
.order-line > label,.order-k{text-align:right;font-size:13px;color:#333;line-height:1.25}
.order-ctrl{min-width:0}
.order-ctrl input,.order-ctrl select,.order-ctrl textarea{width:100%;height:34px;border:1px solid #ccc;border-radius:4px;padding:4px 8px;box-sizing:border-box;background:#fff;color:#111}
.order-ctrl textarea{height:78px;resize:vertical;padding-top:6px}
.order-when{display:flex;gap:8px}
.order-when input[type="date"]{flex:1 1 160px;width:auto;min-width:148px}
.order-when input[type="text"]{width:92px;flex:0 0 92px}
.order-split{display:grid;grid-template-columns:minmax(220px,280px) minmax(0,1fr);gap:20px;align-items:start}
.order-block{display:grid;grid-template-columns:112px minmax(0,1fr);gap:8px 10px;align-items:start}
.order-k,.order-block > label{padding-top:6px;text-align:right;font-size:13px;color:#333;line-height:1.25}
.order-radios{display:flex;flex-direction:column;align-items:flex-start;gap:6px;padding-top:4px}
.order-radios label{display:inline-flex;flex-direction:row;align-items:center;gap:8px;font-size:13px;color:#333;white-space:nowrap;width:auto}
.order-radios input{width:16px;height:16px;margin:0;flex:0 0 16px;accent-color:#31708f}
.order-actions{display:flex;justify-content:flex-end;gap:8px}
@media (max-width:900px){
  .order-line,.order-split,.order-block{grid-template-columns:100px minmax(0,1fr)}
  .order-line > label:nth-of-type(n+2),.order-line > .order-ctrl:nth-of-type(n+2){grid-column:auto}
}
.caiet-actions .lg-btn-success{background:#5cb85c;border-color:#4cae4c;color:#fff}
.caiet-input-plus{display:flex}
.caiet-input-plus input{flex:1}
.caiet-input-plus button,.lg-btn{height:34px;border:1px solid #ccc;background:#fff;cursor:pointer;padding:0 10px}
.lg-btn-success{background:#5cb85c;border-color:#4cae4c;color:#fff}
.lg-btn-warning{background:#f0ad4e;border-color:#eea236;color:#fff}
.lg-btn-info{background:#5bc0de;border-color:#46b8da;color:#fff}
.lg-btn-danger{background:#d9534f;border-color:#d43f3a;color:#fff}
.lg-btn-default{background:#fff;color:#333}
.st-btn{display:inline-block;margin:0 6px 6px 0;padding:6px 10px;border-radius:4px;color:#fff;cursor:pointer;border:1px solid transparent}
.st-btn input{margin-right:4px}
.caiet-suggest{position:relative}
.caiet-suggest ul{position:absolute;z-index:5;left:0;right:0;top:100%;margin:0;padding:0;list-style:none;background:#fff;border:1px solid #ccc;max-height:220px;overflow:auto}
.caiet-suggest li{padding:6px 8px;cursor:pointer}
.caiet-suggest li:hover{background:#d9edf7}
.caiet-lines{width:100%;border-collapse:collapse;margin-top:12px}
.caiet-lines th,.caiet-lines td{border:1px solid #ddd;padding:6px 8px;text-align:center}
.caiet-lines thead tr{background:#fcf8e3}
.order-act{display:flex;flex-wrap:wrap;gap:3px;align-items:center;max-width:148px}
.order-phone{display:block;margin-top:2px;font-weight:600;color:#1f2937}
.order-company{display:block;color:#6b7280}
.order-stamp{display:flex;flex-direction:column;line-height:1.35}
.code-cell{font-weight:700}
.awb-cell{white-space:normal}
.awb-cell strong,.awb-track{display:block}
.awb-track{margin-top:4px;font-size:12px}
.order-act button,.order-act a{min-width:28px;height:26px;padding:0 6px;border-radius:3px;border:1px solid #ccc;background:#fff;color:#333;text-decoration:none;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
.total-luna,.total-zi,.total-cautare{display:inline-block;color:#fff;font-weight:700;padding:8px 15px;border-radius:4px;margin-right:8px}
.total-luna{background:#5bc0de}
.total-zi,.total-cautare{background:#5cb85c}
.caiet-api-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.caiet-api-fields label{display:flex;flex-direction:column;gap:4px;font-size:13px}
.caiet-api-fields label.wide{grid-column:1/-1}
.caiet-api-fields input,.caiet-api-fields textarea{border:1px solid #ccc;padding:6px 8px;width:100%;box-sizing:border-box;background:#fff}
.caiet-frame{width:100%;height:78vh;border:0;border-radius:12px;background:#fff}
.pa-line{display:flex;gap:8px;align-items:center;margin:8px 0}
.pa-edit{display:grid;grid-template-columns:1.4fr .7fr .8fr .6fr .8fr;gap:6px;margin:0 0 10px 22px}
.pa-edit input,.pa-edit select{width:100%;border:1px solid #ccc;padding:4px 6px;background:#fff}
.inv-hidden{display:none !important}
.inv-card{background:#eee;border:1px solid #ddd}
.inv-head{background:#8fbf6a;color:#173017;padding:12px 16px}
.inv-head h4{margin:0;font-size:18px;font-weight:500}
.inv-body{background:#fff;padding:18px 16px 10px}
.inv-grid{display:grid;grid-template-columns:92px minmax(240px,1.6fr) 78px minmax(150px,.9fr) 118px minmax(160px,1fr);gap:14px 12px;align-items:center}
.inv-grid > label{text-align:right;font-size:14px;color:#333;margin:0}
.inv-grid input,.inv-grid select{height:34px;border:1px solid #ccc;border-radius:0;padding:4px 8px;width:100%;box-sizing:border-box;background:#fff;color:#111}
.inv-client{display:flex;position:relative;min-width:0}
.inv-client input{flex:1;min-width:0}
.inv-client > button{width:36px;height:34px;border:1px solid #ccc;border-left:0;background:#fff;cursor:pointer}
.inv-suggest{position:absolute;z-index:6;top:34px;left:0;right:36px;margin:0;padding:0;list-style:none;background:#fff;border:1px solid #ccc;max-height:220px;overflow:auto}
.inv-suggest li{padding:6px 8px;cursor:pointer}
.inv-suggest li:hover{background:#d9edf7}
.inv-actions{display:flex;justify-content:flex-end;gap:8px;margin:18px 0 14px}
.inv-actions button{height:34px;padding:0 12px;border:1px solid #ccc;background:#fff;cursor:pointer;border-radius:2px}
.inv-actions .lg-btn-success{background:#5cb85c;border-color:#4cae4c;color:#fff}
.inv-table{width:100%;border-collapse:collapse;background:#fff}
.inv-table th,.inv-table td{border:1px solid #ddd;padding:8px;text-align:center}
.inv-table thead th{background:#fcf8e3;font-weight:600}
.inv-table .inv-right{text-align:right}
.inc-head{flex:1 0 100%;background:#8fbf6a;color:#173017;padding:12px 16px;margin:-16px -16px 4px}
.inc-head h4{margin:0;font-size:18px;font-weight:500}
.inc-date{display:flex}
.inc-date button,.inc-date input{height:34px;border:1px solid #ccc;background:#fff}
.inc-date button{width:34px;cursor:pointer}
.inc-date input{width:118px;text-align:center;border-left:0;border-right:0}
.caiet-toolbar select{height:34px;min-width:140px;border:1px solid #ccc;background:#fff;padding:0 8px}
.caiet-toolbar .inc-add{height:34px;padding:0 12px;background:#337ab7;color:#fff;border:1px solid #2e6da4;border-radius:2px;cursor:pointer}
.inc-table thead th{background:#8fbf6a;color:#173017;text-align:center;text-transform:none;letter-spacing:0;font-size:14px}
.inc-table td{text-align:center}
.inc-table td.inc-client{text-align:left}
.inc-table td.inc-sum{text-align:right}
.inc-table tbody tr:nth-child(even){background:#f3f8ef}
.inc-table tr.inc-red td{background:#ff6969}
.inc-user{display:flex;flex-direction:column;line-height:1.35}
.inc-badges{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.inc-badges span{color:#fff;font-weight:700;padding:8px 14px;border-radius:4px}
.inc-badges .info{background:#5bc0de}
.inc-badges .ok{background:#5cb85c}
.inc-pager{display:flex;justify-content:flex-end;gap:4px;margin-top:10px}
.inc-pager button{border:1px solid #ddd;background:#fff;color:#337ab7;padding:6px 12px;cursor:pointer}
.inc-pager button.is-on{background:#337ab7;color:#fff}
.inv-form label{display:flex;flex-direction:column;gap:4px;margin:0 0 10px;font-size:13px}
.inv-form input,.inv-form select,.inv-form textarea{border:1px solid #ccc;padding:6px 8px;width:100%;box-sizing:border-box;background:#fff}
.inv-inline{display:flex;gap:8px;align-items:center}
</style>
