<?php
declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/EpiesaCategories.php';
require_once __DIR__ . '/EpiesaImageCache.php';

final class EpiesaCatalog
{
  private static bool $bootstrapping = false;

  /** @return array<string, mixed> */
  public static function load(): array
  {
    $path = ScraperPaths::catalogJsonPath();
    if (!is_file($path)) {
      if (!self::$bootstrapping) {
        self::$bootstrapping = true;
        self::bootstrapFromLatest();
        self::$bootstrapping = false;
      }

      return is_file($path)
        ? (json_decode((string) file_get_contents($path), true) ?: self::emptyCatalog())
        : self::emptyCatalog();
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
      return self::emptyCatalog();
    }

    if (!isset($data['products']) || !is_array($data['products'])) {
      $data['products'] = [];
    }

    return $data;
  }

  public static function save(array $catalog): void
  {
    ScraperPaths::ensureDirs();
    $catalog['updated_at'] = date('c');
    $catalog['product_count'] = count($catalog['products'] ?? []);

    file_put_contents(
      ScraperPaths::catalogJsonPath(),
      json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
      LOCK_EX
    );
    self::$productsRawCache = null;
  }

  /** @param array<int, array<string, mixed>> $products */
  public static function mergeScan(
    array $products,
    string $sourceUrl,
    string $categorySlug,
    string $categoryLabel
  ): int {
    $catalog = self::load();
    $existing = [];
    foreach ($catalog['products'] as $row) {
      if (!is_array($row)) {
        continue;
      }
      $key = self::productKey($row);
      if ($key !== '') {
        $existing[$key] = $row;
      }
    }

    $added = 0;
    $now = date('c');
    foreach ($products as $product) {
      if (!is_array($product)) {
        continue;
      }
      $key = self::productKey($product);
      if ($key === '') {
        continue;
      }

      $row = array_merge($product, [
        'category_slug'  => $categorySlug,
        'category_label' => $categoryLabel,
        'source_url'     => $sourceUrl,
        'scraped_at'     => $now,
        'visible'        => true,
      ]);
      $row = EpiesaImageCache::ensureCached($row);

      if (!isset($existing[$key])) {
        $added++;
      }
      $existing[$key] = $row;
    }

    $catalog['products'] = array_values($existing);
    $catalog['last_scan'] = [
      'at'             => $now,
      'source_url'     => $sourceUrl,
      'category_slug'  => $categorySlug,
      'category_label' => $categoryLabel,
      'fetched'        => count($products),
      'added'          => $added,
      'total'          => count($catalog['products']),
    ];

    self::save($catalog);

    return $added;
  }

  /** Număr rapid — fără mapare imagini pe fiecare produs. */
  public static function productCount(): int
  {
    $path = ScraperPaths::catalogJsonPath();
    if (!is_file($path)) {
      $catalog = self::load();

      return (int) ($catalog['product_count'] ?? count($catalog['products'] ?? []));
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
      return 0;
    }

    if (preg_match('/"product_count"\s*:\s*(\d+)/', $raw, $m)) {
      return (int) $m[1];
    }

    $data = json_decode($raw, true);

    return is_array($data)
      ? (int) ($data['product_count'] ?? count($data['products'] ?? []))
      : 0;
  }

  /** @var array<int, array<string, mixed>>|null */
  private static ?array $productsRawCache = null;

  /** @return array<int, array<string, mixed>> */
  private static function productsRaw(): array
  {
    if (self::$productsRawCache !== null) {
      return self::$productsRawCache;
    }

    $catalog = self::load();
    self::$productsRawCache = array_values(array_filter(
      is_array($catalog['products'] ?? null) ? $catalog['products'] : [],
      static fn($row): bool => is_array($row) && ($row['visible'] ?? true) !== false
    ));

    return self::$productsRawCache;
  }

  /** @return array<string, int> */
  public static function categorySlugCounts(): array
  {
    $counts = ['toate' => 0];
    foreach (self::productsRaw() as $row) {
      $counts['toate']++;
      $slug = (string) ($row['category_slug'] ?? 'altele');
      $counts[$slug] = ($counts[$slug] ?? 0) + 1;
    }

    return $counts;
  }

