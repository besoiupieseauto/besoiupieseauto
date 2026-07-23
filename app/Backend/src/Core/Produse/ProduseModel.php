<?php
declare(strict_types=1);

namespace Besoiu\Core\Produse;

use Besoiu\Services\AiIntelligence\ProductIndexHook;
use Config\Database;
use PDO;

class ProduseModel
{
    private PDO $pdo;
    private string $table = 'produse';
    private array $columns = [
        'name', 'email', 'phone', 'status',
        'pName', 'pNameMarketplace', 'pCar', 'pCode', 'pCodeNorm', 'pBrandNorm', 'pPrice', 'pBasePrice', 'pState', 'pCity', 'pNote', 'pNoteWebsite', 'pNoteMarketplace',
        'pShipping', 'pCurierLivrare', 'pWarranty', 'pReturn', 'pImages', 'pImageSource', 'pWhatsapp',
        'pSupplier', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pStock', 'pCategory', 'pSubcategory', 'pCompatibilitati', 'pOem',
        'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt', 'pBadge', 'pVitrina',
        'connect_id', 'id_users', 'randomn_id',
    ];

    public function __construct()
    {
        $this->pdo = Database::getDB();
        $dualTitle = dirname(__DIR__, 3) . '/Legacy/product_dual_title.php';
        if (is_file($dualTitle)) {
            require_once $dualTitle;
        }
        if (function_exists('besoiu_ensure_name_marketplace_columns')) {
            try {
                besoiu_ensure_name_marketplace_columns($this->pdo);
            } catch (\Throwable $e) {
                // schema optional until first import/edit
            }
        }
    }

