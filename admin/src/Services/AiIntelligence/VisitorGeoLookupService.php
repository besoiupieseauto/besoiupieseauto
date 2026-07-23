<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Throwable;

/**
 * GeoIP via ip-api.com (free, fără cheie — HTTP, max ~45 req/min/IP).
 *
 * @see https://ip-api.com/docs/api:json
 * @see https://ip-api.com/docs/api:batch
 */
final class VisitorGeoLookupService
{
    private const API_JSON = 'http://ip-api.com/json/';
    private const API_BATCH = 'http://ip-api.com/batch';
    private const CACHE_TTL = 86400;
    private const RATE_WINDOW = 60;
    private const RATE_MAX = 40;

    private string $cacheDir;
    private string $rateDir;

    public function __construct(?string $projectRoot = null)
    {
        $root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->cacheDir = $root . '/app/Storage/ai_intel/geo';
        $this->rateDir = $root . '/app/Storage/ai_intel/geo/rate';
        foreach ([$this->cacheDir, $this->rateDir] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
    }

    /**
     * Lookup IP public — cache 24h.
     *
     * @return array{ip_address?:string,country_code?:string,country_name?:string,city?:string,region?:string,timezone?:string,isp?:string,lat?:float,lon?:float,source?:string}
     */
    public function lookup(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return [];
        }
        if (VisitorContextResolver::isPrivateIp($ip)) {
            return [];
        }

        $cached = $this->readCache('ip:' . $ip);
        if ($cached !== null) {
            return $cached;
        }

        if (!$this->rateLimitOk()) {
            return [];
        }

        $data = $this->fetchIpApi($ip);
        if ($data !== []) {
            $data['source'] = 'ip-api.com';
            $this->writeCache('ip:' . $ip, $data);
        }

        return $data;
    }

    /**
     * IP public al conexiunii serverului (util pe Laragon când REMOTE_ADDR=127.0.0.1).
     *
     * @return array{ip_address?:string,country_code?:string,country_name?:string,city?:string,region?:string,timezone?:string,isp?:string,lat?:float,lon?:float,source?:string}
     */
    public function lookupSelf(): array
    {
        $cached = $this->readCache('self');
        if ($cached !== null) {
            return $cached;
        }

        if (!$this->rateLimitOk()) {
            return [];
        }

        $data = $this->fetchIpApi('');
        if ($data !== []) {
            $data['source'] = 'ip-api.com/self';
            $this->writeCache('self', $data, 3600);
        }

        return $data;
    }

    /**
     * Batch ip-api.com — max 100 IP-uri per request.
     *
     * @param list<string> $ips
     * @return array<string, array<string, mixed>> map ip => geo
     */
    public function lookupBatch(array $ips): array
    {
        $ips = array_values(array_unique(array_filter(array_map('trim', $ips), static function (string $ip): bool {
            return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && !VisitorContextResolver::isPrivateIp($ip);
        })));

        if ($ips === []) {
            return [];
        }

        $out = [];
        $pending = [];

        foreach ($ips as $ip) {
            $cached = $this->readCache('ip:' . $ip);
            if ($cached !== null) {
                $out[$ip] = $cached;
                continue;
            }
            $pending[] = $ip;
        }

        foreach (array_chunk($pending, 100) as $chunk) {
            if (!$this->rateLimitOk()) {
                break;
            }
            foreach ($this->fetchIpApiBatch($chunk) as $ip => $row) {
                $row['source'] = 'ip-api.com';
                $this->writeCache('ip:' . $ip, $row);
                $out[$ip] = $row;
            }
        }

        return $out;
    }

    /** @return array{ip_address?:string,country_code?:string,country_name?:string,city?:string,region?:string,timezone?:string,isp?:string,lat?:float,lon?:float} */
    private function fetchIpApi(string $ip): array
    {
        $fields = 'status,message,query,country,countryCode,regionName,city,timezone,isp,lat,lon';
        $url = self::API_JSON . ($ip !== '' ? rawurlencode($ip) : '') . '?fields=' . $fields;

        $json = $this->httpGetJson($url);
        if (!is_array($json) || ($json['status'] ?? '') !== 'success') {
            $this->logFailure('single', $ip, is_array($json) ? (string) ($json['message'] ?? 'fail') : 'invalid_json');

            return [];
        }

        return $this->normalizeRow($json);
    }

