<?php

declare(strict_types=1);

namespace Evasystem\Services\PieseAuto;

/**
 * Configurare robot Python PieseAuto (port local / tunel ngrok / canal izolat).
 */
final class PieseAutoRobotConfig
{
    public static function channelId(): string
    {
        $raw = trim((string) (getenv('ROBOT_CHANNEL_ID') ?: ($_ENV['ROBOT_CHANNEL_ID'] ?? 'besoiu')));
        $clean = strtolower(preg_replace('/[^a-z0-9_-]/', '', $raw) ?? '');

        return $clean !== '' ? $clean : 'besoiu';
    }

    /** ID robot scoped per canal — același target pe Besoiu vs BlueCar nu se suprapune. */
    public static function scopedContId(string $targetUser): string
    {
        $raw = preg_replace('/[^a-zA-Z0-9]/', '', $targetUser) ?: 'default';
        $channel = self::channelId();
        $prefix = $channel . '_';

        if (str_starts_with(strtolower($raw), $prefix)) {
            return $raw;
        }

        return $prefix . $raw;
    }

    public static function baseUrl(): string
    {
        $url = trim((string) (getenv('ROBOT_PIESEAUTO_URL') ?: ($_ENV['ROBOT_PIESEAUTO_URL'] ?? '')));
        if ($url === '') {
            $url = 'http://127.0.0.1:5011';
        }

        return rtrim($url, '/');
    }

    public static function tunnelUrl(): string
    {
        $url = trim((string) (getenv('ROBOT_PIESEAUTO_TUNNEL_URL') ?: ($_ENV['ROBOT_PIESEAUTO_TUNNEL_URL'] ?? '')));

        return $url !== '' ? rtrim($url, '/') : '';
    }

    public static function runtimeListenerPath(): string
    {
        return self::robotDir()
            . DIRECTORY_SEPARATOR . 'data'
            . DIRECTORY_SEPARATOR . 'listener_' . self::channelId() . '.json';
    }

    /** @return array<string, mixed>|null */
    public static function readListenerFile(): ?array
    {
        $path = self::runtimeListenerPath();
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        if (($data['channel'] ?? '') !== self::channelId()) {
            return null;
        }

        return $data;
    }

    public static function resolveBaseUrl(): string
    {
        return self::resolveLiveRobotBaseUrl();
    }

    /** @return list<string> */
    public static function robotUrlCandidates(): array
    {
        $ports = [];

        $runtime = self::readRuntimeFile();
        if ($runtime !== null) {
            $port = (int) ($runtime['listener_port'] ?? 0);
            if ($port > 0) {
                $ports[] = $port;
            }
        }

        $listener = self::readListenerFile();
        if ($listener !== null) {
            $port = (int) ($listener['port'] ?? 0);
            if ($port > 0) {
                $ports[] = $port;
            }
        }

        $parsed = parse_url(self::baseUrl());
        if (!empty($parsed['port'])) {
            $ports[] = (int) $parsed['port'];
        }

        foreach ([5011, 5012, 5811, 5007] as $fallbackPort) {
            $ports[] = $fallbackPort;
        }

        $ports = array_values(array_unique(array_filter($ports, static fn (int $p): bool => $p > 0 && $p < 65536)));
        $urls = [];
        foreach ($ports as $port) {
            $urls[] = 'http://127.0.0.1:' . $port;
        }

        return $urls;
    }

    public static function discoverActiveRobotUrl(bool $quick = true): ?string
    {
        static $cached = null;
        static $cachedAt = 0;
        $ttl = $quick ? 8 : 30;
        if ($cached !== null && (time() - $cachedAt) < $ttl) {
            return $cached !== '' ? $cached : null;
        }

        foreach (self::robotUrlCandidates() as $baseUrl) {
            $details = self::pingRobotStareCompleta($baseUrl, $quick);
            if ($details['online']) {
                $cached = $baseUrl;
                $cachedAt = time();

                return $baseUrl;
            }
        }

        $cached = '';
        $cachedAt = time();

        return null;
    }

    /** URL robot care răspunde live (nu doar din fișier listener). */
    public static function resolveLiveRobotBaseUrl(): string
    {
        $tunnel = self::tunnelUrl();
        if ($tunnel !== '') {
            return $tunnel;
        }

        $live = self::discoverActiveRobotUrl(true);
        if ($live !== null) {
            return $live;
        }

        $port = self::listenerPortFromDisk();

        return 'http://127.0.0.1:' . $port;
    }

