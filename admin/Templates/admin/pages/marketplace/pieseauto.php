<?php require __DIR__ . '/../_partials/fz-surface-styles.php'; ?>

<div class="furnizori-page">
  <div class="fz-header">
    <div>
      <a href="/admin/marketplace" class="fz-btn-outline" style="margin-bottom:12px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Înapoi la marketplace
      </a>
      <h2 class="fz-title">PieseAuto Robot</h2>
      <p class="fz-subtitle">Publicare anunțuri pe PieseAuto.ro — cont Besoiu, magazie și robot browser.</p>
    </div>
  </div>

  <?php
  use Evasystem\Services\PieseAuto\PieseAutoAccountsService;
  use Evasystem\Services\PieseAuto\PieseAutoRobotConfig;
  use Evasystem\Services\PieseAuto\PieseAutoStatusService;

  $accountsService = new PieseAutoAccountsService();
  $accounts = $accountsService->accountsForUi();
  $scannedProducts = [];
  $robotPaCfg = PieseAutoRobotConfig::adminJsConfig();
  $robotPaUrl = PieseAutoRobotConfig::effectiveUrl();
  $paSnapshot = (new PieseAutoStatusService())->snapshot('besoiu', false);
  $robotServiceLabel = (string) ($paSnapshot['service_label'] ?? '127.0.0.1:5011');
  $defaultTarget = 'besoiu';

  require __DIR__ . '/_partials/pieseauto_panel.php';
  ?>
</div>
