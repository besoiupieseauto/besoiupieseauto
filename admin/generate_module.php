<?php

// ================================
// UTILS
// ================================

function prompt(string $msg): string {
    echo $msg;
    return trim(fgets(STDIN));
}

function getPdo(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        require_once __DIR__ . '/vendor/autoload.php';
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();
        $config = require __DIR__ . '/config/config.php';
        \Config\Database::getInstance(
            (string) $config['db_host'],
            (string) $config['db_name'],
            (string) $config['db_user'],
            (string) $config['db_pass']
        );
        $pdo = \Config\Database::getDB();
    }

    return $pdo;
}

function generateFromTemplate(string $templateFile, string $destinationFile, array $replacements): void {
    if (!file_exists($templateFile)) {
        echo "⚠️  Șablonul $templateFile nu a fost găsit.\n";
        return;
    }

    $content = file_get_contents($templateFile);
    foreach ($replacements as $key => $value) {
        $content = str_replace('{{' . $key . '}}', $value, $content);
    }

    file_put_contents($destinationFile, $content);
    echo "✅ Creat: $destinationFile\n";
}

function createMysqlTable(string $tableName, array $fields = []): void {
    $fieldSqlList = [];
    foreach ($fields as $name => $type) {
        $fieldSqlList[] = "`$name` $type";
    }

    if (empty($fieldSqlList)) {
        echo "⚠️ Nu există câmpuri pentru tabelul `$tableName`. Sar peste CREATE TABLE.\n";
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        " . implode(",\n        ", $fieldSqlList) . ",
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    try {
        $pdo = getPdo();
        $pdo->exec($sql);
        echo "✅ Tabelul `$tableName` a fost creat / verificat cu succes.\n";
    } catch (PDOException $e) {
        echo "❌ Eroare la creare tabel: " . $e->getMessage() . "\n";
    }
}

function insertRoutes(string $module, array $config): void {
    $moduleLower = strtolower($module);

    try {
        $pdo = getPdo();

        $routesSql = "
            INSERT INTO routes 
            (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `namenav`, `typenav`, `users`, `icon`, `is_active`) VALUES
            ('GET', '/admin/{$moduleLower}', 'Admin', 'index', 'loadPage', '/Templates/admin/pages/{$moduleLower}/', NULL, NULL, NULL, NULL, 1),
            ('POST', '/admin/crud{$moduleLower}', 'Admin', 'rootFunction', NULL, '/src/Controllers/{$module}/crudu.php', NULL, NULL, NULL, NULL, 1),
            ('GET', '/admin/add{$moduleLower}', 'Admin', 'index', 'loadPage', '/Templates/admin/pages/{$moduleLower}/', NULL, NULL, NULL, NULL, 1),
            ('GET', '/admin/profile{$moduleLower}', 'Admin', 'index', 'loadPage', '/Templates/admin/pages/{$moduleLower}/', NULL, NULL, NULL, NULL, 1);
        ";

        $pdo->exec($routesSql);
        echo "✅ Rutele au fost inserate în baza de date.\n";
    } catch (PDOException $e) {
        echo "❌ Eroare la inserare rute: " . $e->getMessage() . "\n";
    }
}

/**
 * Ștergere recursivă de director (cu fișiere)
 */
function rrmdir(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) return;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

/**
 * chmod -R pe un path
 */
function chmodRecursive(string $path, int $mode = 0777): void {
    if (!file_exists($path)) {
        return;
    }

    @chmod($path, $mode);

    if (is_dir($path)) {
        $items = scandir($path);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $subPath = $path . DIRECTORY_SEPARATOR . $item;
            chmodRecursive($subPath, $mode);
        }
    }
}

/**
 * Aplică permisiuni 777 pe folderele modulului
 */
function applyModulePermissions(string $module): void {
    $moduleLower      = strtolower($module);
    $baseDir          = __DIR__ . '/src/';
    $controllerDir    = $baseDir . "Controllers/{$module}/";
    $coreDir          = $baseDir . "Core/{$module}/";
    $adminTemplateDir = __DIR__ . "/Templates/admin/pages/{$moduleLower}/";

    chmodRecursive($controllerDir, 0777);
    chmodRecursive($coreDir, 0777);
    chmodRecursive($adminTemplateDir, 0777);

    echo "🔐 Permisiuni 777 aplicate pe:\n";
    echo "   - $controllerDir\n";
    echo "   - $coreDir\n";
    echo "   - $adminTemplateDir\n";
}

