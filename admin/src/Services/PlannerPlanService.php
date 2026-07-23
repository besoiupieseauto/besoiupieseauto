<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use InvalidArgumentException;
use PDO;

/**
 * Persistență pe server pentru Planner (audit #43).
 *
 * Un singur tabel `planner_plans`:
 *  - slot = 'current'  → planul de lucru al utilizatorului
 *  - slot = <uid>      → scenariu salvat (name = etichetă)
 */
final class PlannerPlanService
{
    private const CURRENT_SLOT = 'current';
    private const MAX_PAYLOAD_BYTES = 262144; // 256 KB
    private const MAX_PRESETS = 50;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getDB('default');
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS planner_plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                slot VARCHAR(64) NOT NULL DEFAULT 'current',
                name VARCHAR(120) NULL,
                payload LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_planner_user_slot (user_id, slot),
                KEY idx_planner_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * @return array{current: ?array<string,mixed>, presets: array<int, array<string,mixed>>}
     */
    public function load(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT slot, name, payload, updated_at FROM planner_plans WHERE user_id = :uid ORDER BY updated_at DESC'
        );
        $stmt->execute([':uid' => $userId]);

        $current = null;
        $presets = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $state = $this->decodePayload((string) ($row['payload'] ?? ''));
            if ($state === null) {
                continue;
            }
            if ($row['slot'] === self::CURRENT_SLOT) {
                $current = $state;
                continue;
            }
            $presets[] = [
                'id' => (string) $row['slot'],
                'name' => (string) ($row['name'] ?? 'Scenariu'),
                'savedAt' => (string) ($row['updated_at'] ?? ''),
                'state' => $state,
                'meta' => $this->extractMeta($state, (string) ($row['name'] ?? '')),
            ];
        }

        return ['current' => $current, 'presets' => $presets];
    }

    /** @param array<string,mixed> $state */
    public function saveCurrent(int $userId, array $state): void
    {
        $this->upsert($userId, self::CURRENT_SLOT, null, $state);
    }

    /**
     * @param array<string,mixed> $state
     * @return array{id:string, name:string, savedAt:string, state:array<string,mixed>}
     */
    public function savePreset(int $userId, string $id, string $name, array $state): array
    {
        $slot = $this->normalizeSlot($id);
        if ($slot === self::CURRENT_SLOT || $slot === '') {
            $slot = 'preset_' . bin2hex(random_bytes(5));
        }
        $name = $this->normalizeName($name, $state);

        if ($this->countPresets($userId) >= self::MAX_PRESETS && !$this->slotExists($userId, $slot)) {
            throw new InvalidArgumentException('Ai atins limita de scenarii salvate (' . self::MAX_PRESETS . ').');
        }

        $this->upsert($userId, $slot, $name, $state);

        return $this->presetRow($slot, $name, $state);
    }

    /**
     * @param array<string,mixed> $state
     * @return array{id:string, name:string, savedAt:string, state:array<string,mixed>, meta:array<string,mixed>}
     */
    public function updatePreset(int $userId, string $id, string $name, array $state): array
    {
        $slot = $this->normalizeSlot($id);
        if ($slot === '' || $slot === self::CURRENT_SLOT || !$this->slotExists($userId, $slot)) {
            throw new InvalidArgumentException('Scenariul nu există sau nu poate fi editat.');
        }

        $name = $this->normalizeName($name, $state);
        $this->upsert($userId, $slot, $name, $state);

        return $this->presetRow($slot, $name, $state);
    }

    /**
     * @param array<string,mixed> $state
     * @return array{id:string, name:string, savedAt:string, state:array<string,mixed>, meta:array<string,mixed>}
     */
    public function duplicatePreset(int $userId, string $id, string $name, array $state): array
    {
        $newSlot = 'preset_' . bin2hex(random_bytes(5));
        $name = $this->normalizeName($name !== '' ? $name : 'Copie scenariu', $state);

        if ($this->countPresets($userId) >= self::MAX_PRESETS) {
            throw new InvalidArgumentException('Ai atins limita de scenarii salvate (' . self::MAX_PRESETS . ').');
        }

        $this->upsert($userId, $newSlot, $name, $state);

        return $this->presetRow($newSlot, $name, $state);
    }

    public function deletePreset(int $userId, string $id): void
    {
        $slot = $this->normalizeSlot($id);
        if ($slot === '' || $slot === self::CURRENT_SLOT) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM planner_plans WHERE user_id = :uid AND slot = :slot');
        $stmt->execute([':uid' => $userId, ':slot' => $slot]);
    }

    public function resetCurrent(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM planner_plans WHERE user_id = :uid AND slot = :slot');
        $stmt->execute([':uid' => $userId, ':slot' => self::CURRENT_SLOT]);
    }

    /**
     * Importă scenarii din planul strategic (docs) — sare peste sloturile deja existente.
     *
     * @return array{imported: int, skipped: int, presets: array<int, array<string,mixed>>, source: string}
     */
    public function importStrategicPlans(int $userId, bool $skipExisting = true): array
    {
        require_once dirname(__DIR__, 2) . '/config/planner_strategic_plan.php';

        $catalog = planner_strategic_plan_catalog();
        $templates = $catalog['templates'] ?? [];
        $imported = 0;
        $skipped = 0;

        foreach ($templates as $template) {
            if (!is_array($template)) {
                continue;
            }

            $slot = $this->normalizeSlot((string) ($template['slot'] ?? ''));
            if ($slot === '' || $slot === self::CURRENT_SLOT) {
                continue;
            }

            if ($skipExisting && $this->slotExists($userId, $slot)) {
                $skipped++;
                continue;
            }

            if ($this->countPresets($userId) >= self::MAX_PRESETS && !$this->slotExists($userId, $slot)) {
                break;
            }

            $state = $this->buildStateFromStrategicTemplate($template);
            $name = (string) ($template['name'] ?? ($state['meta']['name'] ?? 'Scenariu strategic'));
            $this->upsert($userId, $slot, $name, $state);
            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'presets' => $this->load($userId)['presets'],
            'source' => (string) ($catalog['source'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private function buildStateFromStrategicTemplate(array $template): array
    {
        $meta = is_array($template['meta'] ?? null) ? $template['meta'] : [];

        return [
            'version' => 4,
            'meta' => [
                'name' => (string) ($meta['name'] ?? $template['name'] ?? 'Scenariu strategic'),
                'periodStart' => (string) ($meta['periodStart'] ?? ''),
                'periodEnd' => (string) ($meta['periodEnd'] ?? ''),
                'assumptions' => (string) ($meta['assumptions'] ?? ''),
                'type' => (string) ($meta['type'] ?? 'strategic'),
            ],
            'expenses' => $this->normalizeStrategicRows($template['expenses'] ?? []),
            'income' => $this->normalizeStrategicRows($template['income'] ?? []),
            'scenario' => $this->normalizeStrategicRows($template['scenario'] ?? []),
            'investments' => $this->normalizeStrategicRows($template['investments'] ?? []),
        ];
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string,mixed>>
     */
    private function normalizeStrategicRows($rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = [
                'id' => (string) ($row['id'] ?? ('row_' . bin2hex(random_bytes(4)))),
                'label' => (string) ($row['label'] ?? 'Linie'),
                'value' => (float) ($row['value'] ?? 0),
            ];
            if (!empty($row['calcKey'])) {
                $normalized['calcKey'] = (string) $row['calcKey'];
            }
            if (isset($row['step'])) {
                $normalized['step'] = (string) $row['step'];
            }
            if (isset($row['min'])) {
                $normalized['min'] = is_numeric($row['min']) ? (float) $row['min'] : 0;
            }
            if (isset($row['max'])) {
                $normalized['max'] = is_numeric($row['max']) ? (float) $row['max'] : 100;
            }
            $out[] = $normalized;
        }

        return $out;
    }

    /** @param array<string,mixed> $state */
    private function upsert(int $userId, string $slot, ?string $name, array $state): void
    {
        $payload = $this->encodePayload($state);
        $stmt = $this->pdo->prepare(
            'INSERT INTO planner_plans (user_id, slot, name, payload)
             VALUES (:uid, :slot, :name, :payload)
             ON DUPLICATE KEY UPDATE name = VALUES(name), payload = VALUES(payload)'
        );
        $stmt->execute([
            ':uid' => $userId,
            ':slot' => $slot,
            ':name' => $name,
            ':payload' => $payload,
        ]);
    }

    private function countPresets(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM planner_plans WHERE user_id = :uid AND slot <> :slot'
        );
        $stmt->execute([':uid' => $userId, ':slot' => self::CURRENT_SLOT]);

        return (int) $stmt->fetchColumn();
    }

    private function slotExists(int $userId, string $slot): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM planner_plans WHERE user_id = :uid AND slot = :slot LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':slot' => $slot]);

        return (bool) $stmt->fetchColumn();
    }

    private function normalizeSlot(string $id): string
    {
        $id = preg_replace('/[^A-Za-z0-9_\-]/', '', trim($id)) ?? '';

        return mb_substr($id, 0, 64);
    }

    /** @param array<string,mixed> $state */
    private function encodePayload(array $state): string
    {
        if (!$this->isValidState($state)) {
            throw new InvalidArgumentException('Structură plan invalidă.');
        }
        $json = json_encode($state, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException('Plan neserializabil.');
        }
        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            throw new InvalidArgumentException('Planul este prea mare pentru salvare.');
        }

        return $json;
    }

    /** @return array<string,mixed>|null */
    private function decodePayload(string $payload): ?array
    {
        if ($payload === '') {
            return null;
        }
        $decoded = json_decode($payload, true);

        return is_array($decoded) && $this->isValidState($decoded) ? $decoded : null;
    }

    /** @param mixed $state */
    private function isValidState($state): bool
    {
        if (!is_array($state)) {
            return false;
        }

        $version = (int) ($state['version'] ?? 0);

        return in_array($version, [3, 4], true)
            && is_array($state['expenses'] ?? null)
            && is_array($state['income'] ?? null)
            && is_array($state['scenario'] ?? null)
            && is_array($state['investments'] ?? null);
    }

    /** @param array<string,mixed> $state */
    private function normalizeName(string $name, array $state): string
    {
        $name = trim($name);
        if ($name === '' && is_array($state['meta'] ?? null)) {
            $name = trim((string) ($state['meta']['name'] ?? ''));
        }
        if ($name === '') {
            $name = 'Scenariu';
        }

        return mb_substr($name, 0, 120);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function extractMeta(array $state, string $fallbackName): array
    {
        $meta = is_array($state['meta'] ?? null) ? $state['meta'] : [];

        return [
            'name' => (string) ($meta['name'] ?? $fallbackName),
            'periodStart' => (string) ($meta['periodStart'] ?? ''),
            'periodEnd' => (string) ($meta['periodEnd'] ?? ''),
            'assumptions' => (string) ($meta['assumptions'] ?? ''),
            'type' => (string) ($meta['type'] ?? 'custom'),
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array{id:string, name:string, savedAt:string, state:array<string,mixed>, meta:array<string,mixed>}
     */
    private function presetRow(string $slot, string $name, array $state): array
    {
        return [
            'id' => $slot,
            'name' => $name,
            'savedAt' => date('c'),
            'state' => $state,
            'meta' => $this->extractMeta($state, $name),
        ];
    }
}
