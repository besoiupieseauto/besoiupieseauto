<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Services\PieseAuto\PieseAutoRobotConfig;
use Evasystem\Services\PieseAuto\PieseAutoRobotLauncher;
use Evasystem\Services\PieseAuto\PieseAutoStatusService;

ApiBootstrap::runJsonEndpoint(static function (): void {
    ApiBootstrap::requireAuthenticatedSession();

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $payload = $method === 'POST' ? ApiBootstrap::readJsonPayload() : $_GET;
    $action = strtolower(trim((string) ($payload['action'] ?? 'start')));

    if ($method === 'GET' || $action === 'ping') {
        $target = trim((string) ($payload['target'] ?? 'besoiu'));
        $snapshot = (new PieseAutoStatusService())->snapshot($target);
        $ping = PieseAutoRobotConfig::pingDetails();

        ApiBootstrap::json([
            'success' => true,
            'robot' => 'pieseauto',
            'online' => (bool) ($snapshot['service_online'] ?? false),
            'already_running' => (bool) ($snapshot['service_online'] ?? false),
            'robot_base' => PieseAutoRobotConfig::effectiveUrl(),
            'auto_start' => PieseAutoRobotConfig::autoStartEnabled(),
            'ping' => $ping,
            'snapshot' => $snapshot,
        ]);
    }

    ApiBootstrap::requireHttpMethod('POST');
    $force = !empty($payload['force']);
    $result = (new PieseAutoRobotLauncher())->ensureRunning($force);
    $target = trim((string) ($payload['target'] ?? 'besoiu'));
    $snapshot = (new PieseAutoStatusService())->snapshot($target);
    $result['online'] = (bool) ($snapshot['service_online'] ?? false);
    $result['snapshot'] = $snapshot;
    $result['robot_base'] = PieseAutoRobotConfig::effectiveUrl();

    if (($result['already_running'] ?? false) && !($result['online'] ?? false)) {
        $result['message'] = 'Proces detectat, dar portul robot nu răspunde corect. Repornește robot\\start_pieseauto_visible.bat.';
        $result['success'] = false;
    }

    ApiBootstrap::json($result, ($result['success'] ?? false) ? 200 : 503);
}, 'pieseauto_robot_launcher_endpoint', false);
