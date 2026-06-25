<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

use Evasystem\Exceptions\ValidationException;

require_once dirname(__DIR__) . '/Produse/import_supplier_lib.php';

/**
 * Controller pentru furnizori.
 */
final class Furnizori
{
    private const FORBIDDEN_INPUT_KEYS = [
        'type_product', 'type', 'id', 'idusers', 'randomnid', 'usersveryfi', 'experiences', 'save_tab',
        'price_round_to', 'price_min_margin', 'adaos_template_rule_id',
    ];

    private const ALLOWED_STATUSES = ['active', 'blocked'];
    private const ALLOWED_CONNECTION_TYPES = ['ftp', 'sftp', 'email', 'api'];
    private const ALLOWED_MARKUP_TYPES = ['percentage', 'fixed'];
    private const ALLOWED_STOCK_ZERO_MODES = ['hide', 'full', 'out_of_stock'];

    public function __construct(
        private readonly FurnizoriService $furnizoriService
    ) {
    }

    /** @param array<string, mixed> $rawInput @return array{randomn_id:int} */
    public function add(array $rawInput): array
    {
        return $this->furnizoriService->createFurnizor($this->buildPayload($rawInput, false));
    }

    /** @param array<string, mixed> $rawInput @return array{randomn_id:int} */
    public function update(array $rawInput): array
    {
        return $this->furnizoriService->updateFurnizor(
            $this->requireRandomId($rawInput),
            $this->buildPayload($rawInput, true)
        );
    }

    /** @param array<string, mixed> $rawInput */
    public function changeStatus(array $rawInput): void
    {
        $this->furnizoriService->changeFurnizorStatus(
            $this->requireRandomId($rawInput),
            $this->normalizeChoice($rawInput['status'] ?? null, self::ALLOWED_STATUSES, 'Statusul nu este valid.')
        );
    }

    /** @param array<string, mixed> $rawInput */
    public function block(array $rawInput): void
    {
        $this->furnizoriService->changeFurnizorStatus($this->requireRandomId($rawInput), 'blocked');
    }

