<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Blog;

final class Blog
{
    private BlogService $service;

    public function __construct(?BlogService $service = null)
    {
        $this->service = $service ?? new BlogService();
    }

    public function add(array $data): array
    {
        $payload = $this->buildPayload($data);
        if ($payload['title'] === '') {
            return ['success' => false, 'message' => 'Titlul este obligatoriu.'];
        }
        if ($payload['slug'] === '') {
            $payload['slug'] = $this->slugify($payload['title']);
        }

        $ok = $this->service->create($payload);

        return $ok
            ? ['success' => true, 'message' => 'Articolul a fost creat.']
            : ['success' => false, 'message' => 'Eroare la crearea articolului.'];
    }

    public function edit(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $payload = $this->buildPayload($data);
        if ($payload['title'] === '') {
            return ['success' => false, 'message' => 'Titlul este obligatoriu.'];
        }
        if ($payload['slug'] === '') {
            $payload['slug'] = $this->slugify($payload['title']);
        }

        $ok = $this->service->update($id, $payload);

        return $ok
            ? ['success' => true, 'message' => 'Articolul a fost actualizat.']
            : ['success' => false, 'message' => 'Eroare la actualizare.'];
    }

    public function delete(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $ok = $this->service->delete($id);

        return $ok
            ? ['success' => true, 'message' => 'Articolul a fost șters.']
            : ['success' => false, 'message' => 'Eroare la ștergere.'];
    }

    public function list(): array
    {
        return ['success' => true, 'data' => $this->service->getAll()];
    }

    private function buildPayload(array $data): array
    {
        $isPublished = !empty($data['is_published']) ? 1 : 0;
        $publishedAt = trim((string) ($data['published_at'] ?? ''));
        if ($isPublished && $publishedAt === '') {
            $publishedAt = date('Y-m-d H:i:s');
        }
        if (!$isPublished) {
            $publishedAt = '';
        }

        return [
            'slug' => trim((string) ($data['slug'] ?? '')),
            'title' => trim((string) ($data['title'] ?? '')),
            'tag' => trim((string) ($data['tag'] ?? 'Articole')) ?: 'Articole',
            'excerpt' => trim((string) ($data['excerpt'] ?? '')),
            'body_html' => trim((string) ($data['body_html'] ?? '')),
            'featured_image' => trim((string) ($data['featured_image'] ?? '')),
            'is_published' => $isPublished,
            'published_at' => $publishedAt !== '' ? $publishedAt : null,
        ];
    }

    private function slugify(string $value): string
    {
        $slug = mb_strtolower($value, 'UTF-8');
        $slug = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', ' '],
            ['a', 'a', 'i', 's', 't', '-'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? $slug;
        $slug = trim(preg_replace('/-+/', '-', $slug) ?? $slug, '-');

        return $slug !== '' ? $slug : 'articol-' . time();
    }
}
