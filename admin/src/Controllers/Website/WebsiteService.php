<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Website;

use Evasystem\Core\Website\SitePagesModel;

final class WebsiteService
{
    private SitePagesModel $model;

    /** @var list<string> */
    private const PROTECTED_SLUGS = ['home', 'global'];

    public function __construct(?SitePagesModel $model = null)
    {
        $this->model = $model ?? new SitePagesModel();
    }

    public function getAll(): array
    {
        if (!function_exists('site_pages_repair_labels')) {
            require_once dirname(__DIR__, 4) . '/system/site-admin-form.php';
        }
        site_pages_repair_labels();

        return $this->model->findAll();
    }

    public function getById(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function getBySlug(string $slug): ?array
    {
        return $this->model->findBySlug($slug);
    }

    public function update(int $id, array $payload): bool
    {
        return $this->model->update($id, $payload);
    }

    public function createPage(string $label, string $slug, string $title = ''): array
    {
        $label = trim($label);
        $slug = $this->normalizeSlug($slug !== '' ? $slug : $label);
        $title = trim($title !== '' ? $title : $label);

        if ($label === '') {
            return ['success' => false, 'message' => 'Numele paginii este obligatoriu.'];
        }

        if ($slug === '') {
            return ['success' => false, 'message' => 'Adresa (slug) este invalidă.'];
        }

        if ($this->isProtectedSlug($slug)) {
            return ['success' => false, 'message' => 'Acest slug este rezervat sistemului.'];
        }

        if ($this->model->slugExists($slug)) {
            return ['success' => false, 'message' => 'Există deja o pagină cu această adresă.'];
        }

        $id = $this->model->create([
            'slug' => $slug,
            'label' => $label,
            'title' => $title,
            'meta_description' => '',
            'hero_label' => '',
            'hero_title' => $title,
            'hero_subtitle' => '',
            'body_html' => '',
            'sections_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'faq_json' => '',
            'cta_json' => '',
            'is_active' => 1,
            'sort_order' => $this->model->nextSortOrder(),
        ]);

        if ($id <= 0) {
            return ['success' => false, 'message' => 'Nu s-a putut crea pagina.'];
        }

        return [
            'success' => true,
            'message' => 'Pagina a fost creată.',
            'id' => $id,
            'slug' => $slug,
        ];
    }

    public function deletePage(int $id): array
    {
        $page = $this->model->findById($id);
        if ($page === null) {
            return ['success' => false, 'message' => 'Pagina nu există.'];
        }

        $slug = (string) ($page['slug'] ?? '');
        if ($this->isProtectedSlug($slug)) {
            return ['success' => false, 'message' => 'Această pagină nu poate fi ștearsă.'];
        }

        if ($this->isBuiltinSlug($slug)) {
            return ['success' => false, 'message' => 'Paginile standard se pot doar dezactiva, nu șterge.'];
        }

        $ok = $this->model->delete($id);

        return $ok
            ? ['success' => true, 'message' => 'Pagina a fost ștearsă.']
            : ['success' => false, 'message' => 'Eroare la ștergerea paginii.'];
    }

    public function toggleActive(int $id): array
    {
        $page = $this->model->findById($id);
        if ($page === null) {
            return ['success' => false, 'message' => 'Pagina nu există.'];
        }

        $slug = (string) ($page['slug'] ?? '');
        if ($slug === 'global') {
            return ['success' => false, 'message' => 'Header & Footer nu poate fi dezactivat.'];
        }

        $active = (int) ($page['is_active'] ?? 1) === 1;
        $ok = $this->model->setActive($id, !$active);

        return $ok
            ? [
                'success' => true,
                'message' => $active ? 'Pagina a fost dezactivată.' : 'Pagina a fost activată.',
                'is_active' => $active ? 0 : 1,
            ]
            : ['success' => false, 'message' => 'Eroare la actualizarea stării.'];
    }

    public function normalizeSlug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ'],
            ['a', 'a', 'i', 's', 't', 's', 't'],
            $value
        );
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value;
    }

    public function isProtectedSlug(string $slug): bool
    {
        return in_array($slug, self::PROTECTED_SLUGS, true);
    }

    public function isBuiltinSlug(string $slug): bool
    {
        if (!function_exists('site_live_builtin_slugs')) {
            require_once dirname(__DIR__, 4) . '/system/site-live-cms.php';
        }

        return in_array($slug, site_live_builtin_slugs(), true);
    }
}
