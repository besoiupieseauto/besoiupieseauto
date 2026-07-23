<?php

declare(strict_types=1);

/**
 * Bara marker DOM pe site public (operator admin autentificat).
 */
if (!function_exists('dom_marker_mode_active')) {
    require_once __DIR__ . '/dom-marker-lib.php';
}

if (!dom_marker_mode_active()) {
    return;
}
?>
<div id="bpaDomMarkerBar" class="bpa-dm-bar" role="toolbar" aria-label="Marker DOM antrenare chat">
    <span class="bpa-dm-bar__badge"><i class="fa-solid fa-crosshairs" aria-hidden="true"></i> Marker DOM</span>
    <span class="bpa-dm-bar__hint">Click pe orice element → descrie ce face → salvează în RAG chat</span>
    <span id="bpaDomMarkerStatus" class="bpa-dm-bar__status" aria-live="polite"></span>
    <button type="button" class="bpa-dm-bar__btn" id="bpaDomMarkerToggle" aria-pressed="true">Oprește marker</button>
    <a href="/admin/comunicare-chat" class="bpa-dm-bar__btn bpa-dm-bar__btn--ghost" target="_blank" rel="noopener">Baza RAG</a>
    <button type="button" class="bpa-dm-bar__btn bpa-dm-bar__btn--ghost" id="bpaDomMarkerExit">Închide mod</button>
</div>
<script>
(function () {
  document.body.classList.add('bpa-dom-marker-page');
  var exitBtn = document.getElementById('bpaDomMarkerExit');
  if (exitBtn) {
    exitBtn.addEventListener('click', function () {
      try { localStorage.removeItem('bpa_dom_marker_on'); } catch (e) {}
      var u = new URL(location.href);
      u.searchParams.delete('bpa_dom_marker');
      location.href = u.toString();
    });
  }
})();
</script>
