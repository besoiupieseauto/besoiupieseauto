<?php
declare(strict_types=1);

use Besoiu\Services\AdaosComercial\AdaosComercialService;
use Besoiu\Services\Products\ProduseService;
use Besoiu\Core\Produse\ProduseModel;
use Config\Database;
use Besoiu\Core\Crud\LegacyJsonCrud;

LegacyJsonCrud::prepare();

require_once __DIR__ . '/import_supplier_lib.php';
require_once __DIR__ . '/import_identity_lib.php';
import_require_system_file('products_oem.php');
import_require_system_file('product_dual_description.php');
import_require_system_file('product_dual_title.php');

ini_set('display_errors', '0');
if (ob_get_level() === 0) {
    ob_start();
}

function produse_json(array $payload, int $status = 200): void
{
    static $hookLoaded = false;
    if (!$hookLoaded) {
        $hook = import_system_file_path('ai_admin_hook.php');
        if ($hook !== null) {
            require_once $hook;
        }
        $hookLoaded = true;
    }
    if (function_exists('ai_admin_hook_json')) {
        ai_admin_hook_json('produse', $payload, $status);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function produse_badge_config_path(): string
{
    return dirname(__DIR__, 5) . '/app/Config/product-badges.php';
}

function produse_normalize_badge(string $badge): string
{
    $badge = trim($badge);
    if ($badge === '') {
        return '';
    }
    $badgeConfigPath = produse_badge_config_path();
    $badgeConfig = is_file($badgeConfigPath) ? (require $badgeConfigPath) : [];
    if (!is_array($badgeConfig) || !isset($badgeConfig[$badge])) {
        return '';
    }

    return $badge;
}

function produse_normalize_status(mixed $status): int
{
    $value = trim((string) $status);
    if ($value === '0' || $value === 'inactive' || $value === 'inactiv') {
        return 0;
    }

    return 1;
}

function produse_status_options(): array
{
    $path = dirname(__DIR__, 5) . '/app/Backend/config/product-status.php';

    return is_file($path) ? (require $path) : ['1' => 'Activ', '0' => 'Inactiv'];
}

function produse_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST ?: [];
}

/** @return array<string,string> */
function produse_normalize_list_filters(array $input): array
{
    $allowed = ['q', 'category', 'subcategory', 'marca', 'brand', 'supplier', 'image', 'markup', 'status', 'origin'];
    $filters = [];
    foreach ($allowed as $key) {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value !== '') {
            $filters[$key] = mb_substr($value, 0, 255, 'UTF-8');
        }
    }

    return $filters;
}

function produse_images_from_keep($value): array
{
    if (!$value) {
        return [];
    }
    if (is_array($value)) {
        return array_values(array_filter($value));
    }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? array_values(array_filter($decoded)) : [];
}

function produse_upload_images(): array
{
    if (empty($_FILES['pImages'])) {
        return [];
    }

    $files = $_FILES['pImages'];
    $uploadDir = dirname(__DIR__, 3) . '/public/uploads/produse';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $saved = [];

    foreach ($names as $i => $originalName) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (($errors[$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            produse_json(['success' => false, 'message' => 'Una dintre imagini nu s-a incarcat corect.'], 422);
        }
        if (($sizes[$i] ?? 0) > 8 * 1024 * 1024) {
            produse_json(['success' => false, 'message' => 'Imaginea este prea mare. Maxim 8MB.'], 422);
        }

        $tmp = $tmpNames[$i] ?? '';
        $mime = is_file($tmp) ? (mime_content_type($tmp) ?: '') : '';
        if (!isset($allowed[$mime])) {
            produse_json(['success' => false, 'message' => 'Sunt permise doar imagini JPG, PNG, WEBP sau GIF.'], 422);
        }

        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
        $target = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $target)) {
            produse_json(['success' => false, 'message' => 'Nu am putut salva imaginea.'], 500);
        }
        $saved[] = '/admin/uploads/produse/' . $filename;
    }

    return $saved;
}

function produse_audit_max_batch(): int
{
    $raw = (int) ($_ENV['IMAGE_AUDIT_MAX_BATCH'] ?? getenv('IMAGE_AUDIT_MAX_BATCH') ?: 100);

    return max(1, min(500, $raw));
}

/** @return list<string> */
function produse_audit_ids_all_catalog(ProduseService $service): array
{
    $ids = [];
    foreach ($service->listAllProductIdentifiers() as $row) {
        $rid = trim((string) ($row['randomn_id'] ?? ''));
        if ($rid !== '') {
            $ids[] = $rid;
        }
    }

    return $ids;
}

