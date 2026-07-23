<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Config\Database;
use PDO;
use Throwable;

/**
 * Completează sesiuni fără IP/geo — lookup batch via ip-api.com.
 */
final class VisitorSessionEnricherService
{
    private PDO $pdo;

    public function __construct(
        private readonly EventTrackingStore $store,
        ?PDO $pdo = null,
        private readonly ?string $projectRoot = null,
    ) {
        $root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->pdo = $pdo ?? $this->resolvePdo($root);
    }

    /** @return array{scanned:int,updated:int,geo_resolved:int,provider:string} */
    public function enrichMissing(int $limit = 200): array
    {
        $this->store->ensureSessionVisitorColumns();
        $limit = max(1, min(500, $limit));
        $table = EventTrackingStore::TABLE_SESSIONS;
        $geo = new VisitorGeoLookupService($this->projectRoot);

        $stmt = $this->pdo->prepare(
            "SELECT session_id, ip_address, country_code, country_name, city, user_agent, device_type, browser, os, referrer
             FROM {$table}
             WHERE (ip_address IS NULL OR ip_address = '' OR country_code IS NULL OR country_code = '' OR country_code IN ('UNK', 'LAN'))
             ORDER BY COALESCE(last_seen_at, started_at) DESC
             LIMIT {$limit}"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $stmt->closeCursor();

        $publicIps = [];
        foreach ($rows as $row) {
            $ip = trim((string) ($row['ip_address'] ?? ''));
            if ($ip !== '' && !VisitorContextResolver::isPrivateIp($ip)) {
                $publicIps[] = $ip;
            }
        }
        $batchGeo = $geo->lookupBatch($publicIps);
        $selfGeo = $geo->lookupSelf();

        $updated = 0;
        $geoResolved = 0;

        foreach ($rows as $row) {
            $patch = $this->buildPatch($row, $batchGeo, $selfGeo);
            if ($patch === []) {
                continue;
            }
            $cc = (string) ($patch['country_code'] ?? '');
            if ($cc !== '' && !in_array($cc, ['UNK'], true)) {
                ++$geoResolved;
            }
            if ($this->applyPatch((string) $row['session_id'], $patch)) {
                ++$updated;
            }
        }

        return [
            'scanned' => count($rows),
            'updated' => $updated,
            'geo_resolved' => $geoResolved,
            'provider' => 'ip-api.com',
        ];
    }

    /**
     * Test conexiune ip-api.com (admin).
     *
     * @return array<string, mixed>
     */
    public function testGeoProvider(?string $ip = null): array
    {
        $geo = new VisitorGeoLookupService($this->projectRoot);
        if ($ip !== null && $ip !== '' && !VisitorContextResolver::isPrivateIp($ip)) {
            return ['mode' => 'lookup', 'ip' => $ip, 'result' => $geo->lookup($ip)];
        }

        return ['mode' => 'self', 'result' => $geo->lookupSelf()];
    }

    /**
     * @param array<string, array<string, mixed>> $batchGeo
     * @param array<string, mixed> $selfGeo
     * @return array<string, string|null>
     */
    private function buildPatch(array $row, array $batchGeo, array $selfGeo): array
    {
        $patch = [];
        $ip = trim((string) ($row['ip_address'] ?? ''));
        $ua = trim((string) ($row['user_agent'] ?? ''));

        if ($ua !== '') {
            $parsed = VisitorUaParser::parse($ua);
            if (empty($row['device_type'])) {
                $patch['device_type'] = $parsed['device_type'];
            }
            if (empty($row['browser'])) {
                $patch['browser'] = $parsed['browser'];
            }
            if (empty($row['os'])) {
                $patch['os'] = $parsed['os'];
            }
        }

        $cc = trim((string) ($row['country_code'] ?? ''));
        if ($cc !== '' && !in_array($cc, ['UNK', 'LAN'], true)) {
            return array_filter($patch, static fn ($v) => $v !== null && $v !== '');
        }

        if ($ip === '') {
            $patch['country_code'] = 'UNK';
            $patch['country_name'] = 'Necunoscut (sesiune veche)';

            return $patch;
        }

        if (VisitorContextResolver::isPrivateIp($ip)) {
            $patch['ip_address'] = $ip;
            if ($selfGeo !== []) {
                $patch['country_code'] = (string) ($selfGeo['country_code'] ?? 'RO');
                $patch['country_name'] = (string) ($selfGeo['country_name'] ?? 'Romania');
                $patch['city'] = mb_substr('~' . (string) ($selfGeo['city'] ?? 'Local'), 0, 80);
                $patch['region'] = (string) ($selfGeo['region'] ?? '');
            } else {
                $patch['country_code'] = 'LAN';
                $patch['country_name'] = 'Rețea locală';
                $patch['city'] = '127.0.0.1 / LAN';
            }

            return array_filter($patch, static fn ($v) => $v !== null && $v !== '');
        }

        $lookup = $batchGeo[$ip] ?? [];
        if ($lookup !== []) {
            $patch['country_code'] = (string) ($lookup['country_code'] ?? '');
            $patch['country_name'] = (string) ($lookup['country_name'] ?? '');
            $patch['city'] = (string) ($lookup['city'] ?? '');
            $patch['region'] = (string) ($lookup['region'] ?? '');
        }

        return array_filter($patch, static fn ($v) => $v !== null && $v !== '');
    }

    /** @param array<string, string|null> $patch */
    private function applyPatch(string $sessionId, array $patch): bool
    {
        if ($patch === []) {
            return false;
        }

        $sets = [];
        $params = [':sid' => $sessionId];
        foreach ($patch as $col => $val) {
            $sets[] = $col . ' = :' . $col;
            $params[':' . $col] = $val;
        }

        try {
            $sql = 'UPDATE ' . EventTrackingStore::TABLE_SESSIONS . ' SET ' . implode(', ', $sets) . ' WHERE session_id = :sid';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function resolvePdo(string $root): PDO
    {
        if (class_exists(Database::class) && Database::hasConnection()) {
            return Database::getDB();
        }
        $config = require $root . '/admin/config/config.php';
        Database::getInstance(
            (string) ($config['db_host'] ?? '127.0.0.1'),
            (string) ($config['db_name'] ?? ''),

            (string) ($config['db_user'] ?? ''),

            (string) ($config['db_pass'] ?? '')

        );



        return Database::getDB();

    }

}

