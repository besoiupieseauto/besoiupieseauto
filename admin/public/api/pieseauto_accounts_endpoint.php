<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Exceptions\ValidationException;
use Evasystem\Services\PieseAuto\PieseAutoAccountsService;

ApiBootstrap::runJsonEndpoint(static function (): void {
    ApiBootstrap::requireHttpMethod('POST');
    ApiBootstrap::requireAuthenticatedSession();

    $payload = ApiBootstrap::readJsonPayload();
    $service = new PieseAutoAccountsService();

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    if ($action === 'delete' || ($payload['type_product'] ?? '') === 'delete') {
        $randomnId = (string) ($payload['randomn_id'] ?? $payload['ridusers'] ?? '');
        $result = $service->delete($randomnId);
        ApiBootstrap::json(['success' => true, 'message' => $result['message']]);
    }

    $result = $service->save($payload);
    ApiBootstrap::json([
        'success' => true,
        'message' => $result['message'],
        'data' => ['randomn_id' => $result['randomn_id'] ?? null],
    ]);
}, 'pieseauto_accounts_endpoint', true);