/**
 * Sync DB cu config: DROP + CREATE TABLE din JSON
 */
function syncTableFromConfig(string $module, array $config, bool $dropFirst = true): void {
    $table  = $config['table']  ?? strtolower($module);
    $fields = $config['fields'] ?? [];

    if (empty($fields)) {
        echo "⚠️ Config nu conține câmpuri valide, nu pot sincroniza tabela.\n";
        return;
    }

    $pdo = getPdo();

    if ($dropFirst) {
        try {
            $sql = "DROP TABLE IF EXISTS `$table`;";
            $pdo->exec($sql);
            echo "🗑️  Tabelul existent `$table` a fost șters (dacă exista).\n";
        } catch (PDOException $e) {
            echo "❌ Eroare la DROP TABLE `$table`: " . $e->getMessage() . "\n";
        }
    }

    createMysqlTable($table, $fields);
    echo "🔁 Tabela `$table` a fost recreată din config.\n";
}

/**
 * Dezinstalează COMPLET un modul:
 * - DROP TABLE
 * - șterge rânduri din routes
 * - șterge rânduri din role_nav (dacă există)
 * - șterge fișiere din src/Controllers/Module, src/Core/Module, Templates/admin/pages/module
 * - șterge fișierul de config JSON
 */
function uninstallModule(string $module, string $configPath): void {
    $moduleLower = strtolower($module);

    echo "\n⚠️ ATENȚIE! Vei dezinstala modulul: $module\n";
    echo "   - tabel DB\n";
    echo "   - rute din tabela routes\n";
    echo "   - eventualele iteme din role_nav\n";
    echo "   - fișierele din src/Controllers/$module\n";
    echo "   - fișierele din src/Core/$module\n";
    echo "   - fișierele din Templates/admin/pages/{$moduleLower}\n";
    echo "   - config-ul JSON: $configPath\n\n";

    $confirmName = prompt("Scrie exact numele modulului pentru confirmare ($module): ");
    if ($confirmName !== $module) {
        echo "❌ Numele nu corespunde. Dezinstalarea a fost anulată.\n";
        return;
    }

    $confirmWord = strtolower(prompt("Scrie 'delete' ca să confirmi dezinstalarea completă: "));
    if ($confirmWord !== 'delete') {
        echo "❌ Confirmare greșită. Dezinstalarea a fost anulată.\n";
        return;
    }

    // 1) Citim config (pentru numele tabelei)
    $table = strtolower($module);
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true) ?: [];
        if (!empty($config['table'])) {
            $table = $config['table'];
        }
    }

    $pdo = getPdo();

    // 2) DROP TABLE
    try {
        $sql = "DROP TABLE IF EXISTS `$table`;";
        $pdo->exec($sql);
        echo "✅ Tabelul `$table` a fost șters (dacă exista).\n";
    } catch (PDOException $e) {
        echo "❌ Eroare la DROP TABLE `$table`: " . $e->getMessage() . "\n";
    }

    // 3) Ștergem rutele
    try {
        $sql = "DELETE FROM `routes` WHERE `path` LIKE '/admin/{$moduleLower}%';";
        $count = $pdo->exec($sql);
        echo "✅ $count rute șterse din tabela `routes` pentru modulul $module.\n";
    } catch (PDOException $e) {
        echo "❌ Eroare la ștergerea din `routes`: " . $e->getMessage() . "\n";
    }

    // 4) Ștergem navigația (role_nav), dacă există
    try {
        $sql = "DELETE FROM `role_nav` WHERE `url` LIKE '/admin/{$moduleLower}%';";
        $count = $pdo->exec($sql);
        echo "✅ $count itemi șterși din `role_nav` pentru modulul $module.\n";
    } catch (PDOException $e) {
        echo "ℹ️ role_nav nu a putut fi curățat (poate tabela nu există): " . $e->getMessage() . "\n";
    }

    // 5) Ștergem fișierele modulului
    $baseDir          = __DIR__ . '/src/';
    $controllerDir    = $baseDir . "Controllers/{$module}/";
    $coreDir          = $baseDir . "Core/{$module}/";
    $adminTemplateDir = __DIR__ . "/Templates/admin/pages/{$moduleLower}/";

    if (is_dir($controllerDir)) {
        rrmdir($controllerDir);
        echo "✅ Director șters: $controllerDir\n";
    } else {
        echo "ℹ️ Directorul $controllerDir nu există (ok).\n";
    }

    if (is_dir($coreDir)) {
        rrmdir($coreDir);
        echo "✅ Director șters: $coreDir\n";
    } else {
        echo "ℹ️ Directorul $coreDir nu există (ok).\n";
    }

    if (is_dir($adminTemplateDir)) {
        rrmdir($adminTemplateDir);
        echo "✅ Director șters: $adminTemplateDir\n";
    } else {
        echo "ℹ️ Directorul $adminTemplateDir nu există (ok).\n";
    }

    // 6) Ștergem config JSON
    if (file_exists($configPath)) {
        @unlink($configPath);
        echo "✅ Config JSON șters: $configPath\n";
    } else {
        echo "ℹ️ Config JSON nu exista: $configPath\n";
    }

    echo "\n🧹 Modulul `$module` a fost dezinstalat COMPLET.\n";
}

