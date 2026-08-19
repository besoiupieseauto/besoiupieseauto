<?php

declare(strict_types=1);

namespace Besoiu\Services;

/** Imagini locale Autototal — baza imagine_autototal, folder Autotal. */
final class AutototalLocalImageLibrary
{
    public const SOURCE_ID = 'autotal_local';

    private const PUBLIC_PREFIX = '/uploads/products/autotal/';

    private static ?self $instance = null;

    private string $projectRoot;

    private string $storageDir;

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
        $this->storageDir = $this->projectRoot . '/uploads/products/autotal';
    }

    /**
     * @param array<string, mixed> $product
     * @return array{url: string, source: string, query: string, match_reason?: string}|null
     */
    public function lookupForProduct(array $product): ?array
    {
        require_once $this->projectRoot . '/app/Legacy/imagine-catalog-lookup.php';
        require_once $this->projectRoot . '/app/Legacy/product-code-normalize.php';
        $dbName = trim((string) ($_ENV['IMAGINE_AUTOTOTAL_DB'] ?? getenv('IMAGINE_AUTOTOTAL_DB') ?: 'imagine_autototal'));
        $pdo = imagine_catalog_pdo($dbName);
        if (!$pdo instanceof \PDO) {
            return null;
        }

        $code = imagine_catalog_norm((string) ($product['pCode'] ?? ''));
        $brand = imagine_catalog_brand((string) ($product['pBrand'] ?? ''));
        if ($code === '') {
            return null;
        }

        $row = null;
        if ($brand !== '') {
            $stmt = $pdo->prepare(
                'SELECT brand, code_norm, disk_name, rel_path FROM images
                 WHERE brand = :b AND code_norm = :c AND downloaded = 1
                 ORDER BY disk_name LIMIT 1'
            );
            $stmt->execute([':b' => $brand, ':c' => $code]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }
        if (!is_array($row)) {
            $stmt = $pdo->prepare(
                'SELECT brand, code_norm, disk_name, rel_path FROM images
                 WHERE code_norm = :c AND downloaded = 1
                 ORDER BY disk_name LIMIT 1'
            );
            $stmt->execute([':c' => $code]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }
        if (!is_array($row)) {
            $stmt = $pdo->prepare(
                'SELECT i.brand, i.code_norm, i.disk_name, i.rel_path
                 FROM aliases a JOIN images i ON i.brand = a.brand AND i.code_norm = a.code_norm
                 WHERE a.alias_code_norm = :c AND i.downloaded = 1
                 LIMIT 1'
            );
            $stmt->execute([':c' => $code]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }
        if (!is_array($row)) {
            return null;
        }

        $root = trim((string) ($_ENV['AUTOTAL_IMAGE_DIR'] ?? getenv('AUTOTAL_IMAGE_DIR') ?: 'C:/laragon/www/besoiupieseimport/Autotal'));
        $abs = imagine_catalog_first_file([
            $root . '/' . $row['brand'] . '/' . $row['disk_name'],
            $root . '/' . $row['disk_name'],
        ]);
        $url = imagine_catalog_publish($abs, $this->storageDir, self::PUBLIC_PREFIX, (string) $row['disk_name']);
        if ($url === '') {
            return null;
        }

        return [
            'url' => $url,
            'source' => self::SOURCE_ID,
            'query' => (string) $row['code_norm'],
            'match_reason' => 'imagine_autototal ' . $row['brand'] . '-' . $row['code_norm'],
        ];
    }
}
