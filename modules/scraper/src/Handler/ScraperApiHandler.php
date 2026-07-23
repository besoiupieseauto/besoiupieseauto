<?php
declare(strict_types=1);

namespace Besoiu\Modules\Scraper\Handler;

use Besoiu\Controllers\Scraper\Scraper;
use Besoiu\Core\Bootstrap\ApiBootstrap;
use InvalidArgumentException;
use Throwable;

/**
 * API JSON + stream pentru /admin/scraper (contract scraper_endpoint.php).
 */
final class ScraperApiHandler
{
    public static function handle(): void
    {
        ApiBootstrap::bootJsonApi();
        ApiBootstrap::registerJsonFatalGuard('scraper_endpoint');

        try {
            ApiBootstrap::requireAuthenticatedSession();
            ApiBootstrap::beginBoundedJsonWork(120);

            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $query = $_GET;
            if (!is_array($query)) {
                $query = [];
            }

            $payload = [];
            if ($method === 'POST') {
                $raw = file_get_contents('php://input') ?: '';
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                } elseif (trim($raw) !== '') {
                    throw new InvalidArgumentException('JSON invalid.');
                }
            }

            $controller = new Scraper();
            $result = $controller->handleApi($method, $query, $payload);

            if (($result['mode'] ?? '') === 'stream') {
                $url = trim((string) ($result['url'] ?? ''));
                if ($url === '') {
                    ApiBootstrap::json(['success' => false, 'message' => 'Lipsește URL imagine.'], 400);
                }
                $controller->streamImage($url);

                return;
            }

            $status = (int) ($result['status'] ?? 200);
            $body = $result['body'] ?? $result;
            if (!is_array($body)) {
                $body = ['success' => true, 'data' => $body];
            }

            ApiBootstrap::json($body, $status);
        } catch (Throwable $e) {
            ApiBootstrap::respondInternalError('scraper_endpoint', $e);
        }
    }
}