/**
 * Generează modulul:
 * - fișiere Controller/Service/crudu/Model
 * - opțional tabel DB
 * - opțional rute în routes
 * - aplică automat chmod -R 777 pe folderele modulului
 */
function generateModule(string $module, array $config, bool $withDb = true, bool $withRoutes = true): void {
    $tableName = strtolower($module);

    $baseDir       = __DIR__ . '/src/';
    $templateDir   = __DIR__ . '/generator/templates/';
    $configDir     = __DIR__ . '/generator/config/';
    $controllerDir = "{$baseDir}Controllers/{$module}/";
    $coreDir       = "{$baseDir}Core/{$module}/";

    // Creăm folderele necesare
    @mkdir($controllerDir, 0755, true);
    @mkdir($coreDir, 0755, true);
    @mkdir($configDir, 0755, true);

    $fields = $config['fields'] ?? [];
    $table  = $config['table'] ?? $tableName;

    $replacements = [
        'Module' => $module,
        'module' => strtolower($module),
        'db'     => $table
    ];

    // Generăm fișiere PHP
    $templateDir = rtrim($templateDir, '/');
    generateFromTemplate("{$templateDir}/Controller.php.tpl", "{$controllerDir}{$module}.php", $replacements);
    generateFromTemplate("{$templateDir}/Service.php.tpl", "{$controllerDir}{$module}Service.php", $replacements);
    generateFromTemplate("{$templateDir}/crudu.php.tpl", "{$controllerDir}crudu.php", $replacements);
    generateFromTemplate("{$templateDir}/Model.php.tpl", "{$coreDir}{$module}Model.php", $replacements);

    // === Creare tabel MySQL (doar dacă vrei)
    if ($withDb && !empty($fields)) {
        createMysqlTable($table, $fields);
    } elseif ($withDb && empty($fields)) {
        echo "⚠️ Config JSON nu conține câmpuri valide, nu pot crea tabela.\n";
    } else {
        echo "ℹ️ Ai ales să NU creez / modific tabela în MySQL pentru acest modul.\n";
    }

    // === Creăm folderul Templates/admin/pages/[modul]/`
    $adminTemplateDir = __DIR__ . "/Templates/admin/pages/{$replacements['module']}/";
    @mkdir($adminTemplateDir, 0755, true);

    $filesToCreate = [
        "add{$replacements['module']}.php",
        "profile{$replacements['module']}.php",
        "{$replacements['module']}.php"
    ];

    foreach ($filesToCreate as $file) {
        $fullPath = $adminTemplateDir . $file;
        if (!file_exists($fullPath)) {
            file_put_contents($fullPath, "<?php\n// TODO: Implement $file");
            echo "✅ Fișier gol creat: $fullPath\n";
        }
    }

    // === Inserare rute în baza de date (dacă vrei)
    if ($withRoutes) {
        insertRoutes($module, $config);
    } else {
        echo "ℹ️ Ai ales să NU inserezi rute pentru acest modul.\n";
    }

    // === Aplicăm chmod -R 777 pe folderele modulului
    applyModulePermissions($module);

    echo "\n🎉 Modulul '{$module}' a fost generat (fișiere + opțional DB + opțional rute).\n";
}

