<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Categorii;

use Evasystem\Core\Categorii\CategoriiModel;

final class CategoriiService
{
    /** tm_021: amânat până la popularea bazei de produse — TecDoc rămâne doar referință în admin. */
    public const TECDOC_STRUCTURE_IMPORT_DEFERRED = true;

    private CategoriiModel $model;

    public function __construct(?CategoriiModel $model = null)
    {
        $this->model = $model ?? new CategoriiModel();
    }

    public function isTecdocStructureImportEnabled(): bool
    {
        return !self::TECDOC_STRUCTURE_IMPORT_DEFERRED;
    }

    public function tecdocStructureImportBlockedMessage(): string
    {
        return 'Importul structurilor TecDoc (categorii, mărci, modele) este amânat până când baza principală de produse este populată. TecDoc rămâne disponibil în admin doar ca referință.';
    }

    public function getAll(): array
    {
        return $this->model->findAll();
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function getPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        return $this->model->findPaginated($page, $perPage, $filters);
    }

    public function getActive(): array
    {
        return $this->model->findActive();
    }

    public function getByType(string $type): array
    {
        return $this->model->findByType($type);
    }

    public function getById(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function getChildren(int $parentId): array
    {
        return $this->model->findByParentId($parentId);
    }

    public function getRootCategories(): array
    {
        return $this->model->findByParentId(null);
    }

    /**
     * Returnează arborele complet de categorii (pentru frontend).
     */
    public function getTree(): array
    {
        $all = $this->model->findActive();
        return $this->buildTree($all, null);
    }

    /**
     * Returnează categoriile formatate pentru popup-ul din index.php.
     */
    public function getForPopup(): array
    {
        $categories = $this->model->findByType('categorie');
        $result = [];
        foreach ($categories as $cat) {
            if ((int)($cat['parent_id'] ?? 0) !== 0) continue;
            $result[] = [
                'id'    => (int)$cat['id'],
                'slug'  => $cat['slug'],
                'label' => $cat['label'],
                'icon'  => $cat['icon'] ?? '',
                'count' => $this->countChildren((int)$cat['id'], $categories),
            ];
        }
        return $result;
    }

    /**
     * Returnează mărcile active.
     */
    public function getMarci(): array
    {
        return $this->model->findByType('marca');
    }

    public function create(array $data): bool
    {
        return $this->model->insert($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->model->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->model->delete($id);
    }

    public function toggleActive(int $id, bool $active): bool
    {
        return $this->model->toggleActive($id, $active ? 1 : 0);
    }

    /**
     * Import categorii inițiale (cele 8 din popup).
     */
    public function importDefaults(): int
    {
        $defaults = [
            ['slug' => 'frane',      'label' => 'Frâne',          'icon' => 'img/icons/01_frane.svg',          'sort_order' => 10],
            ['slug' => 'filtre',     'label' => 'Filtre',         'icon' => 'img/icons/02_filtre.svg',         'sort_order' => 20],
            ['slug' => 'ulei',       'label' => 'Ulei & Lichide', 'icon' => 'img/icons/03_ulei_lichide.svg',   'sort_order' => 30],
            ['slug' => 'suspensie',  'label' => 'Suspensie',      'icon' => 'img/icons/04_suspensie.svg',      'sort_order' => 40],
            ['slug' => 'motor',      'label' => 'Motor',          'icon' => 'img/icons/05_motor.svg',          'sort_order' => 50],
            ['slug' => 'electric',   'label' => 'Electric',       'icon' => 'img/icons/06_electric.svg',       'sort_order' => 60],
            ['slug' => 'caroserie',  'label' => 'Caroserie',      'icon' => 'img/icons/07_caroserie.svg',      'sort_order' => 70],
            ['slug' => 'transmisie', 'label' => 'Transmisie',     'icon' => 'img/icons/08_transmisie.svg',     'sort_order' => 80],
        ];

        $count = 0;
        foreach ($defaults as $cat) {
            $cat['type'] = 'categorie';
            $cat['is_active'] = 1;
            $cat['parent_id'] = null;
            if ($this->model->insert($cat)) {
                $count++;
            }
        }
        return $count;
    }

    private function buildTree(array $items, ?int $parentId): array
    {
        $tree = [];
        foreach ($items as $item) {
            $itemParent = $item['parent_id'] === null ? null : (int)$item['parent_id'];
            if ($itemParent === $parentId) {
                $children = $this->buildTree($items, (int)$item['id']);
                $node = [
                    'id'       => (int)$item['id'],
                    'slug'     => $item['slug'],
                    'label'    => $item['label'],
                    'icon'     => $item['icon'] ?? '',
                    'type'     => $item['type'] ?? 'categorie',
                    'tecdoc_id'=> $item['tecdoc_id'] ?? null,
                ];
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }
        return $tree;
    }

    private function countChildren(int $parentId, array $allCategories): int
    {
        $count = 0;
        foreach ($allCategories as $cat) {
            if ((int)($cat['parent_id'] ?? 0) === $parentId) {
                $count++;
            }
        }
        return $count;
    }
}
