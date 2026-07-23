(function (global) {
    'use strict';

    var base = typeof global.BESOIU_STOREFRONT_BASE === 'string'
        ? global.BESOIU_STOREFRONT_BASE.replace(/\/+$/, '')
        : '';

    global.besoiuPath = function besoiuPath(path) {
        var p = path || '/';
        if (p.charAt(0) !== '/') {
            p = '/' + p;
        }
        return base + p;
    };
}(window));
