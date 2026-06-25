<?php

namespace App\Services\SupplierSearchNew;

use Psr\Http\Message\ResponseInterface;

/**
 * Wraps a Guzzle/PSR response so ResultBuilder can use ->successful(), ->json(), ->body().
 */
class GuzzleResponseAdapter
{
    private string $body = '';

    public function __construct(
        private ?ResponseInterface $response
    ) {
        if ($this->response !== null && $this->response->getBody()) {
            $this->body = (string) $this->response->getBody();
        }
    }

    public function successful(): bool
    {
        return $this->response !== null
            && $this->response->getStatusCode() >= 200
            && $this->response->getStatusCode() < 300;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if ($this->body === '') {
            return [];
        }
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function body(): string
    {
        return $this->body;
    }
}
