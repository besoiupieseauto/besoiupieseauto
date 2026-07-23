/**
 * Besoiu AI Command — parallax, particule, KPI pop, loading „sistem viu”.
 */
(function () {
  'use strict';

  var root = document.getElementById('ai-agent-root');
  if (!root) return;

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  root.classList.add('ai-mission--alive');

  var hero = root.querySelector('.ai-mission-hero');
  var parallaxEls = root.querySelectorAll('[data-parallax]');
  var mx = 0;
  var my = 0;
  var tx = 0;
  var ty = 0;
  var rafId = 0;

  function spawnParticles(container, count) {
    if (!container || reduced) return;
    for (var i = 0; i < count; i++) {
      var p = document.createElement('span');
      p.className = 'ai-particle';
      p.style.left = (8 + Math.random() * 84) + '%';
      p.style.top = (10 + Math.random() * 80) + '%';
      p.style.animationDelay = (Math.random() * 5) + 's';
      p.style.animationDuration = (4 + Math.random() * 5) + 's';
      p.style.opacity = String(0.25 + Math.random() * 0.45);
      p.style.width = p.style.height = (3 + Math.random() * 5) + 'px';
      container.appendChild(p);
    }
  }

  if (hero) {
    var pc = hero.querySelector('.ai-mission-particles');
    spawnParticles(pc, 22);
  }

  function onPointerMove(e) {
    var rect = hero ? hero.getBoundingClientRect() : { left: 0, top: 0, width: window.innerWidth, height: 300 };
    var cx = rect.left + rect.width / 2;
    var cy = rect.top + rect.height / 2;
    tx = (e.clientX - cx) / (rect.width / 2);
    ty = (e.clientY - cy) / (rect.height / 2);
    tx = Math.max(-1, Math.min(1, tx));
    ty = Math.max(-1, Math.min(1, ty));
  }

  function parallaxLoop() {
    mx += (tx - mx) * 0.07;
    my += (ty - my) * 0.07;
    parallaxEls.forEach(function (el) {
      var f = parseFloat(el.getAttribute('data-parallax') || '0.05');
      var dx = mx * f * 90;
      var dy = my * f * 70;
      el.style.transform = 'translate3d(' + dx + 'px,' + dy + 'px,0)';
    });
    var shell = root.querySelector('.ai-mission-shell');
    if (shell && !reduced) {
      shell.style.transform = 'translate3d(' + (mx * -2) + 'px,' + (my * -1.5) + 'px,0)';
    }
    rafId = requestAnimationFrame(parallaxLoop);
  }

  if (!reduced && parallaxEls.length) {
    window.addEventListener('mousemove', onPointerMove, { passive: true });
    window.addEventListener('touchmove', function (e) {
      if (e.touches && e.touches[0]) onPointerMove(e.touches[0]);
    }, { passive: true });
    rafId = requestAnimationFrame(parallaxLoop);
  }

  root.querySelectorAll('.ai-mission-nav__btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (reduced) return;
      btn.classList.remove('ai-nav-bounce');
      void btn.offsetWidth;
      btn.classList.add('ai-nav-bounce');
    });
  });

  function popKpi(el) {
    if (!el || reduced) return;
    el.classList.remove('ai-kpi-pop');
    void el.offsetWidth;
    el.classList.add('ai-kpi-pop');
  }

  function animateNumber(el, nextText) {
    if (!el || el.textContent === nextText) return;
    var prev = el.textContent;
    el.textContent = nextText;
    popKpi(el);
    if (!reduced && /^\d+$/.test(String(nextText)) && /^\d+$/.test(String(prev))) {
      var from = parseInt(prev, 10);
      var to = parseInt(nextText, 10);
      if (Math.abs(to - from) > 0 && Math.abs(to - from) < 500) {
        var start = performance.now();
        var dur = 520;
        function step(now) {
          var t = Math.min(1, (now - start) / dur);
          var eased = 1 - Math.pow(1 - t, 3);
          el.textContent = String(Math.round(from + (to - from) * eased));
          if (t < 1) requestAnimationFrame(step);
          else el.textContent = nextText;
        }
        requestAnimationFrame(step);
      }
    }
  }

  window.aiMissionKpiPop = popKpi;
  window.aiMissionSetText = function (id, text) {
    var el = document.getElementById(id);
    if (!el) return;
    animateNumber(el, text);
  };

  window.aiMissionSetLoading = function (on) {
    root.classList.toggle('ai-mission--loading', !!on);
  };

  document.addEventListener('visibilitychange', function () {
    if (document.hidden && rafId) {
      cancelAnimationFrame(rafId);
      rafId = 0;
    } else if (!document.hidden && !rafId && !reduced && parallaxEls.length) {
      rafId = requestAnimationFrame(parallaxLoop);
    }
  });
})();