    public function all(?int $userId = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        if ($userId) {
            $sql .= " WHERE id_users = :user_id OR connect_id = :user_id";
            $params['user_id'] = $userId;
        }
        $sql .= " ORDER BY id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function paginated(int $page = 1, int $perPage = 10, ?int $userId = null): array
    {
        $where = '';
        $params = [];
        if ($userId) {
            $where = 'WHERE id_users = :user_id OR connect_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $meta = \Besoiu\Core\Pagination::normalize($page, $perPage);
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY id DESC LIMIT {$meta['limit']} OFFSET {$meta['offset']}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return \Besoiu\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
    }

    /** Coloane ușoare pentru lista admin — fără pNote/raw_json (HTML mare). */
    private const ADMIN_LIST_COLUMNS = [
        'id', 'randomn_id', 'status', 'pName', 'pNameMarketplace', 'pCode', 'pBrand', 'pMarca', 'pModel',
        'pMotorizare', 'pCar', 'pCategory', 'pSubcategory', 'pPrice', 'pBasePrice',
        'pStock', 'pShipping', 'pCurierLivrare', 'pState', 'pCity', 'pImages', 'pImageSource', 'pSupplier',
        'pMarkupRuleName', 'pMarkupRuleId', 'pMarkupAppliedAt', 'pBadge',
    ];

    /** Coloane pentru carduri catalog public — fără pNote/raw_json. */
    private const CATALOG_CARD_COLUMNS = [
        'id', 'randomn_id', 'status', 'pName', 'pCode', 'pOem', 'pBrand', 'pMarca', 'pCar',
        'pCategory', 'pSubcategory', 'pPrice', 'pBasePrice', 'pImages', 'pBadge', 'pShipping',
        'pCurierLivrare', 'pStock', 'pNote',
    ];

    /** @return array{items:array<int,array<string,mixed>>,total:int,loaded:int,truncated:bool} */
    public function listCatalogCards(int $limit = 500): array
    {
        $limit = max(50, min(2000, $limit));
        $total = $this->countActiveCached();
        $cols = '`' . implode('`, `', self::CATALOG_CARD_COLUMNS) . '`';
        $sql = "SELECT {$cols} FROM {$this->table} WHERE status <> '0' ORDER BY id DESC LIMIT {$limit}";
        $stmt = $this->pdo->query($sql);
        $items = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        return [
            'items' => $items,
            'total' => $total,
            'loaded' => count($items),
            'truncated' => $total > count($items),
        ];
    }

    public function countActive(): int
    {
        return $this->countActiveCached();
    }

    private function countActiveCached(): int
    {
        $cacheFile = dirname(__DIR__, 2) . '/storage/cache/produse_active_count.json';
        if (is_file($cacheFile)) {
            $payload = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($payload) && (time() - (int) ($payload['at'] ?? 0)) < 120) {
                return (int) ($payload['count'] ?? 0);
            }
        }

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM {$this->table} WHERE status <> '0'")->fetchColumn();
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($cacheFile, json_encode(['at' => time(), 'count' => $count], JSON_UNESCAPED_UNICODE), LOCK_EX);

        return $count;
    }

    private function sqlOnlineActive(): string
    {
        return "(status IS NULL OR status <> '0')";
    }

    private function sqlOnlineWithoutImage(): string
    {
        $missingImage = $this->hasColumn('pImageState')
            ? "pImageState = 'missing'"
            : "(pImages IS NULL OR pImages = '' OR pImages IN ('[]', 'null'))";

        return $this->sqlOnlineActive() . " AND {$missingImage}";
    }

    public function countOnlineWithoutImage(): int
    {
        return $this->cachedCount(
            'produse_no_image_count.json',
            "SELECT COUNT(*) FROM {$this->table} WHERE {$this->sqlOnlineWithoutImage()}"
        );
    }

    /**
     * @param array<string,string> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int,catalog_total:int,page:int,per_page:int,total_pages:int}
     */
    public function paginatedAdminList(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        [$where, $params] = $this->buildAdminListWhere($filters);
        $catalogTotal = $this->countAllCached();
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $meta = \Besoiu\Core\Pagination::normalize($page, $perPage);
        $listCols = array_values(array_filter(
            self::ADMIN_LIST_COLUMNS,
            fn (string $column): bool => in_array($column, ['id', 'randomn_id', 'status'], true) || $this->hasColumn($column)
        ));
        $cols = '`' . implode('`, `', $listCols) . '`';
        $offset = (int) $meta['offset'];
        $orderBy = $this->buildAdminListOrder($filters);
        $sortKey = trim((string) ($filters['sort'] ?? ''));
        $useIdAnchor = $sortKey === '' && $offset > 500;
        $sql = "SELECT {$cols} FROM {$this->table} {$where} {$orderBy} LIMIT {$meta['limit']}";

        // Păstrează linkurile page=N, dar evită OFFSET pe rândurile late la pagini adânci (doar sortare implicită).
        if ($useIdAnchor) {
            $anchorStmt = $this->pdo->prepare(
                "SELECT id FROM {$this->table} {$where} ORDER BY id DESC LIMIT 1 OFFSET {$offset}"
            );
            $anchorStmt->execute($params);
            $anchorId = $anchorStmt->fetchColumn();
            if ($anchorId === false) {
                $items = [];
                $result = \Besoiu\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
                $result['catalog_total'] = $catalogTotal;
                return $result;
            }
            $sql = "SELECT {$cols} FROM {$this->table} {$where}"
                . ($where === '' ? ' WHERE' : ' AND')
                . " id <= :page_anchor ORDER BY id DESC LIMIT {$meta['limit']}";
            $params[':page_anchor'] = (int) $anchorId;
        } elseif ($offset > 0) {
            $sql .= " OFFSET {$offset}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $result = \Besoiu\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
        $result['catalog_total'] = $catalogTotal;
        return $result;
    }

    /** @param array<string,string> $filters @return array{0:string,1:array<string,mixed>} */
    private function buildAdminListWhere(array $filters): array
    {
        $clauses = [];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $clauses[] = '(pName LIKE :q_name OR pCode LIKE :q_code OR pOem LIKE :q_oem OR pCar LIKE :q_car)';
            foreach ([':q_name', ':q_code', ':q_oem', ':q_car'] as $placeholder) {
                $params[$placeholder] = '%' . $q . '%';
            }
        }

        $normalizedFilters = [
            'category' => ['pCategoryNorm', 'pCategory', false],
            'subcategory' => ['pSubcategoryNorm', 'pSubcategory', false],
            'marca' => ['pMarcaNorm', 'pMarca', false],
            'brand' => ['pBrandFilterNorm', 'pBrand', false],
            'supplier' => ['pSupplierNorm', 'pSupplier', true],
        ];
        foreach ($normalizedFilters as $key => [$normalizedColumn, $legacyColumn, $uppercase]) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $column = $this->hasColumn($normalizedColumn) ? $normalizedColumn : $legacyColumn;
            $clauses[] = "{$column} = :{$key}";
            $params[":{$key}"] = $uppercase ? mb_strtoupper($value, 'UTF-8') : mb_strtolower($value, 'UTF-8');
        }

        $origin = trim((string) ($filters['origin'] ?? ''));
        if ($origin === 'site') {
            $clauses[] = "(pSupplier IS NULL OR TRIM(pSupplier) = '')";
        } elseif ($origin === 'export') {
            $clauses[] = "(pSupplier IS NOT NULL AND TRIM(pSupplier) <> '')";
        }

        $image = trim((string) ($filters['image'] ?? ''));
        if ($image === 'missing') {
            $clauses[] = '(' . $this->sqlOnlineWithoutImage() . ')';
        } elseif ($image === 'present') {
            $hasImage = $this->hasColumn('pImageState')
                ? "pImageState <> 'missing'"
                : "(pImages IS NOT NULL AND TRIM(pImages) <> '' AND pImages NOT IN ('[]', 'null'))";
            $clauses[] = '(' . $hasImage . ')';
        } elseif ($image !== '') {
            $clauses[] = 'pImageSource = :image';
            $params[':image'] = $image;
        }

        $markup = trim((string) ($filters['markup'] ?? ''));
        if ($markup === 'with-rule') {
            $clauses[] = $this->hasColumn('pMarkupState') ? "pMarkupState = 'with_rule'" : "(pMarkupRuleName IS NOT NULL AND pMarkupRuleName <> '')";
        } elseif ($markup === 'without-rule') {
            $clauses[] = $this->hasColumn('pMarkupState') ? "pMarkupState = 'without_rule'" : "(pMarkupRuleName IS NULL OR pMarkupRuleName = '')";
        } elseif ($markup !== '') {
            $markupColumn = $this->hasColumn('pMarkupRuleNorm') ? 'pMarkupRuleNorm' : 'pMarkupRuleName';
            $clauses[] = "{$markupColumn} = :markup_rule";
            $params[':markup_rule'] = $this->hasColumn('pMarkupRuleNorm')
                ? mb_strtolower($markup, 'UTF-8')
                : $markup;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'active') {
            $clauses[] = $this->sqlOnlineActive();
        } elseif ($status === 'inactive') {
            $clauses[] = "status = '0'";
        }

        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    /** @param array<string,string> $filters */
    private function buildAdminListOrder(array $filters): string
    {
        $sort = trim((string) ($filters['sort'] ?? ''));
        $brandColumn = $this->hasColumn('pBrandFilterNorm') ? 'pBrandFilterNorm' : 'pBrand';

        return match ($sort) {
            'brand-asc' => "ORDER BY {$brandColumn} ASC, id DESC",
            'brand-desc' => "ORDER BY {$brandColumn} DESC, id DESC",
            default => 'ORDER BY id DESC',
        };
    }

    /** @param array<string,string> $filters */
    public function countAdminList(array $filters = []): int
    {
        [$where, $params] = $this->buildAdminListWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string,string> $filters
     * @return array{all:int,site:int,export:int}
     */
    public function countAdminListByOrigin(array $filters = []): array
    {
        $baseFilters = $filters;
        unset($baseFilters['origin']);
        $all = $this->countAdminList($baseFilters);
        $activeOrigin = trim((string) ($filters['origin'] ?? ''));

        if ($activeOrigin === 'site') {
            return ['all' => $all, 'site' => $all, 'export' => 0];
        }
        if ($activeOrigin === 'export') {
            return ['all' => $all, 'site' => 0, 'export' => $all];
        }

        return [
            'all' => $all,
            'site' => $this->countAdminList($baseFilters + ['origin' => 'site']),
            'export' => $this->countAdminList($baseFilters + ['origin' => 'export']),
        ];
    }

    /**
     * @param array<string,string> $filters
     * @return array{ids:array<int,string>,last_id:?int}
     */
    public function listRandomIdsByFilters(array $filters, int $limit = 200, ?int $beforeId = null): array
    {
        $limit = max(1, min(50000, $limit));
        [$where, $params] = $this->buildAdminListWhere($filters);
        if ($beforeId !== null && $beforeId > 0) {
            $where .= $where === '' ? ' WHERE id < :cursor_id' : ' AND id < :cursor_id';
            $params[':cursor_id'] = $beforeId;
        }
        $sql = "SELECT id, randomn_id FROM {$this->table} {$where} ORDER BY id DESC LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $ids = [];
        $lastId = null;
        foreach ($rows as $row) {
            $randomId = trim((string) ($row['randomn_id'] ?? ''));
            if ($randomId === '') {
                continue;
            }
            $ids[] = $randomId;
            $rowId = (int) ($row['id'] ?? 0);
            if ($lastId === null || $rowId < $lastId) {
                $lastId = $rowId;
            }
        }

        return ['ids' => $ids, 'last_id' => $lastId];
    }

    /**
     * @param array<string,string> $filters
     * @return array{updated:int,failed:int}
     */
    public function updateFieldByFilters(array $filters, array $data): array
    {
        $data = $this->filter($data);
        unset($data['randomn_id']);
        if ($data === []) {
            return ['updated' => 0, 'failed' => 0];
        }

        [$where, $params] = $this->buildAdminListWhere($filters);
        $set = [];
        foreach ($data as $field => $value) {
            $placeholder = ":set_{$field}";
            $set[] = "`{$field}` = {$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . ' ' . $where;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ['updated' => $stmt->rowCount(), 'failed' => 0];
    }

    private function countAllCached(): int
    {
        $cacheFile = dirname(__DIR__, 2) . '/storage/cache/produse_count.json';
        if (is_file($cacheFile)) {
            $payload = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($payload) && (time() - (int) ($payload['at'] ?? 0)) < 120) {
                return (int) ($payload['count'] ?? 0);
            }
        }

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($cacheFile, json_encode(['at' => time(), 'count' => $count], JSON_UNESCAPED_UNICODE), LOCK_EX);

        return $count;
    }

    /** @return array<int, array{id:int,randomn_id:string}> */
    public function listIdentifiers(): array
    {
        $stmt = $this->pdo->query('SELECT id, randomn_id FROM ' . $this->table . ' ORDER BY id DESC');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    /**
     * Încarcă într-o singură interogare produsele indicate prin id numeric sau randomn_id.
     *
     * @param array<int, string> $ids
     * @param array<int, string> $columns
     * @return array<int, array<string, mixed>>
     */
    public function findMany(array $ids, array $columns = []): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id): string => trim((string) $id),
            $ids
        ))));
        if ($ids === []) {
            return [];
        }

        $allowed = array_values(array_unique(array_merge(['id', 'randomn_id'], $this->columns)));
        $columns = $columns === [] ? $allowed : array_values(array_intersect($columns, $allowed));
        foreach (['id', 'randomn_id'] as $required) {
            if (!in_array($required, $columns, true)) {
                $columns[] = $required;
            }
        }

        $rows = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            [$where, $params] = $this->identifierWhere($chunk);
            if ($where === '') {
                continue;
            }
            $sql = 'SELECT `' . implode('`,`', $columns) . "` FROM {$this->table} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            array_push($rows, ...($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []));
        }

        return $rows;
    }

    /**
     * Actualizare set-based pentru identificatori publici/numerici.
     *
     * @param array<int, string> $ids
     * @return array{updated:int,failed:int}
     */
    public function updateMany(array $ids, array $data): array
    {
        $data = $this->filter($data);
        unset($data['randomn_id']);
        $products = $this->findMany($ids, ['id']);
        $numericIds = array_values(array_unique(array_map('intval', array_column($products, 'id'))));
        if ($numericIds === [] || $data === []) {
            return ['updated' => $data === [] ? count($numericIds) : 0, 'failed' => max(0, count(array_unique($ids)) - count($numericIds))];
        }

        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            foreach (array_chunk($numericIds, 400) as $chunk) {
                $set = [];
                $params = [];
                foreach ($data as $field => $value) {
                    $set[] = "`{$field}` = ?";
                    $params[] = $value;
                }
                $params = array_merge($params, $chunk);
                $stmt = $this->pdo->prepare(
                    "UPDATE {$this->table} SET " . implode(', ', $set)
                    . ' WHERE id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
                );
                $stmt->execute($params);
            }
            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'updated' => count($numericIds),
            'failed' => max(0, count(array_unique($ids)) - count($numericIds)),
        ];
    }

    /**
     * Ștergere set-based, inclusiv referințele OEM, în aceeași tranzacție.
     *
     * @param array<int, string> $ids
     * @return array{deleted:int,failed:int}
     */
    public function deleteMany(array $ids, bool $all = false): array
    {
        $requested = array_values(array_unique(array_filter(array_map('strval', $ids))));
        $numericIds = $all
            ? []
            : array_values(array_unique(array_map('intval', array_column($this->findMany($requested, ['id']), 'id'))));
        $deleted = $all ? (int) $this->pdo->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn() : count($numericIds);
        if (!$all && $numericIds === []) {
            return ['deleted' => 0, 'failed' => count($requested)];
        }

        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            if ($this->productsOemTableExists()) {
                if ($all) {
                    $this->pdo->exec('DELETE FROM products_oem');
                } else {
                    $this->deleteIdsFromTable('products_oem', 'product_id', $numericIds);
                }
            }
            if ($all) {
                $this->pdo->exec("DELETE FROM {$this->table}");
            } else {
                $this->deleteIdsFromTable($this->table, 'id', $numericIds);
            }
            if ($startedTransaction) {
                $this->pdo->commit();
            }
            $this->bustCountCache();
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return ['deleted' => $deleted, 'failed' => $all ? 0 : max(0, count($requested) - $deleted)];
    }

    /**
     * Flux keyset cu numai coloanele necesare calculelor de adaos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function markupChunk(int $afterId = 0, int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));
        $columns = [
            'id', 'randomn_id', 'pName', 'pCategory', 'pSubcategory', 'pBrand', 'pMarca',
            'pBasePrice', 'pPrice', 'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt',
        ];
        $stmt = $this->pdo->prepare(
            'SELECT `' . implode('`,`', $columns) . "` FROM {$this->table}
             WHERE id > :after_id ORDER BY id ASC LIMIT {$limit}"
        );
        $stmt->execute(['after_id' => max(0, $afterId)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Actualizează mai multe rânduri prin CASE, într-o singură instrucțiune per chunk.
     *
     * @param array<int, array<string, mixed>> $rows fiecare rând conține id
     */
    public function updateMarkupRows(array $rows): int
    {
        $fields = ['pBasePrice', 'pPrice', 'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt'];
        $updated = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $sets = [];
            $params = [];
            foreach ($fields as $field) {
                $case = "`{$field}` = CASE id";
                foreach ($chunk as $row) {
                    $case .= ' WHEN ? THEN ?';
                    $params[] = (int) ($row['id'] ?? 0);
                    $params[] = $row[$field] ?? null;
                }
                $sets[] = $case . " ELSE `{$field}` END";
            }
            $ids = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $chunk);
            $params = array_merge($params, $ids);
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET " . implode(', ', $sets)
                . ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
            );
            $stmt->execute($params);
            $updated += count($ids);
        }

        return $updated;
    }

    public function find(string $id): ?array
    {
        if ($this->isNumericIdentifier($id)) {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => (int)$id]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE randomn_id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Checkout site — doar randomn_id public (hex 16), fără id numeric / idprodus ERP.
     */
    public function findByStorefrontPublicId(string $publicId): ?array
    {
        $publicId = strtolower(trim($publicId));
        if ($publicId === '' || !preg_match('/^[a-f0-9]{16}$/', $publicId)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE randomn_id = :id AND status <> '0' LIMIT 1"
        );
        $stmt->execute(['id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function create(array $data): string
    {
        $data = $this->filter($data);
        if (empty($data['randomn_id'])) {
            $data['randomn_id'] = bin2hex(random_bytes(8));
        }
        if (!array_key_exists('status', $data) || $data['status'] === '') {
            $data['status'] = 1;
        }

        $fields = array_keys($data);
        $sql = "INSERT INTO {$this->table} (`" . implode('`,`', $fields) . "`) VALUES (:" . implode(',:', $fields) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        $insertId = (int) $this->pdo->lastInsertId();
        $this->bustCountCache();
        if ($insertId > 0) {
            ProductIndexHook::afterSave($insertId, (string) $data['randomn_id']);
        }

        return (string)$data['randomn_id'];
    }

    public function update(string $id, array $data): bool
    {
        $data = $this->filter($data);
        unset($data['randomn_id']);
        if (!$data) {
            return true;
        }

        $sets = [];
        foreach ($data as $field => $_) {
            $sets[] = "`{$field}` = :{$field}";
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        if ($this->isNumericIdentifier($id)) {
            $data['row_id'] = (int)$id;
            $sql .= " WHERE id = :row_id";
        } else {
            $data['row_id'] = $id;
            $sql .= " WHERE randomn_id = :row_id";
        }
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute($data);
        if ($ok) {
            $this->bustCountCache();
            if ($this->isNumericIdentifier($id)) {
                ProductIndexHook::afterSave((int) $id, null);
            } else {
                ProductIndexHook::afterSave(null, $id);
            }
        }
        return $ok;
    }

    /** @return array<int, array<string, mixed>> */
    public function listVitrina(int $limit = 48): array
    {
        if (!$this->hasColumn('pVitrina')) {
            return [];
        }
        $limit = max(1, min(120, $limit));
        $cacheFile = $this->cachePath("produse_vitrina_{$limit}.json");
        $cached = $this->readCache($cacheFile, 120);
        if (is_array($cached) && isset($cached['items']) && is_array($cached['items'])) {
            return $cached['items'];
        }
        $sql = "SELECT * FROM {$this->table}
                WHERE status <> '0' AND pVitrina = 1
                ORDER BY id DESC
                LIMIT {$limit}";
        $stmt = $this->pdo->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->writeCache($cacheFile, ['items' => $items]);
        return $items;
    }

    /** SQL: consumabile eligibile vitrină homepage (ulei, lichid, electrice). */
    private function consumableVitrinaSql(): string
    {
        return "(
            LOWER(pName) LIKE '%ulei motor%'
            OR LOWER(pName) LIKE '%ulei cutie%'
            OR LOWER(pName) LIKE '%ulei transmisie%'
            OR LOWER(pName) LIKE '%lubrifiant%'
            OR LOWER(pName) LIKE '%antigel%'
            OR LOWER(pName) LIKE '%adblue%'
            OR LOWER(pName) LIKE '%lichid de fran%'
            OR LOWER(pName) LIKE '%lichid fran%'
            OR LOWER(pName) LIKE '%lichid parbriz%'
            OR LOWER(pName) LIKE '%lichid stergator%'
            OR LOWER(pName) LIKE '%lichid ștergător%'
            OR LOWER(pName) LIKE '%lichid racire%'
            OR LOWER(pName) LIKE '%lichid răcire%'
            OR LOWER(pName) LIKE '%dot 3%'
            OR LOWER(pName) LIKE '%dot 4%'
            OR LOWER(pName) LIKE '%coolant%'
            OR LOWER(pName) LIKE '%vaselin%'
            OR LOWER(pName) LIKE '% bec %'
            OR LOWER(pName) LIKE 'bec %'
            OR LOWER(pName) LIKE '%becuri%'
            OR LOWER(pName) LIKE '%baterie auto%'
            OR LOWER(pName) LIKE '%baterie %'
            OR LOWER(pName) LIKE '%acumulator auto%'
            OR LOWER(pName) LIKE '%acumulator %'
            OR LOWER(pName) LIKE '%siguranta auto%'
            OR LOWER(pName) LIKE '%siguranță auto%'
            OR LOWER(pName) LIKE '%set sigurante%'
            OR LOWER(pName) LIKE '%cutie sigurante%'
            OR LOWER(pCategory) LIKE '%ulei%'
            OR LOWER(pCategory) LIKE '%lichid%'
            OR LOWER(pCategory) LIKE '%electrice%'
        )";
    }

    /** SQL: exclude piese mecanice (cuzineti, frane etc.). */
    private function mechanicalPartExcludeSql(): string
    {
        return "LOWER(pName) NOT LIKE '%cuzinet%'
            AND LOWER(pName) NOT LIKE '%biela%'
            AND LOWER(pName) NOT LIKE '%lagar%'
            AND LOWER(pName) NOT LIKE '%lagăr%'
            AND LOWER(pName) NOT LIKE '%disc fran%'
            AND LOWER(pName) NOT LIKE '%sabot fran%'
            AND LOWER(pName) NOT LIKE '%placa fran%'
            AND LOWER(pName) NOT LIKE '%placute fran%'
            AND LOWER(pName) NOT LIKE '%cilindru fran%'
            AND LOWER(pName) NOT LIKE '%ambreiaj%'
            AND LOWER(pName) NOT LIKE '%piston%'
            AND LOWER(pName) NOT LIKE '%segment%'
            AND LOWER(pName) NOT LIKE '%filtru ulei%'
            AND LOWER(pName) NOT LIKE '%filtru %'
            AND LOWER(pName) NOT LIKE '%radiator motor%'
            AND LOWER(pName) NOT LIKE '%lamela stergator%'
            AND LOWER(pName) NOT LIKE '%conector furtun%'
            AND LOWER(pName) NOT LIKE '%bujie%'
            AND LOWER(pName) NOT LIKE '%papuc%'
            AND LOWER(pName) NOT LIKE '%alternator%'
            AND LOWER(pName) NOT LIKE '%demaror%'
            AND LOWER(pName) NOT LIKE '%releu%'
            AND LOWER(pName) NOT LIKE '%lampa%'
            AND LOWER(pSubcategory) NOT LIKE '%cuzinet%'
            AND LOWER(pSubcategory) NOT LIKE '%biela%'
            AND LOWER(pSubcategory) NOT LIKE '%fran%'";
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function paginatedVitrinaAdminPicker(int $page = 1, int $perPage = 20, string $search = ''): array
    {
        $where = "WHERE status <> '0' AND {$this->consumableVitrinaSql()} AND {$this->mechanicalPartExcludeSql()}";
        $params = [];
        $search = trim($search);
        if ($search !== '') {
            $where .= ' AND (pName LIKE :q OR pCode LIKE :q OR pBrand LIKE :q OR pCategory LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $meta = \Besoiu\Core\Pagination::normalize($page, $perPage);
        $vitrinaCol = $this->hasColumn('pVitrina') ? ', pVitrina' : '';
        $badgeCol = $this->hasColumn('pBadge') ? ', pBadge' : '';
        $sql = "SELECT id, randomn_id, pName, pCode, pBrand, pPrice, pImages, pCategory, pSubcategory, status{$vitrinaCol}{$badgeCol}
                FROM {$this->table} {$where}
                ORDER BY " . ($this->hasColumn('pVitrina') ? 'pVitrina DESC, ' : '') . "id DESC
                LIMIT {$meta['limit']} OFFSET {$meta['offset']}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return \Besoiu\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
    }

    public function setBadge(string $id, string $badge): bool
    {
        if (!$this->hasColumn('pBadge')) {
            return false;
        }
        if ($this->isNumericIdentifier($id)) {
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET pBadge = :badge WHERE id = :id");
            return $stmt->execute(['badge' => $badge, 'id' => (int) $id]);
        }
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET pBadge = :badge WHERE randomn_id = :id");
        return $stmt->execute(['badge' => $badge, 'id' => $id]);
    }

    public function setBadgeOnAllActive(string $badge): int
    {
        if (!$this->hasColumn('pBadge')) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET pBadge = :badge WHERE COALESCE(status, 1) <> 0"
        );
        $stmt->execute(['badge' => $badge]);

        return (int) $stmt->rowCount();
    }

    public function setCurierLivrare(string $id, string $value): bool
    {
        if (!$this->hasColumn('pCurierLivrare')) {
            return false;
        }
        $normalized = $this->normalizeCurierLivrare($value);
        if ($this->isNumericIdentifier($id)) {
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET pCurierLivrare = :val WHERE id = :id");
            return $stmt->execute(['val' => $normalized, 'id' => (int) $id]);
        }
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET pCurierLivrare = :val WHERE randomn_id = :id");
        return $stmt->execute(['val' => $normalized, 'id' => $id]);
    }

    /**
     * @param array<int, string> $ids
     * @return array{updated:int,failed:int}
     */
    public function setCurierLivrareBulk(array $ids, string $value): array
    {
        return $this->updateMany($ids, [
            'pCurierLivrare' => $this->normalizeCurierLivrare($value),
        ]);
    }

    /**
     * @return array{updated:int,failed:int}
     */
    public function setCurierLivrareByCategory(string $category, string $value, ?string $subcategory = null): array
    {
        if (!$this->hasColumn('pCurierLivrare')) {
            return ['updated' => 0, 'failed' => 0];
        }

        $category = trim($category);
        if ($category === '') {
            return ['updated' => 0, 'failed' => 0];
        }

        $normalized = $this->normalizeCurierLivrare($value);
        $categoryColumn = $this->hasColumn('pCategoryNorm') ? 'pCategoryNorm' : 'pCategory';
        $sql = "UPDATE {$this->table}
                SET pCurierLivrare = :val
                WHERE status <> '0'
                  AND {$categoryColumn} = :category";
        $params = [
            'val' => $normalized,
            'category' => $this->hasColumn('pCategoryNorm') ? mb_strtolower($category, 'UTF-8') : $category,
        ];

        $subcategory = $subcategory !== null ? trim($subcategory) : '';
        if ($subcategory !== '') {
            $subcategoryColumn = $this->hasColumn('pSubcategoryNorm') ? 'pSubcategoryNorm' : 'pSubcategory';
            $sql .= " AND {$subcategoryColumn} = :subcategory";
            $params['subcategory'] = $this->hasColumn('pSubcategoryNorm')
                ? mb_strtolower($subcategory, 'UTF-8')
                : $subcategory;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ['updated' => (int) $stmt->rowCount(), 'failed' => 0];
    }

    private function normalizeCurierLivrare(string $value): string
    {
        $value = trim($value);
        return $value === 'Nu' ? 'Nu' : 'Da';
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function paginatedForVitrinaPicker(int $page = 1, int $perPage = 20, string $search = ''): array
    {
        $where = "WHERE status <> '0'";
        $params = [];
        $search = trim($search);
        if ($search !== '') {
            $where .= ' AND (pName LIKE :q OR pCode LIKE :q OR pBrand LIKE :q OR pCategory LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $meta = \Besoiu\Core\Pagination::normalize($page, $perPage);
        $sql = "SELECT id, randomn_id, pName, pCode, pBrand, pPrice, pImages, pCategory, pSubcategory, pVitrina, status
                FROM {$this->table} {$where}
                ORDER BY pVitrina DESC, id DESC
                LIMIT {$meta['limit']} OFFSET {$meta['offset']}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return \Besoiu\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
    }

    public function setVitrina(string $id, bool $enabled): bool
    {
        if (!$this->hasColumn('pVitrina')) {
            return false;
        }
        $value = $enabled ? 1 : 0;
        if ($this->isNumericIdentifier($id)) {
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET pVitrina = :v WHERE id = :id");
            $ok = $stmt->execute(['v' => $value, 'id' => (int) $id]);
            if ($ok) {
                $this->bustCountCache();
            }
            return $ok;
        }
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET pVitrina = :v WHERE randomn_id = :id");
        $ok = $stmt->execute(['v' => $value, 'id' => $id]);
        if ($ok) {
            $this->bustCountCache();
        }
        return $ok;
    }

    /**
     * Scoate toate produsele de pe vitrină; opțional elimină badge-ul RECOMANDAT.
     *
     * @return array{vitrina_before:int,rows_affected:int}
     */
    public function clearAllVitrina(bool $clearRecomandatBadges = true): array
    {
        if (!$this->hasColumn('pVitrina')) {
            return ['vitrina_before' => 0, 'rows_affected' => 0];
        }

        $vitrinaBefore = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE status <> '0' AND pVitrina = 1"
        )->fetchColumn();

        $setBadge = $clearRecomandatBadges && $this->hasColumn('pBadge')
            ? ", pBadge = CASE WHEN pBadge = 'recomandat' THEN '' ELSE pBadge END"
            : '';
        $badgeWhere = $clearRecomandatBadges && $this->hasColumn('pBadge')
            ? ' OR pBadge = \'recomandat\''
            : '';

        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET pVitrina = 0{$setBadge}
             WHERE status <> '0' AND (pVitrina = 1{$badgeWhere})"
        );
        $stmt->execute();
        $this->bustCountCache();

        return [
            'vitrina_before' => $vitrinaBefore,
            'rows_affected' => $stmt->rowCount(),
        ];
    }

    public function countVitrina(): int
    {
        if (!$this->hasColumn('pVitrina')) {
            return 0;
        }
        return $this->cachedCount(
            'produse_vitrina_count.json',
            "SELECT COUNT(*) FROM {$this->table} WHERE status <> '0' AND pVitrina = 1"
        );
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function paginatedVitrinaOnly(int $page = 1, int $perPage = 12, string $search = ''): array
    {
        if (!$this->hasColumn('pVitrina')) {
            return \Besoiu\Core\Pagination::envelope([], 0, $page, $perPage);
        }

        $where = "WHERE status <> '0' AND pVitrina = 1";
        $params = [];
        $search = trim($search);
        if ($search !== '') {
            $where .= ' AND (pName LIKE :q OR pCode LIKE :q OR pBrand LIKE :q OR pCategory LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $meta = \Besoiu\Core\Pagination::normalize($page, $perPage);
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY id DESC LIMIT {$meta['limit']} OFFSET {$meta['offset']}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return \Besoiu\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
    }

    private function hasColumn(string $column): bool
    {
        static $cache = [];
        if (isset($cache[$column])) {
            return $cache[$column];
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$this->table, $column]);
        $cache[$column] = (int) $stmt->fetchColumn() > 0;
        return $cache[$column];
    }

    /** @param array<int, string> $ids @return array{0:string,1:array<int,mixed>} */
    private function identifierWhere(array $ids): array
    {
        $numeric = [];
        $public = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            if ($this->isNumericIdentifier($id)) {
                $numeric[] = (int) $id;
            } else {
                $public[] = $id;
            }
        }
        $clauses = [];
        $params = [];
        if ($numeric !== []) {
            $clauses[] = 'id IN (' . implode(',', array_fill(0, count($numeric), '?')) . ')';
            $params = array_merge($params, $numeric);
        }
        if ($public !== []) {
            $clauses[] = 'randomn_id IN (' . implode(',', array_fill(0, count($public), '?')) . ')';
            $params = array_merge($params, $public);
        }

        return [$clauses !== [] ? '(' . implode(' OR ', $clauses) . ')' : '', $params];
    }

    /** @param array<int, int> $ids */
    private function deleteIdsFromTable(string $table, string $column, array $ids): void
    {
        foreach (array_chunk($ids, 400) as $chunk) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM `{$table}` WHERE `{$column}` IN ("
                . implode(',', array_fill(0, count($chunk), '?')) . ')'
            );
            $stmt->execute($chunk);
        }
    }

    private function productsOemTableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute(['products_oem']);
        $exists = (int) $stmt->fetchColumn() > 0;

        return $exists;
    }

    public function delete(string $id): bool
    {
        if ($this->isNumericIdentifier($id)) {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
            $stmt->execute(['id' => (int)$id]);
            if ($stmt->rowCount() > 0) {
                $this->bustCountCache();

                return true;
            }
        }

        if ($id !== '') {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE randomn_id = :id");
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() > 0) {
                $this->bustCountCache();

                return true;
            }
        }

        return false;
    }

    public function bustCountCache(): void
    {
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        foreach ([
            'produse_count.json',
            'produse_active_count.json',
            'produse_no_image_count.json',
            'produse_vitrina_count.json',
            'product_facets_admin.json',
            'product_facets_public.json',
        ] as $file) {
            $path = $cacheDir . '/' . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        foreach (glob($cacheDir . '/produse_vitrina_*.json') ?: [] as $path) {
            @unlink($path);
        }
    }

    private function cachedCount(string $file, string $sql, int $ttl = 120): int
    {
        $path = $this->cachePath($file);
        $cached = $this->readCache($path, $ttl);
        if (is_array($cached) && array_key_exists('count', $cached)) {
            return (int) $cached['count'];
        }
        $count = (int) $this->pdo->query($sql)->fetchColumn();
        $this->writeCache($path, ['count' => $count]);
        return $count;
    }

    private function cachePath(string $file): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/' . $file;
    }

    private function readCache(string $path, int $ttl): ?array
    {
        if (!is_file($path) || (time() - (int) filemtime($path)) >= $ttl) {
            return null;
        }
        $value = json_decode((string) file_get_contents($path), true);
        return is_array($value) ? $value : null;
    }

    /** @param array<string,mixed> $value */
    private function writeCache(string $path, array $value): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($value, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function filter(array $data): array
    {
        $clean = [];
        foreach ($this->columns as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            if ($column === 'pNameMarketplace' && !$this->hasColumn('pNameMarketplace')) {
                continue;
            }
            $clean[$column] = is_string($data[$column]) ? trim($data[$column]) : $data[$column];
        }
        return $clean;
    }

    private function isNumericIdentifier(string $id): bool
    {
        return $id !== '' && ctype_digit($id);
    }
}