    /**
     * @param list<string> $ips
     * @return array<string, array<string, mixed>>
     */
    private function fetchIpApiBatch(array $ips): array
    {
        $fields = 'status,message,query,country,countryCode,regionName,city,timezone,isp,lat,lon';
        $payload = array_map(static fn (string $ip): array => ['query' => $ip], $ips);

        $json = $this->httpPostJson(self::API_BATCH . '?fields=' . $fields, $payload);
        if (!is_array($json)) {
            $this->logFailure('batch', implode(',', $ips), 'invalid_json');

            return [];
        }

        $out = [];
        foreach ($json as $row) {
            if (!is_array($row) || ($row['status'] ?? '') !== 'success') {
                continue;
            }
            $ip = (string) ($row['query'] ?? '');
            if ($ip === '') {
                continue;
            }
            $out[$ip] = $this->normalizeRow($row);
        }

        return $out;
    }

    /** @param array<string, mixed> $json @return array<string, mixed> */
    private function normalizeRow(array $json): array
    {
        $cc = mb_strtoupper((string) ($json['countryCode'] ?? ''));

        return array_filter([
            'ip_address' => (string) ($json['query'] ?? ''),
            'country_code' => strlen($cc) === 2 ? $cc : null,
            'country_name' => (string) ($json['country'] ?? ''),
            'city' => (string) ($json['city'] ?? ''),
            'region' => (string) ($json['regionName'] ?? ''),
            'timezone' => (string) ($json['timezone'] ?? ''),
            'isp' => (string) ($json['isp'] ?? ''),
            'lat' => isset($json['lat']) ? (float) $json['lat'] : null,
            'lon' => isset($json['lon']) ? (float) $json['lon'] : null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /** @return array<string, mixed>|null */
    private function readCache(string $key): ?array
    {
        $file = $this->cacheDir . '/' . md5($key) . '.json';
        if (!is_file($file)) {
            return null;
        }
        $cached = json_decode((string) file_get_contents($file), true);
        if (!is_array($cached) || ($cached['expires'] ?? 0) <= time()) {
            return null;
        }

        return is_array($cached['data'] ?? null) ? $cached['data'] : null;
    }

    /** @param array<string, mixed> $data */
    private function writeCache(string $key, array $data, int $ttl = self::CACHE_TTL): void
    {
        @file_put_contents($this->cacheDir . '/' . md5($key) . '.json', json_encode([
            'expires' => time() + $ttl,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE));
    }

    private function rateLimitOk(): bool
    {
        $file = $this->rateDir . '/' . date('YmdHi') . '.cnt';
        $count = is_file($file) ? (int) file_get_contents($file) : 0;
        if ($count >= self::RATE_MAX) {
            return false;
        }
        @file_put_contents($file, (string) ($count + 1));

        return true;
    }

    private function logFailure(string $mode, string $ip, string $message): void
    {
        $line = date('c') . "\t{$mode}\t{$ip}\t{$message}\n";
        @file_put_contents($this->cacheDir . '/failures.log', $line, FILE_APPEND);
    }

    /** @return array<string, mixed>|null */
    private function httpGetJson(string $url): ?array
    {
        $raw = $this->httpRequest('GET', $url);
        if ($raw === null || $raw === '') {
            return null;
        }
        $json = json_decode($raw, true);

        return is_array($json) ? $json : null;
    }

    /** @return array<string, mixed>|list<mixed>|null */
    private function httpPostJson(string $url, array $payload): array|null
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($body)) {
            return null;
        }
        $raw = $this->httpRequest('POST', $url, $body);
        if ($raw === null || $raw === '') {
            return null;
        }
        $json = json_decode($raw, true);

        return is_array($json) ? $json : null;
    }

    private function httpRequest(string $method, string $url, ?string $body = null): ?string
    {
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                if ($ch === false) {
                    return null;
                }
                $opts = [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_TIMEOUT => 6,
                    CURLOPT_USERAGENT => 'BesoiuIntelligence/1.0 (ip-api.com)',
                ];
                if ($method === 'POST') {
                    $opts[CURLOPT_POST] = true;
                    $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json', 'Accept: application/json'];
                    $opts[CURLOPT_POSTFIELDS] = $body ?? '';
                }
                curl_setopt_array($ch, $opts);
                $raw = curl_exec($ch);
                curl_close($ch);

                return is_string($raw) ? $raw : null;
            }

            $header = "User-Agent: BesoiuIntelligence/1.0 (ip-api.com)\r\nAccept: application/json\r\n";
            if ($method === 'POST') {
                $header .= "Content-Type: application/json\r\n";
            }
            $ctx = stream_context_create([
                'http' => [
                    'method' => $method,
                    'timeout' => 6,
                    'ignore_errors' => true,
                    'header' => $header,
                    'content' => $method === 'POST' ? ($body ?? '') : null,
                ],
            ]);
            $raw = @file_get_contents($url, false, $ctx);

            return is_string($raw) ? $raw : null;
        } catch (Throwable) {
            return null;
        }
    }
}
