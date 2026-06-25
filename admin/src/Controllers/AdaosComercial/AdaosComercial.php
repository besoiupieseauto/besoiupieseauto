<?php
declare(strict_types=1);

namespace Evasystem\Controllers\AdaosComercial;

final class AdaosComercial
{
    private AdaosComercialService $service;

    public function __construct(?AdaosComercialService $service = null)
    {
        $this->service = $service ?? new AdaosComercialService();
    }

    public function save(array $data): array
    {
        $id = (int)($data['id'] ?? 0);

        try {
            $payload = $this->buildPayload($data);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        if ($id > 0) {
            $ok = $this->service->update($id, $payload);

            return $ok
                ? ['success' => true, 'message' => 'Regula a fost actualizată.']
                : ['success' => false, 'message' => 'Nu am putut actualiza regula.'];
        }

        $newId = $this->service->create($payload);

        return [
            'success' => $newId > 0,
            'message' => $newId > 0 ? 'Regula a fost adăugată.' : 'Nu am putut salva regula.',
            'id' => $newId,
        ];
    }

    public function delete(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $ok = $this->service->delete($id);

        return $ok
            ? ['success' => true, 'message' => 'Regula a fost ștearsă.']
            : ['success' => false, 'message' => 'Nu am putut șterge regula.'];
    }

    public function toggle(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $active = (int)($data['is_active'] ?? 0) === 1;
        $ok = $this->service->toggleActive($id, $active);

        return $ok
            ? ['success' => true, 'message' => $active ? 'Regula a fost activată.' : 'Regula a fost dezactivată.']
            : ['success' => false, 'message' => 'Nu am putut schimba starea regulii.'];
    }

    public function preview(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $limit = max(1, min(100, (int)($data['limit'] ?? 25)));
        $result = $this->service->previewRule($id, $limit);

        return ['success' => true, 'data' => $result];
    }

    public function apply(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalid.'];
        }

        $result = $this->service->applyRule($id);

        return [
            'success' => true,
            'message' => 'Adaosul a fost aplicat pe produsele eligibile.',
            'data' => $result,
        ];
    }

    public function saveVatSettings(array $data): array
    {
        $vat = (float) ($data['commercial_vat_percent'] ?? 21);
        if ($vat < 0 || $vat > 100) {
            return ['success' => false, 'message' => 'TVA invalid (0–100%).'];
        }

        $ok = $this->service->saveCommercialVatPercent($vat);

        return $ok
            ? ['success' => true, 'message' => 'TVA comercial salvat.', 'commercial_vat_percent' => $vat]
            : ['success' => false, 'message' => 'Nu am putut salva TVA comercial.'];
    }

