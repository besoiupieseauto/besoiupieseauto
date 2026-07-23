<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Auth\AdminSectionDashboardCatalog;
use Besoiu\Core\Auth\AdminWorkspace;
use Besoiu\Core\Auth\AdminWorkspaceCatalog;
use Besoiu\Core\Module\ModuleGate;
use Besoiu\Core\Module\ModuleManifest;
use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Core\Module\ModulesPaths;
use RuntimeException;

/** Registru navigație — app/Backend/storage/navigation/registry.json */
final class AdminNavJsonRegistry
{
    public const SCHEMA = 'besoiu_nav_registry_v1';

    private readonly string $adminRoot;
    private readonly string $registryPath;

    public function __construct(?string $adminRoot = null)
    {
        $this->adminRoot = $adminRoot ?? dirname(__DIR__, 2);
        $this->registryPath = $this->adminRoot . '/storage/navigation/registry.json';
    }

    public function registryPath(): string { return $this->registryPath; }
    public function isReady(): bool { return is_file($this->registryPath); }

    /** @var array<string, bool> */
    private static array $bootstrappedInRequest = [];

    public function ensureBootstrapped(bool $syncModules = false): void
    {
        $memoKey = $this->registryPath . '|' . ($syncModules ? '1' : '0');
        if (!empty(self::$bootstrappedInRequest[$memoKey])) {
            return;
        }

        if (!is_file($this->registryPath)) {
            $this->bootstrapFromSeed();
        } else {
            $data = $this->readRaw();
            $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
            if ($sections === []) {
                $this->bootstrapFromSeed();
            }
        }
        $this->maybeSanitizeRegistry();
        $this->ensureCoreSeedSectionsPresent();
        if ($this->registryHasSections()) {
            $this->ensureCoreNavHub();
            $this->ensureAdminPanelSidebar();
        }
        if ($syncModules) {
            $this->syncAllModules();
            if ($this->registryHasSections()) {
                $this->ensureCoreNavHub();
                $this->ensureAdminPanelSidebar();
            }
        }

        self::$bootstrappedInRequest[$memoKey] = true;
        // syncModules=false e acoperit; dacă apoi vine sync=true, permite re-run.
        if ($syncModules) {
            self::$bootstrappedInRequest[$this->registryPath . '|0'] = true;
        }
    }

    private function registryHasSections(): bool
    {
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];