  /**
   * Listă rapidă — fără mapare imagini pe fiecare rând (opțional).
   *
   * @return array<int, array<string, mixed>>
   */
  public static function listProductsLite(
    ?string $categorySlug = null,
    int $limit = 0,
    bool $withImages = false
  ): array {
    $rows = self::productsRaw();

    if ($categorySlug !== null && $categorySlug !== '' && $categorySlug !== 'toate') {
      $rows = array_values(array_filter(
        $rows,
        static fn(array $p): bool => ($p['category_slug'] ?? '') === $categorySlug
      ));
    }

    if ($limit > 0) {
      $rows = array_slice($rows, 0, $limit);
    }

    if (!$withImages) {
      return $rows;
    }

    return array_map(
      static fn(array $row): array => EpiesaImageCache::withPublicImage($row),
      $rows
    );
  }

  /** @return array<int, array<string, mixed>> */
  public static function listProducts(?string $categorySlug = null): array
  {
    return self::listProductsLite($categorySlug, 0, true);
  }

  /** Reîncarcă imaginile locale pentru tot catalogul. */
  public static function refreshAllImages(): int
  {
    $catalog = self::load();
    $updated = 0;
    $products = [];

    foreach ($catalog['products'] ?? [] as $row) {
      if (!is_array($row)) {
        continue;
      }
      $before = (string) ($row['image'] ?? '');
      $row = EpiesaImageCache::ensureCached($row);
      if ((string) ($row['image'] ?? '') !== $before) {
        $updated++;
      }
      $products[] = $row;
    }

    $catalog['products'] = $products;
    self::save($catalog);

    return $updated;
  }

  /**
   * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
   */
  public static function listProductsPaginated(
    int $page = 1,
    int $perPage = 12,
    ?string $categorySlug = null,
    string $search = ''
  ): array {
    $rows = self::listProducts($categorySlug);
    $search = mb_strtolower(trim($search), 'UTF-8');

    if ($search !== '') {
      $rows = array_values(array_filter($rows, static function (array $p) use ($search): bool {
        $hay = mb_strtolower(implode(' ', [
          (string) ($p['title'] ?? ''),
          (string) ($p['description'] ?? ''),
          (string) ($p['category_label'] ?? ''),
          (string) ($p['price'] ?? ''),
        ]), 'UTF-8');

        return str_contains($hay, $search);
      }));
    }

    $total = count($rows);
    $page = max(1, $page);
    $perPage = max(1, min(50, $perPage));
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
      $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    return [
      'items'       => array_slice($rows, $offset, $perPage),
      'total'       => $total,
      'page'        => $page,
      'per_page'    => $perPage,
      'total_pages' => $totalPages,
    ];
  }

  /** @return array<string, mixed> */
  public static function stats(): array
  {
    $catalog = self::load();
    $rows = is_array($catalog['products'] ?? null) ? $catalog['products'] : [];
    $byCategory = [];

    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $slug = (string) ($row['category_slug'] ?? 'altele');
      $byCategory[$slug] = ($byCategory[$slug] ?? 0) + 1;
    }

    $categories = [];
    foreach (EpiesaCategories::presets() as $preset) {
      $categories[] = [
        'slug'  => $preset['slug'],
        'label' => $preset['label'],
        'url'   => $preset['url'],
        'count' => (int) ($byCategory[$preset['slug']] ?? 0),
      ];
      unset($byCategory[$preset['slug']]);
    }

    foreach ($byCategory as $slug => $count) {
      $categories[] = [
        'slug'  => $slug,
        'label' => EpiesaCategories::labelForSlug($slug),
        'url'   => '',
        'count' => (int) $count,
      ];
    }

    $latest = self::loadLatestScanMeta();
    $status = self::loadStatus();

