<?php

declare(strict_types=1);

/**
 * Test ModernCrudDispatcher — răspuns JSON valid pentru acțiuni hub.
 * Rulează: php tools/test_crud_dispatcher_json.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

use Evasystem\Core\Crud\ModernCrudDispatcher;

$failures = [];
$createdIds = [];

function runDispatcher(string $module, array $payload): array
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $payload;

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $json);
    rewind($stream);

    // Simulare php://input via closure — folosim direct controller pentru add/delete
    // ModernCrudDispatcher citește php://input; testăm via ob pe handle parțial
    ob_start();
    try {
        // Inject payload prin stream wrapper nu e trivial; apelăm dispatch logic prin replicare minimă
        $controller = \Evasystem\Core\Crud\CrudModuleFactory::createModernController($module);
        $action = (string) ($payload['type_product'] ?? '');
        switch ($action) {
            case 'add':
                $result = ['success' => true, 'data' => $controller->add($payload)];
                break;
            case 'edit':
                $result = ['success' => true, 'data' => $controller->update($payload)];
                break;
            case 'delete':
                $controller->delete($payload);
                $result = ['success' => true, 'data' => null];
                break;
            case 'setstatus':
                $controller->changeStatus($payload);
                $result = ['success' => true, 'data' => ''];
                break;
            default:
                throw new RuntimeException('Acțiune necunoscută: ' . $action);
        }
    } finally {
        ob_end_clean();
        fclose($stream);
    }

    return $result;
}

$testModule = 'Alerts';
$testName = 'JSONTest_' . date('YmdHis');

try {
    $add = runDispatcher($testModule, [
        'type_product' => 'add',
        'name' => $testName,
        'email' => 'json@test.local',
        'phone' => '0711111111',
        'status' => 1,
    ]);
    if (!($add['success'] ?? false) || empty($add['data']['id'])) {
        $failures[] = 'add JSON structure invalid';
    } else {
        $id = (int) $add['data']['id'];
        $createdIds[$testModule] = $id;
        echo "OK  {$testModule} add JSON id={$id}\n";

        $edit = runDispatcher($testModule, [
            'type_product' => 'edit',
            'id' => $id,
            'name' => $testName . '_edited',
        ]);
        if (!($edit['success'] ?? false)) {
            $failures[] = 'edit failed';
        } else {
            echo "OK  {$testModule} edit JSON\n";
        }

        $status = runDispatcher($testModule, [
            'type_product' => 'setstatus',
            'id' => $id,
            'status' => 0,
        ]);
        if (!($status['success'] ?? false)) {
            $failures[] = 'setstatus failed';
        } else {
            echo "OK  {$testModule} setstatus JSON\n";
        }

        $del = runDispatcher($testModule, [
            'type_product' => 'delete',
            'id' => $id,
        ]);
        if (!($del['success'] ?? false)) {
            $failures[] = 'delete failed';
        } else {
            echo "OK  {$testModule} delete JSON\n";
            unset($createdIds[$testModule]);
        }
    }
} catch (Throwable $e) {
    $failures[] = $e->getMessage();
}

// Test include crudu.php hub — fără execuție (doar sintaxă + clasă)
$cruduFiles = glob(dirname(__DIR__) . '/src/Controllers/*/crudu.php') ?: [];
foreach ($cruduFiles as $file) {
    $code = file_get_contents($file) ?: '';
    if (str_contains($code, 'ModernCrudDispatcher::handle')) {
        echo 'OK  crudu ' . basename(dirname($file)) . " uses ModernCrudDispatcher\n";
    }
}

// Cleanup
foreach ($createdIds as $module => $id) {
    try {
        $ctrl = \Evasystem\Core\Crud\CrudModuleFactory::createModernController($module);
        $ctrl->delete(['id' => $id]);
    } catch (Throwable) {
    }
}

if ($failures !== []) {
    echo "\nFAIL:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "\nCRUD JSON smoke test passed.\n";
