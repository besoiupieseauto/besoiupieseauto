<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

/**
 * Surse pilot research piață — config JSON + robots.txt check.
 */
final class AiMarketResearchSourceService
{
    private string $root;
    private string $configPath;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $dir = $this->root . '/admin/storage/ai_rag';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->configPath = $dir . '/market_sources.json';
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if (!is_file($this->configPath)) {
            return ['sources' => [], 'defaults' => []];
        }
        $json = json_decode((string) file_get_contents($this->configPath), true);

        return is_array($json) ? $json : ['sources' => []];
    }

    /** @return array<string, mixed>|null */
    public function get(string $sourceId): ?array
    {
        foreach ($this->all()['sources'] ?? [] as $src) {
            if (is_array($src) && (string) ($src['id'] ?? '') === $sourceId) {
                return $src;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public function activeSources(): array
    {
        $out = [];
        foreach ($this->all()['sources'] ?? [] as $src) {
            if (!is_array($src)) {
                continue;
            }
            if ((string) ($src['status'] ?? '') === 'active') {
                $out[] = $src;
            }
        }

        return $out;
    }

    /** @return array{allowed:bool,reason:string,robots_url:string} */
    public function checkRobots(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return ['allowed' => false, 'reason' => 'URL invalid', 'robots_url' => ''];
        }
        $scheme = $parts['scheme'] ?? 'https';
        $robotsUrl = $scheme . '://' . $parts['host'] . '/robots.txt';
        $path = (string) ($parts['path'] ?? '/');

        $ch = curl_init($robotsUrl);
        if ($ch === false) {
            return ['allowed' => true, 'reason' => 'robots.txt indisponibil — continuă cu precauție', 'robots_url' => $robotsUrl];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'BesoiuMarketResearch/1.0 (+internal)',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        if (!is_string($body) || trim($body) === '') {
            return ['allowed' => true, 'reason' => 'robots.txt gol/lipsă', 'robots_url' => $robotsUrl];
        }

        $disallowed = false;
        $inAll = false;
        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^User-agent:\s*\*/i', $line)) {
                $inAll = true;
                continue;
            }
            if (preg_match('/^User-agent:/i', $line)) {
                $inAll = false;
                continue;
            }
            if ($inAll && preg_match('/^Disallow:\s*(.+)/i', $line, $m)) {
                $rule = trim($m[1]);
                if ($rule !== '' && str_starts_with($path, $rule)) {
                    $disallowed = true;
                }
            }
        }

        return [
            'allowed' => !$disallowed,
            'reason' => $disallowed ? 'Disallow robots.txt pentru calea ' . $path : 'Permis conform robots.txt',
            'robots_url' => $robotsUrl,
        ];
    }

    public function humanDelay(): void
    {
        $cfg = $this->all()['defaults'] ?? [];
        $min = max(1, (int) ($cfg['delay_min_sec'] ?? 2));
        $max = max($min, (int) ($cfg['delay_max_sec'] ?? 5));
        usleep(random_int($min * 1_000_000, $max * 1_000_000));
    }

    /**
     * Adaugă/actualizează până la 5 surse definite de operator (URL + categorie).
     *
     * @param list<array<string, mixed>> $sites name, url, category
     * @return list<array<string, mixed>>
     */
    public function upsertUserSites(array $sites): array
    {
        $sites = array_slice(array_values($sites), 0, 5);
        $cfg = $this->all();
        $existing = is_array($cfg['sources'] ?? null) ? $cfg['sources'] : [];
        $saved = [];

        foreach ($sites as $i => $site) {
            if (!is_array($site)) {
                continue;
            }
            $url = trim((string) ($site['url'] ?? ''));
            $name = trim((string) ($site['name'] ?? ('Sursă ' . ($i + 1))));
            $category = trim((string) ($site['category'] ?? ''));
            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $host = (string) parse_url($url, PHP_URL_HOST);
            $id = 'user_' . substr(hash('sha256', $url), 0, 10);

            $row = [
                'id' => $id,
                'name' => $name,
                'domain' => $host,
                'category' => $category,
                'tip_sursa' => 'seo_keyword',
                'url_template' => $url,
                'frequency' => 'manual',
                'status' => 'active',
                'use_stealth' => true,
                'robots_checked' => false,
                'notes' => 'Adăugat din panou Bibliotecă — categorie: ' . ($category !== '' ? $category : '—'),
            ];

            $found = false;
            foreach ($existing as &$ex) {
                if (is_array($ex) && (string) ($ex['id'] ?? '') === $id) {
                    $ex = array_merge($ex, $row);
                    $found = true;
                    break;
                }
            }
            unset($ex);
            if (!$found) {
                $existing[] = $row;
            }
            $saved[] = $row;
        }

        $cfg['sources'] = $existing;
        $cfg['_meta']['updated_at'] = date('c');
        @file_put_contents($this->configPath, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $saved;
    }
}