function produse_reapply_markup(ProduseService $service, AdaosComercialService $markupService, string $id): bool
{
    if ($id === '') {
        return false;
    }

    $product = $service->getIdProduses($id);
    if (!$product) {
        return false;
    }

    $pricing = $markupService->applyAutomaticMarkup($product, $product, true);
    return $service->editProduse($id, $pricing['data']);
}

try {
    $input = produse_input();
    $type = (string)($input['type_product'] ?? $input['type'] ?? '');
    $service = new ProduseService();
    $productsModel = new ProduseModel();
    $markupService = new AdaosComercialService();

    if ($type === 'delete') {
        $id = (string)($input['id'] ?? '');
        if ($id === '') {
            produse_json(['success' => false, 'message' => 'ID lipsa.'], 422);
        }
        $existing = $service->getIdProduses($id);
        if (!$service->deleteProduse($id)) {
            produse_json(['success' => false, 'message' => 'Produsul nu a putut fi sters sau nu mai exista.'], 404);
        }
        if ($existing && isset($existing['id'])) {
            products_oem_delete_for_product(Database::getDB(), (int) $existing['id']);
        }
        produse_json(['success' => true, 'message' => 'Produs sters.']);
    }

    if ($type === 'delete_bulk') {
        $deleteAll = !empty($input['all']);
        $ids = array_values(array_filter(array_map('strval', (array) ($input['ids'] ?? []))));

        if ($deleteAll) {
            $confirm = trim((string) ($input['confirm'] ?? ''));
            if ($confirm !== 'STERGE TOT') {
                produse_json(['success' => false, 'message' => 'Confirmare invalidă. Tastează exact: STERGE TOT'], 422);
            }

            $result = $productsModel->deleteMany([], true);
            $deleted = (int) ($result['deleted'] ?? 0);

            produse_json([
                'success' => $deleted > 0,
                'message' => $deleted > 0
                    ? 'Au fost șterse ' . $deleted . ' produse din magazin.'
                    : 'Nu există produse de șters.',
                'count' => $deleted,
            ]);
        }

        if ($ids === []) {
            produse_json(['success' => false, 'message' => 'Nu ai selectat produse.'], 422);
        }

        $result = $productsModel->deleteMany($ids);
        $deleted = (int) ($result['deleted'] ?? 0);

        produse_json([
            'success' => $deleted > 0,
            'message' => $deleted > 0
                ? 'Au fost șterse ' . $deleted . ' produse.'
                : 'Niciun produs nu a putut fi șters.',
            'count' => $deleted,
        ]);
    }

    if ($type === 'reapply_markup') {
        $id = (string)($input['id'] ?? '');
        if ($id === '') {
            produse_json(['success' => false, 'message' => 'ID lipsa.'], 422);
        }

        if (!produse_reapply_markup($service, $markupService, $id)) {
            produse_json(['success' => false, 'message' => 'Nu am putut reaplica adaosul pentru produsul selectat.'], 404);
        }

        produse_json(['success' => true, 'message' => 'Adaosul a fost reaplicat pentru produsul selectat.']);
    }

    if ($type === 'toggle_vitrina') {
        $id = (string) ($input['id'] ?? '');
        if ($id === '') {
            produse_json(['success' => false, 'message' => 'ID lipsa.'], 422);
        }
        $enabled = filter_var($input['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if ($enabled) {
            $resolvedId = $service->ensureProductForVitrina($id);
            if ($resolvedId === null) {
                produse_json(['success' => false, 'message' => 'Produsul nu a fost gasit in catalogul scanat.'], 404);
            }
            $id = $resolvedId;
        }
        if ($enabled && !$service->canAddToVitrina($id)) {
            produse_json(['success' => false, 'message' => 'Maxim ' . $service->vitrinaHomepageMax() . ' produse pe vitrina homepage.'], 422);
        }
        if (!$service->setProductVitrina($id, $enabled)) {
            produse_json(['success' => false, 'message' => 'Nu am putut actualiza vitrina.'], 500);
        }
        if ($enabled) {
            $service->setProductBadge($id, 'recomandat');
        }
        produse_json([
            'success' => true,
            'message' => $enabled ? 'Produs adaugat pe vitrina.' : 'Produs scos de pe vitrina.',
            'vitrina_count' => $service->countVitrinaProducts(),
        ]);
    }

    if ($type === 'toggle_vitrina_bulk') {
        $ids = array_values(array_filter(array_map('strval', (array) ($input['ids'] ?? []))));
        $enabled = filter_var($input['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if ($ids === []) {
            produse_json(['success' => false, 'message' => 'Nu ai selectat produse.'], 422);
        }
        $resolvedIds = [];
        foreach ($ids as $id) {
            if ($enabled && str_starts_with($id, 'epiesa_')) {
                $id = (string) ($service->ensureProductForVitrina($id) ?? '');
            }
            if ($id !== '') {
                $resolvedIds[] = $id;
            }
        }
        $rows = $productsModel->findMany($resolvedIds, ['id', 'randomn_id', 'pVitrina']);
        $rowsByIdentifier = [];
        foreach ($rows as $row) {
            $rowsByIdentifier[(string) ($row['id'] ?? '')] = $row;
            $publicId = trim((string) ($row['randomn_id'] ?? ''));
            if ($publicId !== '') {
                $rowsByIdentifier[$publicId] = $row;
            }
        }
        $targets = [];
        if ($enabled) {
            $capacity = max(0, $service->vitrinaHomepageMax() - $service->countVitrinaProducts());
            foreach ($resolvedIds as $resolvedId) {
                $row = $rowsByIdentifier[$resolvedId] ?? null;
                if (!is_array($row)) {
                    continue;
                }
                $identifier = trim((string) ($row['randomn_id'] ?? '')) ?: (string) ($row['id'] ?? '');
                if ((int) ($row['pVitrina'] ?? 0) === 1) {
                    $targets[] = $identifier;
                } elseif ($capacity > 0) {
                    $targets[] = $identifier;
                    --$capacity;
                }
            }
        } else {
            $targets = $resolvedIds;
        }
        $bulk = $productsModel->updateMany(
            $targets,
            $enabled ? ['pVitrina' => 1, 'pBadge' => 'recomandat'] : ['pVitrina' => 0]
        );
        $updated = (int) ($bulk['updated'] ?? 0);
        produse_json([
            'success' => $updated > 0,
            'message' => $updated > 0
                ? ($enabled ? "Adaugate {$updated} produse pe vitrina." : "Scoase {$updated} produse de pe vitrina.")
                : 'Niciun produs actualizat (limita ' . $service->vitrinaHomepageMax() . ' sau produse invalide).',
            'vitrina_count' => $service->countVitrinaProducts(),
            'count' => $updated,
        ]);
    }

    if ($type === 'clear_vitrina_all') {
        $clearBadges = !array_key_exists('clear_badges', $input) || !empty($input['clear_badges']);
        $result = $service->clearAllVitrinaProducts($clearBadges);
        $before = (int) ($result['vitrina_before'] ?? 0);
        $affected = (int) ($result['rows_affected'] ?? 0);
        produse_json([
            'success' => true,
            'message' => $before > 0 || $affected > 0
                ? ('Vitrina golită: ' . $before . ' produse scoase de pe homepage'
                    . ($clearBadges ? ' și badge RECOMANDAT eliminat.' : '.'))
                : 'Vitrina era deja goală.',
            'vitrina_count' => $service->countVitrinaProducts(),
            'count' => $affected,
            'vitrina_before' => $before,
        ]);
    }

    if ($type === 'set_badge') {
        $id = (string) ($input['id'] ?? '');
        $badgeRaw = trim((string) ($input['badge'] ?? ''));
        $badge = $badgeRaw === '' ? '' : produse_normalize_badge($badgeRaw);
        if ($id === '') {
            produse_json(['success' => false, 'message' => 'ID lipsa.'], 422);
        }
        if ($badgeRaw !== '' && $badge === '') {
            produse_json(['success' => false, 'message' => 'Badge invalid.'], 422);
        }
        if ($badge !== '') {
            $resolvedId = $service->ensureProductForVitrina($id);
            if ($resolvedId === null) {
                produse_json(['success' => false, 'message' => 'Produsul nu a fost gasit in catalogul scanat.'], 404);
            }
            $id = $resolvedId;
        }
        if (!$service->setProductBadge($id, $badge)) {
            produse_json(['success' => false, 'message' => 'Nu am putut actualiza badge-ul.'], 500);
        }
        produse_json(['success' => true, 'message' => $badge !== '' ? 'Badge setat.' : 'Badge eliminat.']);
    }

    if ($type === 'set_badge_bulk') {
        $ids = array_values(array_filter(array_map('strval', (array) ($input['ids'] ?? []))));
        $badgeRaw = trim((string) ($input['badge'] ?? ''));
        $badge = $badgeRaw === '' ? '' : produse_normalize_badge($badgeRaw);
        if ($ids === []) {
            produse_json(['success' => false, 'message' => 'Nu ai selectat produse.'], 422);
        }
        if ($badgeRaw !== '' && $badge === '') {
            produse_json(['success' => false, 'message' => 'Badge invalid.'], 422);
        }
        $resolvedIds = [];
        foreach ($ids as $id) {
            if ($badge !== '' && str_starts_with($id, 'epiesa_')) {
                $id = (string) ($service->ensureProductForVitrina($id) ?? '');
            }
            if ($id !== '') {
                $resolvedIds[] = $id;
            }
        }
        $bulk = $productsModel->updateMany($resolvedIds, ['pBadge' => $badge]);
        $updated = (int) ($bulk['updated'] ?? 0);
        produse_json([
            'success' => $updated > 0,
            'message' => "Badge actualizat pentru {$updated} produse.",
            'count' => $updated,
        ]);
    }

    if ($type === 'reapply_markup_bulk') {
        $ids = array_values(array_filter(array_map('strval', (array)($input['ids'] ?? []))));
        if ($ids === []) {
            produse_json(['success' => false, 'message' => 'Nu ai selectat produse pentru reaplicare.'], 422);
        }

        $result = $markupService->reapplyAutomaticMarkupToProductIds($ids);
        $updated = (int) ($result['updated_count'] ?? 0);

        produse_json([
            'success' => true,
            'message' => 'Adaosul a fost reaplicat pentru ' . $updated . ' produse.',
            'count' => $updated,
        ]);
    }

    if ($type === 'reapply_markup_by_filters') {
        $filters = produse_normalize_list_filters(is_array($input['filters'] ?? null) ? $input['filters'] : []);
        $total = $service->countAdminList($filters);
        if ($total <= 0) {
            produse_json(['success' => false, 'message' => 'Nu există produse care să corespundă filtrelor.'], 422);
        }

        $updated = 0;
        $chunkSize = 200;
        $cursorId = null;
        while (true) {
            $batch = $productsModel->listRandomIdsByFilters($filters, $chunkSize, $cursorId);
            $ids = $batch['ids'] ?? [];
            if ($ids === []) {
                break;
            }
            $result = $markupService->reapplyAutomaticMarkupToProductIds($ids);
            $updated += (int) ($result['updated_count'] ?? 0);
            $cursorId = $batch['last_id'] ?? null;
            if ($cursorId === null || count($ids) < $chunkSize) {
                break;
            }
        }

        produse_json([
            'success' => true,
            'message' => 'Adaosul a fost reaplicat pentru ' . $updated . ' produse (din ' . $total . ' filtrate).',
            'count' => $updated,
            'total_filtered' => $total,
        ]);
    }

    if ($type === 'set_curier_livrare_bulk') {
        $ids = array_values(array_filter(array_map('strval', (array) ($input['ids'] ?? []))));
        if ($ids === []) {
            produse_json(['success' => false, 'message' => 'Nu ai selectat produse pentru livrare curier.'], 422);
        }

        $value = trim((string) ($input['value'] ?? 'Nu'));
        if (!in_array($value, ['Da', 'Nu'], true)) {
            $value = 'Nu';
        }

        $result = $service->setCurierLivrareBulk($ids, $value);
        $updated = (int) ($result['updated'] ?? 0);

        produse_json([
            'success' => $updated > 0,
            'message' => 'Livrare curier: ' . $value . ' aplicat pe ' . $updated . ' produse.',
            'count' => $updated,
            'failed' => (int) ($result['failed'] ?? 0),
        ]);
    }

    if ($type === 'set_curier_livrare_by_filters') {
        $filters = produse_normalize_list_filters(is_array($input['filters'] ?? null) ? $input['filters'] : []);
        $total = $service->countAdminList($filters);
        if ($total <= 0) {
            produse_json(['success' => false, 'message' => 'Nu există produse care să corespundă filtrelor.'], 422);
        }

        $value = trim((string) ($input['value'] ?? 'Nu'));
        if (!in_array($value, ['Da', 'Nu'], true)) {
            $value = 'Nu';
        }

        $result = $productsModel->updateFieldByFilters($filters, [
            'pCurierLivrare' => $value === 'Da' ? 'Da' : 'Nu',
        ]);
        $updated = (int) ($result['updated'] ?? 0);

        produse_json([
            'success' => $updated > 0,
            'message' => 'Livrare curier: ' . $value . ' aplicat pe ' . $updated . ' produse filtrate.',
            'count' => $updated,
            'total_filtered' => $total,
            'failed' => (int) ($result['failed'] ?? 0),
        ]);
    }

    if ($type === 'set_badge_by_filters') {
        $filters = produse_normalize_list_filters(is_array($input['filters'] ?? null) ? $input['filters'] : []);
        $total = $service->countAdminList($filters);
        if ($total <= 0) {
            produse_json(['success' => false, 'message' => 'Nu există produse care să corespundă filtrelor.'], 422);
        }

        $badgeRaw = trim((string) ($input['badge'] ?? ''));
        $badge = $badgeRaw === '' ? '' : produse_normalize_badge($badgeRaw);
        if ($badgeRaw !== '' && $badge === '') {
            produse_json(['success' => false, 'message' => 'Badge invalid.'], 422);
        }

        $result = $productsModel->updateFieldByFilters($filters, ['pBadge' => $badge]);
        $updated = (int) ($result['updated'] ?? 0);

        produse_json([
            'success' => $updated > 0,
            'message' => 'Badge actualizat pentru ' . $updated . ' produse filtrate.',
            'count' => $updated,
            'total_filtered' => $total,
        ]);
    }

    if ($type === 'set_curier_livrare_by_category') {
        $category = trim((string) ($input['category'] ?? ''));
        if ($category === '') {
            produse_json(['success' => false, 'message' => 'Selectează o categorie din filtru.'], 422);
        }

        $subcategory = trim((string) ($input['subcategory'] ?? ''));
        $value = trim((string) ($input['value'] ?? 'Nu'));
        if (!in_array($value, ['Da', 'Nu'], true)) {
            $value = 'Nu';
        }

        $result = $service->setCurierLivrareByCategory(
            $category,
            $value,
            $subcategory !== '' ? $subcategory : null
        );
        $updated = (int) ($result['updated'] ?? 0);

        $scope = $subcategory !== ''
            ? 'categoria „' . $category . '” / subcategoria „' . $subcategory . '”'
            : 'categoria „' . $category . '”';

        produse_json([
            'success' => $updated > 0,
            'message' => 'Livrare curier: ' . $value . ' aplicat pe ' . $updated . ' produse din ' . $scope . '.',
            'count' => $updated,
            'failed' => (int) ($result['failed'] ?? 0),
        ]);
    }

    if ($type === 'apply_markup_rule') {
        $ruleId = (int) ($input['rule_id'] ?? 0);
        if ($ruleId <= 0) {
            produse_json(['success' => false, 'message' => 'Selectează o regulă de adaos.'], 422);
        }

        $ids = array_values(array_filter(array_map('strval', (array) ($input['ids'] ?? []))));
        if ($ids === []) {
            produse_json(['success' => false, 'message' => 'Nu ai selectat produse pentru aplicarea adaosului.'], 422);
        }

        try {
            $result = $markupService->applyRuleToProductIds($ruleId, $ids);
        } catch (\InvalidArgumentException $e) {
            produse_json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            produse_json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            produse_json(['success' => false, 'message' => 'Nu am putut aplica regula de adaos.'], 500);
        }

        $updated = (int) ($result['updated_count'] ?? 0);
        $ruleName = (string) ($result['rule']['name'] ?? 'Regulă');
        $message = 'Regula „' . $ruleName . '” aplicată pe ' . $updated . ' produse.';
        if (($result['not_found_count'] ?? 0) > 0) {
            $message .= ' ' . (int) $result['not_found_count'] . ' produse nu au fost găsite.';
        }

        produse_json([
            'success' => true,
            'message' => $message,
            'data' => $result,
        ]);
    }

    if (in_array($type, ['audit_images', 'audit_images_bulk', 'audit_images_reload', 'audit_images_status', 'audit_images_pipeline_retry', 'audit_images_preview', 'audit_images_find_image'], true)) {
        set_time_limit(900);

        require_once dirname(__DIR__, 2) . '/Services/ProductImageAuditService.php';

        $maxAudit = produse_audit_max_batch();
        $selectAllCatalog = !empty($input['all']);

        if ($selectAllCatalog) {
            $ids = produse_audit_ids_all_catalog($service);
        } elseif (in_array($type, ['audit_images_bulk', 'audit_images_reload', 'audit_images_status', 'audit_images_pipeline_retry', 'audit_images_preview', 'audit_images_find_image'], true)) {
            $ids = array_values(array_filter(array_map('strval', (array) ($input['ids'] ?? []))));
        } else {
            $ids = array_values(array_filter([(string) ($input['id'] ?? '')]));
        }

        if ($ids === [] && !in_array($type, ['audit_images_reload', 'audit_images_status'], true)) {
            produse_json(['success' => false, 'message' => 'Nu ai selectat produse pentru audit imagini.'], 422);
        }

        if (!in_array($type, ['audit_images_reload', 'audit_images_status', 'audit_images_pipeline_retry', 'audit_images_preview', 'audit_images_find_image'], true) && count($ids) > $maxAudit) {
            produse_json([
                'success' => false,
                'message' => 'Maxim ' . $maxAudit . ' produse per audit (ai selectat ' . count($ids) . ').',
            ], 422);
        }

        $projectRoot = dirname(__DIR__, 4);
        $auditService = new \Besoiu\Services\ProductImageAuditService($projectRoot);
        $pdo = Database::getDB();

        if ($type === 'audit_images_status') {
            $jobId = trim((string) ($input['job_id'] ?? ''));
            if ($jobId === '') {
                produse_json(['success' => false, 'message' => 'Lipsește job_id pentru progres audit.'], 422);
            }
            $progress = $auditService->buildCursorJobProgress($jobId);
            if ($progress === null) {
                produse_json(['success' => false, 'message' => 'Job audit negăsit sau expirat.'], 404);
            }
            if (!empty($progress['finished']) && (int) ($progress['done'] ?? 0) < (int) ($progress['total'] ?? 0)) {
                $filled = $auditService->fillMissingCursorAuditResults($pdo, $jobId);
                if ($filled > 0) {
                    $progress = $auditService->buildCursorJobProgress($jobId);
                }
            }
            produse_json($progress ?? ['success' => false, 'message' => 'Job audit negăsit.']);
        }

        if ($type === 'audit_images_reload') {
            if ($ids === []) {
                produse_json(['success' => false, 'message' => 'Lipsesc ID-uri produse.'], 422);
            }
            $results = $auditService->loadAuditResultsForIds($ids);
            $productsPreview = $auditService->loadProductsByPublicIds($pdo, $ids);
            produse_json([
                'success' => true,
                'message' => count($results) > 0
                    ? 'Încărcate ' . count($results) . ' rezultate salvate.'
                    : 'Încă nu există rezultate — rulează auditul din nou.',
                'results' => $results,
                'products' => $productsPreview,
                'count' => count($results),
            ]);
        }

        if ($type === 'audit_images_preview') {
            if ($ids === []) {
                produse_json(['success' => false, 'message' => 'Lipsesc ID-uri produse.'], 422);
            }
            produse_json([
                'success' => true,
                'products' => $auditService->loadProductsByPublicIds($pdo, $ids),
                'results' => $auditService->loadAuditResultsForIds($ids),
                'ids' => $ids,
                'count' => count($ids),
            ]);
        }

        if ($type === 'audit_images_pipeline_retry' || $type === 'audit_images_find_image') {
            if ($ids === []) {
                produse_json(['success' => false, 'message' => 'Lipsesc ID-uri produse pentru pipeline imagini.'], 422);
            }
            @set_time_limit(300);
            @ini_set('max_execution_time', '300');
            require_once dirname(__DIR__, 2) . '/Services/ImageAuditImportBridge.php';
            require_once dirname(__DIR__, 2) . '/Services/ImageAuditPipelineRetryService.php';
            $bridge = new \Besoiu\Services\ImageAuditImportBridge($auditService);
            $retry = new \Besoiu\Services\ImageAuditPipelineRetryService($projectRoot, $auditService, $bridge);
            $force = $type === 'audit_images_find_image' || !empty($input['force']);
            $dryRun = !empty($input['dry_run']);
            $pipelineOut = $retry->retryForProductIds($pdo, $ids, [
                'force' => $force,
                'dry_run' => $dryRun,
            ]);
            produse_json([
                'success' => !empty($pipelineOut['ok']),
                'message' => (string) ($pipelineOut['message'] ?? 'Pipeline imagini finalizat.'),
                'pipeline' => $pipelineOut,
                'results' => $auditService->loadAuditResultsForIds($ids),
                'products' => $auditService->loadProductsByPublicIds($pdo, $ids),
            ]);
        }

        $products = $auditService->loadProductsByPublicIds($pdo, $ids);
        if ($products === []) {
            produse_json(['success' => false, 'message' => 'Produsele selectate nu au fost găsite.'], 404);
        }

        $engine = \Besoiu\Services\ProductImageAuditService::auditEngine();
        $openAiKey = \Besoiu\Services\ProductImageAuditService::normalizeOpenAiKey(
            (string) ($_ENV['OPENAI_KEY'] ?? getenv('OPENAI_KEY') ?: '')
        );
        $model = trim((string) ($_ENV['IMAGE_AUDIT_MODEL'] ?? getenv('IMAGE_AUDIT_MODEL') ?: 'gpt-4o-mini'));
        $openAiFallback = \Besoiu\Services\ProductImageAuditService::openAiFallbackEnabled();

        if ($engine === 'ollama') {
            require_once dirname(__DIR__, 2) . '/Services/OllamaLlmClient.php';
            $ollama = new \Besoiu\Services\OllamaLlmClient($projectRoot);
            if (empty($ollama->visionReadiness()['ready'])) {
                produse_json([
                    'success' => false,
                    'message' => 'Ollama vision indisponibil — rulează: ollama pull llava:7b',
                    'products' => $products,
                    'engine' => 'ollama',
                ], 422);
            }
            $results = [];
            foreach ($products as $product) {
                $row = $auditService->analyzeProductWithOllamaVision($product, $ollama);
                $row['engine'] = 'ollama';
                $auditService->saveProductAuditResult($row);
                $results[] = $row;
            }
            produse_json([
                'success' => true,
                'message' => 'Audit Ollama finalizat pe ' . count($results) . ' produs(e).',
                'results' => $results,
                'products' => $products,
                'count' => count($results),
                'total' => count($products),
                'done' => count($results),
                'engine' => 'ollama',
            ]);
        }

        if ($engine === 'openai') {
            if ($openAiKey === '') {
                produse_json([
                    'success' => false,
                    'message' => 'Mod OpenAI activ — setează OPENAI_KEY în admin/.env sau folosește IMAGE_AUDIT_ENGINE=manual.',
                    'products' => $products,
                ], 422);
            }
            $keyError = $auditService->verifyOpenAiKey($openAiKey, $model);
            if ($keyError !== null) {
                produse_json(['success' => false, 'message' => $keyError, 'api_error' => true, 'products' => $products], 401);
            }
            $sync = $auditService->runOpenAiAuditSync($products, $ids, $openAiKey, $model);
            if ($sync['ok']) {
                produse_json([
                    'success' => true,
                    'message' => $sync['message'],
                    'results' => $sync['results'],
                    'products' => $products,
                    'count' => count($sync['results']),
                    'total' => count($products),
                    'done' => count($sync['results']),
                    'engine' => 'openai',
                ]);
            }
            $cached = $auditService->loadAuditResultsForIds($ids);
            if ($cached !== []) {
                produse_json([
                    'success' => true,
                    'message' => $sync['message'] . ' — afișez ultimul audit salvat.',
                    'results' => $cached,
                    'products' => $products,
                    'count' => count($cached),
                    'total' => count($products),
                    'done' => count($cached),
                    'cached' => true,
                    'api_error' => true,
                    'engine' => 'openai',
                ]);
            }
            produse_json([
                'success' => false,
                'message' => $sync['message'],
                'api_error' => true,
                'results' => $sync['results'],
                'products' => $products,
                'engine' => 'openai',
            ], 401);
        }

        $prep = $auditService->prepareCursorAuditBatch($products, [
            'source' => 'admin_crud',
            'ids' => $ids,
        ]);
        produse_json([
            'success' => true,
            'mode' => 'manual',
            'engine' => 'manual-composer',
            'message' => 'Lot pregătit — rulează auditul manual în Cursor IDE (@product-image-audit) sau setează IMAGE_AUDIT_ENGINE=openai.',
            'cursor_prompt' => (string) ($prep['cursor_prompt'] ?? ''),
            'batch_path' => (string) ($prep['batch_path'] ?? ''),
            'batch_name' => (string) ($prep['batch_name'] ?? ''),
            'products' => $products,
            'ids' => $ids,
            'results' => $auditService->loadAuditResultsForIds($ids),
            'count' => 0,
            'total' => count($products),
            'done' => 0,
        ]);
    }

    if (!in_array($type, ['add', 'edit'], true)) {
        produse_json(['success' => false, 'message' => 'Actiune invalida.'], 422);
    }

    $data = [
        'pName' => $input['pName'] ?? '',
        'pNameMarketplace' => $input['pNameMarketplace'] ?? '',
        'pCar' => $input['pCar'] ?? '',
        'pCode' => $input['pCode'] ?? '',
        'pBasePrice' => $input['pBasePrice'] ?? ($input['pPrice'] ?? ''),
        'pState' => 'Nou',
        'pCity' => '',
        'pNote' => $input['pNote'] ?? '',
        'pShipping' => $input['pShipping'] ?? '',
        'pCurierLivrare' => in_array(trim((string) ($input['pCurierLivrare'] ?? 'Da')), ['Da', 'Nu'], true)
            ? trim((string) ($input['pCurierLivrare'] ?? 'Da'))
            : 'Da',
        'pWarranty' => '2 ani',
        'pReturn' => '14 zile',
        'pWhatsapp' => $input['pWhatsapp'] ?? '',
        'pBrand' => $input['pBrand'] ?? '',
        'pMarca' => $input['pMarca'] ?? '',
        'pModel' => $input['pModel'] ?? '',
        'pMotorizare' => $input['pMotorizare'] ?? '',
        'pStock' => $input['pStock'] ?? '',
        'pCategory' => $input['pCategory'] ?? '',
        'pSubcategory' => $input['pSubcategory'] ?? '',
        'pBadge' => $input['pBadge'] ?? '',
        'pVitrina' => isset($input['pVitrina']) ? (int) (bool) $input['pVitrina'] : 0,
        'status' => produse_normalize_status($input['status'] ?? 1),
    ];

    $badgeConfigPath = produse_badge_config_path();
    $badgeConfig = is_file($badgeConfigPath) ? (require $badgeConfigPath) : [];
    $badgeValue = trim((string) ($data['pBadge'] ?? ''));
    if ($badgeValue !== '' && (!is_array($badgeConfig) || !isset($badgeConfig[$badgeValue]))) {
        $badgeValue = '';
    }
    $data['pBadge'] = $badgeValue;

    if (trim((string) $data['pName']) === '') {
        produse_json(['success' => false, 'message' => 'Completeaza titlul pentru site (web).'], 422);
    }

    $siteTitle = trim((string) $data['pName']);
    $marketplaceTitle = trim((string) ($data['pNameMarketplace'] ?? ''));
    if ($marketplaceTitle === '') {
        $marketplaceTitle = $siteTitle;
    }
    if (function_exists('besoiu_apply_dual_product_titles')) {
        besoiu_apply_dual_product_titles($data, $siteTitle, $marketplaceTitle);
    } else {
        $data['pName'] = $siteTitle;
        $data['pNameMarketplace'] = $marketplaceTitle;
    }

    try {
        if (function_exists('besoiu_ensure_name_marketplace_columns')) {
            besoiu_ensure_name_marketplace_columns(Database::getDB());
        } elseif (function_exists('import_ensure_name_marketplace_columns')) {
            import_ensure_name_marketplace_columns(Database::getDB());
        }
    } catch (Throwable $e) {
        // coloana poate exista deja
    }

    $note = trim((string) ($data['pNote'] ?? ''));
    besoiu_apply_product_description($data, $note);

    $images = array_merge(produse_images_from_keep($input['pImages_keep'] ?? ''), produse_upload_images());
    $data['pImages'] = json_encode(array_values($images), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $existingProduct = null;
    if ($type === 'edit') {
        $existingId = (string)($input['id'] ?? '');
        if ($existingId === '') {
            produse_json(['success' => false, 'message' => 'ID lipsa pentru editare.'], 422);
        }
        $existingProduct = $service->getIdProduses($existingId);
    }

    $pricing = $markupService->applyAutomaticMarkup($data, $existingProduct);
    $data = $pricing['data'];

    $messageSuffix = '';
    if (!empty($pricing['rule']['name'])) {
        $messageSuffix = ' Adaos aplicat: ' . (string)$pricing['rule']['name'] . '. Pret final: ' . (string)$pricing['final_price'] . ' lei.';
    }

    if ($type === 'add') {
        $pdo = Database::getDB();
        $existing = import_find_existing_product($pdo, $data);
        if ($existing !== null) {
            produse_json([
                'success' => false,
                'message' => 'Produs deja existent in magazin (cod + brand).',
                'existing_id' => (int)$existing['id'],
                'existing_code' => (string)($existing['pCode'] ?? ''),
                'existing_brand' => (string)($existing['pBrand'] ?? ''),
            ], 409);
        }

        $id = $service->addProduse(import_apply_identity_to_row($data));
        $service->bustProductCountCache();
        $saved = $service->getIdProduses($id);
        if ($saved && isset($saved['id'])) {
            products_oem_sync_product(Database::getDB(), (int) $saved['id'], $saved, 'admin');
        }
        produse_json(['success' => true, 'message' => 'Produs adaugat cu succes.' . $messageSuffix, 'id' => $id]);
    }

    $id = (string)($input['id'] ?? '');
    if ($id === '') {
        produse_json(['success' => false, 'message' => 'ID lipsa pentru editare.'], 422);
    }
    $service->editProduse($id, $data);
    $saved = $service->getIdProduses($id);
    if ($saved && isset($saved['id'])) {
        products_oem_sync_product(Database::getDB(), (int) $saved['id'], $saved, 'admin');
    }
    produse_json(['success' => true, 'message' => 'Produs salvat cu succes.' . $messageSuffix, 'id' => $id]);
} catch (Throwable $e) {
    produse_json(['success' => false, 'message' => 'Eroare: ' . $e->getMessage()], 500);
}