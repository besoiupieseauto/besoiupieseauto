/**
 * Inițializare grafice Chart.js în tab-uri produs (site + previzualizare admin).
 */
(function () {
  'use strict';

  var chartJsUrl = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
  var loading = false;
  var queue = [];

  function parsePayload(el) {
    var raw = el.getAttribute('data-besoiu-chart') || '';
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function ensureChartJs(callback) {
    if (window.Chart) {
      callback();
      return;
    }
    queue.push(callback);
    if (loading) return;
    loading = true;
    var script = document.createElement('script');
    script.src = chartJsUrl;
    script.async = true;
    script.onload = function () {
      loading = false;
      var pending = queue.slice();
      queue = [];
      pending.forEach(function (fn) { fn(); });
    };
    script.onerror = function () {
      loading = false;
      queue = [];
    };
    document.head.appendChild(script);
  }

  function formatRon(value) {
    var n = Number(value);
    if (!isFinite(n)) return value;
    return n.toLocaleString('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' RON';
  }

  function buildOptions(cfg) {
    var type = cfg.type || 'bar';
    var currency = !!cfg.currency;
    var dual = !!cfg.dual_axis;
    var opts = {
      responsive: true,
      maintainAspectRatio: true,
      aspectRatio: type === 'doughnut' || type === 'pie' ? 1.4 : 2.1,
      plugins: {
        legend: {
          display: type === 'doughnut' || type === 'pie' || dual || (cfg.datasets && cfg.datasets.length > 1),
          position: 'bottom',
        },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              var label = ctx.dataset.label ? ctx.dataset.label + ': ' : '';
              var val = ctx.parsed.y != null ? ctx.parsed.y : ctx.parsed;
              if (currency && ctx.dataset.yAxisID !== 'y1') {
                return label + formatRon(val);
              }
              if (ctx.dataset.yAxisID === 'y1') {
                return label + formatRon(val);
              }
              return label + val;
            },
          },
        },
      },
    };

    if (type === 'doughnut' || type === 'pie') {
      return opts;
    }

    opts.scales = {
      x: { grid: { display: false } },
      y: {
        beginAtZero: true,
        position: 'left',
        ticks: currency ? { callback: function (v) { return formatRon(v); } } : {},
      },
    };

    if (dual) {
      opts.scales.y1 = {
        beginAtZero: true,
        position: 'right',
        grid: { drawOnChartArea: false },
        ticks: { callback: function (v) { return formatRon(v); } },
      };
    }

    return opts;
  }

  function destroyChart(el) {
    if (el._besoiuChart) {
      el._besoiuChart.destroy();
      el._besoiuChart = null;
    }
  }

  function initCharts(root) {
    var scope = root || document;
    var nodes = scope.querySelectorAll('.besoiu-product-tab-chart:not([data-chart-ready="1"])');
    if (!nodes.length) return;

    ensureChartJs(function () {
      if (!window.Chart) return;
      nodes.forEach(function (wrap) {
        var cfg = parsePayload(wrap);
        var canvas = wrap.querySelector('canvas');
        if (!cfg || !canvas) return;

        destroyChart(wrap);
        wrap.setAttribute('data-chart-ready', '1');

        var chartCfg = {
          type: cfg.type || 'bar',
          data: {
            labels: cfg.labels || [],
            datasets: cfg.datasets || [],
          },
          options: buildOptions(cfg),
        };

        wrap._besoiuChart = new Chart(canvas.getContext('2d'), chartCfg);
      });
    });
  }

  function boot() {
    initCharts(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  document.addEventListener('shown.bs.tab', function () {
    initCharts(document);
  });

  window.besoiuInitProductTabCharts = initCharts;
})();