/**
 * Meniu interactiv pentru editarea unui config JSON
 */
function interactiveConfigEditor(string $module, string $configPath): void {
    echo "📄 Lucrăm cu modulul: $module\n";
    $config = [];

    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true) ?: [];
        echo "✅ Config încărcat din $configPath\n";
    } else {
        echo "⚠️ Config nu există, creăm unul nou.\n";
        $config = [
            'table'  => strtolower($module),
            'fields' => [
                "name"   => "VARCHAR(255) NOT NULL",
                "email"  => "VARCHAR(255)",
                "phone"  => "VARCHAR(50)",
                "status" => "TINYINT(1) DEFAULT 1"
            ]
        ];
    }

    // buclă de meniu
    while (true) {
        echo "\n================ MENU [$module] =================\n";
        echo "1) Vezi config (table + fields)\n";
        echo "2) Adaugă câmp nou în fields (și eventual ALTER TABLE)\n";
        echo "3) Șterge un câmp din fields (și eventual ALTER TABLE DROP)\n";
        echo "4) Schimbă numele tabelei (și eventual RENAME TABLE)\n";
        echo "5) Salvează config JSON\n";
        echo "6) Generează modul (fișiere + întrebări DB/rute)\n";
        echo "7) Dezinstalează COMPLET modulul\n";
        echo "8) Sincronizează tabela MySQL din config (DROP + CREATE)\n";
        echo "0) Ieșire din modul\n";
        echo "===============================================\n";

        $choice = prompt("Alege opțiunea: ");

        if ($choice === '0') {
            echo "👋 Ieșire din editor pentru modulul $module.\n";
            break;
        }

        if ($choice === '1') {
            echo "\n--- CONFIG CURENT ---\n";
            echo "Table: " . ($config['table'] ?? '[nesetată]') . "\n";
            echo "Fields:\n";
            if (!empty($config['fields'])) {
                foreach ($config['fields'] as $fname => $ftype) {
                    echo " - $fname : $ftype\n";
                }
            } else {
                echo " (niciun câmp definit)\n";
            }
            echo "---------------------\n";
        } elseif ($choice === '2') {
            $fname = prompt("Nume câmp nou (ex: role): ");
            if ($fname === '') {
                echo "⚠️ Nume de câmp gol, anulat.\n";
                continue;
            }
            $ftype = prompt("Tip SQL (ex: VARCHAR(50) NOT NULL, INT, TEXT etc.): ");
            if ($ftype === '') {
                echo "⚠️ Tip SQL gol, anulat.\n";
                continue;
            }
            $config['fields'][$fname] = $ftype;
            echo "✅ Câmp adăugat: $fname => $ftype\n";

            // mic quiz: vrei și ALTER TABLE?
            $doAlter = strtolower(prompt("Vrei să faci și ALTER TABLE ADD COLUMN în DB acum? (y/n): "));
            if ($doAlter === 'y') {
                try {
                    $table = $config['table'] ?? strtolower($module);
                    $sql   = "ALTER TABLE `$table` ADD COLUMN `$fname` $ftype;";
                    $pdo   = getPdo();
                    $pdo->exec($sql);
                    echo "✅ ALTER TABLE executat: $sql\n";
                } catch (PDOException $e) {
                    echo "❌ Eroare la ALTER TABLE: " . $e->getMessage() . "\n";
                }
            }
        } elseif ($choice === '3') {
            if (empty($config['fields'])) {
                echo "⚠️ Nu există câmpuri de șters.\n";
                continue;
            }
            echo "Câmpuri existente:\n";
            $i = 1;
            $keys = array_keys($config['fields']);
            foreach ($keys as $k) {
                echo " $i) $k : " . $config['fields'][$k] . "\n";
                $i++;
            }
            $idx = (int)prompt("Alege numărul câmpului de șters (0 = anulează): ");
            if ($idx <= 0 || $idx > count($keys)) {
                echo "Anulat.\n";
                continue;
            }
            $fieldName = $keys[$idx - 1];

            $confirm = strtolower(prompt("Sigur ștergi câmpul '$fieldName' din config? (y/n): "));
            if ($confirm !== 'y') {
                echo "Anulat.\n";
                continue;
            }

            unset($config['fields'][$fieldName]);
            echo "✅ Câmp '$fieldName' șters din config.\n";

            $doAlter = strtolower(prompt("Vrei să faci și ALTER TABLE DROP COLUMN în DB? (y/n): "));
            if ($doAlter === 'y') {
                try {
                    $table = $config['table'] ?? strtolower($module);
                    $sql   = "ALTER TABLE `$table` DROP COLUMN `$fieldName`;";
                    $pdo   = getPdo();
                    $pdo->exec($sql);
                    echo "✅ ALTER TABLE executat: $sql\n";
                } catch (PDOException $e) {
                    echo "❌ Eroare la ALTER TABLE: " . $e->getMessage() . "\n";
                }
            }
        } elseif ($choice === '4') {
            $oldTable = $config['table'] ?? strtolower($module);
            echo "Numele actual al tabelei: $oldTable\n";
            $newTable = prompt("Nume nou pentru tabel (gol = anulează): ");
            if ($newTable === '') {
                echo "Anulat.\n";
                continue;
            }
            $doAlter = strtolower(prompt("Vrei să redenumești și tabela în DB? (y/n): "));
            if ($doAlter === 'y') {
                try {
                    $sql = "RENAME TABLE `$oldTable` TO `$newTable`;";
                    $pdo = getPdo();
                    $pdo->exec($sql);
                    echo "✅ Tabela a fost redenumită în DB: $oldTable → $newTable\n";
                } catch (PDOException $e) {
                    echo "❌ Eroare la RENAME TABLE: " . $e->getMessage() . "\n";
                }
            }
            $config['table'] = $newTable;
            echo "✅ Nume tabel actualizat în config: $newTable\n";
        } elseif ($choice === '5') {
            file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "✅ Config salvat în $configPath\n";
        } elseif ($choice === '6') {
            // întrebări ca în „quiz”: vrei DB? vrei rute?
            $withDb     = strtolower(prompt("Vrei să creez / actualizez tabela MySQL pentru acest modul? (y/n): ")) === 'y';
            $withRoutes = strtolower(prompt("Vrei să inserez rutele în tabela routes? (y/n): ")) === 'y';

            file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "💾 Config salvat. Generăm modulul...\n";
            generateModule($module, $config, $withDb, $withRoutes);
        } elseif ($choice === '7') {
            uninstallModule($module, $configPath);
            // după dezinstalare, ieșim din editor
            break;
        } elseif ($choice === '8') {
            echo "⚠️ Această acțiune va șterge și va recrea tabela în DB pe baza config-ului.\n";
            $ok = strtolower(prompt("Sigur vrei să continui? (y/n): "));
            if ($ok === 'y') {
                syncTableFromConfig($module, $config, true);
            } else {
                echo "Anulat.\n";
            }
        } else {
            echo "⚠️ Opțiune necunoscută.\n";
        }
    }

    // la final, salvăm oricum config-ul (dacă mai există sens)
    if (!file_exists($configPath)) {
        echo "ℹ️ Config a fost șters în timpul dezinstalării.\n";
    } else {
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "💾 Config final salvat în $configPath\n";
    }
}

