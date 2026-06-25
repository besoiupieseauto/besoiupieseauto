/**
 * Guard shell admin — overlay-uri blocate, loader, modale rămase deschise.
 */
(function () {
  'use strict';

  var MODAL_IDS = [
    'addProductModal',
    'markupSelectModal',
    'badgeSelectModal',
    'importQueueEditModal',
    'legacy-comenzi-modal',
  ];

  function hideLoaders() {
    document.querySelectorAll('.page-loader').forEach(function (el) {
      el.classList.add('hidden', 'opacity-0');
      el.setAttribute('aria-hidden', 'true');
      el.style.pointerEvents = 'none';
    });
  }

  function closeModal(el) {
    if (!el) {
      return;
    }
    el.classList.remove('is-open');
    el.setAttribute('aria-hidden', 'true');
    el.removeAttribute('hidden');
    el.style.setProperty('display', 'none', 'important');
    el.style.pointerEvents = 'none';
  }

  function closeStuckModals() {
    MODAL_IDS.forEach(function (id) {
      closeModal(document.getElementById(id));
    });
    document.querySelectorAll('.products-overlay-modal:not(.is-open), .categorii-overlay-modal:not(.is-open)').forEach(closeModal);
  }

  function closeAccountMenu() {
    var popup = document.getElementById('besoiu-account-popup');
    var backdrop = document.getElementById('besoiu-account-backdrop');
    var trigger = document.getElementById('besoiu-account-trigger');
    if (popup) {
      popup.hidden = true;
    }
    if (backdrop) {
      backdrop.hidden = true;
    }
    if (trigger) {
      trigger.setAttribute('aria-expanded', 'false');
    }
  }

  function clearImportScanLock() {
    var onImportPage = /\/admin\/(?:import|importreview)(?:\/|$)/.test(window.location.pathname);
    if (onImportPage) {
      return;
    }
    document.body.classList.remove('import-image-scan-active');
    document.body.removeAttribute('data-image-scan-active');
  }

  function ensureClickableShell() {
    hideLoaders();
    closeStuckModals();
    closeAccountMenu();
    clearImportScanLock();
  }

  ensureClickableShell();
  document.addEventListener('DOMContentLoaded', ensureClickableShell);
  window.addEventListener('load', ensureClickableShell);
  window.addEventListener('pageshow', ensureClickableShell);
})();
