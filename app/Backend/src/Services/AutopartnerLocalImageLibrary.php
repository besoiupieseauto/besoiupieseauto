<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Imagini locale Autopartner — index SQLite + copiere în uploads/products/autopartner/.
 */
final class AutopartnerLocalImageLibrary
{
    public const SOURCE_ID = 'autopartner_local';

    private const PUBLIC_PREFIX = '/uploads/products/autopartner/';

    private static ?self $instance = null;

    private string $projectRoot;

    private string $storageDir;

    private string $sqlitePath;

    private ?\PDO $db = null;

    public static function instance(?string $projectRoot = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($projectRoot);
        }

        return self::$instance;
    }

    private function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->storageDir = $this->projectRoot . '/uploads/products/autopartner';
        $this->sqlitePath = $this->projectRoot . '/admin/public/bovsoft-import/api/cache/autopartner.sqlite';
    }

    public function isAvailable(): bool
    {
        return is_file($this->sqlitePath) && $this->resolveImagesRoot() !== '';
    }

    public function sqlitePath(): string
    {
        return $this->sqlitePath;
    }

    /**
     * @param array<string, mixed> $product
     * @return array{url: string, source: string, ap_code: string, query: string, match_reason?: string}|null
     */
    public function lookupForProduct(array $product): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $code = trim((string) ($product['pCode'] ?? ''));
        $brand = trim((string) ($product['pBrand'] ?? ''));
        if ($code === '') {
            return null;
        }

        require_once $this->projectRoot . '/system/product-code-normalize.php';

        $candidates = array_values(array_unique(array_filter(array_merge(
            besoiu_product_code_search_variants($code),
            $this->oemCodeVariants($product)
        ))));

        foreach ($candidates as $candidate) {
            $hit = $this->lookupByNormCode($candidate, $brand);
            if ($hit !== null) {
                return $hit;
            }
        }

        if ($brand !== '') {
            $brandPrefixed = besoiu_normalize_product_code($brand . $code);
            if ($brandPrefixed !== '') {
                $hit = $this->lookupByNormCode($brandPrefixed, $brand);
                if ($hit !== null) {
                    return $hit;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function oemCodeVariants(array $product): array
    {
        $out = [];
        $oemField = trim((string) ($product['pOem'] ?? ''));
        foreach (preg_split('/[,;|\r\n]+/', $oemField) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $norm = besoiu_normalize_product_code($part);
            if ($norm !== '' && !in_array($norm, $out, true)) {
                $out[] = $norm;
            }
        }

        return $out;
    }

    private function lookupByNormCode(string $normCode, string $brand): ?array
    {
        $norm = besoiu_normalize_product_code($normCode);
        if ($norm === '') {
            return null;
        }

        $db = $this->pdo();
        $stmt = $db->prepare(
            'SELECT l.ap_index, i.rel_path
             FROM lookup l
             JOIN images i ON i.ap_index = l.ap_index
             WHERE l.norm_code = :norm
             LIMIT 3'
        );
        $stmt->execute([':norm' => $norm]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if ($rows === []) {
            $stmt = $db->prepare(
                "SELECT ap_index, rel_path FROM images
                 WHERE rel_path LIKE :like OR ap_index = :norm
                 LIMIT 3"
            );
            $stmt->execute([':like' => '%' . $norm . '%', ':norm' => $norm]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        if ($rows === []) {
            return null;
        }

        foreach ($rows as $row) {
            $publicUrl = $this->publishLocalImage((string) ($row['ap_index'] ?? ''), (string) ($row['rel_path'] ?? ''));
            if ($publicUrl === '') {
                continue;
            }

            return [
                'url' => $publicUrl,
                'source' => self::SOURCE_ID,
                'ap_code' => (string) ($row['ap_index'] ?? ''),
                'query' => trim($normCode . ($brand !== '' ? ' | ' . $brand : ''), ' |'),
                'match_reason' => 'Autopartner local (' . $norm . ')',
            ];
        }

        return null;
    }

    private function publishLocalImage(string $apIndex, string $relPath): string
    {
        $relPath = str_replace('\\', '/', trim($relPath));
        if ($apIndex === '' || $relPath === '') {
            return '';
        }

        $sourcePath = $this->resolveImagesRoot() . '/' . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relPath), DIRECTORY_SEPARATOR);
        if (!is_file($sourcePath) || (int) filesize($sourcePath) < 512) {
            return '';
        }

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $apIndex) ?: 'ap';
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }
        $destPath = $this->storageDir . '/' . $safeName . '.' . $ext;

        if (!is_file($destPath) || (int) filesize($destPath) < 512) {
            if (!@copy($sourcePath, $destPath)) {
                return '';
            }
        }

        return self::PUBLIC_PREFIX . rawurlencode($safeName . '.' . $ext);
    }

    private function resolveImagesRoot(): string
    {
        $candidates = [];
        $env = trim((string) ($_ENV['AUTOPARTNER_IMAGE_DIR'] ?? getenv('AUTOPARTNER_IMAGE_DIR') ?: ''));
        if ($env !== '') {
            $candidates[] = $env;
        }
        $candidates[] = $this->projectRoot . '/admin/public/bovsoft-import/autoparner';
        $candidates[] = $this->projectRoot . '/admin/public/bovsoft-import/autopartner';
        $candidates[] = 'F:/laragon/www/besoiupieseauto.ro/autoparner';
        $candidates[] = 'F:/laragon/www/besoiupieseauto.ro/autopartner';
        $candidates[] = $this->projectRoot . '/autoparner';
        $candidates[] = $this->projectRoot . '/autopartner';

        foreach ($candidates as $path) {
            $path = rtrim(str_replace('\\', '/', trim($path)), '/');
            if ($path !== '' && is_dir($path)) {
                return $path;
            }
        }

        return '';
    }

    private function pdo(): \PDO
    {
        if ($this->db instanceof \PDO) {
            return $this->db;
        }

        $this->db = new \PDO('sqlite:' . $this->sqlitePath, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return $this->db;
    }
}
