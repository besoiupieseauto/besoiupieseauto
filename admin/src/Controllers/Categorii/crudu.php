<?php

declare(strict_types=1);

use Evasystem\Controllers\Categorii\Categorii;
use Evasystem\Controllers\Categorii\CategoriiService;
use Evasystem\Core\Crud\LegacyJsonCrud;

LegacyJsonCrud::prepare();

try {
    LegacyJsonCrud::requirePost();
    $data = LegacyJsonCrud::readInput();

    $type = $data['type_product'] ?? null;
    if (!$type) {
        throw new \InvalidArgumentException('Lipsește tipul acțiunii (type_product).');
    }

    $controller = new Categorii();

    switch ($type) {
        case 'add':
            $response = $controller->add($data);
            break;
        case 'edit':
            $response = $controller->edit($data);
            break;
        case 'delete':
            $response = $controller->delete($data);
            break;
        case 'toggle':
            $response = $controller->toggleActive($data);
            break;
        case 'import_defaults':
            $response = $controller->importDefaults();
            break;
        case 'list':
            $service = new CategoriiService();
            $filterType = $data['filter_type'] ?? '';
            $items = $filterType ? $service->getByType($filterType) : $service->getAll();
            $response = ['success' => true, 'data' => $items];
            break;
        case 'tree':
            $response = ['success' => true, 'data' => (new CategoriiService())->getTree()];
            break;
        case 'import_tecdoc':
            $service = new CategoriiService();
            if (!$service->isTecdocStructureImportEnabled()) {
                $response = [
                    'success' => false,
                    'reference_only' => true,
                    'message' => $service->tecdocStructureImportBlockedMessage(),
                ];
                break;
            }
            $items = $data['items'] ?? [];
            if (empty($items) || !is_array($items)) {
                $response = ['success' => false, 'message' => 'Nu s-au trimis categorii.'];
                break;
            }
            $imported = 0;
            foreach ($items as $item) {
                $label = trim((string) ($item['label'] ?? ''));
                $tecdocId = (int) ($item['tecdoc_id'] ?? 0);
                if ($label === '' || $tecdocId <= 0) {
                    continue;
                }
                $slug = mb_strtolower($label, 'UTF-8');
                $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? $slug;
                $slug = trim($slug, '-');
                $payload = [
                    'label' => $label,
                    'slug' => $slug,
                    'type' => 'categorie',
                    'tecdoc_id' => $tecdocId,
                    'is_active' => 1,
                    'sort_order' => $imported * 10,
                    'parent_id' => null,
                ];
                if (!empty($item['parent_tecdoc_id'])) {
                    $payload['meta'] = json_encode(['parent_tecdoc_id' => (int) $item['parent_tecdoc_id']]);
                }
                if ($service->create($payload)) {
                    $imported++;
                }
            }
            $response = ['success' => true, 'message' => $imported . ' categorii importate din TecDoc.', 'count' => $imported];
            break;
        default:
            throw new \InvalidArgumentException("Acțiune necunoscută: {$type}");
    }

    LegacyJsonCrud::emit(is_array($response) ? $response : ['success' => true, 'data' => $response]);
} catch (\Throwable $e) {
    LegacyJsonCrud::emitThrowable($e);
}