    /** @param array<string, mixed> $listener */
    public static function listenerLooksFresh(array $listener, int $maxAgeSeconds = 600): bool
    {
        $started = (float) ($listener['started_at'] ?? 0);
        if ($started <= 0) {
            return false;
        }

        return (time() - $started) <= $maxAgeSeconds;
    }

    public static function effectiveUrl(): string
    {
        $tunnel = self::tunnelUrl();

        return $tunnel !== '' ? $tunnel : self::resolveBaseUrl();
    }

    public static function isLocalAdminHost(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
    }

    /** Laragon / dev local — besoiupieseauto.ro pe PC, nu server remote. */
    public static function isLocalRobotStack(): bool
    {
        if (self::isLocalAdminHost()) {
            return true;
        }

        $env = strtolower(trim((string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ''))));

        return in_array($env, ['development', 'local', 'dev'], true);
    }

    /** @return array{online:bool,http_code:int,url:string,body:string,error:string} */
    public static function localRobotPing(): array
    {
        $live = self::discoverActiveRobotUrl(true);
        if ($live !== null) {
            return self::pingRobotStareCompleta($live, true);
        }

        $port = self::listenerPortFromDisk();

        return self::pingRobotStareCompleta('http://127.0.0.1:' . $port, true);
    }

    /** PHP poate face curl direct la robot (Laragon local sau tunel configurat). */
    public static function robotReachableFromPhp(): bool
    {
        if (self::tunnelUrl() !== '') {
            return true;
        }

        if (!self::isLocalRobotStack()) {
            return false;
        }

        return self::discoverActiveRobotUrl(true) !== null;
    }

    public static function listenerPortFromDisk(): int
    {
        $listener = self::readListenerFile();
        if ($listener !== null) {
            $port = (int) ($listener['port'] ?? 0);
            if ($port > 0) {
                return $port;
            }
        }

        $runtime = self::readRuntimeFile();
        if ($runtime !== null) {
            $port = (int) ($runtime['listener_port'] ?? 0);
            if ($port > 0) {
                return $port;
            }
        }

        $parsed = parse_url(self::baseUrl());

        return (int) ($parsed['port'] ?? 5011);
    }

    /** @return array<string, mixed>|null */
    public static function readRuntimeFile(): ?array
    {
        $path = self::robotDir()
            . DIRECTORY_SEPARATOR . 'data'
            . DIRECTORY_SEPARATOR . 'runtime_'
            . self::channelId()
            . '.json';

        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $runtime */
    public static function runtimeLooksFresh(array $runtime, int $maxAgeSeconds = 900): bool
    {
        $updated = (float) ($runtime['updated_at'] ?? 0);
        if ($updated <= 0) {
            return false;
        }

        return (time() - $updated) <= $maxAgeSeconds;
    }

    public static function profileDirForCont(string $scopedContId): string
    {
        return self::robotDir() . DIRECTORY_SEPARATOR . 'profil_pa_' . $scopedContId;
    }

    public static function chromeProfileRunning(string $profileDir): bool
    {
        if ($profileDir === '' || !is_dir($profileDir)) {
            return false;
        }

        foreach (['SingletonLock', 'SingletonSocket', 'SingletonCookie'] as $marker) {
            if (is_file($profileDir . DIRECTORY_SEPARATOR . $marker)) {
                return true;
            }
        }

        return false;
    }

    /** Mesaj pentru UI când robotul nu e accesibil din PHP. */
    public static function robotAccessHint(): string
    {
        if (self::tunnelUrl() !== '') {
            return '';
        }

        if (self::robotReachableFromPhp() && self::discoverActiveRobotUrl(true) !== null) {
            return '';
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        $listenerPath = self::runtimeListenerPath();
        if (is_file($listenerPath)) {
            return 'Fișier listener există, dar portul nu răspunde. Repornește robot\\start_pieseauto_visible.bat.';
        }

        if (!self::isLocalRobotStack() && $host !== '') {
            return 'Admin pe «' . $host . '» — robotul rulează pe PC-ul tău (127.0.0.1), nu pe server. '
                . 'Deschide adminul din Laragon (local) sau setează ROBOT_PIESEAUTO_TUNNEL_URL în .env.';
        }

        return 'Pornește robot\\start_pieseauto_visible.bat și lasă fereastra deschisă.';
    }

    /** @return array{proxy:string,direct_pieseauto:string,local_robot:string,channel_header:string,scope_cont:bool} */
    public static function adminJsConfig(): array
    {
        $port = self::listenerPortFromDisk();
        $liveBase = self::resolveLiveRobotBaseUrl();
        $parsedLive = parse_url($liveBase);
        if (!empty($parsedLive['port'])) {
            $port = (int) $parsedLive['port'];
        }

        return [
            'proxy' => '/admin/api/robot_pieseauto_proxy.php',
            'direct_pieseauto' => self::tunnelUrl(),
            'local_robot' => $liveBase,
            'channel_header' => self::channelId(),
            'scope_cont' => true,
        ];
    }

    public static function robotDir(): string
    {
        return dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'robot';
    }

    public static function lockFileName(): string
    {
        return '.robot_pieseauto_' . self::channelId() . '.lock';
    }

    public static function autoStartEnabled(): bool
    {
        $v = strtolower(trim((string) (getenv('ROBOT_AUTO_START') ?: ($_ENV['ROBOT_AUTO_START'] ?? '1'))));

        return !in_array($v, ['0', 'false', 'no', 'off'], true);
    }

    public static function ping(): bool
    {
        $result = self::pingDetails();

        return (bool) ($result['online'] ?? false);
    }

    /** @return array{online:bool,http_code:int,url:string,body:string,error:string,resolved_url?:string} */
    public static function pingDetails(bool $quick = false): array
    {
        $discovered = self::discoverActiveRobotUrl($quick);
        if ($discovered !== null) {
            $result = $quick
                ? self::pingUrlFast($discovered . '/verificare_sesiune')
                : self::pingUrl($discovered . '/verificare_sesiune');
            $result['resolved_url'] = $discovered;

            return $result;
        }

        return $quick
            ? self::pingUrlFast(self::baseUrl() . '/verificare_sesiune')
            : self::pingUrl(self::baseUrl() . '/verificare_sesiune');
    }

    /** @return array{online:bool,http_code:int,url:string,body:string,error:string} */
    /** @return array{online:bool,http_code:int,url:string,body:string,error:string} */
    public static function pingRobotStareCompleta(string $baseUrl, bool $quick = true): array
    {
        $contId = rawurlencode(self::scopedContId('besoiu'));
        $url = rtrim($baseUrl, '/') . '/stare_completa?cont_id=' . $contId;
        $details = $quick
            ? self::pingUrlWithTimeouts($url, 1, 3)
            : self::pingUrlWithTimeouts($url, 2, 5);

        $body = $details['body'] ?? '';
        $details['online'] = $details['http_code'] === 200
            && is_string($body)
            && str_contains($body, '"service_online"');

        return $details;
    }

    public static function pingUrl(string $url): array
    {
        return self::pingUrlWithTimeouts($url, 2, 4);
    }

    /** Ping scurt pentru scanare porturi / încărcare rapidă pagină. */
    public static function pingUrlFast(string $url): array
    {
        return self::pingUrlWithTimeouts($url, 1, 2);
    }

    /** @return array{online:bool,http_code:int,url:string,body:string,error:string} */
    private static function pingUrlWithTimeouts(string $url, int $connectTimeout, int $totalTimeout): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['online' => false, 'http_code' => 0, 'url' => $url, 'body' => '', 'error' => 'curl_init failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $totalTimeout,
            CURLOPT_HTTPHEADER => [
                'ngrok-skip-browser-warning: 69420',
                'X-Robot-Channel: ' . self::channelId(),
            ],
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch) ?: '';
        curl_close($ch);

        $online = $code === 200
            && is_string($body)
            && (str_contains($body, '"online"') || str_contains($body, "'online'"));

        return [
            'online' => $online,
            'http_code' => $code,
            'url' => $url,
            'body' => is_string($body) ? substr($body, 0, 200) : '',
            'error' => $error,
        ];
    }

    public static function rewriteRobotPath(string $path): string
    {
        if (!str_contains($path, 'cont_id=')) {
            return $path;
        }

        return (string) preg_replace_callback(
            '/cont_id=([^&]*)/',
            static function (array $m): string {
                $decoded = urldecode($m[1]);

                return 'cont_id=' . rawurlencode(self::scopedContId($decoded));
            },
            $path
        );
    }

    /** @param array<string, mixed> $json */
    public static function rewriteRobotJsonBody(array $json): array
    {
        if (isset($json['cont_id']) && is_string($json['cont_id'])) {
            $json['cont_id'] = self::scopedContId($json['cont_id']);
        }

        return $json;
    }
}
