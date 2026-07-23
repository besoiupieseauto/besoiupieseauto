<?php

declare(strict_types=1);

/**
 * Indexare batch embeddings produse.
 *
 * Usage: php admin/cron_cli/ai_intel_index_products.php --limit=400
 */
define('BESOIU_ROOT', dirname(__DIR__, 2));
require BESOIU_ROOT . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

use Besoiu\Services\AiIntelligence\ProductIndexService;

$limit = 400;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(2000, (int) substr($arg, 8)));
    }
}

$result = ProductIndexService::create(BESOIU_ROOT)->indexBatch($limit);

fwrite(
    STDOUT,
    sprintf(
        "ai-intel-index: indexed=%d errors=%d skipped=%d\n",
        (int) ($result['indexed'] ?? 0),
        (int) ($result['errors'] ?? 0),
        (int) ($result['skipped'] ?? 0)
    )
);
exit(0);