// ================================
// ENTRYPOINT
// ================================

$configDir = __DIR__ . '/generator/config/';

// CLI args
$moduleArg  = $argv[1] ?? '';
$secondArg  = $argv[2] ?? '';

// 1) Mod UNINSTALL direct din shell:
//    php generate_module.php uninstall Firma
//    php generate_module.php uninstall Firma,Cub,Avion
//    php generate_module.php uninstall Firma Cub Avion
if ($moduleArg === 'uninstall' || $moduleArg === '--uninstall') {

    if ($secondArg === '') {
        echo "❌ Trebuie să specifici cel puțin un modul. Exemple:\n";
        echo "   php generate_module.php uninstall Firma\n";
        echo "   php generate_module.php uninstall Firma,Cub,Avion\n";
        echo "   php generate_module.php uninstall Firma Cub Avion\n";
        exit;
    }

    // luăm TOT ce vine după "uninstall" (argv[2..n])
    $raw = implode(' ', array_slice($argv, 2));

    // split după virgulă și spațiu
    $names = preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

    if (empty($names)) {
        echo "❌ Nu am reușit să detectez niciun nume de modul.\n";
        exit;
    }

    foreach ($names as $name) {
        $module     = ucfirst(strtolower($name));
        $configPath = $configDir . strtolower($module) . '.json';

        echo "\n==============================\n";
        echo "🧨 Dezinstalare modul: $module\n";
        echo "==============================\n";

        uninstallModule($module, $configPath);
    }

    echo "\n✅ Procesul de dezinstalare pentru toate modulele a fost rulat.\n";
    exit;
}

