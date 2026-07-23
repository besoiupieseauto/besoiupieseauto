<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Module/ModuleRegistry.php
 * ============================================================================
 * Scop: Descoperă modulele storefront din app/Modules/, citește manifestele
 *       module.json și instanțiază controllerii paginilor la cerere.
 *
 * Include/require: Citește module.json din fiecare subfolder Modules/{Id}/
 *
 * Bază de date: Nu accesează DB; doar filesystem.
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Module;

use Storefront\Core\Template\ThemeRenderer;

/**
 * Registry module pagini — citește module.json din fiecare folder Modules.
 */
final class ModuleRegistry
{
    /** @var array<string, array<string, mixed>> Manifeste indexate după id modul */
    private array $manifests = [];

    /**
     * @param string $modulesRoot Calea către app/Modules/
     * @param ThemeRenderer $theme Renderer injectat în fiecare controller
     */
    public function __construct(
        private readonly string $modulesRoot,
        private readonly ThemeRenderer $theme,
    ) {
        $this->loadManifests();
    }

    /**
     * Creează o instanță a controllerului dat, injectând ThemeRenderer.
     *
     * @param string $class FQCN complet al controllerului
     * @return object|null Instanța controllerului sau null dacă clasa nu există
     */
    public function makeController(string $class): ?object
    {
        if (!class_exists($class)) {
            return null;
        }

        return new $class($this->theme);
    }

    /** @return list<string> Lista ID-urilor modulelor descoperite și activate */
    public function ids(): array
    {
        return array_keys($this->manifests);
    }

    /**
     * @return array<string, mixed>|null Manifestul modulului sau null
     */
    public function manifest(string $id): ?array
    {
        return $this->manifests[$id] ?? null;
    }

    /**
     * Scanează app/Modules/ și încarcă module.json (JSON sau PHP return array).
     * Ignoră modulele cu enabled=false sau fără câmp id.
     */
    private function loadManifests(): void
    {
        if (!is_dir($this->modulesRoot)) {
            return;
        }

        // Parcurge fiecare subfolder din Modules/
        foreach (scandir($this->modulesRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $jsonPath = $this->modulesRoot . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . 'module.json';
            if (!is_file($jsonPath)) {
                continue;
            }
            $raw = file_get_contents($jsonPath);
            if ($raw === false) {
                continue;
            }
            $trimmed = ltrim($raw);
            // Suportă module.json ca fișier PHP (return [...]) sau JSON pur
            if (str_starts_with($trimmed, '<?php')) {
                $data = require $jsonPath;
            } else {
                $data = json_decode($raw, true);
            }
            if (!is_array($data) || empty($data['id'])) {
                continue;
            }
            // Modul explicit dezactivat — îl omitem
            if (isset($data['enabled']) && $data['enabled'] === false) {
                continue;
            }
            $this->manifests[(string) $data['id']] = $data;
        }
    }
}
