<?php

declare(strict_types=1);

namespace Besoiu\Services;

use RuntimeException;

/**
 * Preferințe navigație per-utilizator — strat personal peste registry.json.
 *
 * Stocare: app/Backend/storage/navigation/user_prefs/{userId}.json
 * Nu afectează registry-ul global și nu strică sync-ul modulelor.
 */
final class AdminNavUserPreferences
{
    public const SCHEMA = 'besoiu_nav_user_prefs_v1';

    private readonly string $baseDir;
    private readonly int $userId;

    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data */
    private function __construct(int $userId, string $baseDir, array $data)
    {
        $this->userId = $userId;
        $this->baseDir = $baseDir;
        $this->data = $data;
    }

    public static function forUser(int $userId, ?string $adminRoot = null): self
    {
        $root = $adminRoot ?? dirname(__DIR__, 2);
        $baseDir = $root . '/storage/navigation/user_prefs';
        $data = self::emptyPayload($userId);

        if ($userId > 0) {
            $file = $baseDir . '/' . $userId . '.json';
            if (is_file($file)) {
                $raw = file_get_contents($file);
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($decoded)) {
                    $data = array_merge($data, self::normalize($decoded, $userId));
                }
            }
        }

        return new self($userId, $baseDir, $data);
    }

    /** @return array<string, mixed> */
    private static function emptyPayload(int $userId): array
    {
        return [
            'schema' => self::SCHEMA,
            'user_id' => $userId,
            'updated_at' => null,
            'hidden_sections' => [],
            'hidden_items' => [],
            'section_order' => [],
        ];
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<string, mixed>
     */
    private static function normalize(array $decoded, int $userId): array
    {
        $hiddenSections = [];
        foreach ((array) ($decoded['hidden_sections'] ?? []) as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $hiddenSections[$key] = true;
            }
        }

        $hiddenItems = [];
        foreach ((array) ($decoded['hidden_items'] ?? []) as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $hiddenItems[$key] = true;
            }
        }

        $order = [];
        foreach ((array) ($decoded['section_order'] ?? []) as $sectionKey => $pos) {
            $sectionKey = trim((string) $sectionKey);
            if ($sectionKey !== '') {
                $order[$sectionKey] = (int) $pos;
            }
        }

        return [
            'user_id' => $userId,
            'hidden_sections' => array_keys($hiddenSections),
            'hidden_items' => array_keys($hiddenItems),
            'section_order' => $order,
        ];
    }

    /** @return list<string> */
    public function hiddenSections(): array
    {
        return array_values((array) ($this->data['hidden_sections'] ?? []));
    }

    /** @return list<string> */
    public function hiddenItems(): array
    {
        return array_values((array) ($this->data['hidden_items'] ?? []));
    }

    /** @return array<string, int> */
    public function sectionOrder(): array
    {
        return (array) ($this->data['section_order'] ?? []);
    }

    public function isSectionHidden(string $sectionKey): bool
    {
        return in_array($sectionKey, $this->hiddenSections(), true);
    }

    public function isItemHidden(string $itemId): bool
    {
        return in_array($itemId, $this->hiddenItems(), true);
    }

    public function hasAny(): bool
    {
        return $this->hiddenSections() !== []
            || $this->hiddenItems() !== []
            || $this->sectionOrder() !== [];
    }

    public function setSectionHidden(string $sectionKey, bool $hidden): self
    {
        $sectionKey = trim($sectionKey);
        if ($sectionKey === '') {
            throw new RuntimeException('Lipsește section_key.');
        }
        $current = $this->hiddenSections();
        $current = array_values(array_filter($current, static fn (string $k): bool => $k !== $sectionKey));
        if ($hidden) {
            $current[] = $sectionKey;
        }
        $this->data['hidden_sections'] = $current;
        $this->save();

        return $this;
    }

    public function setItemHidden(string $itemId, bool $hidden): self
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            throw new RuntimeException('Lipsește item_id (section_key::item_key).');
        }
        $current = $this->hiddenItems();
        $current = array_values(array_filter($current, static fn (string $k): bool => $k !== $itemId));
        if ($hidden) {
            $current[] = $itemId;
        }
        $this->data['hidden_items'] = $current;
        $this->save();

        return $this;
    }

    /** @param list<string> $orderedSectionKeys */
    public function setSectionOrder(array $orderedSectionKeys): self
    {
        $order = [];
        $pos = 0;
        foreach ($orderedSectionKeys as $key) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $pos += 10;
            $order[$key] = $pos;
        }
        $this->data['section_order'] = $order;
        $this->save();

        return $this;
    }

    public function reset(): self
    {
        $this->data = self::emptyPayload($this->userId);
        $file = $this->path();
        if (is_file($file)) {
            @unlink($file);
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'user_id' => $this->userId,
            'updated_at' => $this->data['updated_at'] ?? null,
            'hidden_sections' => $this->hiddenSections(),
            'hidden_items' => $this->hiddenItems(),
            'section_order' => $this->sectionOrder(),
        ];
    }

    private function path(): string
    {
        return $this->baseDir . '/' . $this->userId . '.json';
    }

    private function save(): void
    {
        if ($this->userId <= 0) {
            throw new RuntimeException('user_id invalid.');
        }
        if (!is_dir($this->baseDir) && !mkdir($this->baseDir, 0775, true) && !is_dir($this->baseDir)) {
            throw new RuntimeException('Nu pot crea storage/navigation/user_prefs/.');
        }
        $this->data['schema'] = self::SCHEMA;
        $this->data['user_id'] = $this->userId;
        $this->data['updated_at'] = date('c');

        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($this->path(), $json . "\n") === false) {
            throw new RuntimeException('Nu pot scrie preferințele de navigație.');
        }
    }
}
