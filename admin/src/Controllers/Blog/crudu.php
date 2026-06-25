<?php

declare(strict_types=1);

use Evasystem\Controllers\Blog\Blog;
use Evasystem\Core\Crud\LegacyJsonCrud;

LegacyJsonCrud::prepare();

try {
    LegacyJsonCrud::requirePost();
    $data = LegacyJsonCrud::readInput();

    $type = $data['type_product'] ?? null;
    if (!$type) {
        throw new \InvalidArgumentException('Lipsește tipul acțiunii (type_product).');
    }

    $controller = new Blog();

    switch ($type) {
        case 'add':
            $response = $controller->add($data);
            break;
        case 'edit':
            $response = $controller->edit($data);
            break;
        case 'delete':
            $response = $controller->delete($data);
            break;
        case 'list':
            $response = $controller->list();
            break;
        default:
            throw new \InvalidArgumentException("Acțiune necunoscută: {$type}");
    }

    LegacyJsonCrud::emit(is_array($response) ? $response : ['success' => true, 'data' => $response]);
} catch (\Throwable $e) {
    LegacyJsonCrud::emitThrowable($e);
}
