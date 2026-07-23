<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Repository/SiteContentRepository.php
 * ============================================================================
 * Scop: Acces la datele CMS din tabela site_pages (pagini constructor online).
 *       Folosit de modulul CMS pentru randarea paginilor /p/{slug}.
 *
 * Include/require: AbstractRepository → Connection → shop-db.php
 *
 * Bază de date: PDO MySQL, tabel site_pages (slug, title, meta_description,
 *               sections JSON, is_active, updated_at).
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Repository;

/**
 * Repository CMS — interogări pe tabela site_pages (conținut constructor online).
 */
final class SiteContentRepository extends AbstractRepository
{
    /**
     * Găsește o pagină activă după slug URL.
     *
     * @param string $slug Identificator URL (ex. despre-noi)
     * @return array<string, mixed>|null Rândul complet sau null dacă nu există
     *
     * SQL: SELECT * FROM site_pages WHERE slug = :slug AND is_active = 1 LIMIT 1
     */
    public function findActiveBySlug(string $slug): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM site_pages WHERE slug = :slug AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);

        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Listează toate paginile CMS active (pentru sitemap, navigare).
     *
     * @return list<array<string,mixed>> Rânduri cu slug, title, meta_description, updated_at
     *
     * SQL: SELECT slug, title, meta_description, updated_at FROM site_pages
     *      WHERE is_active = 1 ORDER BY slug
     */
    public function listActiveSlugs(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT slug, title, meta_description, updated_at FROM site_pages WHERE is_active = 1 ORDER BY slug'
        );

        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    /**
     * Decodează coloana sections (JSON) într-un array PHP.
     *
     * @param string|null $json Conținut JSON din DB sau null
     * @return array<mixed> Secțiuni pagină CMS sau array gol la JSON invalid
     */
    public function decodeSections(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
