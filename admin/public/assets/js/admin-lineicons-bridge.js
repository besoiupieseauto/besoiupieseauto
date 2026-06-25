/**
 * Înlocuiește data-lucide cu Lineicons (https://lineicons.com/icons)
 */
(function () {
  'use strict';

  var MAP = {
    'layout-dashboard': 'dashboard',
    'truck': 'delivery',
    'chevron-down': 'chevron-down',
    'chevron-left': 'chevron-left',
    'chevron-right': 'chevron-right',
    'git-compare': 'direction-alt',
    'list': 'list-1-1',
    'package': 'package',
    'layout-grid': 'layout-9',
    'radar': 'target-user',
    'plus-circle': 'plus-circle',
    'folder-tree': 'folder-1',
    'badge-percent': 'offer',
    'file-up': 'upload-circle-1',
    'list-checks': 'check-circle-1',
    'search-x': 'search-alt',
    'shopping-cart': 'cart-1',
    'clipboard-list': 'clipboard',
    'search-check': 'search-alt-1',
    'receipt-text': 'invoice-1',
    'users': 'users-2',
    'users2': 'users-2',
    'bot': 'robot-2',
    'messages-square': 'comment-1',
    'store': 'store',
    'scan-search': 'zoom-in',
    'refresh-cw': 'reload',
    'refresh-ccw': 'reload',
    'bar-chart-3': 'bar-chart-4',
    'globe': 'world-2',
    'file-text': 'file-multiple',
    'newspaper': 'text-format',
    'pen-line': 'pencil-1',
    'user-cog': 'user-multiple-4',
    'bell-ring': 'bell-1',
    'database-backup': 'database-2',
    'settings': 'gear-1',
    'search': 'search-1',
    'banknote': 'money-protection',
    'eye': 'eye',
    'eye-off': 'eye-slash',
    'x': 'close',
    'bell': 'bell-1',
    'external-link': 'link-2-alt',
    'chart-no-axes-column': 'menu-hamburger-1',
    'move-right': 'arrow-right',
    'power': 'power-switch',
    'shield-alert': 'shield-2-check',
    'file-lock': 'locked-1',
    'file-question': 'question-circle',
    'building2': 'buildings-1',
    'kanban-square': 'layout-9',
    'mail-check': 'envelope-1',
    'hotel': 'apartment-1',
    'plus': 'plus',
    'pencil': 'pencil-1',
    'trash-2': 'trash-3',
    'edit': 'pencil-1',
    'copy': 'copy',
    'download': 'download-1',
    'upload': 'upload-circle-1',
    'filter': 'sliders-horizontal-square-2',
    'more-horizontal': 'menu-meatballs-2',
    'check': 'checkmark',
    'alert-circle': 'information',
    'info': 'information',
    'loader-2': 'spinner-arrow',
    'arrow-left': 'arrow-left',
    'arrow-right': 'arrow-right',
    'home': 'home-2',
    'calendar': 'calendar-days',
    'clock': 'timer-1',
    'phone': 'telephone-3',
    'mail': 'envelope-1',
    'map-pin': 'map-marker-5',
    'star': 'star-fat',
    'heart': 'heart',
    'image': 'photos',
    'link': 'link-2-alt',
    'unlink': 'unlink-2-1',
    'zap': 'bolt-2',
    'wifi': 'wifi',
    'cloud': 'cloud-1',
    'server': 'server-1',
    'cpu': 'cpu-1',
    'hard-drive': 'harddrive',
    'shield': 'shield-2',
    'lock': 'locked-1',
    'unlock': 'unlocked-2',
    'log-in': 'enter',
    'log-out': 'exit',
    'user': 'user-4',
    'user-plus': 'user-plus',
    'tag': 'tag-1',
    'tags': 'tags-1',
    'percent': 'offer',
    'trending-up': 'trend-up-1',
    'trending-down': 'trend-down-1',
    'activity': 'pulse',
    'pie-chart': 'pie-chart',
    'line-chart': 'graph-increase',
    'message-circle': 'comment-1',
    'send': 'telegram',
    'share-2': 'share-1',
    'printer': 'printer',
    'save': 'floppy-disk-1',
    'folder': 'folder-1',
    'file': 'file-multiple',
    'paperclip': 'paperclip-1',
    'video': 'video-1',
    'mic': 'microphone-1',
    'camera': 'camera-1',
    'play': 'play',
    'pause': 'pause',
    'stop': 'stop',
    'skip-forward': 'forward',
    'rewind': 'backward',
    'volume-2': 'volume-high',
    'volume-x': 'volume-mute',
    'maximize': 'expand-arrow-1',
    'minimize': 'collapse-arrow-1',
    'grid': 'layout-9',
    'layers': 'layers-1',
    'box': 'package',
    'archive': 'archive-1',
    'inbox': 'inbox',
    'bookmark': 'bookmark-1',
    'flag': 'flag-1',
    'award': 'trophy-1',
    'gift': 'gift-1',
    'credit-card': 'credit-card-multiple',
    'wallet': 'wallet',
    'dollar-sign': 'dollar',
    'euro': 'euro',
    'bitcoin': 'bitcoin',
    'shopping-bag': 'shopping-basket-3',
    'repeat': 'reload',
    'rotate-ccw': 'reload',
    'rotate-cw': 'reload',
    'sync': 'spinner-arrow',
    'history': 'history',
    'timer': 'timer-1',
    'hourglass': 'hourglass',
    'sun': 'sun-1',
    'moon': 'moon-half-right-5',
    'umbrella': 'umbrella-rain-1',
    'thermometer': 'thermometer-1',
    'droplet': 'drop-1',
    'wind': 'wind-1',
    'map': 'map-5',
    'navigation': 'navigation-1',
    'compass': 'compass-1',
    'anchor': 'anchor-1',
    'car': 'car-6',
    'bus': 'bus-1',
    'plane': 'plane-2',
    'ship': 'ship-1',
    'bike': 'bicycle-1',
    'wrench': 'wrench-1',
    'hammer': 'hammer-1',
    'scissors': 'scissors-1',
    'key': 'key-1',
    'fingerprint': 'fingerprint-1',
    'qr-code': 'qrcode',
    'barcode': 'barcode',
    'code': 'code-1',
    'terminal': 'terminal',
    'bug': 'bug-1',
    'puzzle': 'puzzle',
    'lightbulb': 'bulb-2',
    'rocket': 'rocket-5',
    'target': 'target-user',
    'crosshair': 'target-user',
    'aperture': 'camera-1',
    'bluetooth': 'bluetooth',
    'rss': 'rss-right',
    'radio': 'radio-button',
    'tv': 'tv-1',
    'monitor': 'monitor',
    'smartphone': 'mobile-3',
    'tablet': 'tablet-1',
    'laptop': 'laptop-2',
    'watch': 'stopwatch',
    'headphones': 'headphone-1',
    'speaker': 'speaker-1',
    'music': 'music',
    'book': 'book-1',
    'book-open': 'book-1',
    'graduation-cap': 'graduation-cap-1',
    'briefcase': 'briefcase-1',
    'clipboard': 'clipboard',
    'check-square': 'checkbox',
    'square': 'square',
    'circle': 'circle',
    'triangle': 'triangle-1',
    'hexagon': 'hexagon',
    'octagon': 'octagon',
    'slash': 'ban-2',
    'minus': 'minus',
    'divide': 'division-1',
    'equal': 'equal',
    'hash': 'hashtag',
    'at-sign': 'at-sign',
    'smile': 'emoji-smile',
    'frown': 'emoji-expressionless',
    'meh': 'emoji-neutral',
    'thumbs-up': 'thumbs-up-3',
    'thumbs-down': 'thumbs-down-3'
  };

  var SKIP_CLASSES = /^stroke-|^size-|^h-|^w-|\[--color/;

  function lucideToLni(name) {
    if (!name) {
      return 'checkmark-circle';
    }
    if (MAP[name]) {
      return MAP[name];
    }
    return name;
  }

  function buildLniClassList(el) {
    var out = ['lni'];
    var lniName = lucideToLni(el.getAttribute('data-lucide'));
    out.push('lni-' + lniName);

    el.classList.forEach(function (cls) {
      if (SKIP_CLASSES.test(cls)) {
        return;
      }
      if (cls === 'side-menu__link__icon' || cls === 'side-menu__link__chevron') {
        out.push(cls);
        return;
      }
      if (
        cls.indexOf('text-') === 0 ||
        cls.indexOf('admin-') === 0 ||
        cls.indexOf('mr-') === 0 ||
        cls.indexOf('ml-') === 0 ||
        cls.indexOf('ms-') === 0 ||
        cls.indexOf('me-') === 0 ||
        cls === 'rotate-90' ||
        cls === 'transition'
      ) {
        out.push(cls);
      }
    });

    return out.join(' ');
  }

  function convertIcons(root) {
    if (!root) {
      return;
    }

    root.querySelectorAll('[data-lucide]').forEach(function (el) {
      var icon = document.createElement('i');
      icon.className = buildLniClassList(el);
      icon.setAttribute('aria-hidden', 'true');

      var title = el.getAttribute('title');
      if (title) {
        icon.setAttribute('title', title);
      }

      el.replaceWith(icon);
    });
  }

  function boot() {
    convertIcons(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.BesoiuAdminLineicons = {
    convert: convertIcons,
    map: MAP
  };
})();
