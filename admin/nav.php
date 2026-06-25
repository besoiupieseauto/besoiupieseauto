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

// ================================
// HELPER: LISTĂ MODULE DIN CONFIG
// ================================

/**
 * Alege modulul din lista de config-uri existente (generator/config/*.json)
 * Dacă nu există niciun config, te lasă să scrii manual.
 */
function chooseModule(): string {
    $configDir = __DIR__ . '/generator/config/';
    @mkdir($configDir, 0755, true);

    $configFiles = glob($configDir . '*.json');

    if (empty($configFiles)) {
        echo "ℹ️ Nu există config-uri în generator/config. Scrie numele modulului manual.\n";
        $new = prompt("Nume modul (ex: Rooms, Firms, Doc): ");
        return ucfirst(strtolower($new));
    }

    echo "Alege un modul din lista de config-uri:\n";
    $modules = [];
    $i = 1;
    foreach ($configFiles as $file) {
        $base = basename($file, '.json'); // ex: rooms, firms
        $modules[$i] = $base;
        echo " $i) $base\n";
        $i++;
    }
    echo " n) Modul nou (scrii manual)\n";

    while (true) {
        $choice = prompt("Număr modul sau 'n': ");
        if (strtolower($choice) === 'n') {
            $new = prompt("Nume modul nou (ex: Rooms, Firms, Doc): ");
            return ucfirst(strtolower($new));
        }

        $idx = (int)$choice;
        if (isset($modules[$idx])) {
            $m = ucfirst(strtolower($modules[$idx]));
            echo "➡️ Ai ales modulul: {$m}\n";
            return $m;
        }

        echo "⚠️ Opțiune invalidă, încearcă din nou.\n";
    }
}

// ================================
// HELPER: LISTĂ ROLURI
// ================================

/**
 * Afișează o listă de roluri și întoarce rolul ales.
 * Combină:
 *  - rolurile din DB (role_nav)
 *  - cu o listă default
 */
function chooseRoleSlug(): string {
    $pdo = getPdo();

    $dbRoles = [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT role_slug FROM role_nav ORDER BY role_slug ASC");
        $dbRoles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        $dbRoles = [];
    }

    $defaultRoles = [
        'super_ambassador',
        'regional_ambassador',
        'manager',
        'executive',
        'guest',
    ];

    // combinăm și eliminăm duplicatele
    $roles = array_values(array_unique(array_merge($dbRoles, $defaultRoles)));

    echo "\nAlege un rol din listă:\n";
    foreach ($roles as $i => $r) {
        $nr = $i + 1;
        echo " $nr) $r\n";
    }

    while (true) {
        $choice = prompt("Numărul rolului: ");
        $idx = (int)$choice;
        if ($idx >= 1 && $idx <= count($roles)) {
            $chosen = $roles[$idx - 1];
            echo "➡️ Ai ales rolul: {$chosen}\n";
            return $chosen;
        }
        echo "⚠️ Opțiune invalidă, încearcă din nou.\n";
    }
}

// ================================
// HELPER: sort_order SUGERAT
// ================================

/**
 * Sugerează următorul sort_order pentru acel rol + parent_id:
 * - ia MAX(sort_order) și pune +10
 * - dacă nu există, întoarce 10
 */
function suggestSortOrderForRole(string $roleSlug, ?int $parentId): int {
    $pdo = getPdo();

    try {
        if ($parentId === null) {
            $sql = "SELECT MAX(sort_order) FROM role_nav WHERE role_slug = :r AND parent_id IS NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':r' => $roleSlug]);
        } else {
            $sql = "SELECT MAX(sort_order) FROM role_nav WHERE role_slug = :r AND parent_id = :p";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':r' => $roleSlug, ':p' => $parentId]);
        }

        $max = (int)$stmt->fetchColumn();
        if ($max <= 0) {
            return 10;
        }
        return $max + 10;
    } catch (PDOException $e) {
        return 10;
    }
}

// ================================
// DB OPS
// ================================

function addNavItem(array $data): void {
    $pdo = getPdo();

    $sql = "
        INSERT INTO role_nav
        (role_slug, label, path, prime, is_active, url, sort_order, parent_id, icon)
        VALUES
        (:role_slug, :label, :path, :prime, :is_active, :url, :sort_order, :parent_id, :icon)
    ";

    $stmt = $pdo->prepare($sql);

    $parentId = $data['parent_id'] === null ? null : (int)$data['parent_id'];

    $stmt->execute([
        ':role_slug'  => $data['role_slug'],
        ':label'      => $data['label'],
        ':path'       => $data['path'],
        ':prime'      => $data['prime'],
        ':is_active'  => 1,
        ':url'        => $data['url'],
        ':sort_order' => $data['sort_order'],
        ':parent_id'  => $parentId,
        ':icon'       => $data['icon'],
    ]);

    echo "✅ Nav item creat: [{$data['role_slug']}] {$data['label']} → {$data['url']}\n";
}

