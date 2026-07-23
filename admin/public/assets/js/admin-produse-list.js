/**
 * Lista produse — Three.js aurora particles in header + card reveal polish
 * https://threejs.org/
 */
(function () {
  'use strict';

  function initThreeHeader() {
    if (window.__besoiuProduseThreeReady) {
      return;
    }
    var host = document.querySelector('.produse-list-page .admin-panel__head');
    if (!host || typeof THREE === 'undefined') {
      return;
    }
    window.__besoiuProduseThreeReady = true;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }

    var canvas = document.getElementById('plThreeCanvas');
    if (!canvas) {
      canvas = document.createElement('canvas');
      canvas.id = 'plThreeCanvas';
      host.insertBefore(canvas, host.firstChild);
    }

    var width = Math.max(host.clientWidth, 2);
    var height = Math.max(host.clientHeight, 2);
    var renderer = new THREE.WebGLRenderer({
      canvas: canvas,
      alpha: true,
      antialias: true,
      powerPreference: 'low-power'
    });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
    renderer.setSize(width, height, false);

    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(42, width / height, 0.1, 100);
    camera.position.z = 18;

    var count = Math.min(90, Math.max(40, Math.floor(width / 14)));
    var positions = new Float32Array(count * 3);
    var colors = new Float32Array(count * 3);
    var speeds = new Float32Array(count);
    var palette = [
      new THREE.Color('#ffffff'),
      new THREE.Color('#a7f3d0'),
      new THREE.Color('#67e8f9'),
      new THREE.Color('#fde68a'),
      new THREE.Color('#fda4af')
    ];

    for (var i = 0; i < count; i++) {
      positions[i * 3] = (Math.random() - 0.5) * 28;
      positions[i * 3 + 1] = (Math.random() - 0.5) * 10;
      positions[i * 3 + 2] = (Math.random() - 0.5) * 8;
      var c = palette[i % palette.length];
      colors[i * 3] = c.r;
      colors[i * 3 + 1] = c.g;
      colors[i * 3 + 2] = c.b;
      speeds[i] = 0.15 + Math.random() * 0.45;
    }

    var geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

    var material = new THREE.PointsMaterial({
      size: 0.28,
      vertexColors: true,
      transparent: true,
      opacity: 0.85,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
      sizeAttenuation: true
    });

    var points = new THREE.Points(geometry, material);
    scene.add(points);

    var glowGeo = new THREE.SphereGeometry(3.2, 24, 24);
    var glowMat = new THREE.MeshBasicMaterial({
      color: 0xffffff,
      transparent: true,
      opacity: 0.08
    });
    var glow = new THREE.Mesh(glowGeo, glowMat);
    glow.position.set(-6, 0.5, -2);
    scene.add(glow);

    var glow2 = glow.clone();
    glow2.position.set(7, -0.4, -3);
    glow2.material = glowMat.clone();
    glow2.material.opacity = 0.06;
    scene.add(glow2);

    var running = true;
    var raf = 0;
    var t0 = performance.now();
    var resizeTimer = 0;

    canvas.style.display = 'block';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.maxWidth = '100%';
    canvas.style.pointerEvents = 'none';

    function onResize() {
      if (resizeTimer) {
        window.clearTimeout(resizeTimer);
      }
      resizeTimer = window.setTimeout(function () {
        var nextW = Math.max(host.clientWidth, 2);
        var nextH = Math.max(host.clientHeight, 2);
        if (Math.abs(nextW - width) < 2 && Math.abs(nextH - height) < 2) {
          return;
        }
        width = nextW;
        height = nextH;
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height, false);
      }, 120);
    }

    function tick(now) {
      if (!running) {
        return;
      }
      var t = (now - t0) * 0.001;
      var pos = geometry.attributes.position.array;
      for (var i = 0; i < count; i++) {
        var ix = i * 3;
        pos[ix] += Math.sin(t * speeds[i] + i) * 0.01;
        pos[ix + 1] += Math.cos(t * speeds[i] * 1.2 + i) * 0.008;
        if (pos[ix] > 14) pos[ix] = -14;
        if (pos[ix] < -14) pos[ix] = 14;
      }
      geometry.attributes.position.needsUpdate = true;
      points.rotation.y = t * 0.08;
      glow.position.x = -6 + Math.sin(t * 0.6) * 1.5;
      glow2.position.x = 7 + Math.cos(t * 0.5) * 1.2;
      renderer.render(scene, camera);
      raf = requestAnimationFrame(tick);
    }

    var observer = null;
    if ('IntersectionObserver' in window) {
      observer = new IntersectionObserver(function (entries) {
        var visible = entries.some(function (e) { return e.isIntersecting; });
        if (visible && !running) {
          running = true;
          t0 = performance.now();
          raf = requestAnimationFrame(tick);
        } else if (!visible && running) {
          running = false;
          cancelAnimationFrame(raf);
        }
      }, { threshold: 0.05 });
      observer.observe(host);
    }

    window.addEventListener('resize', onResize, { passive: true });
    raf = requestAnimationFrame(tick);

    window.__besoiuProduseThreeCleanup = function () {
      running = false;
      cancelAnimationFrame(raf);
      window.removeEventListener('resize', onResize);
      if (observer) observer.disconnect();
      geometry.dispose();
      material.dispose();
      glowGeo.dispose();
      glowMat.dispose();
      renderer.dispose();
    };
  }

  function polishCards() {
    var cards = document.querySelectorAll('.produse-list-page .product-card');
    cards.forEach(function (card, index) {
      card.style.animationDelay = (Math.min(index, 8) * 0.05) + 's';
    });
  }

  function boot() {
    polishCards();
    initThreeHeader();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
