<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Descoperă module din admin/modules/{Id}/module.json, respectă enabled state,
 * bootează doar modulele active cu dependențe satisfăcute.
 *
 * Scoți un folder din modules/ → la următorul boot dispare din registry
 * (fără fatal pe CORE).
 */
final class ModuleRegistry
{
    private static ?self $instance = null;

    private bool $discovered = false;

    private bool $booted = false;

    /** @var array<string, ModuleManifest> */
    private array $manifests = [];

    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    /** @var array<string, bool> id => enabled */
    private array $enabledState = [];

    /** @var list<string> */
    private array $bootLog = [];

    public function __construct(
        private readonly string $modulesRoot,
        private readonly string $stateFile,
        private readonly string $adminRoot,
    ) {
    }

    public static function instance(?string $adminRoot = null): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = new self(
            ModulesPaths::modulesRoot(),
            ModulesPaths::stateFile(),
            $adminRoot ?? ModulesPaths::adminRoot(),
        );

        return self::$instance;
    }

    /** @internal teste */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->loadState();
        $this->manifests = [];
        $this->modules = [];

        if (!is_dir($this->modulesRoot)) {
            $this->discovered = true;
            $this->bootLog[] = 'modules/ lipsește — registry gol (CORE OK)';

            return;
        }

        $dirs = scandir($this->modulesRoot) ?: [];
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $path = $this->modulesRoot . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($path)) {
                continue;
            }
            $json = $path . DIRECTORY_SEPARATOR . 'module.json';
            if (!is_file($json)) {
                continue;
            }

            try {
                $manifest = ModuleManifest::fromFile($json);
                $this->manifests[$manifest->id()] = $manifest;
                $this->registerAutoload($manifest);
            } catch (\Throwable $e) {
                $this->bootLog[] = 'SKIP invalid module ' . $dir . ': ' . $e->getMessage();
            }
        }

        $this->discovered = true;
    }

    /**
     * Încarcă clasele din modules/{Id}/src/ pe namespace Besoiu\\Modules\\{Id}\\
     */
    private function registerAutoload(ModuleManifest $manifest): void
    {
        $src = $manifest->path() . DIRECTORY_SEPARATOR . 'src';
        if (!is_dir($src)) {
            return;
        }

        $prefix = 'Besoiu\\Modules\\' . $this->studly($manifest->id()) . '\\';
        spl_autoload_register(static function (string $class) use ($prefix, $src): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $src . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    private function studly(string $id): string
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', $id) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $out .= ucfirst(strtolower($part));
        }

        return $out !== '' ? $out : 'Module';
    }

    public function boot(): void
    {
        $this->discover();
        if ($this->booted) {
            return;
        }

        $context = new ModuleContext($this, $this->adminRoot);

        foreach ($this->manifests as $id => $manifest) {
            if (!$this->isEnabled($id)) {
                $this->bootLog[] = "disabled: {$id}";
                continue;
            }

            if (!$this->dependenciesMet($manifest)) {
                $this->bootLog[] = "deps unmet: {$id} needs [" . implode(',', $manifest->dependencies()) . ']';
                continue;
            }

            try {
                $module = $this->instantiate($manifest);
                $module->boot($context);
                $this->modules[$id] = $module;
                $this->bootLog[] = "booted: {$id} v{$module->version()}";
            } catch (\Throwable $e) {
                $this->bootLog[] = "boot FAIL {$id}: " . $e->getMessage();
            }
        }

        $this->booted = true;
    }

    private function instantiate(ModuleManifest $manifest): ModuleInterface
    {
        $class = $manifest->entryClass();
        if (!class_exists($class)) {
            throw new \RuntimeException("Clasă entry lipsă: {$class}");
        }

        $obj = new $class($manifest);
        if (!$obj instanceof ModuleInterface) {
            throw new \RuntimeException("{$class} nu implementează ModuleInterface");
        }

        return $obj;
    }

    private function dependenciesMet(ModuleManifest $manifest): bool
    {
        foreach ($manifest->dependencies() as $dep) {
            // Dependență pe alt modul din registry
            if (isset($this->manifests[$dep])) {
                if (!$this->isEnabled($dep)) {
                    return false;
                }
                continue;
            }
            // Dependență pe CORE (produse, auth…) — tratată ca satisfăcută
            // (CORE nu e în modules/; e mereu prezent)
        }

        return true;
    }

    public function isEnabled(string $id): bool
    {
        $this->discover();

        if (!isset($this->manifests[$id])) {
            return false;
        }

        if ($this->manifests[$id]->type() === 'core') {
            return true;
        }

        if (array_key_exists($id, $this->enabledState)) {
            return $this->enabledState[$id];
        }

        return $this->manifests[$id]->defaultEnabled();
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $this->discover();
        if (!isset($this->manifests[$id])) {
            throw new \InvalidArgumentException("Modul necunoscut: {$id}");
        }
        $this->enabledState[$id] = $enabled;
        $this->saveState();
    }

    public function has(string $id): bool
    {
        $this->boot();

        return isset($this->modules[$id]);
    }

    public function get(string $id): ?ModuleInterface
    {
        $this->boot();

        return $this->modules[$id] ?? null;
    }

    /** @return array<string, ModuleInterface> */
    public function allBooted(): array
    {
        $this->boot();

        return $this->modules;
    }

    /** @return array<string, ModuleManifest> */
    public function allManifests(): array
    {
        $this->discover();

        return $this->manifests;
    }

    /**
     * Nav agregat din modulele booted (pentru UI viitor — nu înlocuiește încă nav.php).
     *
     * @return list<array{id: string, label: string, href: string, icon?: string, group?: string, module: string}>
     */
    public function aggregatedNav(): array
    {
        $this->boot();
        $out = [];
        foreach ($this->modules as $module) {
            foreach ($module->navItems() as $item) {
                $item['module'] = $module->id();
                $out[] = $item;
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function bootLog(): array
    {
        $this->boot();

        return $this->bootLog;
    }

    /** @return array{discovered: int, enabled: int, booted: int, disabled: int, log: list<string>} */
    public function status(): array
    {
        $this->boot();
        $enabled = 0;
        $disabled = 0;
        foreach ($this->manifests as $id => $_) {
            if ($this->isEnabled($id)) {
                $enabled++;
            } else {
                $disabled++;
            }
        }

        return [
            'discovered' => count($this->manifests),
            'enabled' => $enabled,
            'booted' => count($this->modules),
            'disabled' => $disabled,
            'log' => $this->bootLog,
        ];
    }

    private function loadState(): void
    {
        $this->enabledState = [];
        if (!is_file($this->stateFile)) {
            return;
        }
        $raw = file_get_contents($this->stateFile);
        if ($raw === false || $raw === '') {
            return;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['enabled']) || !is_array($data['enabled'])) {
            return;
        }
        foreach ($data['enabled'] as $id => $flag) {
            $this->enabledState[(string) $id] = (bool) $flag;
        }
    }

    private function saveState(): void
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $payload = [
            'updated_at' => date('c'),
            'enabled' => $this->enabledState,
        ];
        file_put_contents(
            $this->stateFile,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
