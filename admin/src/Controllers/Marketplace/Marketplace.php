<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Marketplace;

use Evasystem\Exceptions\ValidationException;

/**
 * Controller pentru conexiunile marketplace.
 */
final class Marketplace
{
    private const FORBIDDEN_INPUT_KEYS = ['type_product', 'type', 'id', 'idusers', 'randomnid', 'usersveryfi', 'experiences'];
    private const ALLOWED_PLATFORMS = ['pieseauto', 'dezro', 'olx', 'facebook', 'baselinker', 'whatsapp', 'custom'];
    private const ALLOWED_TOKEN_STATUSES = ['active', 'expired', 'disabled', 'pending'];
    private const ALLOWED_TOKEN_PLANS = ['free', 'paid'];
    private const ALLOWED_SYNC_MODES = ['manual', 'automatic', 'scheduled'];

    public function __construct(
        private readonly MarketplaceService $marketplaceService
    ) {
    }

    /** @param array<string, mixed> $rawInput @return array{randomn_id:int} */
    public function add(array $rawInput): array
    {
        return $this->marketplaceService->createMarketplace($this->buildMarketplacePayload($rawInput, false));
    }

    /** @param array<string, mixed> $rawInput @return array{randomn_id:int} */
    public function update(array $rawInput): array
    {
        return $this->marketplaceService->updateMarketplace(
            $this->requireRandomId($rawInput),
            $this->buildMarketplacePayload($rawInput, true)
        );
    }

    /** @param array<string, mixed> $rawInput */
    public function changeStatus(array $rawInput): void
    {
        $this->marketplaceService->changeMarketplaceStatus(
            $this->requireRandomId($rawInput),
            $this->normalizeChoice($rawInput['token_status'] ?? null, self::ALLOWED_TOKEN_STATUSES, 'Statusul tokenului nu este valid.')
        );
    }