    return [
      'total_products' => count($rows),
      'categories'     => $categories,
      'last_scan'      => $catalog['last_scan'] ?? null,
      'latest_run'     => $latest,
      'status'         => $status,
      'updated_at'     => $catalog['updated_at'] ?? null,
    ];
  }

  /** @return array<string, mixed> */
  private static function emptyCatalog(): array
  {
    return [
      'updated_at'    => null,
      'product_count' => 0,
      'products'      => [],
      'last_scan'     => null,
    ];
  }

  private static function bootstrapFromLatest(): void
  {
    if (is_file(ScraperPaths::catalogJsonPath())) {
      return;
    }

    $latestPath = ScraperPaths::latestJsonPath();
    if (!is_file($latestPath)) {
      return;
    }

    $data = json_decode((string) file_get_contents($latestPath), true);
    if (!is_array($data) || empty($data['products'])) {
      return;
    }

    $url = (string) ($data['source_url'] ?? 'https://www.epiesa.ro/gmtn1:auto/gmtn2:uleiuri-si-lubrifianti-auto/');
    $cat = EpiesaCategories::resolveFromUrl($url);
    $now = (string) ($data['scraped_at'] ?? date('c'));
    $products = [];

    foreach ($data['products'] as $product) {
      if (!is_array($product)) {
        continue;
      }
      $products[] = array_merge($product, [
        'category_slug'  => $cat['slug'],
        'category_label' => $cat['label'],
        'source_url'     => $url,
        'scraped_at'     => $now,
        'visible'        => true,
      ]);
    }

    $catalog = self::emptyCatalog();
    $catalog['products'] = $products;
    $catalog['last_scan'] = [
      'at'             => $now,
      'source_url'     => $url,
      'category_slug'  => $cat['slug'],
      'category_label' => $cat['label'],
      'fetched'        => count($products),
      'added'          => count($products),
      'total'          => count($products),
    ];
    self::save($catalog);
  }

  /** @param array<string, mixed> $product */
  private static function productKey(array $product): string
  {
    $path = trim((string) ($product['url_path'] ?? ''));
    if ($path !== '') {
      return $path;
    }

    return trim((string) ($product['url'] ?? ''));
  }

  /** @return array<string, mixed> */
  private static function loadLatestScanMeta(): array
  {
    $path = ScraperPaths::latestJsonPath();
    if (!is_file($path)) {
      return [];
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
      return [];
    }

    return [
      'scraped_at'    => $data['scraped_at'] ?? null,
      'source_url'    => $data['source_url'] ?? '',
      'product_count' => (int) ($data['product_count'] ?? 0),
      'raw_html'      => $data['raw_html'] ?? '',
    ];
  }

  /** @return array<string, mixed> */
  public static function loadStatus(): array
  {
    $path = ScraperPaths::statusJsonPath();
    if (!is_file($path)) {
      return ['status' => 'idle', 'updated_at' => null];
    }

    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data : ['status' => 'idle'];
  }

  /** @param array<string, mixed> $status */
  public static function saveStatus(array $status): void
  {
    ScraperPaths::ensureDirs();
    $status['updated_at'] = date('c');
    file_put_contents(
      ScraperPaths::statusJsonPath(),
      json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
      LOCK_EX
    );
  }

  /** Șterge catalogul parsat ePiesa (vitrină fallback + produse speciale homepage). */
  public static function clearAll(): array
  {
    ScraperPaths::ensureDirs();

    $deleted = 0;
    $errors = 0;

    foreach ([ScraperPaths::jsonDir(), ScraperPaths::rawDir(), ScraperPaths::imagesDir()] as $dir) {
      if (!is_dir($dir)) {
        continue;
      }
      foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
          continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($path)) {
          continue;
        }
        if (@unlink($path)) {
          $deleted++;
        } else {
          $errors++;
        }
      }
    }

    $logFile = ScraperPaths::logFile();
    if (is_file($logFile)) {
      if (@file_put_contents($logFile, '') !== false) {
        $deleted++;
      } else {
        $errors++;
      }
    }

    self::save(self::emptyCatalog());
    self::saveStatus(['status' => 'idle']);

    return [
      'products' => 0,
      'files_deleted' => $deleted,
      'errors' => $errors,
    ];
  }
}
