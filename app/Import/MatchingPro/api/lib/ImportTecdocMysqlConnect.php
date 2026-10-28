<?php
declare(strict_types=1);

/**
 * Maparea cartelelor: MySQL Laragon de pe discul D, port 3308.
 * Baza tecdoc_rebuild_1998_20261010, tabele matc_{brand}. Nu folosi portul 3306.
 *
 * @return array{host:string,port:int,user:string,pass:string,db:string}
 */
function import_tecdoc_mysql_cfg(): array
{
    static $cfg = null;
    if (is_array($cfg)) {
        return $cfg;
    }

    $settings = [];
    foreach ([
        dirname(__DIR__, 2) . '/config/settings.json',
        dirname(__DIR__, 4) . '/Import/MatchingPro/config/settings.json',
        '/var/www/besoiupieseauto.ro/app/Import/MatchingPro/config/settings.json',
    ] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (is_array($json) && $json !== []) {
            $settings = $json;
            break;
        }
    }

    $env = static function (string $key, string $default = ''): string {
        $v = getenv($key);
        if ($v === false || $v === '') {
            $v = (string) ($_ENV[$key] ?? '');
        }

        return $v !== '' ? (string) $v : $default;
    };

    $port = (int) $env('TECDOC_DB_PORT', (string) ($settings['tecdoc_mysql_port'] ?? 3308));
    if ($port <= 0) {
        $port = 3306;
    }

    $pass = $env('TECDOC_DB_PASS', (string) ($settings['tecdoc_mysql_password'] ?? ''));
    if ($pass === '') {
        $pass = $env('DB_PASS', '');
    }

    $cfg = [
        'host' => $env('TECDOC_DB_HOST', (string) ($settings['tecdoc_mysql_host'] ?? '127.0.0.1')),
        'port' => $port,
        'user' => $env('TECDOC_DB_USER', (string) ($settings['tecdoc_mysql_user'] ?? $env('DB_USER', 'pieseauto'))),
        'pass' => $pass,
        'db' => $env('TECDOC_DB_NAME', (string) ($settings['tecdoc_mysql_db'] ?? 'tecdoc_rebuild_1998_20261010')),
    ];

    return $cfg;
}

function import_tecdoc_mysql_dsn(?string $db = null): string
{
    $c = import_tecdoc_mysql_cfg();
    $name = $db ?? $c['db'];

    return sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $c['host'],
        $c['port'],
        $name
    );
}

function import_tecdoc_mysql_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = import_tecdoc_mysql_cfg();
    $pdo = new PDO(
        import_tecdoc_mysql_dsn(),
        $c['user'],
        $c['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 8,
        ]
    );

    return $pdo;
}

function import_tecdoc_norm_code(string $code): string
{
    return str_replace([' ', '-', '/', '.'], '', strtoupper(trim($code)));
}

function import_tecdoc_uses_matc_cards(PDO $pdo): bool
{
    static $flag = null;
    if ($flag !== null) {
        return $flag;
    }
    $schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare(
        'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_name IN (\'products\', \'matc_valeo\')'
    );
    $st->execute([$schema]);
    $names = $st->fetchAll(PDO::FETCH_COLUMN);
    $flag = !in_array('products', $names, true) && in_array('matc_valeo', $names, true);

    return $flag;
}

function import_tecdoc_matc_table_for_brand(PDO $pdo, string $brand): ?string
{
    static $tables = null;
    if ($tables === null) {
        $tables = [];
        $schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $st = $pdo->prepare(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = ? AND table_name LIKE 'matc_%'
               AND table_name NOT LIKE '%backup%' AND table_name NOT LIKE '%before%'"
        );
        $st->execute([$schema]);
        while ($name = $st->fetchColumn()) {
            $tables[strtolower((string) $name)] = (string) $name;
        }
    }

    $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $brand));
    $aliases = [
        'val' => 'valeo',
        'feb' => 'febibilstein',
        'febi' => 'febibilstein',
        'dep' => 'abakus',
        'depo' => 'abakus',
        'lor' => 'abakus',
        'loro' => 'abakus',
        'aba' => 'abakus',
        'abakusdepo' => 'abakus',
    ];
    if (isset($aliases[$slug])) {
        $slug = $aliases[$slug];
    }
    $key = 'matc_' . $slug;

    return $tables[$key] ?? null;
}

/**
 * Cartelă din tabelele matc_{brand} (ART_BRAND + ART_CODE). Null dacă baza are deja `products`.
 *
 * @return array{id:int,art_code_1:string,art_name:string,art_ean:string,brand:string,score:int}|null
 */
function import_tecdoc_matc_find_card(PDO $pdo, string $brand, string $code): ?array
{
    if (!import_tecdoc_uses_matc_cards($pdo)) {
        return null;
    }
    $table = import_tecdoc_matc_table_for_brand($pdo, $brand);
    $norm = import_tecdoc_norm_code($code);
    if ($table === null || $norm === '' || !preg_match('/^matc_[a-z0-9_]+$/', $table)) {
        return null;
    }

    $st = $pdo->prepare(
        'SELECT ART_CODE_1, ART_NAME, ART_EAN, ART_BRAND FROM `' . $table . '` WHERE ART_CODE_1 = ? OR ART_CODE_2 = ? LIMIT 1'
    );
    $raw = trim($code);
    $st->execute([$raw, $raw]);
    $hit = $st->fetch(PDO::FETCH_ASSOC);
    if (!$hit && $norm !== strtoupper($raw)) {
        $st->execute([$norm, $norm]);
        $hit = $st->fetch(PDO::FETCH_ASSOC);
    }
    if (!$hit) {
        return null;
    }

    return [
        'id' => 0,
        'art_code_1' => (string) ($hit['ART_CODE_1'] ?? ''),
        'art_name' => (string) ($hit['ART_NAME'] ?? ''),
        'art_ean' => (string) ($hit['ART_EAN'] ?? ''),
        'brand' => (string) ($hit['ART_BRAND'] ?? $brand),
        'score' => 10,
    ];
}
