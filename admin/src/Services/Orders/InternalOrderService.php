<?php

declare(strict_types=1);

namespace Evasystem\Services\Orders;

use PDO;

/**
 * @deprecated Folosește LegacyOrderService — păstrat pentru compatibilitate M1b.
 */
final class InternalOrderService
{
    private LegacyOrderService $legacy;

    public function __construct(?PDO $pdo, OrderTmpService $tmpService)
    {
        $this->legacy = new LegacyOrderService($pdo, $tmpService);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createFromTmp(string $sessionId, int $userId, array $payload): array
    {
        return $this->legacy->createInternalFromTmp($sessionId, $userId, $payload);
    }

    /** @return array<int, array{value:int,label:string}> */
    public static function statusOptions(): array
    {
        return LegacyOrderService::statusOptions();
    }

    /** @return array<int, array{value:int,label:string}> */
    public static function locationOptions(): array
    {
        return array_values(array_filter(
            LegacyOrderService::locationOptions(),
            static fn (array $item): bool => (int) ($item['value'] ?? 0) !== 3
        ));
    }
}
