<?php

namespace App\Services\SupplierSearchNew;

/**
 * Lightweight response adapter for aggregated in-memory payloads.
 */
class ArrayResponseAdapter
{
    public function __construct(
        private array $payload,
        private bool $ok = true
    ) {}

    public function successful(): bool
    {
        return $this->ok;
    }

    public function json(): array
    {
        return $this->payload;
    }

    public function body(): string
    {
        return json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]';
    }
}