// 2) Dacă a fost dat un modul ca argument → mod clasic (generate rapid / sync DB)
if ($moduleArg) {
    $module     = ucfirst(strtolower($moduleArg));
    $configPath = $configDir . strtolower($module) . '.json';

    if (!file_exists($configPath)) {
        // creăm config default
        $tableName = strtolower($module);
        $defaultFields = [
            "name"   => "VARCHAR(255) NOT NULL",
            "email"  => "VARCHAR(255)",
            "phone"  => "VARCHAR(50)",
            "status" => "TINYINT(1) DEFAULT 1"
        ];
        $json = [
            "table"  => $tableName,
            "fields" => $defaultFields
        ];
        @mkdir($configDir, 0755, true);
        file_put_contents($configPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "🆕 Fișier JSON creat: $configPath\n";
        $config = $json;
    } else {
        echo "📄 Fișier JSON găsit: $configPath\n";
        $config = json_decode(file_get_contents($configPath), true) ?: [];
    }

    // Mod special: doar sync DB din config
    // Exemple:
    //   php generate_module.php rooms 2
    //   php generate_module.php rooms db
    //   php generate_module.php rooms syncdb
    if (in_array(strtolower($secondArg), ['2', 'db', 'syncdb'], true)) {
        echo "🔁 MODE: Sync DB pentru modulul $module pe baza config-ului.\n";
        syncTableFromConfig($module, $config, true);
        exit;
    }

    // mod rapid normal: creezi fișiere + DB + rute + chmod
    generateModule($module, $config, true, true);
    exit;
}

// 3) Dacă NU a fost dat argument → MOD INTERACTIV (QUIZ)

echo "🔧 MOD INTERACTIV – EDITOR CONFIG + GENERATOR + UNINSTALL + SYNC DB\n";

@mkdir($configDir, 0755, true);

$configFiles = glob($configDir . '*.json');

if (empty($configFiles)) {
    echo "Nu există niciun fișier de config încă.\n";
    $newModule = prompt("Introdu numele unui modul nou (ex: Firma): ");
    if ($newModule === '') {
        echo "Niciun modul introdus. Ieșire.\n";
        exit;
    }
    $module = ucfirst(strtolower($newModule));
    $configPath = $configDir . strtolower($module) . '.json';
    interactiveConfigEditor($module, $configPath);
    exit;
}

// listăm config-urile existente
echo "Config-uri existente:\n";
$modules = [];
$i = 1;
foreach ($configFiles as $file) {
    $base = basename($file, '.json');
    $modules[$i] = $base;
    echo " $i) $base\n";
    $i++;
}

echo " n) Modul nou\n";

$choice = prompt("Alege număr sau 'n' pentru modul nou: ");

if (strtolower($choice) === 'n') {
    $newModule = prompt("Introdu numele unui modul nou (ex: Firma): ");
    if ($newModule === '') {
        echo "Niciun modul introdus. Ieșire.\n";
        exit;
    }
    $module = ucfirst(strtolower($newModule));
    $configPath = $configDir . strtolower($module) . '.json';
    interactiveConfigEditor($module, $configPath);
} else {
    $idx = (int)$choice;
    if (!isset($modules[$idx])) {
        echo "Opțiune invalidă. Ieșire.\n";
        exit;
    }
    $module     = ucfirst(strtolower($modules[$idx]));
    $configPath = $configDir . strtolower($module) . '.json';
    interactiveConfigEditor($module, $configPath);
}
