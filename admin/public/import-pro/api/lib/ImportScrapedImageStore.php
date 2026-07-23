<?php

declare(strict_types=1);

/**
 * Salvează imaginile scrape Import Pro în Poze/ImportPro/{denumire_produs}/{cod_oem}.jpg
 */
final class ImportScrapedImageStore
{
    private const ROOT_FOLDER = 'ImportPro';

    /**
     * @param array<string, mixed> $card
     * @return array{success: bool, relativePath?: string, absolutePath?: string, displayUrl?: string, oemCode?: string, productFolder?: string, error?: string}
     */
    public static function saveFromCard(array $card, string $imageUrl): array
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return ['success' => false, 'error' => 'URL imagine lipsă.'];
        }

        $productFolder = self::productFolderName($card);
        $oemCode = self::resolveOemCode($card);
        if ($oemCode === '') {
            return ['success' => false, 'error' => 'Cod OEM lipsă pe card.'];
        }

        $bytes = self::fetchImageBytes($imageUrl);
        if ($bytes === null) {
            return ['success' => false, 'error' => 'Nu pot descărca imaginea: ' . $imageUrl];
        }

        $ext = self::detectExtension($bytes, $imageUrl);
        $dir = self::pozeRoot() . '/' . self::ROOT_FOLDER . '/' . $productFolder;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['success' => false, 'error' => 'Nu pot crea folderul: ' . $dir];
        }

        $filename = self::uniqueFilename($dir, $oemCode, $ext);
        $absolutePath = $dir . '/' . $filename;
        if (file_put_contents($absolutePath, $bytes, LOCK_EX) === false) {
            return ['success' => false, 'error' => 'Nu pot scrie fișierul: ' . $absolutePath];
        }

        $relativePath = self::ROOT_FOLDER . '/' . $productFolder . '/' . $filename;

        return [
            'success' => true,
            'relativePath' => $relativePath,
            'absolutePath' => str_replace('\\', '/', $absolutePath),
            'displayUrl' => import_motor_proxy_url('scraped-image.php') . '?path=' . rawurlencode($relativePath),
            'oemCode' => $oemCode,
            'productFolder' => $productFolder,
            'filename' => $filename,
        ];
    }

    public static function pozeRoot(): string
    {
        return import_motor_poze_dir();
    }

    public static function resolveAbsolutePath(string $relativePath): ?string
    {
        $relativePath = self::normalizeRelativePath($relativePath);
        if ($relativePath === '') {
            return null;
        }

        $root = realpath(self::pozeRoot());
        if ($root === false) {
            return null;
        }

        $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            return null;
        }

        $rootNorm = str_replace('\\', '/', $root);
        $realNorm = str_replace('\\', '/', $real);
        if (!str_starts_with($realNorm, $rootNorm . '/')) {
            return null;
        }

        if (!str_starts_with($relativePath, self::ROOT_FOLDER . '/')) {
            return null;
        }

        return $real;
    }

    /** @param array<string, mixed> $card */
    public static function productFolderName(array $card): string
    {
        $title = trim((string) ($card['title'] ?? $card['name'] ?? $card['scrapeQuery'] ?? 'Produs'));
        $slug = self::sanitizeSlug($title);

        return $slug !== '' ? $slug : 'Produs';
    }

    /** @param array<string, mixed> $card */
    public static function resolveOemCode(array $card): string
    {
        foreach (['sku', 'matchedTecdocSku', 'supplierSku'] as $key) {
            $raw = trim((string) ($card[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (preg_match('/^TEC-(\d+)$/i', $raw, $m) === 1) {
                return $m[1];
            }
            $clean = preg_replace('/[^A-Za-z0-9._\-]/', '', $raw) ?? '';
            if ($clean !== '') {
                return strtoupper($clean);
            }
        }

        $query = trim((string) ($card['scrapeQuery'] ?? ''));
        if ($query !== '' && preg_match('/\b([A-Z0-9][A-Z0-9._\-]{2,})\b/i', $query, $m) === 1) {
            return strtoupper($m[1]);
        }

        return '';
    }

    private static function normalizeRelativePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return '';
        }

        return $relativePath;
    }

    private static function sanitizeSlug(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $name = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'Ă', 'Â', 'Î', 'Ș', 'Ț'],
            ['a', 'a', 'i', 's', 't', 'A', 'A', 'I', 'S', 'T'],
            $name
        );
        $name = preg_replace('/[^A-Za-z0-9._\- ]+/', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', '-', trim($name)) ?? $name;
        $name = trim($name, '-._');
        if (strlen($name) > 80) {
            $name = substr($name, 0, 80);
            $name = rtrim($name, '-._');
        }

        return $name;
    }

    private static function uniqueFilename(string $dir, string $oemCode, string $ext): string
    {
        $base = self::sanitizeSlug($oemCode);
        if ($base === '') {
            $base = 'OEM';
        }

        $candidate = $base . '.' . $ext;
        if (!is_file($dir . '/' . $candidate)) {
            return $candidate;
        }

        for ($i = 2; $i <= 99; $i++) {
            $candidate = $base . '_' . $i . '.' . $ext;
            if (!is_file($dir . '/' . $candidate)) {
                return $candidate;
            }
        }

        return $base . '_' . substr(md5((string) microtime(true)), 0, 6) . '.' . $ext;
    }

    private static function detectExtension(string $bytes, string $url): string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }
        if (str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 0, 16), 'WEBP')) {
            return 'webp';
        }
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'gif';
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        return 'jpg';
    }

    private static function fetchImageBytes(string $url): ?string
    {
        if (str_starts_with($url, '/')) {
            return self::fetchLocalPublicBytes($url);
        }

        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'BesoiuImportProScraper/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8'],
        ]);

        $data = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($data) || strlen($data) < 64 || $code >= 400) {
            return null;
        }

        return $data;
    }

    private static function fetchLocalPublicBytes(string $publicPath): ?string
    {
        $publicPath = str_replace('\\', '/', $publicPath);
        $candidates = [];

        if (defined('BESOIU_ROOT')) {
            $root = rtrim(str_replace('\\', '/', (string) BESOIU_ROOT), '/');
            $candidates[] = $root . $publicPath;
            $candidates[] = $root . '/public' . $publicPath;
        }

        $docRoot = isset($_SERVER['DOCUMENT_ROOT'])
            ? rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/')
            : '';
        if ($docRoot !== '') {
            $candidates[] = $docRoot . $publicPath;
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $data = file_get_contents($path);
                return is_string($data) && strlen($data) >= 64 ? $data : null;
            }
        }

        return null;
    }
}
