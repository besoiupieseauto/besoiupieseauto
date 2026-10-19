<?php

declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch\Service;

use Besoiu\Modules\SupplierSearch\Service\Clients\BardiAutoClient;
use Besoiu\Modules\SupplierSearch\Service\Clients\ElitClient;
use Besoiu\Modules\SupplierSearch\Service\Clients\IntercarsClient;

/**
 * Oglindă coș webshop pentru furnizori fără API de comandă (Intercars, Elit, Bardi).
 * Identifică doar linia adăugată acum și oprește fluxul înainte de click-ul final.
 */
final class WebshopCartMirrorService
{
    public const WEBSHOP_SUPPLIERS = ['intercars', 'elit', 'bardi'];

    public static function isWebshopSupplier(string $supplier): bool
    {
        return in_array(strtolower(trim($supplier)), self::WEBSHOP_SUPPLIERS, true);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function attachOnAdd(array $item): array
    {
        $supplier = strtolower(trim((string) ($item['supplier'] ?? '')));
        if (!self::isWebshopSupplier($supplier)) {
            return $item;
        }

        $code = $this->lookupCode($item);
        if ($supplier === 'intercars') {
            $digits = preg_replace('/\D+/', '', (string) ($item['product_code'] ?? $code)) ?? $code;
            if ($digits !== '') {
                $code = $digits;
            }
        }
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $added = $this->addRemoteLine($supplier, $code, $qty);
        $item['webshop_line_id'] = (string) ($added['id'] ?? '');
        $item['webshop_status'] = $added['success'] && $item['webshop_line_id'] !== '' ? 'mirrored' : 'mirror_failed';
        $item['webshop_error'] = (string) ($added['error'] ?? '');
        $item['webshop_remote_count'] = count($added['lines'] ?? []);
        $item['webshop_other_count'] = max(0, $item['webshop_remote_count'] - ($item['webshop_line_id'] !== '' ? 1 : 0));

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function rehearse(array $item): array
    {
        $supplier = strtolower(trim((string) ($item['supplier'] ?? '')));
        $lineId = trim((string) ($item['webshop_line_id'] ?? ''));
        $code = $this->lookupCode($item);
        $steps = [];

        if (!self::isWebshopSupplier($supplier)) {
            return [
                'success' => true,
                'submitted' => false,
                'line_id' => '',
                'isolated' => false,
                'steps' => [['name' => 'skip', 'ok' => true, 'detail' => 'Furnizorul are API propriu, nu folosește oglindă webshop.']],
            ];
        }

        if ($supplier === 'intercars') {
            return $this->rehearseIntercars($item, $code, $lineId);
        }
        if ($supplier === 'elit' || $supplier === 'bardi') {
            return $this->rehearseHttpWebshop($supplier, $item, $code, $lineId);
        }

        $listed = $this->listRemote($supplier);
        $steps[] = [
            'name' => 'list_remote_cart',
            'ok' => (bool) $listed['success'],
            'detail' => $listed['success']
                ? ('Coș remote: ' . count($listed['lines']) . ' linii.')
                : (string) ($listed['error'] ?? 'Citirea coșului remote a eșuat.'),
        ];

        if ($lineId === '' && $listed['success']) {
            foreach ($listed['lines'] as $line) {
                if (strcasecmp((string) ($line['code'] ?? ''), $code) === 0 && trim((string) ($line['id'] ?? '')) !== '') {
                    $lineId = (string) $line['id'];
                    break;
                }
            }
        }

        $match = null;
        foreach ($listed['lines'] as $line) {
            if ((string) ($line['id'] ?? '') === $lineId) {
                $match = $line;
                break;
            }
        }
        $isolated = is_array($match);
        $otherCount = max(0, count($listed['lines']) - ($isolated ? 1 : 0));
        $steps[] = [
            'name' => 'isolate_own_line',
            'ok' => $isolated,
            'detail' => $isolated
                ? ('Lucrăm doar linia ' . $lineId . '. Restul în coșul furnizorului: ' . $otherCount . '.')
                : 'Linia noastră nu a fost găsită în coșul furnizorului.',
        ];

        $preview = $this->previewRemote($supplier);
        $steps[] = [
            'name' => 'preview_checkout',
            'ok' => (bool) $preview['success'],
            'detail' => $preview['success']
                ? 'Checkout pregătit, fără trimitere.'
                : (string) ($preview['error'] ?? 'Checkout-ul nu a putut fi citit.'),
        ];
        $steps[] = [
            'name' => 'stop_before_submit',
            'ok' => true,
            'detail' => 'Comanda NU a fost trimisă. Ultimul click rămâne blocat.',
        ];

        $ok = $isolated && $listed['success'];

        return [
            'success' => $ok,
            'submitted' => false,
            'supplier' => $supplier,
            'line_id' => $lineId,
            'isolated' => $isolated,
            'remote_total' => count($listed['lines']),
            'other_count' => $otherCount,
            'own_line' => $match,
            'steps' => $steps,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{id:string,code:string,qty:int}>
     */
    public static function extractLines(array $payload): array
    {
        $candidates = [];
        $selected = is_array($payload['SelectedCart'] ?? null) ? $payload['SelectedCart'] : [];
        if (is_array($selected['Items'] ?? null)) {
            $candidates = $selected['Items'];
        }
        foreach (['items', 'lines', 'cartItems', 'cart_items', 'products', 'Positions', 'Items', 'data'] as $key) {
            if ($candidates === [] && isset($payload[$key]) && is_array($payload[$key])) {
                $candidates = $payload[$key];
                break;
            }
        }
        if ($candidates === [] && array_is_list($payload)) {
            $candidates = $payload;
        }

        $lines = [];
        foreach ($candidates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) (
                $row['id']
                ?? $row['Id']
                ?? $row['itemId']
                ?? $row['cartItemId']
                ?? $row['CartItemId']
                ?? $row['lineId']
                ?? $row['positionId']
                ?? $row['uid']
                ?? ''
            ));
            $code = trim((string) (
                $row['code']
                ?? $row['Code']
                ?? $row['towKod']
                ?? $row['productCode']
                ?? $row['ProductId']
                ?? $row['CatalogueNumber']
                ?? $row['cikk']
                ?? $row['partNumber']
                ?? $row['pn']
                ?? $row['part_number']
                ?? ''
            ));
            if ($id === '' && $code === '') {
                continue;
            }
            $lines[] = [
                'id' => $id !== '' ? $id : $code,
                'code' => $code,
                'qty' => max(1, (int) ($row['qty'] ?? $row['quantity'] ?? $row['Quantity'] ?? $row['Amount'] ?? $row['amount'] ?? 1)),
            ];
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function rehearseIntercars(array $item, string $code, string $lineId): array
    {
        $query = preg_replace('/\D+/', '', (string) ($item['product_code'] ?? $item['api_lookup_code'] ?? $code)) ?? $code;
        $qty = max(1, (int) ($item['qty'] ?? 1));
        if ($lineId !== '') {
            return [
                'success' => true,
                'submitted' => false,
                'supplier' => 'intercars',
                'line_id' => $lineId,
                'isolated' => true,
                'remote_total' => 1,
                'other_count' => 0,
                'own_line' => ['id' => $lineId, 'code' => $code, 'qty' => $qty],
                'steps' => [
                    ['name' => 'reuse_saved_line', 'ok' => true, 'detail' => 'Folosim linia deja capturată: ' . $lineId . '.'],
                    ['name' => 'isolate_own_line', 'ok' => true, 'detail' => 'Lucrăm doar linia ' . $lineId . '.'],
                    ['name' => 'stop_before_submit', 'ok' => true, 'detail' => 'Comanda NU a fost trimisă. Ultimul click rămâne blocat.'],
                ],
                'error' => '',
            ];
        }

        $script = (new IntercarsClient())->rehearseCart($code !== '' ? $code : $query, $query, $qty, 90);
        $newId = trim((string) ($script['line_id'] ?? ''));
        $steps = is_array($script['steps'] ?? null) ? $script['steps'] : [];
        $isolated = !empty($script['isolated']) || $newId !== '';
        $ok = $isolated && empty($script['submitted']);

        return [
            'success' => $ok,
            'submitted' => false,
            'supplier' => 'intercars',
            'line_id' => $newId,
            'isolated' => $isolated,
            'remote_total' => count($script['lines'] ?? []),
            'other_count' => max(0, count($script['lines'] ?? []) - ($isolated ? 1 : 0)),
            'own_line' => $newId !== '' ? ['id' => $newId, 'code' => $code, 'qty' => $qty] : null,
            'steps' => $steps !== [] ? $steps : [['name' => 'rehearse', 'ok' => $ok, 'detail' => (string) ($script['error'] ?? '')]],
            'error' => (string) ($script['error'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function rehearseHttpWebshop(string $supplier, array $item, string $code, string $lineId): array
    {
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $label = $supplier === 'elit' ? 'Elit' : 'Bardi Auto';
        if ($lineId !== '') {
            return [
                'success' => true,
                'submitted' => false,
                'supplier' => $supplier,
                'line_id' => $lineId,
                'isolated' => true,
                'remote_total' => 1,
                'other_count' => 0,
                'own_line' => ['id' => $lineId, 'code' => $code, 'qty' => $qty],
                'steps' => [
                    ['name' => 'reuse_saved_line', 'ok' => true, 'detail' => 'Folosim linia deja capturată: ' . $lineId . '.'],
                    ['name' => 'isolate_own_line', 'ok' => true, 'detail' => 'Lucrăm doar linia ' . $lineId . '.'],
                    ['name' => 'stop_before_submit', 'ok' => true, 'detail' => 'Comanda NU a fost trimisă. Ultimul click rămâne blocat.'],
                ],
                'error' => '',
            ];
        }

        $steps = [];
        $added = $this->addRemoteLine($supplier, $code, $qty);
        $lineId = trim((string) ($added['id'] ?? ''));
        $steps[] = [
            'name' => 'add_remote_line',
            'ok' => $lineId !== '',
            'detail' => $lineId !== ''
                ? ($label . ': linie adăugată ' . $lineId . '.')
                : (string) ($added['error'] ?? ('Nu s-a putut adăuga linia ' . $label . '.')),
        ];

        $listed = $this->listRemote($supplier);
        $steps[] = [
            'name' => 'list_remote_cart',
            'ok' => (bool) $listed['success'],
            'detail' => $listed['success']
                ? ('Coș remote: ' . count($listed['lines']) . ' linii.')
                : (string) ($listed['error'] ?? 'Citirea coșului remote a eșuat.'),
        ];

        if ($lineId === '' && $listed['success']) {
            foreach ($listed['lines'] as $line) {
                if (strcasecmp((string) ($line['code'] ?? ''), $code) === 0 && trim((string) ($line['id'] ?? '')) !== '') {
                    $lineId = (string) $line['id'];
                    break;
                }
            }
        }

        $match = null;
        foreach ($listed['lines'] as $line) {
            if ((string) ($line['id'] ?? '') === $lineId) {
                $match = $line;
                break;
            }
        }
        if ($match === null && $lineId !== '') {
            $match = ['id' => $lineId, 'code' => $code, 'qty' => $qty];
        }
        $isolated = is_array($match) && $lineId !== '';
        $otherCount = max(0, count($listed['lines']) - ($isolated ? 1 : 0));
        $steps[] = [
            'name' => 'isolate_own_line',
            'ok' => $isolated,
            'detail' => $isolated
                ? ('Lucrăm doar linia ' . $lineId . '. Restul în coșul furnizorului: ' . $otherCount . '.')
                : 'Linia noastră nu a fost găsită în coșul furnizorului.',
        ];

        $preview = $this->previewRemote($supplier);
        $steps[] = [
            'name' => 'preview_checkout',
            'ok' => (bool) $preview['success'],
            'detail' => $preview['success']
                ? 'Checkout pregătit, fără trimitere.'
                : (string) ($preview['error'] ?? 'Checkout-ul nu a putut fi citit.'),
        ];
        $steps[] = [
            'name' => 'stop_before_submit',
            'ok' => true,
            'detail' => 'Comanda NU a fost trimisă. Ultimul click rămâne blocat.',
        ];

        return [
            'success' => $isolated && empty($preview['submitted']),
            'submitted' => false,
            'supplier' => $supplier,
            'line_id' => $lineId,
            'isolated' => $isolated,
            'remote_total' => count($listed['lines']),
            'other_count' => $otherCount,
            'own_line' => $match,
            'steps' => $steps,
            'error' => $isolated ? '' : (string) ($added['error'] ?? $listed['error'] ?? 'Linia webshop nu a fost izolată.'),
        ];
    }

    /** @param array<string, mixed> $item */
    private function lookupCode(array $item): string
    {
        foreach (['api_lookup_code', 'variant_code', 'order_code', 'product_code'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array{success:bool,id:string,lines:list<array{id:string,code:string,qty:int}>,error:string,status:int}
     */
    private function addRemoteLine(string $supplier, string $code, int $qty): array
    {
        return match ($supplier) {
            'intercars' => (new IntercarsClient())->addCartLine($code, $qty),
            'elit' => (new ElitClient())->addCartLine($code, $qty),
            'bardi' => (new BardiAutoClient())->addCartLine($code, $qty),
            default => ['success' => false, 'id' => '', 'lines' => [], 'error' => 'Furnizor webshop necunoscut.', 'status' => 0],
        };
    }

    /**
     * @return array{success:bool,lines:list<array{id:string,code:string,qty:int}>,error:string,status?:int}
     */
    private function listRemote(string $supplier): array
    {
        return match ($supplier) {
            'intercars' => (new IntercarsClient())->listCart(),
            'elit' => (new ElitClient())->listCart(),
            'bardi' => (new BardiAutoClient())->listCart(),
            default => ['success' => false, 'lines' => [], 'error' => 'Furnizor webshop necunoscut.'],
        };
    }

    /** @return array{success:bool,ready?:bool,error:string,status?:int} */
    private function previewRemote(string $supplier): array
    {
        return match ($supplier) {
            'intercars' => (new IntercarsClient())->previewCheckout(),
            'elit' => (new ElitClient())->previewCheckout(),
            'bardi' => (new BardiAutoClient())->previewCheckout(),
            default => ['success' => false, 'error' => 'Furnizor webshop necunoscut.'],
        };
    }
}
