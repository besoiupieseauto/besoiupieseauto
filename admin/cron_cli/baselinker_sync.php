<?php
declare(strict_types=1);

use Evasystem\Controllers\Marketplace\MarketplaceService;
use Evasystem\Core\Marketplace\MarketplaceModel;

/** @param array<string, mixed> $payload */
function baselinker_sync_order(PDO $pdo, array $payload): void
{
    $orderRandomId = (int) ($payload['order_randomn_id'] ?? 0);
    if ($orderRandomId <= 0) {
        throw new InvalidArgumentException('order_randomn_id lipsa.');
    }

    $service = new MarketplaceService(new MarketplaceModel());
    $result = $service->syncOrderToBaseLinker($orderRandomId);

    if (($result['status'] ?? '') === 'skipped') {
        echo "SKIP order {$orderRandomId}: " . ($result['message'] ?? '') . "\n";
        return;
    }

    if (($result['status'] ?? '') !== 'success') {
        throw new RuntimeException((string) ($result['message'] ?? 'Sync BaseLinker esuat.'));
    }

    echo "Synced order {$orderRandomId} to BaseLinker.\n";
}

/** @param array<string, mixed> $payload */
function baselinker_sync_products(PDO $pdo, array $payload): void
{
    $connectionRandomId = (int) ($payload['connection_randomn_id'] ?? $payload['randomn_id'] ?? 0);
    if ($connectionRandomId <= 0) {
        throw new InvalidArgumentException('connection_randomn_id lipsa.');
    }

    $batchSize = max(1, min(200, (int) ($payload['batch_size'] ?? $payload['limit'] ?? 50)));
    $offset = max(0, (int) ($payload['offset'] ?? 0));

    $service = new MarketplaceService(new MarketplaceModel());
    $result = $service->syncProductsToBaseLinker($connectionRandomId, [
        'limit' => $batchSize,
        'offset' => $offset,
        'product_randomn_ids' => is_array($payload['product_randomn_ids'] ?? null) ? $payload['product_randomn_ids'] : [],
    ]);

    if (($result['status'] ?? '') === 'skipped') {
        echo 'SKIP products: ' . ($result['message'] ?? '') . "\n";
        return;
    }

    if (($result['status'] ?? '') === 'failed') {
        throw new RuntimeException((string) ($result['message'] ?? 'Sync produse BaseLinker esuat.'));
    }

    echo ($result['message'] ?? 'Sync produse finalizat.') . "\n";

    if (!empty($payload['auto_continue']) && !empty($result['has_more'])) {
        $nextOffset = (int) ($result['next_offset'] ?? ($offset + $batchSize));
        $jobId = $service->continueBaseLinkerCatalogSync($connectionRandomId, $nextOffset, $batchSize);
        echo "Queued next batch offset={$nextOffset} job_id={$jobId}\n";
    }
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename((string) ($argv[0] ?? ''))) {
    require_once __DIR__ . '/../vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
    $config = require dirname(__DIR__) . '/config/config.php';

    \Config\Database::getInstance(
        $config['db_host'],
        $config['db_name'],
        $config['db_user'],
        $config['db_pass']
    );

    $pdo = \Config\Database::getDB();
    $orderRandomId = (int) ($argv[1] ?? 0);
    if ($orderRandomId <= 0) {
        fwrite(STDERR, "Usage: php baselinker_sync.php <order_randomn_id>\n");
        exit(1);
    }

    baselinker_sync_order($pdo, ['order_randomn_id' => $orderRandomId]);
}
