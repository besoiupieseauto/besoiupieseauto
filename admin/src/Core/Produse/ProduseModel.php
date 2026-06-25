<?php
declare(strict_types=1);

namespace Evasystem\Core\Produse;

use Config\Database;
use PDO;

class ProduseModel
{
    private PDO $pdo;
    private string $table = 'produse';
    private array $columns = [
        'name', 'email', 'phone', 'status',
        'pName', 'pCar', 'pCode', 'pCodeNorm', 'pBrandNorm', 'pPrice', 'pBasePrice', 'pState', 'pCity', 'pNote', 'pNoteWebsite', 'pNoteMarketplace',
        'pShipping', 'pCurierLivrare', 'pWarranty', 'pReturn', 'pImages', 'pImageSource', 'pWhatsapp',
        'pSupplier', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pStock', 'pCategory', 'pSubcategory', 'pCompatibilitati', 'pOem',
        'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt', 'pBadge', 'pVitrina',
        'connect_id', 'id_users', 'randomn_id',
    ];

    public function __construct()
    {
        $this->pdo = Database::getDB();
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

        $meta = \Evasystem\Core\Pagination::normalize($page, $perPage);
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY id DESC LIMIT {$meta['limit']} OFFSET {$meta['offset']}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return \Evasystem\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
    }

    /** Coloane ușoare pentru lista admin — fără pNote/raw_json (HTML mare). */
    private const ADMIN_LIST_COLUMNS = [
        'id', 'randomn_id', 'status', 'pName', 'pCode', 'pBrand', 'pMarca', 'pModel',
        'pMotorizare', 'pCar', 'pCategory', 'pSubcategory', 'pPrice', 'pBasePrice',
        'pStock', 'pShipping', 'pCurierLivrare', 'pState', 'pCity', 'pImages', 'pImageSource', 'pSupplier',
        'pMarkupRuleName', 'pMarkupRuleId', 'pMarkupAppliedAt',
    ];

    /** Coloane pentru carduri catalog public — fără pNote/raw_json. */
    private const CATALOG_CARD_COLUMNS = [
        'id', 'randomn_id', 'status', 'pName', 'pCode', 'pOem', 'pBrand', 'pMarca', 'pCar',
        'pCategory', 'pSubcategory', 'pPrice', 'pImages', 'pBadge', 'pShipping', 'pCurierLivrare',
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
        return $this->sqlOnlineActive() . " AND (pImages IS NULL OR TRIM(pImages) = '' OR pImages IN ('[]', 'null'))";
    }

    public function countOnlineWithoutImage(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$this->sqlOnlineWithoutImage()}";

        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function paginatedAdminList(int $page = 1, int $perPage = 10, ?string $listFilter = null): array
    {
        $where = '';
        if ($listFilter === 'no_image') {
            $where = 'WHERE ' . $this->sqlOnlineWithoutImage();
            $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
            $total = (int) $this->pdo->query($countSql)->fetchColumn();
        } else {
            $total = $this->countAllCached();
        }

        $meta = \Evasystem\Core\Pagination::normalize($page, $perPage);
        $cols = '`' . implode('`, `', self::ADMIN_LIST_COLUMNS) . '`';
        $sql = "SELECT {$cols} FROM {$this->table} {$where} ORDER BY id DESC LIMIT {$meta['limit']} OFFSET {$meta['offset']}";
        $stmt = $this->pdo->query($sql);
        $items = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        return \Evasystem\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
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
        $this->bustCountCache();

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
        return $stmt->execute($data);
    }

    /** @return array<int, array<string, mixed>> */
    public function listVitrina(int $limit = 48): array
    {
        if (!$this->hasColumn('pVitrina')) {
            return [];
        }
        $limit = max(1, min(120, $limit));
        $sql = "SELECT * FROM {$this->table}
                WHERE status <> '0' AND pVitrina = 1
                ORDER BY id DESC
                LIMIT {$limit}";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

        $meta = \Evasystem\Core\Pagination::normalize($page, $perPage);
        $vitrinaCol = $this->hasColumn('pVitrina') ? ', pVitrina' : '';
        $badgeCol = $this->hasColumn('pBadge') ? ', pBadge' : '';
        $sql = "SELECT id, randomn_id, pName, pCode, pBrand, pPrice, pImages, pCategory, pSubcategory, status{$vitrinaCol}{$badgeCol}
                FROM {$this->table} {$where}
                ORDER BY " . ($this->hasColumn('pVitrina') ? 'pVitrina DESC, ' : '') . "id DESC
                LIMIT {$meta['limit']} OFFSET {$meta['offset']}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return \Evasystem\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
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
        $updated = 0;
        $failed = 0;
        foreach (array_values(array_unique(array_filter(array_map('strval', $ids)))) as $id) {
            if ($id === '') {
                continue;
            }
            if ($this->setCurierLivrare($id, $value)) {
                ++$updated;
            } else {
                ++$failed;
            }
        }

        return ['updated' => $updated, 'failed' => $failed];
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
        $sql = "UPDATE {$this->table}
                SET pCurierLivrare = :val
                WHERE status <> '0'
                  AND LOWER(TRIM(pCategory)) = LOWER(TRIM(:category))";
        $params = [
            'val' => $normalized,
            'category' => $category,
        ];

        $subcategory = $subcategory !== null ? trim($subcategory) : '';
        if ($subcategory !== '') {
            $sql .= " AND LOWER(TRIM(pSubcategory)) = LOWER(TRIM(:subcategory))";
            $params['subcategory'] = $subcategory;
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

        $meta = \Evasystem\Core\Pagination::normalize($page, $perPage);
        $sql = "SELECT id, randomn_id, pName, pCode, pBrand, pPrice, pImages, pCategory, pSubcategory, pVitrina, status
                FROM {$this->table} {$where}
                ORDER BY pVitrina DESC, id DESC
                LIMIT {$meta['limit']} OFFSET {$meta['offset']}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return \Evasystem\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
    }

    public function setVitrina(string $id, bool $enabled): bool
    {
        if (!$this->hasColumn('pVitrina')) {
            return false;
        }
        $value = $enabled ? 1 : 0;
        if ($this->isNumericIdentifier($id)) {
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET pVitrina = :v WHERE id = :id");
            return $stmt->execute(['v' => $value, 'id' => (int) $id]);
        }
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET pVitrina = :v WHERE randomn_id = :id");
        return $stmt->execute(['v' => $value, 'id' => $id]);
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
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM {$this->table} WHERE status <> '0' AND pVitrina = 1"
        )->fetchColumn();
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function paginatedVitrinaOnly(int $page = 1, int $perPage = 12, string $search = ''): array
    {
        if (!$this->hasColumn('pVitrina')) {
            return \Evasystem\Core\Pagination::envelope([], 0, $page, $perPage);
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

        $meta = \Evasystem\Core\Pagination::normalize($page, $perPage);
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY id DESC LIMIT {$meta['limit']} OFFSET {$meta['offset']}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return \Evasystem\Core\Pagination::envelope($items, $total, $meta['page'], $meta['per_page']);
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
            'product_facets_admin.json',
            'product_facets_public.json',
        ] as $file) {
            $path = $cacheDir . '/' . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function filter(array $data): array
    {
        $clean = [];
        foreach ($this->columns as $column) {
            if (array_key_exists($column, $data)) {
                $clean[$column] = is_string($data[$column]) ? trim($data[$column]) : $data[$column];
            }
        }
        return $clean;
    }

    private function isNumericIdentifier(string $id): bool
    {
        return $id !== '' && ctype_digit($id);
    }
}