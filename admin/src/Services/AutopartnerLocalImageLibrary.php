<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Imagini locale Autopartner — index MySQL imagine_produse (coloana A/C) + fallback vechi.
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

    private string $driver = '';

    private string $schema = '';

    public static function instance(?string $projectRoot = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($projectRoot);
        }

        return self::$instance;
    }

    private function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = self::detectProjectRoot($projectRoot);
        $this->storageDir = $this->projectRoot . '/uploads/products/autopartner';
        $this->sqlitePath = $this->projectRoot . '/admin/public/bovsoft-import/api/cache/autopartner.sqlite';
    }

    public function isAvailable(): bool
    {
        return $this->resolveImagesRoot() !== '' && $this->hasIndex();
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

        $this->requireCodeNormalizer();

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
        $rows = $this->queryImageRows($db, $norm, $brand);
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

    /**
     * @return list<array{ap_index:string,rel_path:string}>
     */
    private function queryImageRows(\PDO $db, string $norm, string $brand = ''): array
    {
        if ($this->driver === 'mysql' && $this->schema === 'imagine_produse') {
            $brandTok = imagine_catalog_brand($brand);
            $sql = 'SELECT disk_name AS ap_index, rel_path FROM images
                    WHERE code_norm = :norm';
            $params = [':norm' => $norm];
            if ($brandTok !== '') {
                $sql .= ' ORDER BY (brand = :brand) DESC, disk_name ASC LIMIT 3';
                $params[':brand'] = $brandTok;
            } else {
                $sql .= ' ORDER BY disk_name ASC LIMIT 3';
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if ($rows !== []) {
                return $rows;
            }
            $stmt = $db->prepare(
                'SELECT i.disk_name AS ap_index, i.rel_path
                 FROM products p
                 JOIN images i ON i.brand = p.brand AND i.code_norm = p.code_norm
                 WHERE p.a_norm = :norm
                 ORDER BY i.disk_name ASC
                 LIMIT 3'
            );
            $stmt->execute([':norm' => $norm]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        if ($this->driver === 'mysql') {
            $stmt = $db->prepare(
                'SELECT i.ap_index, i.rel_path
                 FROM lookup l
                 JOIN images i ON i.ap_index_norm = l.ap_index_norm
                 WHERE l.norm_code = :norm
                 ORDER BY FIELD(i.folder, \'miesieczne\', \'zdjecia\', \'rooks\', \'root\'), i.seq ASC
                 LIMIT 3'
            );
            $stmt->execute([':norm' => $norm]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if ($rows !== []) {
                return $rows;
            }
            $stmt = $db->prepare(
                'SELECT ap_index, rel_path FROM images
                 WHERE ap_index_norm = :norm
                 ORDER BY FIELD(folder, \'miesieczne\', \'zdjecia\', \'rooks\', \'root\'), seq ASC
                 LIMIT 3'
            );
            $stmt->execute([':norm' => $norm]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $db->prepare(
            'SELECT l.ap_index, i.rel_path
             FROM lookup l
             JOIN images i ON i.ap_index = l.ap_index
             WHERE l.norm_code = :norm
             LIMIT 3'
        );
        $stmt->execute([':norm' => $norm]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        if ($rows !== []) {
            return $rows;
        }
        $stmt = $db->prepare(
            'SELECT ap_index, rel_path FROM images
             WHERE rel_path LIKE :like OR ap_index = :norm
             LIMIT 3'
        );
        $stmt->execute([':like' => '%' . $norm . '%', ':norm' => $norm]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function publishLocalImage(string $apIndex, string $relPath): string
    {
        $relPath = str_replace('\\', '/', trim($relPath));
        if ($apIndex === '' || $relPath === '') {
            return '';
        }

        $root = $this->resolveImagesRoot();
        $rel = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relPath), DIRECTORY_SEPARATOR);
        $sourcePath = imagine_catalog_first_file([
            $root . DIRECTORY_SEPARATOR . $rel,
            $root . DIRECTORY_SEPARATOR . 'IMAGINI_RENUMITE' . DIRECTORY_SEPARATOR . $apIndex,
            $root . DIRECTORY_SEPARATOR . $apIndex,
        ]);
        if ($sourcePath === '') {
            return '';
        }

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($apIndex, PATHINFO_FILENAME)) ?: 'ap';
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

    public function resolveImagesRoot(): string
    {
        $candidates = [];
        $env = trim((string) ($_ENV['AUTOPARTNER_IMAGE_DIR'] ?? getenv('AUTOPARTNER_IMAGE_DIR') ?: ''));
        if ($env !== '') {
            $candidates[] = $env;
        }
        $candidates[] = 'C:/Users/Radu/Desktop/autoparner';
        $candidates[] = 'C:/Users/Radu/Desktop/autopartner';
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

    private function hasIndex(): bool
    {
        try {
            $db = $this->pdo();
            $count = (int) $db->query('SELECT COUNT(*) FROM images')->fetchColumn();

            return $count > 0;
        } catch (\Throwable) {
            return is_file($this->sqlitePath);
        }
    }

    private function pdo(): \PDO
    {
        if ($this->db instanceof \PDO) {
            return $this->db;
        }

        $mysql = $this->tryMysql();
        if ($mysql instanceof \PDO) {
            $this->db = $mysql;
            $this->driver = 'mysql';

            return $this->db;
        }

        if (!is_file($this->sqlitePath)) {
            throw new \RuntimeException('Index Autopartner indisponibil (MySQL/SQLite).');
        }

        $this->db = new \PDO('sqlite:' . $this->sqlitePath, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $this->driver = 'sqlite';

        return $this->db;
    }

    private function tryMysql(): ?\PDO
    {
        require_once $this->projectRoot . '/app/Legacy/imagine-catalog-lookup.php';
        imagine_catalog_load_env($this->projectRoot);
        $host = trim((string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1'));
        $user = trim((string) ($_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root'));
        $pass = (string) ($_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '');

        foreach ([
            trim((string) ($_ENV['IMAGINE_PRODUSE_DB'] ?? getenv('IMAGINE_PRODUSE_DB') ?: 'imagine_produse')) => 'imagine_produse',
            trim((string) ($_ENV['AUTOPARTNER_IMAGE_DB'] ?? getenv('AUTOPARTNER_IMAGE_DB') ?: 'autopartner_images')) => 'legacy',
        ] as $name => $schema) {
            if ($name === '') {
                continue;
            }
            try {
                $pdo = new \PDO(
                    'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
                    $user,
                    $pass,
                    [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    ]
                );
                $pdo->query('SELECT 1 FROM images LIMIT 1');
                $hasNorm = $pdo->query("SHOW COLUMNS FROM images LIKE 'code_norm'")->fetch();
                $this->schema = $hasNorm ? 'imagine_produse' : $schema;

                return $pdo;
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function requireCodeNormalizer(): void
    {
        if (function_exists('besoiu_normalize_product_code')) {
            return;
        }
        $candidates = [
            $this->projectRoot . '/app/Legacy/product-code-normalize.php',
            $this->projectRoot . '/system/product-code-normalize.php',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    }

    private static function detectProjectRoot(?string $projectRoot): string
    {
        if ($projectRoot !== null && $projectRoot !== '') {
            return rtrim($projectRoot, '/\\');
        }
        $dir = __DIR__;
        for ($i = 0; $i < 6; $i++) {
            if (is_file($dir . '/app/Config/config.php') || is_file($dir . '/admin/bootstrap.php')) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return dirname(__DIR__, 3);
    }
}
