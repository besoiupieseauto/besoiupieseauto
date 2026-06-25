<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Website;

require_once dirname(__DIR__, 4) . '/system/site-content.php';

final class Website
{
    private WebsiteService $service;

    public function __construct(?WebsiteService $service = null)
    {
        $this->service = $service ?? new WebsiteService();
    }

    public function save(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID pagină invalid.'];
        }

        $payload = [
            'title' => trim((string) ($data['title'] ?? '')),
            'meta_description' => trim((string) ($data['meta_description'] ?? '')),
            'hero_label' => trim((string) ($data['hero_label'] ?? '')),
            'hero_title' => trim((string) ($data['hero_title'] ?? '')),
            'hero_subtitle' => trim((string) ($data['hero_subtitle'] ?? '')),
            'body_html' => trim((string) ($data['body_html'] ?? '')),
            'sections_json' => $this->normalizeJsonField($data['sections_json'] ?? ''),
            'faq_json' => $this->normalizeJsonField($data['faq_json'] ?? ''),
            'cta_json' => $this->normalizeJsonField($data['cta_json'] ?? ''),
        ];

        if ($payload['title'] === '') {
            return ['success' => false, 'message' => 'Titlul paginii este obligatoriu.'];
        }

        $ok = $this->service->update($id, $payload);

        return $ok
            ? ['success' => true, 'message' => 'Pagina a fost salvată.']
            : ['success' => false, 'message' => 'Eroare la salvarea paginii.'];
    }

    public function list(): array
    {
        return ['success' => true, 'data' => $this->service->getAll()];
    }

    public function create(array $data): array
    {
        return $this->service->createPage(
            (string) ($data['label'] ?? ''),
            (string) ($data['slug'] ?? ''),
            (string) ($data['title'] ?? '')
        );
    }

    public function delete(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID pagină invalid.'];
        }

        return $this->service->deletePage($id);
    }

    public function toggleActive(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID pagină invalid.'];
        }

        return $this->service->toggleActive($id);
    }

    private function normalizeJsonField($value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('JSON invalid. Verifică secțiunile, FAQ sau CTA.');
        }

        if (function_exists('site_cms_normalize_phone_blocks')) {
            $decoded = site_cms_normalize_phone_blocks($decoded);
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
