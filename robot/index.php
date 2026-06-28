<?php
/**
 * robot/index.php
 *
 * Dashboard mic de navigatie pentru toate scripturile portate.
 * Verifica si starea cheilor din .env.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';

header('Content-Type: text/html; charset=utf-8');

$keys = [
    'ULTRAMSG_INSTANCE'      => 'WhatsApp UltraMsg instance',
    'ULTRAMSG_TOKEN'         => 'WhatsApp UltraMsg token',
    'WEBHOOK_KEY'            => 'Cheia de validare webhook',
    'OPENAI_KEY'             => 'OpenAI (general)',
    'OPENAI_KEY_VIN'         => 'OpenAI (vin.php translator)',
    'GROQ_KEY'               => 'Groq AI',
    'APIFY_TOKEN'            => 'Apify (Facebook scraping)',
    'RAPIDAPI_TECDOC_KEY'    => 'RapidAPI tecdoc-catalog + allegro2',
    'RAPIDAPI_AUTOPARTS_KEY' => 'RapidAPI auto-parts-catalog',
];

$status = [];
foreach ($keys as $k => $label) {
    $v = env($k, '');
    $status[$k] = [
        'label' => $label,
        'ok'    => $v !== '',
        'len'   => strlen((string)$v),
    ];
}

$tools = [
    ['url' => 'webhook.php', 'name' => 'webhook.php', 'desc' => 'Endpoint webhook UltraMsg (PRINCIPAL — bot WhatsApp). Cheia se configurează în robot/.env.'],
    ['url' => 'webhook.php?debug=1', 'name' => 'webhook (debug)', 'desc' => 'Vezi ultimele log-uri din webhook (necesită WEBHOOK_KEY valid).'],
    ['url' => 'chat.php',          'name' => 'chat.php',          'desc' => 'Manager conversatii (UI simplu) + webhook varianta veche.'],
    ['url' => 'chat.html',         'name' => 'chat.html',         'desc' => 'UI chat — citeste din api.php?action=fetch.'],
    ['url' => 'api.php?action=fetch', 'name' => 'api.php',        'desc' => 'API simplu UltraMsg + arhiva mesaje.'],
    ['url' => 'parser.html',       'name' => 'parser.html',       'desc' => 'UI scanner cereri pieseauto.ro.'],
    ['url' => 'parser.php',        'name' => 'parser.php',        'desc' => 'Backend parser pieseauto.ro (cereri + analiza concurenta).'],
    ['url' => 'run.php',           'name' => 'run.php',           'desc' => 'Pipeline TecDoc (POST). Apelat din parser.html.'],
    ['url' => 'process.php',       'name' => 'process.php',       'desc' => 'Pipeline TecDoc standalone (debug, VIN hardcoded).'],
    ['url' => 'vin.html',          'name' => 'vin.html',          'desc' => 'UI Allegro VIN translator.'],
    ['url' => 'vin.php',           'name' => 'vin.php',           'desc' => 'Backend Allegro scraper + traducere AI + conversie PLN->RON.'],
    ['url' => 'tecdoc.php',        'name' => 'tecdoc.php',        'desc' => 'UI selector marca/model/motorizare cu cache.'],
    ['url' => 'tecdoc_proxy.php',  'name' => 'tecdoc_proxy.php',  'desc' => 'Backend proxy + cache pentru selector TecDoc.'],
    ['url' => 'fb_view.html',      'name' => 'fb_view.html',      'desc' => 'UI scanner Facebook Groups.'],
    ['url' => 'fb_parser.php',     'name' => 'fb_parser.php',     'desc' => 'Backend FB scraper (Apify, sync).'],
    ['url' => 'pars.php',          'name' => 'pars.php',          'desc' => 'Backend FB scraper (Apify, async run trigger).'],
    ['url' => 'genereaza_mesaj.php', 'name' => 'genereaza_mesaj.php', 'desc' => 'Generator comentariu FB cu Groq.'],
    ['url' => 'save-lead.php',     'name' => 'save-lead.php',     'desc' => 'Captura lead-uri (POST JSON).'],
    ['url' => 'auto.html',         'name' => 'auto.html',         'desc' => 'UI auto (frontend secundar).'],
];
?><!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<title>Robot — dashboard</title>
<style>
  *{box-sizing:border-box}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:32px}
  .wrap{max-width:1100px;margin:auto}
  h1{margin:0 0 4px;color:#fff;font-size:28px}
  .subtitle{color:#94a3b8;margin-bottom:28px;font-size:14px}
  .panel{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:18px 20px;margin-bottom:24px}
  .panel h2{margin:0 0 12px;font-size:18px;color:#f1f5f9}
  .keys{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px}
  .keyrow{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-radius:8px;background:#0f172a;border:1px solid #1e293b}
  .keyrow .k{font-family:monospace;font-size:12px;color:#cbd5e1}
  .keyrow .v{font-size:11px;font-weight:bold}
  .ok{color:#22c55e}
  .miss{color:#ef4444}
  .tools{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px}
  .tool{background:#0f172a;border:1px solid #1e293b;border-radius:10px;padding:14px;transition:.15s}
  .tool:hover{border-color:#3b82f6;transform:translateY(-2px)}
  .tool a{color:#60a5fa;text-decoration:none;font-weight:bold;font-family:monospace;font-size:14px}
  .tool a:hover{color:#93c5fd;text-decoration:underline}
  .tool .desc{color:#94a3b8;font-size:13px;margin-top:6px;line-height:1.5}
  .warn{background:#7f1d1d;border:1px solid #b91c1c;color:#fee2e2;padding:14px 18px;border-radius:10px;margin-bottom:24px;font-size:14px;line-height:1.6}
</style>
</head>
<body>
<div class="wrap">
  <h1>🤖 Robot — dashboard</h1>
  <div class="subtitle">Portat 1:1 din <code>aibotpiese.online</code> in <code>besoiupieseauto.ro/robot/</code>. Vezi <code>README.md</code> pentru detalii.</div>

  <div class="warn">
    <strong>⚠️ ATENTIE — chei API expuse.</strong>
    Cheile din <code>.env</code> sunt cele vechi (functionale, dar expuse in cod inainte de portare).
    Trebuie rotate URGENT. Pasi: vezi <code>README.md</code> sectiunea "CHEI EXPUSE — DE ROTAT URGENT".
  </div>

  <div class="panel">
    <h2>Status chei .env</h2>
    <div class="keys">
      <?php foreach ($status as $k => $s): ?>
        <div class="keyrow">
          <div>
            <div class="k"><?= htmlspecialchars($k) ?></div>
            <div style="font-size:11px;color:#64748b"><?= htmlspecialchars($s['label']) ?></div>
          </div>
          <div class="v <?= $s['ok'] ? 'ok' : 'miss' ?>">
            <?= $s['ok'] ? "OK ({$s['len']})" : 'MISSING' ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <h2>Tools / endpoints</h2>
    <div class="tools">
      <?php foreach ($tools as $t): ?>
        <div class="tool">
          <a href="<?= htmlspecialchars($t['url']) ?>" target="_blank"><?= htmlspecialchars($t['name']) ?></a>
          <div class="desc"><?= htmlspecialchars($t['desc']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <h2>Webhook URL pentru UltraMsg</h2>
    <p style="color:#cbd5e1;font-size:14px;line-height:1.6">
      In dashboard-ul UltraMsg (<a href="https://app.ultramsg.com" target="_blank" style="color:#60a5fa">app.ultramsg.com</a>) seteaza webhook-ul instance-ului <code>instance162465</code> la:
    </p>
    <div style="background:#0f172a;padding:12px;border-radius:8px;border:1px solid #1e293b;font-family:monospace;font-size:13px;color:#22c55e;word-break:break-all;">
      <?php
        require_once dirname(__DIR__) . '/system/url.php';
        echo htmlspecialchars(
            besoiu_absolute_url('/robot/webhook.php?key=' . urlencode((string) env('WEBHOOK_KEY', ''))),
            ENT_QUOTES,
            'UTF-8'
        );
      ?>
    </div>
  </div>

</div>
</body>
</html>