    public function saveGlobalPriceRoundSettings(array $data): array
    {
        $mode = (string) ($data['global_price_round_mode'] ?? AdaosComercialService::GLOBAL_ROUND_NONE);
        $value = array_key_exists('global_price_round_value', $data)
            ? (float) $data['global_price_round_value']
            : null;

        try {
            $ok = $this->service->saveGlobalPriceRoundSettings($mode, $value);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $settings = $this->service->getGlobalPriceRoundSettings();

        return $ok
            ? [
                'success' => true,
                'message' => 'Rotunjirea globală a fost salvată.',
                'global_price_round_mode' => $settings['mode'],
                'global_price_round_value' => $settings['value'],
            ]
            : ['success' => false, 'message' => 'Nu am putut salva rotunjirea globală.'];
    }

    public function saveGlobalCommercialMarkupSettings(array $data): array
    {
        $percent = (float) ($data['global_commercial_markup_percent'] ?? 0);
        if ($percent < 0 || $percent > 1000) {
            return ['success' => false, 'message' => 'Adaos global invalid (0–1000%).'];
        }

        $ok = $this->service->saveGlobalCommercialMarkupPercent($percent);

        return $ok
            ? [
                'success' => true,
                'message' => 'Adaosul comercial global a fost salvat.',
                'global_commercial_markup_percent' => $percent,
            ]
            : ['success' => false, 'message' => 'Nu am putut salva adaosul comercial global.'];
    }

    public function reapplyAll(array $data): array
    {
        unset($data);
        $result = $this->service->reapplyGlobalMarkupToAll();

        return [
            'success' => true,
            'message' => 'Adaosul global a fost reaplicat pe ' . (int) ($result['updated_count'] ?? 0) . ' produse.',
            'data' => $result,
        ];
    }

    public function simulateProduct(array $data): array
    {
        $basePrice = trim((string)($data['pBasePrice'] ?? $data['pPrice'] ?? ''));
        if ($basePrice === '') {
            return ['success' => false, 'message' => 'Introduce prețul de bază pentru previzualizare.'];
        }

        $result = $this->service->applyAutomaticMarkup([
            'pName' => $data['pName'] ?? '',
            'pCode' => $data['pCode'] ?? '',
            'pCar' => $data['pCar'] ?? '',
            'pBrand' => $data['pBrand'] ?? '',
            'pMarca' => $data['pMarca'] ?? '',
            'pCategory' => $data['pCategory'] ?? '',
            'pSubcategory' => $data['pSubcategory'] ?? '',
            'pStock' => $data['pStock'] ?? '',
            'pBasePrice' => $basePrice,
        ], null, true);

        return [
            'success' => true,
            'data' => [
                'base_price' => $result['base_price'],
                'final_price' => $result['final_price'],
                'delta' => $result['delta'],
                'applied' => $result['applied'],
                'rule' => $result['rule'] ? [
                    'id' => (int)($result['rule']['id'] ?? 0),
                    'name' => (string)($result['rule']['name'] ?? ''),
                    'type' => (string)($result['rule']['adjustment_type'] ?? ''),
                    'value' => (string)($result['rule']['adjustment_value'] ?? ''),
                ] : null,
            ],
        ];
    }

    public function priceFormationTrace(array $data): array
    {
        $service = new PriceFormationTraceService($this->service);

        if (!empty($data['list_import_suppliers'])) {
            return $service->listImportQueueSuppliersResponse();
        }

        if (!empty($data['import_id'])) {
            return $service->traceByImportRowId((int) $data['import_id']);
        }

        if (!empty($data['import_batch'])) {
            return $service->traceImportBatch(
                isset($data['supplier']) ? (string) $data['supplier'] : null,
                (int) ($data['limit'] ?? 25),
                (int) ($data['offset'] ?? 0)
            );
        }

        $identifier = trim((string) ($data['product'] ?? $data['code'] ?? $data['pCode'] ?? ''));
        if ($identifier === '') {
            return ['success' => false, 'message' => 'Introduce cod produs sau ID import.'];
        }

        return $service->traceByProductIdentifier($identifier);
    }

    private function buildPayload(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Numele regulii este obligatoriu.');
        }

        $adjustmentType = (string)($data['adjustment_type'] ?? 'percentage');
        if (!in_array($adjustmentType, ['percentage', 'fixed'], true)) {
            throw new \InvalidArgumentException('Tipul de adaos este invalid.');
        }

        $adjustmentValue = (float)($data['adjustment_value'] ?? 0);
        if ($adjustmentValue < 0) {
            throw new \InvalidArgumentException('Valoarea adaosului nu poate fi negativă.');
        }

        $priceMin = $this->nullableNumber($data['price_min'] ?? null);
        $priceMax = $this->nullableNumber($data['price_max'] ?? null);
        if ($priceMin !== null && $priceMax !== null && $priceMax < $priceMin) {
            throw new \InvalidArgumentException('Prețul maxim trebuie să fie mai mare sau egal cu prețul minim.');
        }

        $roundTo = $this->nullableNumber($data['round_to'] ?? null);
        if ($roundTo !== null && $roundTo <= 0) {
            throw new \InvalidArgumentException('Rotunjirea trebuie să fie mai mare decât 0.');
        }

        return [
            'name' => $name,
            'category_filter' => $this->nullableString($data['category_filter'] ?? null),
            'brand_filter' => $this->nullableString($data['brand_filter'] ?? null),
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'adjustment_type' => $adjustmentType,
            'adjustment_value' => $adjustmentValue,
            'round_to' => $roundTo,
            'priority' => (int)($data['priority'] ?? 100),
            'note' => $this->nullableString($data['note'] ?? null),
            'is_active' => (int)($data['is_active'] ?? 0) === 1 ? 1 : 0,
        ];
    }

    private function nullableString($value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function nullableNumber($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        return (float)str_replace(',', '.', $value);
    }
}
