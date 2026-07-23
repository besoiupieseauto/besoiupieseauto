<?php declare(strict_types=1);

namespace Besoiu\Core;

/**
 * Performance monitoring și profiling
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class PerformanceMonitor
{
    private static ?self $instance = null;
    private array $timers = [];
    private array $metrics = [];
    private float $startTime;
    private int $startMemory;

    private function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function startTimer(string $name): void
    {
        $this->timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }

    public function stopTimer(string $name): ?array
    {
        if (!isset($this->timers[$name])) {
            return null;
        }

        $timer = $this->timers[$name];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $result = [
            'name' => $name,
            'duration' => round(($endTime - $timer['start']) * 1000, 2),
            'memory_used' => $endMemory - $timer['memory_start'],
            'memory_peak' => memory_get_peak_usage(true)
        ];

        $this->metrics[$name] = $result;
        unset($this->timers[$name]);

        return $result;
    }

    public function recordMetric(string $name, mixed $value, array $tags = []): void
    {
        $this->metrics[$name] = [
            'value' => $value,
            'timestamp' => time(),
            'tags' => $tags
        ];
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getCurrentStats(): array
    {
        return [
            'execution_time' => round((microtime(true) - $this->startTime) * 1000, 2),
            'memory_usage' => memory_get_usage(true) - $this->startMemory,
            'memory_peak' => memory_get_peak_usage(true),
            'active_timers' => array_keys($this->timers),
            'total_metrics' => count($this->metrics)
        ];
    }

    public function profileFunction(callable $function, string $name = null): mixed
    {
        $timerName = $name ?? 'anonymous_function_' . uniqid();
        
        $this->startTimer($timerName);
        $result = $function();
        $this->stopTimer($timerName);

        return $result;
    }

    public function exportReport(): array
    {
        return [
            'summary' => $this->getCurrentStats(),
            'metrics' => $this->getMetrics(),
            'system_info' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'server_load' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 'N/A (Windows)'
            ]
        ];
    }

    public function saveReport(string $filename = null): string
    {
        $filename = $filename ?? 'performance_' . date('Y-m-d_H-i-s') . '.json';
        $reportPath = dirname(__DIR__, 2) . '/storage/performance/' . $filename;
        
        $reportDir = dirname($reportPath);
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0775, true);
        }

        file_put_contents($reportPath, json_encode($this->exportReport(), JSON_PRETTY_PRINT));
        
        return $reportPath;
    }
}