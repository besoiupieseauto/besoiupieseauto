<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

use RuntimeException;

/**
 * Generează un pachet modul complet în admin/modules/{Folder}/.
 *
 * Nu scrie în Controllers/Templates CORE — totul e plugabil.
 */
final class ModulePackageGenerator
{
    public function __construct(
        private readonly string $adminRoot,
    ) {
    }

    /**
     * @param array{
     *   id?: string,
     *   name?: string,
     *   table?: string,
     *   fields?: array<string, string>,
     *   workspace?: string,
     *   permissions?: list<string>,
     *   dependencies?: list<string>,
     *   with_db?: bool,
     *   overwrite?: bool
     * } $config
     * @return array{module_id: string, path: string, files: list<string>, migration: ?string}
     */
    public function generate(string $moduleName, array $config = []): array
    {
        $studly = $this->studly($moduleName);
        $id = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', (string) ($config['id'] ?? $moduleName)) ?: '');
        $id = trim($id, '_');
        if ($id === '' || !preg_match('/^[a-z][a-z0-9_]{1,40}$/', $id)) {
            throw new RuntimeException('id modul invalid (a-z, 0-9, _).');
        }

        $name = trim((string) ($config['name'] ?? $studly));
        $table = strtolower(trim((string) ($config['table'] ?? $id)));
        $table = preg_replace('/[^a-z0-9_]/', '_', $table) ?: $id;
        /** @var array<string, string> $fields */
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [
            'name' => 'VARCHAR(255) NOT NULL',
            'status' => "VARCHAR(20) NOT NULL DEFAULT '1'",
            'notes' => 'TEXT NULL',
        ];
        $workspace = (string) ($config['workspace'] ?? 'company');
        $permissions = $config['permissions'] ?? ['module.' . $id];
        if (!is_array($permissions) || $permissions === []) {
            $permissions = ['module.' . $id];
        }
        $dependencies = is_array($config['dependencies'] ?? null) ? $config['dependencies'] : [];
        $withDb = ($config['with_db'] ?? true) !== false;
        $overwrite = !empty($config['overwrite']);

        $folder = $studly;
        $target = $this->adminRoot . '/modules/' . $folder;
        if (is_dir($target) && !$overwrite) {
            throw new RuntimeException('Modulul există deja: ' . $target . ' (folosește overwrite=1).');
        }
        if (is_dir($target) && $overwrite) {
            $this->rrmdir($target);
        }

        $files = [];
        $this->mkdir($target);
        $this->mkdir($target . '/src');
        $this->mkdir($target . '/pages');
        $this->mkdir($target . '/assets');
        $this->mkdir($target . '/api');
        $this->mkdir($target . '/migrations');

        $slug = str_replace('_', '-', $id);
        $perm0 = (string) $permissions[0];

        $moduleJson = [
            'id' => $id,
            'name' => $name,
            'version' => '1.0.0',
            'type' => 'optional',
            'enabled' => true,
            'description' => 'Modul generat: ' . $name . ($withDb ? ' (tabel `' . $table . '`)' : ''),
            'entry' => 'Besoiu\\Modules\\' . $studly . '\\' . $studly . 'Module',
            'dependencies' => array_values($dependencies),
            'permissions' => array_values($permissions),
            'workspace' => $workspace,
            'nav' => [[
                'id' => $slug,
                'label' => $name,
                'href' => '/admin/' . $slug,
                'icon' => 'puzzle',
                'group' => 'optional',
            ]],
            'provides' => [
                'slugs' => [$slug],
                'paths' => ['/admin/' . $slug, '/admin/public/' . $slug],
                'api_scripts' => [$id . '_endpoint.php'],
                'quick_actions' => [$slug],
                'folder' => $folder,
                'workspace' => $workspace,
            ],
            'migrations' => $withDb ? ['migrations/001_create_' . $table . '.sql'] : [],
        ];

        $files[] = $this->write($target . '/module.json', json_encode(
            $moduleJson,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . "\n");

        $files[] = $this->write($target . '/src/' . $studly . 'Module.php', $this->tplModule($studly, $id));
        $files[] = $this->write($target . '/src/' . $studly . 'Service.php', $this->tplService($studly, $id, $table, $fields, $withDb));
        $files[] = $this->write($target . '/pages/index.php', $this->tplPage($studly, $id, $slug, $name, $table, $withDb));
        $files[] = $this->write($target . '/assets/' . $id . '.css', $this->tplCss($id));
        $files[] = $this->write($target . '/assets/' . $id . '.js', $this->tplJs($id));
        $files[] = $this->write($target . '/api/' . $id . '_endpoint.php', $this->tplApi($studly, $id));

        $migrationFile = null;
        if ($withDb) {
            $migrationFile = 'migrations/001_create_' . $table . '.sql';
            $files[] = $this->write(
                $target . '/' . $migrationFile,
                $this->tplMigration($table, $fields)
            );
        }

        $files[] = $this->write($target . '/ARCHITECTURE.md', $this->tplArch($studly, $id, $table, $withDb));
        $files[] = $this->write($target . '/CITESTE-MA.md', $this->tplCite($studly, $id, $slug, $withDb));
        $files[] = $this->write(
            $target . '/schema.json',
            json_encode([
                'table' => $table,
                'fields' => $fields,
                'with_db' => $withDb,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Activează în state
        ModuleRegistry::resetInstance();
        $reg = ModuleRegistry::instance($this->adminRoot);
        $reg->discover();
        if (isset($reg->allManifests()[$id])) {
            $reg->setEnabled($id, true);
            try {
                $mod = $reg->get($id);
                $mod?->install();
            } catch (\Throwable) {
                // migrări pot eșua dacă DB nu e disponibil în CLI — OK
            }
        }
        ModuleGate::resetCache();

        return [
            'module_id' => $id,
            'path' => $target,
            'files' => $files,
            'migration' => $migrationFile,
        ];
    }

    private function tplModule(string $studly, string $id): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Besoiu\\Modules\\{$studly};

use Besoiu\\Core\\Module\\AbstractModule;
use Besoiu\\Core\\Module\\ModuleContext;

final class {$studly}Module extends AbstractModule
{
    public function boot(ModuleContext \$context): void
    {
        if (!\$context->isCoreAvailable()) {
            return;
        }
        \$context->registry();
    }
}

PHP;
    }

    /**
     * @param array<string, string> $fields
     */
    private function tplService(string $studly, string $id, string $table, array $fields, bool $withDb): string
    {
        if (!$withDb) {
            return <<<PHP
<?php

declare(strict_types=1);

namespace Besoiu\\Modules\\{$studly};

final class {$studly}Service
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        return [
            'module' => '{$id}',
            'ok' => true,
            'db' => false,
            'at' => date('c'),
        ];
    }
}

PHP;
        }

        $colNames = array_keys($fields);
        $colNames = array_values(array_filter($colNames, static fn ($c) => $c !== 'id'));
        if ($colNames === []) {
            $colNames = ['name', 'status', 'notes'];
        }
        $colsSql = implode(', ', array_map(static fn ($c) => '`' . $c . '`', $colNames));
        $placeholders = implode(', ', array_fill(0, count($colNames), '?'));
        $extractLines = [];
        $execVars = [];
        foreach ($colNames as $col) {
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $col) ?: 'field';
            $default = match (true) {
                $safe === 'status' => "'1'",
                $safe === 'months' => '24',
                default => "''",
            };
            $extractLines[] = "        \${$safe} = \$data['{$safe}'] ?? {$default};";
            $execVars[] = '$' . $safe;
        }
        $extractBlock = implode("\n", $extractLines);
        $execList = implode(', ', $execVars);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Besoiu\\Modules\\{$studly};

use Config\\Database;
use PDO;

final class {$studly}Service
{
    private function pdo(): PDO
    {
        return Database::getDB('default');
    }

    /** @return list<array<string, mixed>> */
    public function list(int \$limit = 50): array
    {
        \$limit = max(1, min(200, \$limit));
        \$stmt = \$this->pdo()->query('SELECT * FROM `{$table}` ORDER BY id DESC LIMIT ' . \$limit);

        return \$stmt ? (\$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @param array<string, mixed> \$data */
    public function create(array \$data): int
    {
{$extractBlock}
        \$stmt = \$this->pdo()->prepare(
            'INSERT INTO `{$table}` ({$colsSql}) VALUES ({$placeholders})'
        );
        \$stmt->execute([{$execList}]);

        return (int) \$this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        try {
            \$n = (int) \$this->pdo()->query('SELECT COUNT(*) FROM `{$table}`')->fetchColumn();
        } catch (\\Throwable) {
            \$n = -1;
        }

        return [
            'module' => '{$id}',
            'ok' => true,
            'table' => '{$table}',
            'rows' => \$n,
            'at' => date('c'),
        ];
    }
}

PHP;
    }

    private function tplPage(string $studly, string $id, string $slug, string $name, string $table, bool $withDb): string
    {
        $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $dbNote = $withDb
            ? "Tabel: <code>{$table}</code> (creat din migrations/)."
            : 'Fără tabel BD (modul UI/API).';

        return <<<PHP
<?php

declare(strict_types=1);

use Besoiu\\Core\\AdminUrl;
use Besoiu\\Core\\Module\\ModuleAssets;
use Besoiu\\Modules\\{$studly}\\{$studly}Service;

\$service = new {$studly}Service();
\$status = \$service->status();
\$css = ModuleAssets::url('{$id}', '{$id}.css');
\$js = ModuleAssets::url('{$id}', '{$id}.js');
\$api = AdminUrl::moduleApi('{$id}', '{$id}_endpoint.php');
\$rows = method_exists(\$service, 'list') ? \$service->list(20) : [];
?>
<link rel="stylesheet" href="<?= htmlspecialchars(\$css, ENT_QUOTES, 'UTF-8') ?>">
<div class="box p-6 mod-{$id}" style="max-width:900px" data-api="<?= htmlspecialchars(\$api, ENT_QUOTES, 'UTF-8') ?>">
    <h1 class="text-xl font-semibold mb-1">{$nameEsc}</h1>
    <p class="text-sm opacity-80 mb-4">Modul generat în <code>modules/{$studly}/</code>. {$dbNote}</p>
    <pre class="text-xs opacity-70 mb-4 overflow-auto"><?= htmlspecialchars(json_encode(\$status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></pre>
<?php if (\$rows !== []): ?>
    <div class="overflow-auto mb-4">
        <table class="w-full text-sm">
            <thead><tr><th class="text-left p-2">ID</th><th class="text-left p-2">Name</th><th class="text-left p-2">Status</th></tr></thead>
            <tbody>
            <?php foreach (\$rows as \$row): ?>
                <tr class="border-t">
                    <td class="p-2"><?= (int) (\$row['id'] ?? 0) ?></td>
                    <td class="p-2"><?= htmlspecialchars((string) (\$row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="p-2"><?= htmlspecialchars((string) (\$row['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
    <button type="button" id="{$id}-ping" class="box rounded-md border bg-primary px-4 py-2 text-sm text-white">Test API</button>
    <p id="{$id}-out" class="text-sm mt-2 opacity-70"></p>
</div>
<script src="<?= htmlspecialchars(\$js, ENT_QUOTES, 'UTF-8') ?>"></script>

PHP;
    }

    private function tplCss(string $id): string
    {
        return ".mod-{$id} pre { border: 1px solid rgba(26,188,156,.3); border-radius: 8px; padding: .75rem; background: rgba(26,188,156,.06); }\n";
    }

    private function tplJs(string $id): string
    {
        return <<<JS
(function () {
  var root = document.querySelector('.mod-{$id}');
  var btn = document.getElementById('{$id}-ping');
  var out = document.getElementById('{$id}-out');
  if (!btn || !root || !out) return;
  btn.addEventListener('click', async function () {
    var api = root.getAttribute('data-api');
    out.textContent = 'Se apelează…';
    try {
      var r = await fetch(api, { credentials: 'include', headers: { 'Accept': 'application/json' } });
      var j = await r.json();
      out.textContent = JSON.stringify(j, null, 2);
    } catch (e) {
      out.textContent = 'Eroare: ' + (e.message || e);
    }
  });
})();

JS;
    }

    private function tplApi(string $studly, string $id): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

use Besoiu\\Modules\\{$studly}\\{$studly}Service;

\$service = new {$studly}Service();
\$action = (string) (\$_GET['action'] ?? \$_POST['action'] ?? 'status');

header('Content-Type: application/json; charset=utf-8');

if (\$action === 'list' && method_exists(\$service, 'list')) {
    echo json_encode(['success' => true, 'data' => \$service->list()], JSON_UNESCAPED_UNICODE);
    return;
}

if (\$action === 'create' && method_exists(\$service, 'create')) {
    \$id = \$service->create(\$_POST + \$_GET);
    echo json_encode(['success' => true, 'id' => \$id], JSON_UNESCAPED_UNICODE);
    return;
}

echo json_encode(['success' => true, 'data' => \$service->status()], JSON_UNESCAPED_UNICODE);

PHP;
    }

    /**
     * @param array<string, string> $fields
     */
    private function tplMigration(string $table, array $fields): string
    {
        $cols = ["  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY"];
        foreach ($fields as $name => $type) {
            $name = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $name) ?: 'field';
            $type = trim((string) $type);
            if ($type === '') {
                $type = 'VARCHAR(255) NULL';
            }
            $cols[] = "  `{$name}` {$type}";
        }
        $cols[] = '  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $cols[] = '  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP';

        return "-- Modul migration: {$table}\n"
            . "CREATE TABLE IF NOT EXISTS `{$table}` (\n"
            . implode(",\n", $cols) . "\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
    }

    private function tplArch(string $studly, string $id, string $table, bool $withDb): string
    {
        $db = $withDb ? "Tabel `{$table}` din migrations/." : 'Fără BD.';

        return "# {$studly}\n\nPachet modular self-contained.\n\n- id: `{$id}`\n- {$db}\n- API: `/admin/api/module/{$id}/{$id}_endpoint.php`\n\nGhid: `admin/modules/START_HERE.md`\n";
    }

    private function tplCite(string $studly, string $id, string $slug, bool $withDb): string
    {
        $dbLine = $withDb ? "4. `migrations/` — tabelul BD\n5. `pages/index.php` — ecranul\n6. `api/{$id}_endpoint.php` — API\n" : "4. `pages/index.php` — ecranul\n5. `api/{$id}_endpoint.php` — API\n";

        return <<<MD
# Cum lucrezi pe {$studly}

Ai generat acest modul. Nu trebuie să ghicești — urmează:

1. Citește `admin/modules/START_HERE.md` (ghidul mare)
2. Editează `src/{$studly}Service.php` — logica ta
3. Editează `pages/index.php` — ce vezi în browser
{$dbLine}
## Lansare

1. Setări → Module → **{$id}** = Activ
2. Deschide `/admin/{$slug}`
3. Testează butonul API de pe pagină

## Ce NU schimbi la început

- `module.json` (doar label / workspace dacă e nevoie)
- `{$studly}Module.php`

MD;
    }

    private function studly(string $name): string
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', $name) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $out .= ucfirst(strtolower($part));
            }
        }

        return $out !== '' ? $out : 'Module';
    }

    private function mkdir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Nu pot crea ' . $dir);
        }
    }

    private function write(string $path, string $contents): string
    {
        file_put_contents($path, $contents);

        return $path;
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}