    /** @param array<string, mixed> $rawInput */
    public function unblock(array $rawInput): void
    {
        $this->furnizoriService->changeFurnizorStatus($this->requireRandomId($rawInput), 'active');
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function sync(array $rawInput): array
    {
        return $this->furnizoriService->syncNow($this->requireRandomId($rawInput), $rawInput);
    }

    /** @param array<string, mixed> $rawInput @return array<string, string|null> */
    public function test(array $rawInput): array
    {
        return $this->furnizoriService->testConnection($this->requireRandomId($rawInput), $rawInput);
    }

    /** @param array<string, mixed> $rawInput */
    public function delete(array $rawInput): void
    {
        $this->furnizoriService->deleteFurnizor($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput @return array{items:array<int,array<string,mixed>>,total:int} */
    public function products(array $rawInput): array
    {
        $limit = isset($rawInput['limit']) && is_numeric($rawInput['limit']) ? (int) $rawInput['limit'] : 50;
        $offset = isset($rawInput['offset']) && is_numeric($rawInput['offset']) ? (int) $rawInput['offset'] : 0;
        $scope = trim((string) ($rawInput['scope'] ?? 'imported'));

        return $this->furnizoriService->listFurnizorProducts(
            $this->requireRandomId($rawInput),
            $limit,
            $offset,
            $scope !== '' ? $scope : 'imported'
        );
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function browse(array $rawInput): array
    {
        return $this->furnizoriService->browseConnection($this->requireRandomId($rawInput), $rawInput);
    }

    /** @param array<string, mixed> $rawInput @return array{copied:array<int,string>,skipped:array<int,string>,folder:string,path:string} */
    public function mirrorFeedFiles(array $rawInput): array
    {
        return $this->furnizoriService->mirrorFeedFilesFromImport($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput @return array<int, array<string, mixed>>|array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function list(array $rawInput = []): array
    {
        return $this->furnizoriService->listFurnizori($rawInput);
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function find(array $rawInput): array
    {
        return $this->furnizoriService->findFurnizor($this->requireRandomId($rawInput));
    }

    /** @return array<string, mixed> */
    public function getPriceLogic(): array
    {
        $service = new PriceFormationLogicService();

        return [
            'config' => $service->getConfig(),
            'suppliers' => $service->listAvailableSuppliers(),
        ];
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function savePriceLogic(array $rawInput): array
    {
        $service = new PriceFormationLogicService();
        $config = $service->saveConfig($rawInput);

        return [
            'config' => $config,
            'priority_map' => $service->getPriorityMap(),
        ];
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function testPriceLogic(array $rawInput): array
    {
        $service = new PriceFormationLogicService();
        $override = isset($rawInput['config']) && is_array($rawInput['config']) ? $rawInput['config'] : null;

        return $service->testConfig($override);
    }

    /** @param array<string, mixed> $rawInput @return array<string, string|int|float|null> */
    private function buildPayload(array $rawInput, bool $isUpdate): array
    {
        $payload = $this->sanitizePayload($rawInput);

        if (!$isUpdate || isset($payload['name'])) {
            $this->validateText($payload['name'] ?? null, 'Numele furnizorului este obligatoriu.', 255);
        }

        foreach ([
            'status' => self::ALLOWED_STATUSES,
            'connection_type' => self::ALLOWED_CONNECTION_TYPES,
            'price_markup_type' => self::ALLOWED_MARKUP_TYPES,
            'stock_zero_mode' => self::ALLOWED_STOCK_ZERO_MODES,
        ] as $field => $allowedValues) {
            if (isset($payload[$field])) {
                $payload[$field] = $this->normalizeChoice($payload[$field], $allowedValues, "Campul {$field} nu este valid.");
            }
        }

        if (!$isUpdate) {
            $payload['status'] = $payload['status'] ?? 'active';
            $payload['connection_type'] = $payload['connection_type'] ?? 'api';
            $payload['price_markup_type'] = $payload['price_markup_type'] ?? 'percentage';
            $payload['stock_zero_mode'] = $payload['stock_zero_mode'] ?? 'full';
        }

        if (isset($payload['conn_email']) && $payload['conn_email'] !== '' && !filter_var($payload['conn_email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Email-ul principal nu este valid.');
        }

        if (isset($payload['conn_email_inbox']) && $payload['conn_email_inbox'] !== '' && !filter_var($payload['conn_email_inbox'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Inbox-ul de email nu este valid.');
        }

        if (isset($payload['api_base_url']) && $payload['api_base_url'] !== '' && !filter_var($payload['api_base_url'], FILTER_VALIDATE_URL)) {
            throw new ValidationException('URL-ul API nu este valid.');
        }

        foreach (['price_markup_value', 'price_round_to', 'price_min_margin'] as $decimalField) {
            if (isset($payload[$decimalField]) && !is_numeric($payload[$decimalField])) {
                throw new ValidationException("Campul {$decimalField} trebuie sa fie numeric.");
            }
        }

        foreach (['scan_interval_minutes', 'products_count', 'adaos_template_rule_id'] as $intField) {
            if (isset($payload[$intField])) {
                if (!is_numeric($payload[$intField]) || (int) $payload[$intField] < 0) {
                    throw new ValidationException("Campul {$intField} trebuie sa fie numeric pozitiv.");
                }
                $payload[$intField] = (int) $payload[$intField];
            }
        }

        foreach (['scan_include_zero_stock', 'scan_skip_unavailable', 'scan_auto_enabled'] as $boolField) {
            if (isset($payload[$boolField])) {
                $payload[$boolField] = in_array((string) $payload[$boolField], ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
            }
        }

        if (isset($payload['scan_schedule_mode'])) {
            $payload['scan_schedule_mode'] = $this->normalizeChoice(
                $payload['scan_schedule_mode'],
                SupplierScanScheduleService::allowedModes(),
                'Modul de programare sincronizare nu este valid.'
            );
        }

        foreach (['scan_schedule_time', 'scan_window_start', 'scan_window_end'] as $timeField) {
            if (!isset($payload[$timeField]) || $payload[$timeField] === '') {
                continue;
            }
            if (!preg_match('/^\d{1,2}:\d{2}$/', (string) $payload[$timeField])) {
                throw new ValidationException("Campul {$timeField} trebuie sa fie in format HH:MM.");
            }
            [$hour, $minute] = array_map('intval', explode(':', (string) $payload[$timeField]));
            $payload[$timeField] = sprintf('%02d:%02d', max(0, min(23, $hour)), max(0, min(59, $minute)));
        }

        if (isset($payload['scan_interval_minutes'])) {
            $payload['scan_interval_minutes'] = max(5, (int) $payload['scan_interval_minutes']);
        }

        if (isset($payload['conn_passive'])) {
            $payload['conn_passive'] = in_array((string) $payload['conn_passive'], ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
        }

        if (!$isUpdate && !isset($payload['scan_interval_minutes'])) {
            $payload['scan_interval_minutes'] = 60;
        }

        $hasPriceFields = isset($payload['price_markup_value'])
            || isset($payload['price_markup_type'])
            || isset($payload['feed_markup_override']);
        $supplierCode = import_supplier_normalize_code((string) ($payload['code'] ?? ''));
        if (
            $hasPriceFields
            && $supplierCode !== ''
            && in_array($supplierCode, import_supplier_return10_codes(), true)
        ) {
            $payload['price_markup_type'] = 'percentage';
            $feedMarkupOverride = in_array((string) ($payload['feed_markup_override'] ?? '0'), ['1', 'true', 'on', 'yes'], true);
            $payload['price_markup_value'] = $feedMarkupOverride
                ? max(0.0, (float) ($payload['price_markup_value'] ?? 0))
                : 0.0;
        } elseif ($hasPriceFields) {
            $payload['price_markup_type'] = 'percentage';
            $feedMarkupOverride = in_array((string) ($payload['feed_markup_override'] ?? '0'), ['1', 'true', 'on', 'yes'], true);
            $markupValue = max(0.0, (float) ($payload['price_markup_value'] ?? 0));
            if (!$feedMarkupOverride && !in_array($markupValue, import_supplier_feed_markup_presets(), true)) {
                throw new ValidationException(
                    'Compensatorul pre-import acceptă doar 0%, 5% sau 10%. Adaosul comercial se setează în Adaos Comercial.'
                );
            }
            $payload['price_markup_value'] = $markupValue;
        }
        unset($payload['feed_markup_override']);

        return $payload;
    }

    /** @param array<string, mixed> $rawInput @return array<string, string> */
    private function sanitizePayload(array $rawInput): array
    {
        $withoutForbidden = array_diff_key($rawInput, array_flip(self::FORBIDDEN_INPUT_KEYS));
        $cleanPayload = [];

        foreach ($withoutForbidden as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $stringValue = trim((string) $value);
            if ($stringValue !== '') {
                $cleanPayload[$key] = $stringValue;
            }
        }

        return $cleanPayload;
    }

    /** @param mixed $value */
    private function validateText($value, string $message, int $maxLength): void
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > $maxLength) {
            throw new ValidationException($message);
        }
    }

    /** @param mixed $value @param array<int, string> $allowedValues */
    private function normalizeChoice($value, array $allowedValues, string $message): string
    {
        $choice = mb_strtolower(trim((string) $value));
        if (!in_array($choice, $allowedValues, true)) {
            throw new ValidationException($message);
        }

        return $choice;
    }

    /** @param array<string, mixed> $rawInput */
    private function requireRandomId(array $rawInput): int
    {
        $randomId = $rawInput['randomn_id'] ?? $rawInput['id'] ?? null;
        if (!is_numeric($randomId) || (int) $randomId <= 0) {
            throw new ValidationException('Lipseste identificatorul furnizorului.');
        }

        return (int) $randomId;
    }
}
