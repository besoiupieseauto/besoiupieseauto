<?php

declare(strict_types=1);

/**
 * Context magazin public vs operator admin autentificat.
 * Notificările tehnice (migrări, RapidAPI, admin intern/extern) rămân doar în admin.
 */

require_once __DIR__ . '/site-live-cms.php';

if (!function_exists('besoiu_admin_storefront_context')) {
    function besoiu_admin_storefront_context(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        return site_live_admin_authenticated();
    }
}

if (!function_exists('besoiu_storefront_html_class')) {
    function besoiu_storefront_html_class(): string
    {
        return besoiu_admin_storefront_context() ? 'besoiu-admin-ctx' : 'besoiu-public-storefront';
    }
}

if (!function_exists('besoiu_storefront_notice_is_technical')) {
    function besoiu_storefront_notice_is_technical(string $text): bool
    {
        $lower = mb_strtolower(trim($text), 'UTF-8');
        if ($lower === '') {
            return false;
        }

        $needles = [
            'admin',
            'migrare',
            'migrat',
            'migration',
            'rapidapi',
            'tecdoc',
            'psubcategory',
            'pvitrina',
            '.env',
            'eroare intern',
            'sistem intern',
            'sistem extern',
            'intern/extern',
            'internă / externă',
            'operator_message',
            'ruleaza migrarea',
            'rulează migrarea',
            'trimisa in admin',
            'trimisă în admin',
            'http 4',
            'http 5',
            'fatal error',
            'sqlstate',
        ];

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('besoiu_storefront_public_notice')) {
    function besoiu_storefront_public_notice(?string $notice): string
    {
        $notice = trim((string) $notice);
        if ($notice === '') {
            return '';
        }

        if (besoiu_admin_storefront_context()) {
            return $notice;
        }

        if (besoiu_storefront_notice_is_technical($notice)) {
            return '';
        }

        return $notice;
    }
}

if (!function_exists('besoiu_storefront_quota_notice')) {
    function besoiu_storefront_quota_notice(): string
    {
        if (besoiu_admin_storefront_context()) {
            return 'Catalogul TecDoc (RapidAPI) a atins limita lunară. Căutarea continuă doar în stocul local până la resetarea planului sau upgrade RapidAPI.';
        }

        return 'Căutarea continuă în stocul local al magazinului.';
    }
}

if (!function_exists('besoiu_storefront_sanitize_api_payload')) {
    /** @param array<string, mixed> $payload */
    function besoiu_storefront_sanitize_api_payload(array $payload): array
    {
        if (besoiu_admin_storefront_context()) {
            return $payload;
        }

        if (isset($payload['notice'])) {
            $clean = besoiu_storefront_public_notice((string) $payload['notice']);
            if ($clean === '') {
                unset($payload['notice']);
            } else {
                $payload['notice'] = $clean;
            }
        }

        if (isset($payload['message'])) {
            $raw = (string) $payload['message'];
            $clean = besoiu_storefront_public_notice($raw);
            if ($clean === '' && besoiu_storefront_notice_is_technical($raw)) {
                $payload['message'] = 'Operațiunea nu a putut fi finalizată. Încearcă din nou.';
            } elseif ($clean !== '') {
                $payload['message'] = $clean;
            }
        }

        unset(
            $payload['last_error'],
            $payload['cache'],
            $payload['error_detail'],
            $payload['operator_message']
        );

        return $payload;
    }
}
