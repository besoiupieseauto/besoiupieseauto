<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Categorii;

use Besoiu\Services\BesoiuCategoryTreeImportService;
use Besoiu\Services\CategoriiService;
use Besoiu\Services\PieseAutoCategoryCatalog;
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
            ? ['success' => true, 'message' => 'Intrare adăugată și sincronizată în catalog JSON.']
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
            ? ['success' => true, 'message' => 'Intrare actualizată și sincronizată în catalog JSON.']
            : ['success' => false, 'message' => 'Eroare la actualizare.'];
    }

    public function delete(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $ok = $this->service->delete($id);

        if (!$ok) {
            return ['success' => false, 'message' => 'Eroare la ștergere.'];
        }

        return [
            'success' => true,
            'message' => 'Intrare ștearsă. Catalog JSON resincronizat.',
        ];
    }

    public function toggleActive(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $active = (bool)($data['is_active'] ?? false);

        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $ok = $this->service->toggleActive($id, $active);

        if (!$ok) {
            return ['success' => false, 'message' => 'Eroare.'];
        }

        return [
            'success' => true,
            'message' => $active ? 'Activată și sincronizată.' : 'Dezactivată și sincronizată.',
        ];
    }

    public function syncTaxonomy(): array
    {
        $sync = $this->service->syncTaxonomy(null);

        return [
            'success' => true,
            'message' => sprintf(
                'Sincronizare completă: %d cat. piese, %d subcat., %d mărci în fitment JSON.',
                (int) ($sync['pieseauto']['categories'] ?? 0),
                (int) ($sync['pieseauto']['subcategories'] ?? 0),
                (int) ($sync['vehicule']['brands'] ?? 0)
            ),
            'sync' => $sync,
            'stats' => $this->service->getPieseAutoCatalogStats(),
        ];
    }

    public function importDefaults(): array
    {
        $count = $this->service->importDefaults();
        return ['success' => true, 'message' => "$count categorii importate/actualizate.", 'count' => $count];
    }

    public function backfillIcons(): array
    {
        $result = $this->service->backfillMissingIcons();

        return [
            'success' => true,
            'message' => $result['updated'] . ' iconițe atribuite. ' . $result['skipped'] . ' aveau deja icon.',
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ];
    }

    public function pieseautoStats(): array
    {
        return [
            'success' => true,
            'stats' => $this->service->getPieseAutoCatalogStats(),
        ];
    }

    public function importPieseAuto(array $data): array
    {
        $onlyMissing = !isset($data['force_update']) || !$data['force_update'];
        $result = $this->service->importPieseAutoCatalog($onlyMissing);

        return [
            'success' => true,
            'message' => sprintf(
                'Import PieseAuto: %d categorii, %d subcategorii noi; %d actualizate; %d sărite.',
                $result['imported_categories'],
                $result['imported_subcategories'],
                $result['updated'],
                $result['skipped']
            ),
            'result' => $result,
        ];
    }

    public function parsePieseAutoHtml(array $data): array
    {
        $html = trim((string) ($data['html'] ?? ''));
        if ($html === '') {
            $snapshot = PieseAutoCategoryCatalog::htmlSnapshotPath();
            if (is_file($snapshot)) {
                $html = (string) file_get_contents($snapshot);
            }
        }
        if ($html === '') {
            $fallback = PieseAutoCategoryCatalog::projectRoot() . '/tmp_pieseauto_user.html';
            if (is_file($fallback)) {
                $html = (string) file_get_contents($fallback);
            }
        }
        if ($html === '') {
            return ['success' => false, 'message' => 'Lipsește HTML-ul catalogului PieseAuto.'];
        }

        $parsed = $this->service->parsePieseAutoHtml($html);

        return [
            'success' => true,
            'message' => sprintf(
                'Catalog parsat: %d categorii, %d subcategorii.',
                $parsed['categories'],
                $parsed['subcategories']
            ),
            'parsed' => $parsed,
            'stats' => $this->service->getPieseAutoCatalogStats(),
        ];
    }

    public function matchCategory(array $data): array
    {
        $match = $this->service->matchProductCategory([
            'name' => (string) ($data['name'] ?? $data['pName'] ?? ''),
            'brand' => (string) ($data['brand'] ?? $data['pBrand'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'specs' => (string) ($data['specs'] ?? ''),
            'oem' => (string) ($data['oem'] ?? $data['pOem'] ?? ''),
            'use_ollama' => (bool) ($data['use_ollama'] ?? true),
            'use_tecdoc' => (bool) ($data['use_tecdoc'] ?? true),
        ]);

        return [
            'success' => true,
            'match' => $match,
            'ollama_status' => $this->service->categoryMatchOllamaStatus(),
        ];
    }

    public function previewBesoiuTree(array $data = []): array
    {
        $path = trim((string) ($data['path'] ?? ''));
        $preview = $this->service->previewBesoiuCategoryTree($path !== '' ? $path : null);

        return [
            'success' => true,
            'preview' => $preview,
            'message' => sprintf(
                '%d categorii (%d rădăcini, %d frunze, adâncime max %d).',
                (int) ($preview['total'] ?? 0),
                (int) ($preview['roots'] ?? 0),
                (int) ($preview['leaves'] ?? 0),
                (int) ($preview['max_depth'] ?? 0)
            ),
        ];
    }

    public function importBesoiuTree(array $data = []): array
    {
        $replace = !isset($data['keep_existing']) || !$data['keep_existing'];
        $renew = !empty($data['renew']);
        $path = trim((string) ($data['path'] ?? ''));
        $forceExcel = !empty($data['from_excel']);

        try {
            if ($renew) {
                $result = $this->service->renewBesoiuCategoryTree($path !== '' ? $path : null);
            } elseif ($forceExcel) {
                $result = $this->service->importBesoiuCategoryTreeFromExcel($replace, $path !== '' ? $path : null);
            } else {
                $result = $this->service->importBesoiuCategoryTree($replace, $path !== '' ? $path : null);
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $source = (string) ($result['source'] ?? 'visual_tree');
        $fallback = (string) ($result['fallback'] ?? '');

        return [
            'success' => true,
            'message' => sprintf(
                '%s Besoiu (%s%s): %d categorii, %d frunze, %d sinonime, %d secundare.',
                $renew ? 'Reînnoire completă' : 'Import',
                $source === 'excel' ? 'Excel' : 'vizual.txt',
                $fallback !== '' ? ', fallback vizual' : '',
                (int) ($result['imported'] ?? 0) + (int) ($result['updated'] ?? 0),
                (int) ($result['leaves'] ?? 0),
                (int) ($result['synonyms']['imported'] ?? 0),
                (int) ($result['secondary']['imported'] ?? 0)
            ),
            'result' => $result,
            'stats' => $this->service->getPieseAutoCatalogStats(),
        ];
    }

    public function purgeAlternateCatalog(): array
    {
        try {
            $result = $this->service->purgeAlternateCatalog();
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'message' => sprintf(
                'Catalog alternativ curățat: %d intrări șterse. Rămân %d noduri arbore Besoiu.',
                (int) ($result['deleted'] ?? 0),
                (int) ($result['remaining_besoiu'] ?? 0)
            ),
            'result' => $result,
            'stats' => $this->service->getPieseAutoCatalogStats(),
        ];
    }

    public function uploadBesoiuExcel(array $files): array
    {
        if (empty($files['excel']) || ($files['excel']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Niciun fișier Excel trimis sau eroare upload.'];
        }

        $file = $files['excel'];
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            return ['success' => false, 'message' => 'Doar fișiere .xlsx sunt acceptate.'];
        }

        $target = BesoiuCategoryTreeImportService::excelPath();
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            return ['success' => false, 'message' => 'Nu s-a putut salva Excel-ul în categorie/.'];
        }

        return [
            'success' => true,
            'message' => 'Excel salvat: categorie/categorii_tree_final.xlsx',
            'path' => $target,
        ];
    }

    public function syncTecdocMarci(array $data = []): array
    {
        $allowApi = !isset($data['db_only']) || !$data['db_only'];
        $updateExisting = !isset($data['keep_existing']) || !$data['keep_existing'];

        try {
            $result = $this->service->syncTecdocMarci($allowApi, $updateExisting);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'message' => (string) ($result['message'] ?? 'Sincronizare finalizată.'),
            'result' => $result,
            'stats' => $this->service->getPieseAutoCatalogStats(),
        ];
    }

    public function previewTecdocMarci(array $data = []): array
    {
        $allowApi = !isset($data['db_only']) || !$data['db_only'];
        $preview = $this->service->previewTecdocMarci($allowApi);

        return [
            'success' => true,
            'preview' => $preview,
            'message' => sprintf(
                '%d mărci disponibile pentru sincronizare (%d cu ID TecDoc).',
                (int) ($preview['total'] ?? 0),
                (int) ($preview['with_tecdoc_id'] ?? 0)
            ),
        ];
    }

    public function syncTecdocModele(array $data = []): array
    {
        $allowApi = !isset($data['db_only']) || !$data['db_only'];
        $updateExisting = !isset($data['keep_existing']) || !$data['keep_existing'];

        try {
            $result = $this->service->syncTecdocModele($allowApi, $updateExisting);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'message' => (string) ($result['message'] ?? 'Sincronizare finalizată.'),
            'result' => $result,
            'stats' => $this->service->getPieseAutoCatalogStats(),
        ];
    }

    public function previewTecdocModele(array $data = []): array
    {
        $allowApi = !isset($data['db_only']) || !$data['db_only'];
        $preview = $this->service->previewTecdocModele($allowApi);

        return [
            'success' => true,
            'preview' => $preview,
            'message' => sprintf(
                '%d modele disponibile (%d cu ID TecDoc, %d mărci).',
                (int) ($preview['total'] ?? 0),
                (int) ($preview['with_tecdoc_id'] ?? 0),
                (int) ($preview['brands'] ?? 0)
            ),
        ];
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
