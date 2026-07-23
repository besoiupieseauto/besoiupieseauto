<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

/**
 * Config 10 site-uri — ce URL, ce categorie, ce misiune (instrucțiuni scrape).
 */
final class AiUserSitesConfigService
{
    public const MAX_SITES = 10;

    /** @var list<string> */
    public const TIP_SURSA_OPTIONS = [
        'catalog_produse' => 'Catalog produse (titlu, preț, SKU, imagini)',
        'pret_concurenta' => 'Preț concurență',
        'seo_keyword' => 'SEO / cuvinte cheie',
        'specificatie_tehnica' => 'Specificații tehnice',
        'tendinta_piata' => 'Tendințe / forum / articole',
    ];

    private string $root;
    private string $configPath;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $dir = $this->root . '/admin/storage/ai_rag';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->configPath = $dir . '/user_sites.json';
        $this->ensureFile();
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $json = json_decode((string) file_get_contents($this->configPath), true);

        return is_array($json) ? $this->normalizeConfig($json) : $this->emptyConfig();
    }

    /** @return list<array<string, mixed>> */
    public function slots(): array
    {
        return $this->all()['sites'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function activeSites(): array
    {
        $out = [];
        foreach ($this->slots() as $site) {
            if ((string) ($site['status'] ?? '') === 'active' && trim((string) ($site['url'] ?? '')) !== '') {
                $out[] = $site;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return array<string, mixed>
     */
    public function save(array $sites): array
    {
        $normalized = [];
        for ($i = 1; $i <= self::MAX_SITES; ++$i) {
            $incoming = null;
            foreach ($sites as $s) {
                if (!is_array($s)) {
                    continue;
                }
                if ((int) ($s['slot'] ?? 0) === $i) {
                    $incoming = $s;
                    break;
                }
            }
            $normalized[] = $this->normalizeSite($incoming ?? ['slot' => $i], $i);
        }

        $cfg = $this->all();
        $cfg['sites'] = $normalized;
        $cfg['_meta']['updated_at'] = date('c');
        @file_put_contents($this->configPath, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['ok' => true, 'sites' => $normalized, 'active_count' => count(array_filter(
            $normalized,
            static fn (array $s): bool => ($s['status'] ?? '') === 'active' && ($s['url'] ?? '') !== ''
        ))];
    }

    /** @return array<string, mixed> */
    private function emptyConfig(): array
    {
        $sites = [];
        for ($i = 1; $i <= self::MAX_SITES; ++$i) {
            $sites[] = $this->normalizeSite([], $i);
        }

        return [
            '_meta' => ['updated_at' => date('c'), 'max_sites' => self::MAX_SITES],
            'defaults' => ['delay_min_sec' => 2, 'delay_max_sec' => 5, 'max_follow' => 3],
            'sites' => $sites,
        ];
    }

    /** @param array<string, mixed> $cfg @return array<string, mixed> */
    private function normalizeConfig(array $cfg): array
    {
        $base = $this->emptyConfig();
        $sites = [];
        $incoming = is_array($cfg['sites'] ?? null) ? $cfg['sites'] : [];
        for ($i = 1; $i <= self::MAX_SITES; ++$i) {
            $found = null;
            foreach ($incoming as $s) {
                if (is_array($s) && (int) ($s['slot'] ?? 0) === $i) {
                    $found = $s;
                    break;
                }
            }
            $sites[] = $this->normalizeSite($found ?? [], $i);
        }
        $base['sites'] = $sites;
        $base['_meta'] = array_merge($base['_meta'], is_array($cfg['_meta'] ?? null) ? $cfg['_meta'] : []);
        $base['defaults'] = array_merge($base['defaults'], is_array($cfg['defaults'] ?? null) ? $cfg['defaults'] : []);

        return $base;
    }

    /** @param array<string, mixed> $site @return array<string, mixed> */
    private function normalizeSite(array $site, int $slot): array
    {
        $url = trim((string) ($site['url'] ?? ''));
        $tip = trim((string) ($site['tip_sursa'] ?? 'catalog_produse'));
        if (!array_key_exists($tip, self::TIP_SURSA_OPTIONS)) {
            $tip = 'catalog_produse';
        }
        $status = trim((string) ($site['status'] ?? 'paused'));
        if (!in_array($status, ['active', 'paused', 'blocked'], true)) {
            $status = $url !== '' ? 'active' : 'paused';
        }

        return [
            'slot' => $slot,
            'id' => 'site_' . $slot,
            'name' => trim((string) ($site['name'] ?? '')),
            'url' => $url,
            'category' => trim((string) ($site['category'] ?? '')),
            'mission' => trim((string) ($site['mission'] ?? '')),
            'tip_sursa' => $tip,
            'tip_label' => self::TIP_SURSA_OPTIONS[$tip],
            'follow_links' => !isset($site['follow_links']) || !empty($site['follow_links']),
            'max_follow' => max(0, min(8, (int) ($site['max_follow'] ?? 3))),
            'status' => $status,
            'last_run_at' => (string) ($site['last_run_at'] ?? ''),
            'last_run_ok' => !empty($site['last_run_ok']),
            'last_fragments' => (int) ($site['last_fragments'] ?? 0),
        ];
    }

    private function ensureFile(): void
    {
        if (!is_file($this->configPath)) {
            $empty = $this->emptyConfig();
            @file_put_contents($this->configPath, json_encode($empty, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    /** @param array<string, mixed> $patch */
    public function patchSlot(int $slot, array $patch): void
    {
        $cfg = $this->all();
        foreach ($cfg['sites'] as &$s) {
            if ((int) ($s['slot'] ?? 0) === $slot) {
                $s = $this->normalizeSite(array_merge($s, $patch), $slot);
                break;
            }
        }
        unset($s);
        $cfg['_meta']['updated_at'] = date('c');
        @file_put_contents($this->configPath, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
