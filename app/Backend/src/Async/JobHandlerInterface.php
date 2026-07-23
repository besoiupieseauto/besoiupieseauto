<?php

declare(strict_types=1);

namespace Besoiu\Async;

interface JobHandlerInterface
{
    /** @param array<string, mixed> $payload @param callable(int,string):void $reportProgress */
    public function handle(array $payload, callable $reportProgress): array;
}
