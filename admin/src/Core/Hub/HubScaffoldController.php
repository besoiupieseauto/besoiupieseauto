<?php

declare(strict_types=1);

namespace Evasystem\Core\Hub;

use Evasystem\Exceptions\ValidationException;

/**
 * Controller CRUD pentru module hub cu tabele scaffold identice.
 */
abstract class HubScaffoldController
{
    protected const TABLE = '';
    protected const LABEL = 'Record';
    protected const SESSION_KEY = 'hub_record';

    private const FORBIDDEN_INPUT_KEYS = [
        'type_product',
        'type',
        'idusers',
        'randomnid',
        'usersveryfi',
        'experiences',
        'ridusers',
        'duct',
    ];

    private HubScaffoldService $service;

    public function __construct(?HubScaffoldService $service = null)
    {
        $this->service = $service ?? new HubScaffoldService(new HubScaffoldModel(static::TABLE));
    }

    /** @param array<string, mixed> $rawInput @return array{id: int} */
    public function add(array $rawInput): array
    {
        return $this->service->createRecord($this->buildPayload($rawInput, false));
    }

    /** @param array<string, mixed> $rawInput @return array{id: int} */
    public function update(array $rawInput): array
    {
        return $this->service->updateRecord($this->requireRecordId($rawInput), $this->buildPayload($rawInput, true));
    }

    /** @param array<string, mixed> $rawInput */
    public function changeStatus(array $rawInput): void
    {
        $status = $rawInput['status'] ?? 1;
        $this->service->changeStatus($this->requireRecordId($rawInput), ((int) $status) === 1 ? 1 : 0);
    }

    /** @param array<string, mixed> $rawInput */
    public function delete(array $rawInput): void
    {
        $this->service->deleteRecord($this->requireRecordId($rawInput));
    }

    /** @param array<string, mixed> $rawInput @return array<int, array<string, mixed>>|array{items:array,total:int,page:int,per_page:int,total_pages:int} */
    public function list(array $rawInput = [])
    {
        if (isset($rawInput['page']) || isset($rawInput['per_page'])) {
            return $this->service->listPaginated($rawInput);
        }

        return $this->service->listAll();
    }

    public function getService(): HubScaffoldService
    {
        return $this->service;
    }

    /** @param array<string, mixed> $rawInput @return array<string, scalar|null> */
    private function buildPayload(array $rawInput, bool $isUpdate): array
    {
        $payload = $this->sanitizePayload($rawInput);

        if (!$isUpdate || array_key_exists('name', $payload)) {
            $this->validateText($payload['name'] ?? null, 'Numele este obligatoriu.', 255);
        }

        if (isset($payload['email']) && $payload['email'] !== '') {
            if (!filter_var((string) $payload['email'], FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException('Emailul nu este valid.');
            }
        }

        if (isset($payload['status'])) {
            $payload['status'] = ((int) $payload['status']) === 1 ? 1 : 0;
        } elseif (!$isUpdate) {
            $payload['status'] = 1;
        }

        return $payload;
    }

    /** @param array<string, mixed> $rawInput @return array<string, scalar|null> */
    private function sanitizePayload(array $rawInput): array
    {
        $withoutForbidden = array_diff_key($rawInput, array_flip(self::FORBIDDEN_INPUT_KEYS));
        $cleanPayload = [];

        foreach ($withoutForbidden as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $stringValue = trim((string) $value);
            if ($stringValue !== '' || $key === 'status') {
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

    /** @param array<string, mixed> $rawInput */
    private function requireRecordId(array $rawInput): int
    {
        $id = $rawInput['id'] ?? $rawInput['randomn_id'] ?? null;
        if (!is_numeric($id) || (int) $id <= 0) {
            throw new ValidationException('Lipsește identificatorul înregistrării.');
        }

        return (int) $id;
    }
}