        return $sections !== [];
    }

    /** @return array<string, mixed> */
    public function readRaw(): array
    {
        if (!is_file($this->registryPath)) {
            return ['schema' => self::SCHEMA, 'updated_at' => null, 'sections' => []];
        }
        $raw = file_get_contents($this->registryPath);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) ? $data : ['schema' => self::SCHEMA, 'updated_at' => null, 'sections' => []];
    }

    /** @param array<string, mixed> $data */
    public function writeRaw(array $data): void
    {
        $data['schema'] = self::SCHEMA;
        $data['updated_at'] = date('c');
        if (!isset($data['sections']) || !is_array($data['sections'])) {
            $data['sections'] = [];
        }
        $data['sections'] = $this->sanitizeSections($data['sections']);
        $dir = dirname($this->registryPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Nu pot crea storage/navigation/.');
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($this->registryPath, $json . "\n") === false) {
            throw new RuntimeException('Nu pot scrie registry.json.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function listTreeForAdmin(): array
    {
        $this->ensureBootstrapped(false);
        return $this->buildTree($this->readRaw(), false);
    }

    /** @param array{new_orders?: int, abandoned_carts?: int, unread_messages?: int} $badges */
    public function listTreeForRender(array $badges = []): array
    {
        $this->ensureBootstrapped(false);
        return $this->buildTree($this->readRaw(), true, $badges);
    }

    /** @return array<string, mixed> */
    public function syncAllModules(): array
    {
        $registry = ModuleRegistry::instance($this->adminRoot);
        $registry->discover();
        $synced = [];
        foreach ($registry->allManifests() as $id => $manifest) {
            $synced[$id] = $this->syncModule((string) $id, $manifest, ModuleGate::enabled((string) $id));
        }
        $this->deactivateOrphanModuleSections(array_keys($registry->allManifests()));
        return ['synced' => $synced, 'path' => $this->registryPath];
    }

    public function syncModule(string $moduleId, ?ModuleManifest $manifest = null, ?bool $enabled = null): bool
    {
        $moduleId = strtolower(trim($moduleId));
        if ($moduleId === '') {
            return false;
        }
        if (!is_file($this->registryPath)) {
            $this->bootstrapFromSeed();
        }
        $registry = ModuleRegistry::instance($this->adminRoot);
        $registry->discover();
        $manifest ??= $registry->allManifests()[$moduleId] ?? null;
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];

        if ($manifest === null) {
            $this->setModuleSectionActiveInData($sections, $moduleId, false);
            $data['sections'] = $sections;
            $this->writeRaw($data);
            return false;
        }

        $enabled ??= ModuleGate::enabled($moduleId);
        $navItems = $manifest->navItems();
        if ($navItems === []) {
            $this->setModuleSectionActiveInData($sections, $moduleId, false);
            $data['sections'] = $sections;
            $this->writeRaw($data);
            return false;
        }

        $sectionKey = 'mod_' . $moduleId;
        $navSectionMeta = $manifest->raw()['nav_section'] ?? [];
        $groupLabel = is_array($navSectionMeta) ? (string) ($navSectionMeta['group_label'] ?? '') : '';
        $meta = ModuleGate::optionalMap()[$moduleId] ?? [];
        $workspace = AdminWorkspaceCatalog::workspaceForModuleId($moduleId, is_array($meta) ? $meta : []);

        $sectionIndex = null;
        foreach ($sections as $i => $sec) {
            if (is_array($sec) && (string) ($sec['section_key'] ?? '') === $sectionKey) {
                $sectionIndex = $i;
                break;
            }
        }

        if ($sectionIndex === null) {
            $sections[] = [
                'section_key' => $sectionKey,
                'module_id' => $moduleId,
                'label' => $manifest->name(),
                'group_label' => $groupLabel !== '' ? $groupLabel : null,
                'workspace' => $workspace,
                'sort_order' => $this->moduleSortOrder($moduleId),
                'is_active' => $enabled,
                'source' => 'module',
                'customized' => false,
                'items' => [],
            ];
            $sectionIndex = array_key_last($sections);
        }

        $section = $sections[$sectionIndex];
        $customized = (bool) ($section['customized'] ?? false);
        if (!$customized) {
            $section['label'] = $manifest->name();
            if ($groupLabel !== '') {
                $section['group_label'] = $groupLabel;
            }
            $section['workspace'] = $workspace;
        }
        $section['module_id'] = $moduleId;
        $section['source'] = 'module';
        if (!$customized) {
            $section['is_active'] = $enabled;
        } elseif (!$enabled) {
            $section['is_active'] = false;
        }

        $itemsByKey = [];
        foreach ((array) ($section['items'] ?? []) as $item) {
            if (is_array($item) && ($item['item_key'] ?? '') !== '') {
                $itemsByKey[(string) $item['item_key']] = $item;
            }
        }

        $order = 0;
        $seen = [];
        foreach ($navItems as $navItem) {
            $itemKey = (string) ($navItem['id'] ?? '');
            if ($itemKey === '') {
                continue;
            }
            $seen[] = $itemKey;
            $order += 10;
            $existing = $itemsByKey[$itemKey] ?? null;
            $itemCustomized = is_array($existing) && (bool) ($existing['customized'] ?? false);
            $row = [
                'item_key' => $itemKey,
                'module_id' => $moduleId,
                'item_type' => 'link',
                'label' => $itemCustomized ? (string) ($existing['label'] ?? $itemKey) : (string) ($navItem['label'] ?? $itemKey),
                'url' => $itemCustomized ? (string) ($existing['url'] ?? '#') : (string) ($navItem['href'] ?? '#'),
                'icon' => $itemCustomized ? (string) ($existing['icon'] ?? 'puzzle') : (string) ($navItem['icon'] ?? 'puzzle'),
                'badge_key' => $existing['badge_key'] ?? null,
                'alert_key' => $existing['alert_key'] ?? null,
                'is_global' => (bool) ($existing['is_global'] ?? false),
                'open_new_tab' => (bool) ($existing['open_new_tab'] ?? false),
                'sort_order' => $itemCustomized ? (int) ($existing['sort_order'] ?? $order) : $order,
                'is_active' => $enabled
                    ? ($itemCustomized ? $this->itemIsActive($existing) : true)
                    : false,
                'source' => 'module',
                'customized' => $itemCustomized,
            ];
            if ($moduleId === 'messages' && $itemKey === 'messages') {
                $row['badge_key'] = 'unread_messages';
            }
            $itemsByKey[$itemKey] = $row;
        }

        foreach ($itemsByKey as $ik => $item) {
            if ((string) ($item['module_id'] ?? '') === $moduleId && !in_array($ik, $seen, true)) {
                $item['is_active'] = false;
                $itemsByKey[$ik] = $item;
            }
        }

        usort($itemsByKey, static fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));
        $section['items'] = array_values($itemsByKey);
        $sections[$sectionIndex] = $section;

        usort($sections, static fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));
        $data['sections'] = $sections;
        $this->writeRaw($data);
        return true;
    }

    public function onModuleDisabled(string $moduleId): void
    {
        if (!is_file($this->registryPath)) {
            return;
        }
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $this->setModuleSectionActiveInData($sections, strtolower(trim($moduleId)), false);
        $data['sections'] = $sections;
        $this->writeRaw($data);
    }

    public function onModuleUninstalled(string $moduleId): void
    {
        if (!is_file($this->registryPath)) {
            return;
        }
        $moduleId = strtolower(trim($moduleId));
        $data = $this->readRaw();
        $sections = [];
        foreach ($data['sections'] ?? [] as $section) {
            if (!is_array($section) || (string) ($section['module_id'] ?? '') === $moduleId) {
                continue;
            }
            $sections[] = $section;
        }
        $data['sections'] = $sections;
        $this->writeRaw($data);
    }

    /** @param array<string, mixed> $payload */
    public function updateItem(string $sectionKey, string $itemKey, array $payload): array
    {
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $found = null;
        foreach ($sections as $si => $section) {
            if (!is_array($section) || (string) ($section['section_key'] ?? '') !== $sectionKey) {
                continue;
            }
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];
            foreach ($items as $ii => $item) {
                if (!is_array($item) || (string) ($item['item_key'] ?? '') !== $itemKey) {
                    continue;
                }
                foreach (['label', 'url', 'icon'] as $f) {
                    if (array_key_exists($f, $payload)) {
                        $item[$f] = (string) $payload[$f];
                    }
                }
                foreach (['is_global', 'open_new_tab'] as $f) {
                    if (array_key_exists($f, $payload)) {
                        $item[$f] = $this->normalizeActiveFlag($payload[$f]);
                    }
                }
                if (array_key_exists('is_active', $payload)) {
                    $item['is_active'] = $this->normalizeActiveFlag($payload['is_active']);
                }
                if (array_key_exists('sort_order', $payload)) {
                    $item['sort_order'] = (int) $payload['sort_order'];
                }
                $item['customized'] = true;
                $sections[$si]['items'][$ii] = $item;
                $found = $item;
                break 2;
            }
        }
        if ($found === null) {
            throw new RuntimeException('Link inexistent.');
        }
        $data['sections'] = $sections;
        $this->writeRaw($data);
        return $found;
    }

    /** @param array<string, mixed> $payload */
    public function updateSection(string $sectionKey, array $payload): array
    {
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $found = null;
        foreach ($sections as $si => $section) {
            if (!is_array($section) || (string) ($section['section_key'] ?? '') !== $sectionKey) {
                continue;
            }
            if (array_key_exists('label', $payload)) {
                $section['label'] = (string) $payload['label'];
            }
            if (array_key_exists('group_label', $payload)) {
                $section['group_label'] = $payload['group_label'] === null ? null : (string) $payload['group_label'];
            }
            if (array_key_exists('sort_order', $payload)) {
                $section['sort_order'] = (int) $payload['sort_order'];
            }
            if (array_key_exists('is_active', $payload)) {
                $section['is_active'] = $this->normalizeActiveFlag($payload['is_active']);
                if (!$section['is_active']) {
                    $items = is_array($section['items'] ?? null) ? $section['items'] : [];
                    foreach ($items as $ii => $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $item['is_active'] = false;
                        $item['customized'] = true;
                        $items[$ii] = $item;
                    }
                    $section['items'] = $items;
                }
            }
            $section['customized'] = true;
            $sections[$si] = $section;
            $found = $section;
            break;
        }
        if ($found === null) {
            throw new RuntimeException('Secțiune inexistentă.');
        }
        $data['sections'] = $sections;
        $this->writeRaw($data);
        return $found;
    }

    /** @param array<string, mixed> $payload */
    public function addManualSection(array $payload): array
    {
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $key = trim((string) ($payload['section_key'] ?? ''));
        if ($key === '') {
            $key = 'manual_' . bin2hex(random_bytes(4));
        }
        $section = [
            'section_key' => $key,
            'module_id' => null,
            'label' => (string) ($payload['label'] ?? 'Secțiune nouă'),
            'group_label' => $payload['group_label'] ?? null,
            'workspace' => $payload['workspace'] ?? 'company',
            'sort_order' => (int) ($payload['sort_order'] ?? 500),
            'is_active' => true,
            'source' => 'manual',
            'customized' => true,
            'items' => [],
        ];
        $sections[] = $section;
        $data['sections'] = $sections;
        $this->writeRaw($data);
        return $section;
    }

    /** @param array<string, mixed> $payload */
    public function addManualItem(string $sectionKey, array $payload): array
    {
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $created = null;
        foreach ($sections as $si => $section) {
            if (!is_array($section) || (string) ($section['section_key'] ?? '') !== $sectionKey) {
                continue;
            }
            $itemKey = trim((string) ($payload['item_key'] ?? ''));
            if ($itemKey === '') {
                $itemKey = 'link_' . bin2hex(random_bytes(4));
            }
            $row = [
                'item_key' => $itemKey,
                'module_id' => null,
                'item_type' => (string) ($payload['item_type'] ?? 'link'),
                'label' => (string) ($payload['label'] ?? 'Link nou'),
                'url' => (string) ($payload['url'] ?? '/admin/dashboard'),
                'icon' => (string) ($payload['icon'] ?? 'link'),
                'is_global' => (bool) ($payload['is_global'] ?? false),
                'open_new_tab' => (bool) ($payload['open_new_tab'] ?? false),
                'sort_order' => (int) ($payload['sort_order'] ?? 100),
                'is_active' => true,
                'source' => 'manual',
                'customized' => true,
            ];
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];
            $items[] = $row;
            $sections[$si]['items'] = array_values($items);
            $created = $row;
            break;
        }
        if ($created === null) {
            throw new RuntimeException('Secțiune inexistentă.');
        }
        $data['sections'] = $sections;
        $this->writeRaw($data);
        return $created;
    }

    public function deleteItem(string $sectionKey, string $itemKey): bool
    {
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $found = false;
        foreach ($sections as $si => $section) {
            if (!is_array($section) || (string) ($section['section_key'] ?? '') !== $sectionKey) {
                continue;
            }
            $next = [];
            foreach ((array) ($section['items'] ?? []) as $item) {
                if (!is_array($item) || (string) ($item['item_key'] ?? '') !== $itemKey) {
                    $next[] = $item;
                    continue;
                }
                $found = true;
                if ((string) ($item['source'] ?? '') === 'manual') {
                    continue;
                }
                $item['is_active'] = false;
                $item['customized'] = true;
                $next[] = $item;
            }
            $sections[$si]['items'] = array_values($next);
            if ($found) {
                $sections[$si]['customized'] = true;
            }
            break;
        }
        if (!$found) {
            throw new RuntimeException('Link inexistent.');
        }
        $data['sections'] = $sections;
        $this->writeRaw($data);
        return true;
    }

    /** @return array{workspace: string, sections: int, items: int} */
    public function hideWorkspace(string $workspaceId): array
    {
        $workspaceId = strtolower(trim($workspaceId));
        if ($workspaceId === '') {
            throw new RuntimeException('Lipsește workspace_id.');
        }

        $adminPanelKeys = ['core_dashboard', 'core_produse', 'mod_calculator'];
        $zoneSectionKeys = $workspaceId === 'admin_panel' ? $adminPanelKeys : [];

        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $hiddenSections = 0;
        $hiddenItems = 0;

        foreach ($sections as $si => $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionKey = (string) ($section['section_key'] ?? '');
            $sectionWs = (string) ($section['workspace'] ?? 'company');

            $inZone = $zoneSectionKeys !== []
                ? in_array($sectionKey, $zoneSectionKeys, true)
                : (!in_array($sectionKey, $adminPanelKeys, true) && $sectionWs === $workspaceId);

            if (!$inZone) {
                continue;
            }

            if ($this->itemIsActive($section)) {
                $hiddenSections++;
            }
            $section['is_active'] = false;
            $section['customized'] = true;

            $items = is_array($section['items'] ?? null) ? $section['items'] : [];
            foreach ($items as $ii => $item) {
                if (!is_array($item)) {
                    continue;
                }
                if ($this->itemIsActive($item)) {
                    $hiddenItems++;
                }
                $item['is_active'] = false;
                $item['customized'] = true;
                $items[$ii] = $item;
            }
            $section['items'] = $items;
            $sections[$si] = $section;
        }

        if ($hiddenSections === 0 && $hiddenItems === 0) {
            throw new RuntimeException('Nicio secțiune activă în această zonă.');
        }

        $data['sections'] = $sections;
        $this->writeRaw($data);

        return [
            'workspace' => $workspaceId,
            'sections' => $hiddenSections,
            'items' => $hiddenItems,
        ];
    }

    public function resetModuleNav(string $moduleId): bool
    {
        $moduleId = strtolower(trim($moduleId));
        $registry = ModuleRegistry::instance($this->adminRoot);
        $registry->discover();
        $manifest = $registry->allManifests()[$moduleId] ?? null;
        if ($manifest === null) {
            return false;
        }
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        foreach ($sections as $si => $section) {
            if (!is_array($section) || (string) ($section['module_id'] ?? '') !== $moduleId) {
                continue;
            }
            $section['customized'] = false;
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];
            foreach ($items as $ii => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $item['customized'] = false;
                $items[$ii] = $item;
            }
            $section['items'] = $items;
            $sections[$si] = $section;
        }
        $data['sections'] = $sections;
        $this->writeRaw($data);
        return $this->syncModule($moduleId, $manifest, ModuleGate::enabled($moduleId));
    }

    /** @param array<string, mixed> $decoded */
    public function importDecoded(array $decoded): void
    {
        $this->writeRaw($decoded);
    }

    private function bootstrapFromSeed(): void
    {
        $this->writeRaw(['sections' => $this->buildSeedSections()]);
    }

    /** Re-adaugă secțiuni CORE din seed dacă lipsesc (registry parțial corupt). */
    private function ensureCoreSeedSectionsPresent(): void
    {
        $seedSections = $this->buildSeedSections();
        if ($seedSections === []) {
            return;
        }

        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $existingKeys = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if ($key !== '') {
                $existingKeys[$key] = true;
            }
        }

        $added = false;
        foreach ($seedSections as $seedSection) {
            $key = (string) ($seedSection['section_key'] ?? '');
            if ($key === '' || isset($existingKeys[$key])) {
                continue;
            }
            $sections[] = $seedSection;
            $existingKeys[$key] = true;
            $added = true;
        }

        if (!$added) {
            return;
        }

        usort($sections, static fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));
        $data['sections'] = $sections;
        $this->writeRaw($data);
    }

    /** @return list<array<string, mixed>> */
    private function buildSeedSections(): array
    {
        $seedFile = ModulesPaths::adminRoot() . '/config/admin_nav_seed.php';
        if (!is_file($seedFile)) {
            return [];
        }
        /** @var list<array<string, mixed>> $seedSections */
        $seedSections = require $seedFile;
        $sections = [];
        foreach ($seedSections as $def) {
            if (!is_array($def)) {
                continue;
            }
            $items = [];
            foreach (($def['items'] ?? []) as $itemDef) {
                if (!is_array($itemDef)) {
                    continue;
                }
                $items[] = [
                    'item_key' => (string) ($itemDef['item_key'] ?? ''),
                    'module_id' => null,
                    'item_type' => (string) ($itemDef['item_type'] ?? 'link'),
                    'label' => (string) ($itemDef['label'] ?? ''),
                    'url' => (string) ($itemDef['url'] ?? ''),
                    'icon' => (string) ($itemDef['icon'] ?? 'circle'),
                    'badge_key' => $itemDef['badge_key'] ?? null,
                    'alert_key' => $itemDef['alert_key'] ?? null,
                    'is_global' => (bool) ($itemDef['is_global'] ?? false),
                    'open_new_tab' => (bool) ($itemDef['open_new_tab'] ?? false),
                    'sort_order' => (int) ($itemDef['sort_order'] ?? 100),
                    'is_active' => true,
                    'source' => 'core',
                    'customized' => false,
                ];
            }
            $sections[] = [
                'section_key' => (string) ($def['section_key'] ?? ''),
                'module_id' => null,
                'label' => (string) ($def['label'] ?? ''),
                'group_label' => $def['group_label'] ?? null,
                'workspace' => $def['workspace'] ?? null,
                'sort_order' => (int) ($def['sort_order'] ?? 100),
                'is_active' => true,
                'source' => 'core',
                'customized' => false,
                'items' => $items,
            ];
        }

        return $sections;
    }

    /** @param array<string, mixed> $data @param array<string, int> $badges */
    private function buildTree(array $data, bool $renderOnly, array $badges = []): array
    {
        $out = [];
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        usort($sections, static fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        // Strat personalizare + filtrare workspace — doar la randare (nu în editorul global).
        $restrictWorkspace = null;
        $userPrefs = null;
        if ($renderOnly) {
            $currentWs = class_exists(AdminWorkspace::class) ? AdminWorkspace::getCurrent() : null;
            if ($currentWs !== null) {
                $restrictWorkspace = $currentWs;
            }
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ($userId > 0) {
                $userPrefs = AdminNavUserPreferences::forUser($userId, $this->adminRoot);
                $sections = $this->applyUserSectionOrder($sections, $userPrefs);
            }
        }

        foreach ($sections as $section) {
            if (!is_array($section) || ($renderOnly && !$this->itemIsActive($section))) {
                continue;
            }
            $sectionKey = (string) ($section['section_key'] ?? '');
            $moduleId = (string) ($section['module_id'] ?? '');
            if ($renderOnly && $moduleId !== '' && !ModuleGate::enabled($moduleId)) {
                continue;
            }
            if ($userPrefs !== null && $userPrefs->isSectionHidden($sectionKey)) {
                continue;
            }
            $sectionWorkspace = (string) ($section['workspace'] ?? '');

            $itemsOut = [];
            foreach ((array) ($section['items'] ?? []) as $item) {
                if (!is_array($item) || ($renderOnly && !$this->itemIsActive($item))) {
                    continue;
                }
                $itemModule = (string) ($item['module_id'] ?? '');
                if ($renderOnly && $itemModule !== '' && !ModuleGate::enabled($itemModule)) {
                    continue;
                }
                $itemId = $sectionKey . '::' . (string) ($item['item_key'] ?? '');
                $isGlobal = (int) ($item['is_global'] ?? 0) === 1;

                // Filtrare workspace pe server: item vizibil doar dacă e global sau aparține zonei curente.
                if ($restrictWorkspace !== null
                    && !$isGlobal
                    && $sectionWorkspace !== ''
                    && $sectionWorkspace !== $restrictWorkspace
                ) {
                    continue;
                }

                if ($userPrefs !== null && $userPrefs->isItemHidden($itemId)) {
                    continue;
                }

                $key = (string) ($item['badge_key'] ?? '');
                $item['badge_count'] = ($key !== '' && isset($badges[$key])) ? (int) $badges[$key] : 0;
                $item['id'] = $itemId;
                $itemsOut[] = $item;
            }
            if ($renderOnly && $itemsOut === []) {
                continue;
            }
            $sectionRow = $section;
            $sectionRow['id'] = $sectionKey;
            $sectionRow['besoiu_section'] = $this->resolveBesoiuSection($section);
            $out[] = ['section' => $sectionRow, 'items' => $itemsOut];
        }
        return $out;
    }

    /**
     * Reordonează secțiunile după preferința utilizatorului (fallback sort_order).
     *
     * @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    private function applyUserSectionOrder(array $sections, AdminNavUserPreferences $prefs): array
    {
        $order = $prefs->sectionOrder();
        if ($order === []) {
            return $sections;
        }
        usort($sections, static function ($a, $b) use ($order) {
            $ak = (string) ($a['section_key'] ?? '');
            $bk = (string) ($b['section_key'] ?? '');
            $ao = $order[$ak] ?? ((int) ($a['sort_order'] ?? 0) + 100000);
            $bo = $order[$bk] ?? ((int) ($b['sort_order'] ?? 0) + 100000);

            return $ao <=> $bo;
        });

        return $sections;
    }

    /** @param list<array<string, mixed>> $sections */
    private function setModuleSectionActiveInData(array &$sections, string $moduleId, bool $active): void
    {
        foreach ($sections as &$section) {
            if ((string) ($section['module_id'] ?? '') !== $moduleId) {
                continue;
            }
            $section['is_active'] = $active;
            foreach ($section['items'] ?? [] as &$item) {
                if ((string) ($item['module_id'] ?? '') === $moduleId) {
                    $item['is_active'] = $active;
                }
            }
            unset($item);
        }
        unset($section);
    }

    /** @param list<string> $activeModuleIds */
    private function deactivateOrphanModuleSections(array $activeModuleIds): void
    {
        $data = $this->readRaw();
        foreach ($data['sections'] ?? [] as &$section) {
            $mid = (string) ($section['module_id'] ?? '');
            if ($mid !== '' && !in_array($mid, $activeModuleIds, true)) {
                $section['is_active'] = false;
            }
        }
        unset($section);
        $this->writeRaw($data);
    }

    private function moduleSortOrder(string $moduleId): int
    {
        $map = [
            'nav' => 5,
            'import' => 35,
            'import_pro' => 35,
            'calculator' => 36,
            'messages' => 65,
            'ai' => 71,
            'scraper' => 72,
            'scraper_web' => 73,
            'marketplace' => 81,
            'searchlogs' => 91,
            'blog' => 101,
            'backup' => 111,
            'garantie' => 112,
            'furnizori' => 200,
            'supplier_search' => 201,
            'clienti' => 202,
        ];
        return $map[$moduleId] ?? 200;
    }

    /** @param array<string, mixed> $section */
    private function resolveBesoiuSection(array $section): string
    {
        $map = ['suppliers' => 'produse', 'orders' => 'comenzi', 'social' => 'comunicare', 'ai' => 'automatizare', 'marketing' => 'marketing', 'shop' => 'website', 'company' => 'sistem', 'navigatie' => 'navigatie'];
        return $map[(string) ($section['workspace'] ?? '')] ?? 'sistem';
    }

    /** @param array<string, mixed>|null $row */
    private function itemIsActive(?array $row): bool
    {
        if ($row === null) {
            return true;
        }
        if (!array_key_exists('is_active', $row)) {
            return true;
        }

        $flag = $row['is_active'];
        if (is_bool($flag)) {
            return $flag;
        }
        if (is_int($flag) || is_float($flag)) {
            return (int) $flag === 1;
        }
        if (is_string($flag)) {
            return in_array(strtolower(trim($flag)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $flag;
    }

    private function normalizeActiveFlag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /** Elimină secțiuni/linkuri duplicate (registry corupt pe deploy). */
    private function maybeSanitizeRegistry(): void
    {
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $sanitized = $this->sanitizeSections($sections);
        if ($this->sectionsFingerprint($sections) === $this->sectionsFingerprint($sanitized)) {
            return;
        }
        $data['sections'] = $sanitized;
        $this->writeRaw($data);
    }

    /** @param list<array<string, mixed>> $sections @return list<array<string, mixed>> */
    private function sanitizeSections(array $sections): array
    {
        $out = [];
        $indexByKey = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if ($key === '') {
                $out[] = $section;
                continue;
            }
            if (!isset($indexByKey[$key])) {
                $indexByKey[$key] = count($out);
                $section['items'] = $this->sanitizeItems(is_array($section['items'] ?? null) ? $section['items'] : []);
                $out[] = $section;
                continue;
            }

            $si = $indexByKey[$key];
            $mergedItems = array_merge(
                is_array($out[$si]['items'] ?? null) ? $out[$si]['items'] : [],
                is_array($section['items'] ?? null) ? $section['items'] : []
            );
            $out[$si]['items'] = $this->sanitizeItems($mergedItems);
        }

        usort($out, static fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        return array_values($out);
    }

    /** @param list<array<string, mixed>> $items @return list<array<string, mixed>> */
    private function sanitizeItems(array $items): array
    {
        $byKey = [];
        $noKey = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $ik = (string) ($item['item_key'] ?? '');
            if ($ik === '') {
                $noKey[] = $item;
                continue;
            }
            if (!isset($byKey[$ik])) {
                $byKey[$ik] = $item;
            }
        }
        $out = array_merge(array_values($byKey), $noKey);
        usort($out, static fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        return $out;
    }

    /** @param list<array<string, mixed>> $sections */
    private function sectionsFingerprint(array $sections): string
    {
        $keys = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sk = (string) ($section['section_key'] ?? '');
            $items = [];
            foreach ((array) ($section['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $items[] = (string) ($item['item_key'] ?? '');
                }
            }
            $keys[] = $sk . ':' . implode(',', $items);
        }

        return implode('|', $keys);
    }

    /** Aliniază grup sidebar ADMIN PANEL (Dashboard + Produse + Calculator). */
    private function ensureAdminPanelSidebar(): void
    {
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $changed = false;
        foreach ($sections as $si => $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if (!in_array($key, ['core_produse', 'mod_calculator'], true)) {
                continue;
            }
            if (!empty($section['customized'])) {
                continue;
            }
            if ((string) ($section['group_label'] ?? '') !== 'ADMIN PANEL') {
                $sections[$si]['group_label'] = 'ADMIN PANEL';
                $changed = true;
            }
        }
        if (!$changed) {
            return;
        }
        $data['sections'] = $sections;
        $this->writeRaw($data);
    }

    /** Secțiune dedicată NAVIGAȚIE ADMIN — separată de Company Settings. */
    private function ensureCoreNavHub(): void
    {
        $data = $this->readRaw();
        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        $hubItems = [
            [
                'item_key' => 'nav-editor',
                'module_id' => null,
                'item_type' => 'link',
                'label' => 'Editor meniu sidebar',
                'url' => '/admin/nav',
                'icon' => 'menu',
                'is_global' => false,
                'open_new_tab' => false,
                'sort_order' => 10,
                'is_active' => true,
                'source' => 'core',
                'customized' => false,
            ],
        ];

        $hubIndex = null;
        $filtered = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if ($key === 'mod_nav') {
                continue;
            }
            if ($key === 'core_navigatie') {
                $hubIndex = count($filtered);
            }
            $filtered[] = $section;
        }
        $sections = $filtered;

        if ($hubIndex === null) {
            $sections[] = [
                'section_key' => 'core_navigatie',
                'module_id' => null,
                'label' => 'Navigație admin',
                'group_label' => 'NAVIGAȚIE ADMIN',
                'workspace' => 'company',
                'sort_order' => 3,
                'is_active' => true,
                'source' => 'core',
                'customized' => false,
                'items' => $hubItems,
            ];
        } else {
            $hub = $sections[$hubIndex];
            $hub['group_label'] = 'NAVIGAȚIE ADMIN';
            $hub['workspace'] = 'company';
            $hub['sort_order'] = (int) ($hub['sort_order'] ?? 3);
            $hub['is_active'] = true;
            if (empty($hub['customized'])) {
                $hub['label'] = 'Navigație admin';
                $hub['items'] = $hubItems;
            } else {
                $itemsByKey = [];
                foreach ((array) ($hub['items'] ?? []) as $item) {
                    if (is_array($item) && ($item['item_key'] ?? '') !== '') {
                        $itemsByKey[(string) $item['item_key']] = $item;
                    }
                }
                foreach ($hubItems as $item) {
                    $ik = (string) $item['item_key'];
                    if (!isset($itemsByKey[$ik])) {
                        $itemsByKey[$ik] = $item;
                    } elseif (empty($itemsByKey[$ik]['customized'])) {
                        $itemsByKey[$ik] = array_merge($item, ['customized' => false]);
                    }
                }
                usort($itemsByKey, static fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));
                $hub['items'] = array_values($itemsByKey);
            }
            $sections[$hubIndex] = $hub;
        }

        usort($sections, static fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        // Scrie DOAR dacă s-a schimbat ceva — altfel rescriem registry pe fiecare request (lent pe Windows).
        $before = $this->sectionsFingerprint(is_array($data['sections'] ?? null) ? $data['sections'] : []);
        $after = $this->sectionsFingerprint($sections);
        if ($before === $after) {
            return;
        }
        $data['sections'] = $sections;
        $this->writeRaw($data);
    }
}
