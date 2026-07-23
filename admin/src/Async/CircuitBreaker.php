<?php

declare(strict_types=1);

namespace Besoiu\Async;

/**
 * Circuit breaker per serviciu extern (ex: RapidAPI, FTP).
 * După N eșecuri consecutive, refuză apelurile în cooldown.
 */
final class CircuitBreaker
{
    public function isOpen(string $service): bool
    {
        $state = $this->readState($service);
        if (($state['open_until'] ?? 0) > time()) {
            return true;
        }

        return false;
    }

    public function assertClosed(string $service): void
    {
        if ($this->isOpen($service)) {
            throw new \RuntimeException('Circuit breaker deschis pentru: ' . $service);
        }
    }

    public function recordSuccess(string $service): void
    {
        $this->writeState($service, ['failures' => 0, 'open_until' => 0]);
    }

    public function recordFailure(string $service): void
    {
        $threshold = (int) AsyncConfig::get('circuit_failure_threshold', 5);
        $cooldown = (int) AsyncConfig::get('circuit_cooldown_sec', 60);
        $state = $this->readState($service);
        $failures = (int) ($state['failures'] ?? 0) + 1;
        $openUntil = 0;

        if ($failures >= $threshold) {
            $openUntil = time() + $cooldown;
            JobLogger::log('circuit_open', ['service' => $service, 'failures' => $failures, 'open_until' => $openUntil]);
        }

        $this->writeState($service, ['failures' => $failures, 'open_until' => $openUntil]);
    }

    /** @return array{failures:int,open_until:int} */
    private function readState(string $service): array
    {
        $path = $this->statePath($service);
        if (!is_file($path)) {
            return ['failures' => 0, 'open_until' => 0];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : ['failures' => 0, 'open_until' => 0];
    }

    /** @param array{failures:int,open_until:int} $state */
    private function writeState(string $service, array $state): void
    {
        $dir = AsyncConfig::storageDir() . '/circuit';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($this->statePath($service), json_encode($state), LOCK_EX);
    }

    private function statePath(string $service): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '_', $service) ?: 'default';

        return AsyncConfig::storageDir() . '/circuit/' . $safe . '.json';
    }
}
