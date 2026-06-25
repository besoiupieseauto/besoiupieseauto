<?php
declare(strict_types=1);

namespace Evasystem\Controllers\Produse;

use Evasystem\Core\Produse\ProduseModel;

class ProduseService
{
    private ProduseModel $model;

    public function __construct()
    {
        $this->model = new ProduseModel();
    }

    public function getProdusesAllid(): array
    {
        return $this->model->all($this->currentUserId());
    }

    public function getAllProduses(): array
    {
        return $this->model->all();
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,loaded:int,truncated:bool} */
    public function getCatalogProducts(int $limit = 500): array
    {
        return $this->model->listCatalogCards($limit);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function getProdusesPaginated(int $page = 1, int $perPage = 10, ?string $listFilter = null): array
    {
        return $this->model->paginatedAdminList($page, $perPage, $listFilter);
    }

    public function countOnlineWithoutImage(): int
    {
        return $this->model->countOnlineWithoutImage();
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function getProdusesPaginatedLegacy(int $page = 1, int $perPage = 10): array
    {
        return $this->model->paginated($page, $perPage);
    }

    public function getIdProduses($id): ?array
    {
        return $this->model->find((string)$id);
    }

    public function addProduse(array $data): string
    {
        $data['id_users'] = $data['id_users'] ?? $this->currentUserId();
        $data['connect_id'] = $data['connect_id'] ?? $this->currentUserId();
        return $this->model->create($data);
    }

    public function editProduse($id, array $data): bool
    {
        return $this->model->update((string)$id, $data);
    }

    public function deleteProduse($id): bool
    {
        return $this->model->delete((string)$id);
    }

    /**
     * @param array<int, string> $ids randomn_id sau id numeric
     * @return array{deleted:int,failed:int}
     */
    public function deleteProduseBulk(array $ids): array
    {
        $deleted = 0;
        $failed = 0;

        foreach (array_values(array_unique(array_filter(array_map('strval', $ids)))) as $id) {
            if ($id === '') {
                continue;
            }
            if ($this->deleteProduse($id)) {
                ++$deleted;
            } else {
                ++$failed;
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    /** @return array<int, array{id:int,randomn_id:string}> */
    public function listAllProductIdentifiers(): array
    {
        return $this->model->listIdentifiers();
    }

    public function bustProductCountCache(): void
    {
        $this->model->bustCountCache();
    }

    /** @return array<int, array<string, mixed>> */
    public function getVitrinaProducts(int $limit = 48): array
    {
        return $this->model->listVitrina($limit);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function getVitrinaPickerPaginated(int $page = 1, int $perPage = 20, string $search = ''): array
    {
        return $this->model->paginatedForVitrinaPicker($page, $perPage, $search);
    }

    public function setProductVitrina(string $id, bool $enabled): bool
    {
        return $this->model->setVitrina($id, $enabled);
    }

    public function countVitrinaProducts(): int
    {
        return $this->model->countVitrina();
    }

    /** @return array{vitrina_before:int,rows_affected:int} */
    public function clearAllVitrinaProducts(bool $clearRecomandatBadges = true): array
    {
        return $this->model->clearAllVitrina($clearRecomandatBadges);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function getVitrinaProductsPaginated(int $page = 1, int $perPage = 12, string $search = ''): array
    {
        return $this->model->paginatedVitrinaOnly($page, $perPage, $search);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function getVitrinaAdminPickerPaginated(int $page = 1, int $perPage = 20, string $search = ''): array
    {
        return $this->model->paginatedVitrinaAdminPicker($page, $perPage, $search);
    }

    public function setProductBadge(string $id, string $badge): bool
    {
        return $this->model->setBadge($id, $badge);
    }

    public function setProductCurierLivrare(string $id, string $value): bool
    {
        return $this->model->setCurierLivrare($id, $value);
    }

    /**
     * @param array<int, string> $ids
     * @return array{updated:int,failed:int}
     */
    public function setCurierLivrareBulk(array $ids, string $value): array
    {
        return $this->model->setCurierLivrareBulk($ids, $value);
    }

    /**
     * @return array{updated:int,failed:int}
     */
    public function setCurierLivrareByCategory(string $category, string $value, ?string $subcategory = null): array
    {
        return $this->model->setCurierLivrareByCategory($category, $value, $subcategory);
    }

    public function canAddToVitrina(string $id): bool
    {
        $max = $this->vitrinaHomepageMax();
        if ($this->countVitrinaProducts() < $max) {
            return true;
        }
        $product = $this->getIdProduses($id);
        return $product !== null && (int) ($product['pVitrina'] ?? 0) === 1;
    }

    public function vitrinaHomepageMax(): int
    {
        static $max = null;
        if ($max !== null) {
            return $max;
        }
        $path = dirname(__DIR__, 4) . '/system/home-vitrina-render.php';
        if (is_file($path)) {
            require_once $path;
        }
        $max = function_exists('besoiu_home_vitrina_limit') ? besoiu_home_vitrina_limit() : 10;

        return $max;
    }

    public function ensureProductForVitrina(string $id): ?string
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        if ($this->getIdProduses($id) !== null) {
            return $id;
        }
        if (!str_starts_with($id, 'epiesa_')) {
            return null;
        }

        $siteRoot = dirname(__DIR__, 4);
        $scraperHome = $siteRoot . '/system/scraper-home.php';
        if (!is_file($scraperHome)) {
            return null;
        }
        require_once $scraperHome;

        $scraperProduct = besoiu_scraper_find_by_id($id);
        if ($scraperProduct === null) {
            return null;
        }

        $mapped = besoiu_scraper_as_page_product($scraperProduct, $id);
        $this->addProduse([
            'randomn_id' => $id,
            'pName' => (string) ($mapped['pName'] ?? 'Produs'),
            'pCode' => (string) ($mapped['pCode'] ?? ''),
            'pBrand' => (string) ($mapped['pBrand'] ?? ''),
            'pCategory' => (string) ($mapped['pCategory'] ?? 'Uleiuri'),
            'pPrice' => (string) ($mapped['pPrice'] ?? ''),
            'pBasePrice' => (string) ($mapped['pPrice'] ?? ''),
            'pImages' => (string) ($mapped['pImages'] ?? '[]'),
            'pNote' => (string) ($mapped['pNote'] ?? ''),
            'pStock' => '1',
            'status' => 1,
        ]);

        return $id;
    }

    private function currentUserId(): int
    {
        return (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 126);
    }
}