function listNav(?string $roleSlug = null): void {
    $pdo = getPdo();

    if ($roleSlug) {
        $stmt = $pdo->prepare("SELECT * FROM role_nav WHERE role_slug = :r ORDER BY sort_order ASC, id ASC");
        $stmt->execute([':r' => $roleSlug]);
    } else {
        $stmt = $pdo->query("SELECT * FROM role_nav ORDER BY role_slug, sort_order ASC, id ASC");
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "ℹ️ Nu există iteme în role_nav pentru filtrul dat.\n";
        return;
    }

    $currentRole = null;
    foreach ($rows as $row) {
        if ($currentRole !== $row['role_slug']) {
            $currentRole = $row['role_slug'];
            echo "\n=== ROLE: {$currentRole} ===\n";
        }
        $parent = $row['parent_id'] ? " (parent_id={$row['parent_id']})" : '';
        echo "#{$row['id']} [sort {$row['sort_order']}] {$row['label']} → {$row['url']}{$parent}\n";
    }
}

// ================================
// MOD INTERACTIV NAV
// ================================

function interactiveNav(): void {
    echo "🔧 MOD INTERACTIV – CREARE NAV ITEM\n\n";

    // 1) Alegi modulul din lista de config-uri
    $module      = chooseModule();
    $moduleLower = strtolower($module);

    // 2) Alegi rolul din listă
    $role = chooseRoleSlug();

    // 3) Label
    $defaultLabel = $module;
    $label = prompt("Label pentru meniu (default: {$defaultLabel}): ");
    if ($label === '') {
        $label = $defaultLabel;
    }

    // 4) URL / path
    $defaultUrl = "/public/{$moduleLower}";
    $url = prompt("URL/path (default: {$defaultUrl}): ");
    if ($url === '') {
        $url = $defaultUrl;
    }

    // 5) parent_id
    $parentStr = prompt("parent_id (gol = NULL / item root): ");
    $parentId = $parentStr === '' ? null : (int)$parentStr;

    // 6) sort_order – calculat automat
    $suggestedSort = suggestSortOrderForRole($role, $parentId);
    $sortStr = prompt("sort_order (default sugerat: {$suggestedSort}): ");
    $sort = (int)($sortStr === '' ? $suggestedSort : $sortStr);

    // 7) prime (yes / NULL)
    $primeStr = strtolower(prompt("Prime (yes/ENTER pentru NULL, default NULL): "));
    $prime = ($primeStr === 'yes') ? 'yes' : null;

    // 8) icon
    $defaultIcon = 'bx bx-right-arrow-alt';
    $icon = prompt("Icon (default: {$defaultIcon}): ");
    if ($icon === '') {
        $icon = $defaultIcon;
    }

    echo "\n--- REZUMAT ---\n";
    echo "module    : {$module}\n";
    echo "role_slug : {$role}\n";
    echo "label     : {$label}\n";
    echo "url/path  : {$url}\n";
    echo "parent_id : " . ($parentId === null ? 'NULL' : $parentId) . "\n";
    echo "sort_order: {$sort}\n";
    echo "prime     : " . ($prime ?? 'NULL') . "\n";
    echo "icon      : {$icon}\n";
    echo "----------------\n";

    $ok = strtolower(prompt("Confirmi inserarea? (y/n): "));
    if ($ok !== 'y') {
        echo "Anulat.\n";
        return;
    }

    addNavItem([
        'role_slug'  => $role,
        'label'      => $label,
        'path'       => $url,   // la tine path == url
        'prime'      => $prime,
        'url'        => $url,
        'sort_order' => $sort,
        'parent_id'  => $parentId,
        'icon'       => $icon,
    ]);

    echo "🎉 Gata, ai un nav nou pentru modulul {$module}.\n";
}

// ================================
// ENTRYPOINT
// ================================

$arg1 = $argv[1] ?? null;

// 1) Mod listare: php nav.php list [role_slug]
if ($arg1 === 'list') {
    $role = $argv[2] ?? null;
    listNav($role);
    exit;
}

// 2) Mod direct (non-interactiv – dacă insiști să dai totul dintr-o comandă):
//    php nav.php rooms super_ambassador "Rooms" 20 "bx bx-home" 0
if ($arg1 && $arg1 !== 'list') {
    $module      = ucfirst(strtolower($arg1));
    $moduleLower = strtolower($module);

    $role       = $argv[2] ?? null;
    if ($role === null) {
        echo "❌ Lipsă role_slug. Exemplu:\n";
        echo "   php nav.php rooms super_ambassador \"Rooms\" 20\n";
        exit;
    }

    $label      = $argv[3] ?? $module;
    $sort       = isset($argv[4]) ? (int)$argv[4] : 10;
    $icon       = $argv[5] ?? 'bx bx-right-arrow-alt';
    $parentArg  = $argv[6] ?? '';
    $parentId   = $parentArg === '' ? null : (int)$parentArg;

    $url        = "/public/{$moduleLower}";

    echo "📦 Creăm nav pentru modul: $module\n";

    addNavItem([
        'role_slug'  => $role,
        'label'      => $label,
        'path'       => $url,
        'prime'      => null,
        'url'        => $url,
        'sort_order' => $sort,
        'parent_id'  => $parentId,
        'icon'       => $icon,
    ]);

    echo "🎉 Gata.\n";
    exit;
}

// 3) Dacă nu ai dat argumente → mod interactiv
interactiveNav();
