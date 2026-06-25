<?php

declare(strict_types=1);

use Evasystem\Controllers\AdaosComercial\AdaosComercial;
use Evasystem\Controllers\AdaosComercial\AdaosComercialService;
use Evasystem\Core\Crud\LegacyJsonCrud;

LegacyJsonCrud::prepare();

try {
    LegacyJsonCrud::requirePost();
    $data = LegacyJsonCrud::readInput();

    $type = (string) ($data['type_product'] ?? '');
    if ($type === '') {
        throw new \InvalidArgumentException('Lipsește tipul acțiunii.');
    }

    $controller = new AdaosComercial();
    $service = new AdaosComercialService();

    switch ($type) {
        case 'list':
            $response = ['success' => true, 'data' => $service->getAll()];
            break;
        case 'save':
            $response = $controller->save($data);
            break;
        case 'delete':
            $response = $controller->delete($data);
            break;
        case 'toggle':
            $response = $controller->toggle($data);
            break;
        case 'preview':
            $response = $controller->preview($data);
            break;
        case 'apply':
            $response = $controller->apply($data);
            break;
        case 'simulate_product':
            $response = $controller->simulateProduct($data);
            break;
        case 'save_vat':
            $response = $controller->saveVatSettings($data);
            break;
        case 'save_price_round':
            $response = $controller->saveGlobalPriceRoundSettings($data);
            break;
        case 'save_global_markup':
            $response = $controller->saveGlobalCommercialMarkupSettings($data);
            break;
        case 'reapply_all':
            $response = $controller->reapplyAll($data);
            break;
        case 'price_formation_trace':
        case 'price_trace':
            $response = $controller->priceFormationTrace($data);
            break;
        default:
            throw new \InvalidArgumentException('Acțiune necunoscută: ' . $type);
    }

    LegacyJsonCrud::emit(is_array($response) ? $response : ['success' => true, 'data' => $response]);
} catch (\Throwable $e) {
    LegacyJsonCrud::emitThrowable($e);
}
