<?php

declare(strict_types=1);

use Evasystem\Controllers\Website\Website;
use Evasystem\Core\Crud\LegacyJsonCrud;

LegacyJsonCrud::prepare();

try {
    LegacyJsonCrud::requirePost();
    $data = LegacyJsonCrud::readInput();

    $type = $data['type_product'] ?? null;
    if (!$type) {
        throw new \InvalidArgumentException('Lipsește tipul acțiunii (type_product).');
    }

    $controller = new Website();

    switch ($type) {
        case 'save':
            $response = $controller->save($data);
            break;
        case 'list':
            $response = $controller->list();
            break;
        case 'create':
            $response = $controller->create($data);
            break;
        case 'delete':
            $response = $controller->delete($data);
            break;
        case 'toggle_active':
            $response = $controller->toggleActive($data);
            break;
        default:
            throw new \InvalidArgumentException("Acțiune necunoscută: {$type}");
    }

    LegacyJsonCrud::emit(is_array($response) ? $response : ['success' => true, 'data' => $response]);
} catch (\Throwable $e) {
    LegacyJsonCrud::emitThrowable($e);
}
