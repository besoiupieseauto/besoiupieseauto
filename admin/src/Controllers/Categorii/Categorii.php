<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Categorii;

final class Categorii
{
    private CategoriiService $service;

    public function __construct(?CategoriiService $service = null)
    {
        $this->service = $service ?? new CategoriiService();
    }

    public function add(array $data): array
    {
        $payload = $this->buildPayload($data);

        if (empty($payload['label'])) {
            return ['success' => false, 'message' => 'Label-ul este obligatoriu.'];
        }
        if (empty($payload['slug'])) {
            $payload['slug'] = $this->generateSlug($payload['label']);
        }

        $ok = $this->service->create($payload);

        return $ok
            ? ['success' => true, 'message' => 'Categorie adăugată.']
            : ['success' => false, 'message' => 'Eroare la salvare.'];
    }

    public function edit(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $payload = $this->buildPayload($data);
        if (empty($payload['slug']) && !empty($payload['label'])) {
            $payload['slug'] = $this->generateSlug($payload['label']);
        }

        $ok = $this->service->update($id, $payload);

        return $ok
            ? ['success' => true, 'message' => 'Categorie actualizată.']
            : ['success' => false, 'message' => 'Eroare la actualizare.'];
    }

    public function delete(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $ok = $this->service->delete($id);

        return $ok
            ? ['success' => true, 'message' => 'Categorie ștearsă.']
            : ['success' => false, 'message' => 'Eroare la ștergere.'];
    }

    public function toggleActive(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $active = (bool)($data['is_active'] ?? false);

        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $ok = $this->service->toggleActive($id, $active);

        return $ok
            ? ['success' => true, 'message' => $active ? 'Activată.' : 'Dezactivată.']
            : ['success' => false, 'message' => 'Eroare.'];
    }

    public function importDefaults(): array
    {
        $count = $this->service->importDefaults();
        return ['success' => true, 'message' => "$count categorii importate.", 'count' => $count];
    }

    private function buildPayload(array $data): array
    {
        $allowed = ['slug', 'label', 'icon', 'parent_id', 'sort_order', 'is_active', 'type', 'tecdoc_id', 'meta'];
        $payload = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $data[$key];
            }
        }
        if (isset($payload['parent_id']) && $payload['parent_id'] === '') {
            $payload['parent_id'] = null;
        }
        if (isset($payload['sort_order'])) {
            $payload['sort_order'] = (int)$payload['sort_order'];
        }
        if (isset($payload['is_active'])) {
            $payload['is_active'] = (int)$payload['is_active'];
        }
        return $payload;
    }

    private function generateSlug(string $label): string
    {
        $slug = mb_strtolower($label, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? $slug;
        return trim($slug, '-');
    }
}