    /** @param array<string, mixed> $rawInput @return array<string, string|null> */
    public function test(array $rawInput): array
    {
        return $this->marketplaceService->testMarketplace($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput */
    public function delete(array $rawInput): void
    {
        $this->marketplaceService->deleteMarketplace($this->requireRandomId($rawInput));
    }

    /** @return array<int, array<string, mixed>> */
    public function list(): array
    {
        return $this->marketplaceService->listMarketplaces();
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function find(array $rawInput): array
    {
        return $this->marketplaceService->findMarketplace($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput @return array{last_test_status:string,last_test_message:string,inventories?:array<int,array<string,mixed>>} */
    public function testBaseLinker(array $rawInput): array
    {
        return $this->marketplaceService->testBaseLinkerConnection($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput @return array<int, array<string, mixed>> */
    public function baselinkerInventories(array $rawInput): array
    {
        return $this->marketplaceService->getBaseLinkerInventories($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function baselinkerConfig(array $rawInput): array
    {
        return $this->marketplaceService->getBaseLinkerConfig($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput @return array{randomn_id:int,field_mapping:array<string,string>} */
    public function baselinkerSaveMapping(array $rawInput): array
    {
        $mapping = $rawInput['field_mapping'] ?? null;
        if (!is_array($mapping)) {
            throw new ValidationException('Maparea câmpurilor lipsește.');
        }

        /** @var array<string, string> $clean */
        $clean = [];
        foreach ($mapping as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $clean[$key] = trim((string) $value);
            }
        }

        return $this->marketplaceService->saveBaseLinkerFieldMapping($this->requireRandomId($rawInput), $clean);
    }

    /** @param array<string, mixed> $rawInput @return array{randomn_id:int,bl_inventory_id:int} */
    public function baselinkerSaveInventory(array $rawInput): array
    {
        $inventoryId = $rawInput['bl_inventory_id'] ?? $rawInput['inventory_id'] ?? null;
        if (!is_numeric($inventoryId)) {
            throw new ValidationException('ID inventar BaseLinker lipsă.');
        }

        return $this->marketplaceService->saveBaseLinkerInventory($this->requireRandomId($rawInput), (int) $inventoryId);
    }

    /** @param array<string, mixed> $rawInput @return array{status:string,message:string,synced:int,failed:int,errors:array<int,string>,has_more?:bool,offset?:int,total_products?:int} */
    public function baselinkerSyncProducts(array $rawInput): array
    {
        $options = [
            'limit' => $rawInput['limit'] ?? 50,
            'offset' => $rawInput['offset'] ?? 0,
            'product_randomn_ids' => $rawInput['product_randomn_ids'] ?? $rawInput['ids'] ?? [],
        ];

        return $this->marketplaceService->syncProductsToBaseLinker($this->requireRandomId($rawInput), $options);
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function baselinkerCatalogStats(array $rawInput): array
    {
        return $this->marketplaceService->getBaseLinkerCatalogStats($this->requireRandomId($rawInput));
    }

    /** @param array<string, mixed> $rawInput @return array<string, mixed> */
    public function baselinkerEnqueueCatalog(array $rawInput): array
    {
        return $this->marketplaceService->enqueueBaseLinkerCatalogSync($this->requireRandomId($rawInput), [
            'limit' => $rawInput['limit'] ?? $rawInput['batch_size'] ?? 50,
        ]);
    }

    /** @return array<string, mixed> */
    public function baselinkerFeedInfo(array $rawInput): array
    {
        unset($rawInput);

        return $this->marketplaceService->getBaseLinkerFeedInfo();
    }

    /** @return array<string, mixed> */
    public function baselinkerFeedRegenerate(array $rawInput): array
    {
        unset($rawInput);

        return $this->marketplaceService->regenerateBaseLinkerFeed();
    }

    /** @return array<string, mixed> */
    public function baselinkerStoreImportInfo(array $rawInput): array
    {
        unset($rawInput);

        return $this->marketplaceService->getBaseLinkerStoreImportInfo();
    }

    /** @param array<string, mixed> $rawInput @return array<string, string|int|null> */
    private function buildMarketplacePayload(array $rawInput, bool $isUpdate): array
    {
        $payload = $this->sanitizePayload($rawInput);

        if (!$isUpdate || isset($payload['name'])) {
            $this->validateText($payload['name'] ?? null, 'Numele marketplace-ului este obligatoriu.', 255);
        }

        foreach ([
            'platform' => self::ALLOWED_PLATFORMS,
            'token_status' => self::ALLOWED_TOKEN_STATUSES,
            'token_plan' => self::ALLOWED_TOKEN_PLANS,
            'sync_mode' => self::ALLOWED_SYNC_MODES,
        ] as $field => $allowedValues) {
            if (isset($payload[$field])) {
                $payload[$field] = $this->normalizeChoice($payload[$field], $allowedValues, "Campul {$field} nu este valid.");
            }
        }

        $payload['platform'] = $payload['platform'] ?? 'custom';
        $payload['token_status'] = $payload['token_status'] ?? 'active';
        $payload['token_plan'] = $payload['token_plan'] ?? 'free';
        $payload['sync_mode'] = $payload['sync_mode'] ?? 'manual';

        if (isset($payload['account_email']) && !filter_var($payload['account_email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Email-ul contului nu este valid.');
        }

        foreach (['api_base_url', 'webhook_url'] as $urlField) {
            if (isset($payload[$urlField]) && !filter_var($payload[$urlField], FILTER_VALIDATE_URL)) {
                throw new ValidationException("URL-ul {$urlField} nu este valid.");
            }
        }

        foreach (['products_synced', 'requests_today', 'offers_sent', 'errors_count'] as $integerField) {
            if (isset($payload[$integerField])) {
                if (!is_numeric($payload[$integerField]) || (int) $payload[$integerField] < 0) {
                    throw new ValidationException("Campul {$integerField} trebuie sa fie numeric pozitiv.");
                }
                $payload[$integerField] = (int) $payload[$integerField];
            }
        }

        if (isset($payload['bl_inventory_id'])) {
            if (!is_numeric($payload['bl_inventory_id']) || (int) $payload['bl_inventory_id'] < 0) {
                throw new ValidationException('bl_inventory_id trebuie sa fie numeric pozitiv.');
            }
            $payload['bl_inventory_id'] = (int) $payload['bl_inventory_id'];
        }

        if (isset($payload['field_mapping']) && is_array($payload['field_mapping'])) {
            $payload['field_mapping'] = json_encode($payload['field_mapping'], JSON_UNESCAPED_UNICODE);
        }

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
            throw new ValidationException('Lipseste identificatorul marketplace.');
        }

        return (int) $randomId;
    }
}
