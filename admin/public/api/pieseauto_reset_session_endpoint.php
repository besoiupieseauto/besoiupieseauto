<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Services\PieseAuto\PieseAutoSessionResetService;

ApiBootstrap::runJsonEndpoint(static function (): void {
    ApiBootstrap::requireAuthenticatedSession();
    ApiBootstrap::requireHttpMethod('POST');

    $payload = ApiBootstrap::readJsonPayload();
    $target = trim((string) ($payload['target'] ?? $payload['cont_id'] ?? ''));

    if ($target === '') {
        ApiBootstrap::json([
            'success' => false,
            'status' => 'eroare',
            'mesaj' => 'Lipsește utilizator target.',
        ], 400);
    }

    $result = (new PieseAutoSessionResetService())->reset($target);

    ApiBootstrap::json([
        'success' => $result['success'],
        'status' => $result['success'] ? 'succes' : 'eroare',
        'mesaj' => $result['mesaj'],
        'cont_id' => $result['cont_id'],
        'profile_deleted' => $result['profile_deleted'],
        'runtime_cleaned' => $result['runtime_cleaned'],
        'robot_stopped' => $result['robot_stopped'],
    ], $result['success'] ? 200 : 500);
}, 'pieseauto_reset_session_endpoint', false);
