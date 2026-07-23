(function (global) {
    'use strict';

    var SLUG_MAP = {
        frane: 'fa-solid fa-compact-disc',
        filtre: 'fa-solid fa-filter',
        ulei: 'fa-solid fa-oil-can',
        suspensie: 'fa-solid fa-car-side',
        motor: 'fa-solid fa-gears',
        electric: 'fa-solid fa-bolt',
        caroserie: 'fa-solid fa-car',
        transmisie: 'fa-solid fa-gears',
        bmw: 'fa-solid fa-car',
        piston: 'fa-solid fa-gears',
        'second hand': 'fa-solid fa-box-open',
        'piese auto': 'fa-solid fa-box-open'
    };

    var FILE_MAP = {
        '01_frane': 'fa-solid fa-compact-disc',
        '02_filtre': 'fa-solid fa-filter',
        '03_ulei_lichide': 'fa-solid fa-oil-can',
        '04_suspensie': 'fa-solid fa-car-side',
        '05_motor': 'fa-solid fa-gears',
        '06_electric': 'fa-solid fa-bolt',
        '07_caroserie': 'fa-solid fa-car',
        '08_transmisie': 'fa-solid fa-gears',
        '09_livrare_rapida': 'fa-solid fa-truck-fast',
        '10_retur_simplu': 'fa-solid fa-rotate-left',
        '11_verificare_compatibilitate': 'fa-solid fa-shield-halved',
        '12_telefon': 'fa-solid fa-phone',
        '13_cont_utilizator': 'fa-solid fa-user',
        '14_cos_cumparaturi': 'fa-solid fa-cart-shopping',
        '16_meniu_categorii': 'fa-solid fa-bars-staggered',
        '17_marca_auto': 'fa-solid fa-car',
        '18_model_auto': 'fa-solid fa-car-side',
        '20_motorizare_ceas': 'fa-solid fa-gauge-high',
        '21_scut_compatibilitate': 'fa-solid fa-shield-halved',
        '22_cutie_produse': 'fa-solid fa-box-open',
        '26_plata_card': 'fa-solid fa-credit-card',
        '27_casti_suport': 'fa-solid fa-headset',
        '28_locatie_pin': 'fa-solid fa-location-dot',
        '29_email_plic': 'fa-solid fa-envelope',
        '31_chevron_jos': 'fa-solid fa-chevron-down',
        '32_facebook': 'fa-brands fa-facebook-f',
        '33_instagram': 'fa-brands fa-instagram',
        '34_youtube': 'fa-brands fa-youtube',
        '35_tiktok': 'fa-brands fa-tiktok'
    };

    var DEFAULT = 'fa-solid fa-box-open';

    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function resolveIcon(source, slug) {
        var raw = String(source || '').trim();
        var key = normalize(slug);

        if (raw.indexOf('fa-') !== -1) {
            return raw;
        }

        if (key && SLUG_MAP[key]) {
            return SLUG_MAP[key];
        }

        for (var slugKey in SLUG_MAP) {
            if (key && key.indexOf(slugKey) !== -1) {
                return SLUG_MAP[slugKey];
            }
        }

        if (raw) {
            var file = raw.split('/').pop().replace(/\.svg$/i, '');
            if (FILE_MAP[file]) {
                return FILE_MAP[file];
            }

            for (var fileKey in FILE_MAP) {
                if (raw.indexOf(fileKey) !== -1) {
                    return FILE_MAP[fileKey];
                }
            }
        }

        if (raw) {
            var label = normalize(raw);
            for (var labelKey in SLUG_MAP) {
                if (label.indexOf(labelKey) !== -1) {
                    return SLUG_MAP[labelKey];
                }
            }
        }

        return DEFAULT;
    }

    function iconHtml(source, slug, extraClass) {
        var cls = resolveIcon(source, slug);
        var suffix = extraClass ? ' ' + extraClass : '';
        return '<i class="' + cls + suffix + '" aria-hidden="true"></i>';
    }

    global.SiteIcons = {
        resolve: resolveIcon,
        html: iconHtml,
        default: DEFAULT
    };
}(window));
