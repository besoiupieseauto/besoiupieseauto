<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

/**
 * Extrage IP, UA, referrer din request storefront + hint-uri client (timezone, limbă).
 */
final class VisitorContextResolver
{
    /** @param array<string, mixed> $server @param array<string, mixed> $clientHints */
    public static function fromRequest(array $server, array $clientHints = []): array
    {
        $ip = self::resolveClientIp($server);
        $ua = mb_substr(trim((string) ($server['HTTP_USER_AGENT'] ?? '')), 0, 512);
        $referrer = mb_substr(trim((string) ($server['HTTP_REFERER'] ?? '')), 0, 500);

        $parsed = VisitorUaParser::parse($ua);
        $device = $parsed['device_type'];
        $browser = $parsed['browser'];
        $os = $parsed['os'];

        $geo = [];
        if ($ip !== '' && !self::isPrivateIp($ip)) {
            $geo = (new VisitorGeoLookupService())->lookup($ip);
        } elseif ($ip !== '') {
            $geo = self::localNetworkGeo($ip, $clientHints);
            // Laragon/local: ip-api.com pe IP-ul public al conexiunii (~ locație reale dev)
            $selfGeo = (new VisitorGeoLookupService())->lookupSelf();
            if ($selfGeo !== []) {
                if (!empty($selfGeo['country_code'])) {
                    $geo['country_code'] = (string) $selfGeo['country_code'];
                    $geo['country_name'] = (string) ($selfGeo['country_name'] ?? 'Romania');
                }
                if (!empty($selfGeo['city'])) {
                    $geo['city'] = mb_substr('~' . (string) $selfGeo['city'], 0, 80);
                }
                if (!empty($selfGeo['region'])) {
                    $geo['region'] = (string) $selfGeo['region'];
                }
                if (!empty($selfGeo['timezone'])) {
                    $geo['timezone'] = (string) $selfGeo['timezone'];
                }
            }
        }

        $ctx = [
            'ip_address' => $ip !== '' ? $ip : null,
            'country_code' => $geo['country_code'] ?? null,
            'country_name' => $geo['country_name'] ?? null,
            'city' => $geo['city'] ?? null,
            'region' => $geo['region'] ?? null,
            'user_agent' => $ua !== '' ? $ua : null,
            'device_type' => $device,
            'browser' => $browser,
            'os' => $os,
            'referrer' => $referrer !== '' ? $referrer : null,
        ];

        return self::mergeClientHints($ctx, $clientHints);
    }

    public static function isPrivateIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** @param array<string, mixed> $clientHints @return array{country_code?:string,country_name?:string,city?:string,region?:string} */
    private static function localNetworkGeo(string $ip, array $clientHints): array
    {
        $city = trim((string) ($clientHints['city_hint'] ?? ''));
        if ($city === '') {
            $tz = trim((string) ($clientHints['timezone'] ?? ''));
            $city = $tz !== '' ? 'Dev · ' . $tz : 'Dev / LAN';
        }

        return [
            'country_code' => 'LAN',
            'country_name' => 'Rețea locală',
            'city' => mb_substr($city, 0, 80),
            'region' => trim((string) ($clientHints['region'] ?? 'Development')) ?: 'Development',
        ];
    }

    /** @param array<string, mixed> $ctx @param array<string, mixed> $hints @return array<string, mixed> */
    public static function mergeClientHints(array $ctx, array $hints): array
    {
        if ($hints === []) {
            return $ctx;
        }

        if (empty($ctx['referrer']) && !empty($hints['referrer'])) {
            $ctx['referrer'] = mb_substr((string) $hints['referrer'], 0, 500);
        }

        if (empty($ctx['device_type']) && !empty($hints['device_type'])) {
            $ctx['device_type'] = mb_substr((string) $hints['device_type'], 0, 20);
        }

        if (empty($ctx['browser']) && !empty($hints['browser'])) {
            $ctx['browser'] = mb_substr((string) $hints['browser'], 0, 80);
        }

        if (empty($ctx['os']) && !empty($hints['platform'])) {
            $ctx['os'] = mb_substr((string) $hints['platform'], 0, 80);
        }

        if ((empty($ctx['country_code']) || $ctx['country_code'] === 'LAN') && !empty($hints['timezone'])) {
            $ctx['city'] = $ctx['city'] ?: mb_substr('TZ ' . (string) $hints['timezone'], 0, 80);
        }

        if (!empty($hints['language'])) {
            $ctx['language'] = mb_substr((string) $hints['language'], 0, 16);
        }

        if (!empty($hints['screen'])) {
            $ctx['screen'] = mb_substr((string) $hints['screen'], 0, 24);
        }

        return $ctx;
    }

    /** @param array<string, mixed> $server */
    public static function resolveClientIp(array $server): string
    {
        $candidates = [];
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $raw = trim((string) ($server[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                foreach (explode(',', $raw) as $part) {
                    $candidates[] = trim($part);
                }
            } else {
                $candidates[] = $raw;
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        return $candidates[0] ?? '';
    }
}
