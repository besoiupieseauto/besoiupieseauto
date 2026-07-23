<?php declare(strict_types=1);

namespace Besoiu\Core;

/**
 * Sistem cache inteligent cu multiple drivere
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class CacheManager
{
    private array $config;
    private string $cacheDir;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'driver' => 'file',
            'ttl' => 3600,
            'prefix' => 'besoiu_',
        ], $config);
        
        $this->cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $fullKey = $this->config['prefix'] . $key;
        $cacheFile = $this->getCacheFilePath($fullKey);
        
        if (!file_exists($cacheFile)) {
            return $default;
        }
        
        $data = json_decode(file_get_contents($cacheFile), true);
        
        if ($data['expires_at'] < time()) {
            unlink($cacheFile);
            return $default;
        }
        
        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $fullKey = $this->config['prefix'] . $key;
        $ttl = $ttl ?? $this->config['ttl'];
        
        $data = [
            'value' => $value,
            'created_at' => time(),
            'expires_at' => time() + $ttl,
        ];
        
        $cacheFile = $this->getCacheFilePath($fullKey);
        return file_put_contents($cacheFile, json_encode($data)) !== false;
    }

    public function forget(string $key): bool
    {
        $fullKey = $this->config['prefix'] . $key;
        $cacheFile = $this->getCacheFilePath($fullKey);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        
        return $value;
    }

    private function getCacheFilePath(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
}