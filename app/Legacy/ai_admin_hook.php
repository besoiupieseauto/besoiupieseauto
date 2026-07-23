<?php

declare(strict_types=1);

function ai_admin_hook_json(string $module, array $payload, int $status = 200, string $actionHint = ''): void
{
    static $loaded = false;
    if (!$loaded) {
        $path = __DIR__ . '/ai_learning.php';
        if (is_file($path)) {
            require_once $path;
        }
        $loaded = true;
    }

    $isError = $status >= 400 || empty($payload['success']);
    if ($isError && function_exists('ai_admin_notify_failure')) {
        ai_admin_notify_failure($module, $payload, $status, $actionHint !== '' ? $actionHint : 'admin_error');

        return;
    }
    if (!$isError && function_exists('ai_admin_notify_success')) {
        ai_admin_notify_success($module, $payload, $status, $actionHint);
    }
}

/** Apelat din dispatcher CRUD când modulul nu e cunoscut la emit. */
function ai_admin_hook_infer_module(): string
{
    $bridgeClass = '\\Besoiu\\Services\\AiSupervisor\\Phase1\\AdminEventBridge';
    if (!class_exists($bridgeClass)) {
        $autoload = BESOIU_BACKEND . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }
    if (class_exists($bridgeClass)) {
        return (new $bridgeClass())->inferModuleFromRequest();
    }

    return 'admin';
}
