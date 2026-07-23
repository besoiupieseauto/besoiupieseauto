<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/** Mapare bot (randomn_id) → agent AI slug. */
final class AiBotAgentLinkService
{
    private string $linksPath;

    public function __construct(?string $linksPath = null)
    {
        $root = dirname(__DIR__, 3);
        $this->linksPath = $linksPath ?? ($root . '/robot/data/ai_agents/bot_links.json');
    }

    /** @return array<string, string> */
    public function allLinks(): array
    {
        $data = $this->readLinks();

        return is_array($data) ? $data : [];
    }

    public function getAgentSlugForBot(int $randomnId): ?string
    {
        $links = $this->allLinks();
        $key = (string) $randomnId;
        $slug = $links[$key] ?? null;

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function setBotAgent(int $randomnId, ?string $agentSlug): void
    {
        $links = $this->allLinks();
        $key = (string) $randomnId;
        $registry = new AiAgentRegistryService();
        $normalized = null;

        if ($agentSlug !== null && trim($agentSlug) !== '') {
            if ($registry->getAgent($agentSlug) === null) {
                throw new \InvalidArgumentException('Agentul nu există: ' . $agentSlug);
            }
            $normalized = $registry->normalizeSlug($agentSlug);
            $links[$key] = $normalized;
        } else {
            unset($links[$key]);
        }

        if ($this->botChannel($randomnId) === 'website') {
            if ($normalized !== null) {
                $links['_website_default'] = $normalized;
            } elseif (($links['_website_default'] ?? '') !== '' && !in_array($links['_website_default'], $links, true)) {
                // păstrează default dacă alt bot website e legat
            } elseif (!isset($links['_website_default'])) {
                unset($links['_website_default']);
            }
        }

        $this->writeLinks($links);
    }

    public function defaultWebsiteAgentSlug(): string
    {
        $links = $this->allLinks();
        $slug = (string) ($links['_website_default'] ?? '');

        return $slug !== '' ? $slug : 'context-master';
    }

    private function botChannel(int $randomnId): string
    {
        try {
            $pdo = Database::getDB();
            $stmt = $pdo->prepare('SELECT channel FROM bots WHERE randomn_id = ? LIMIT 1');
            $stmt->execute([$randomnId]);
            $channel = $stmt->fetchColumn();

            return is_string($channel) ? $channel : '';
        } catch (Throwable) {
            return '';
        }
    }

    /** @return list<array<string, mixed>> */
    public function listBotsWithAgents(): array
    {
        $links = $this->allLinks();
        $registry = new AiAgentRegistryService();
        $agentsBySlug = [];
        foreach ($registry->listAgents() as $a) {
            $agentsBySlug[(string) ($a['slug'] ?? '')] = $a;
        }

        $out = [];
        try {
            $pdo = Database::getDB();
            $rows = $pdo->query(
                'SELECT randomn_id, name, channel, bot_type, token_status FROM bots ORDER BY name ASC'
            )?->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $rid = (int) ($row['randomn_id'] ?? 0);
                $slug = $links[(string) $rid] ?? '';
                $out[] = [
                    'randomn_id' => $rid,
                    'name' => (string) ($row['name'] ?? ''),
                    'channel' => (string) ($row['channel'] ?? ''),
                    'bot_type' => (string) ($row['bot_type'] ?? ''),
                    'token_status' => (string) ($row['token_status'] ?? ''),
                    'ai_agent_slug' => $slug,
                    'ai_agent_name' => $slug !== '' ? (string) ($agentsBySlug[$slug]['name'] ?? $slug) : '',
                ];
            }
        } catch (Throwable) {
            // fără bots table
        }

        return $out;
    }

    /** @return array<string, string> */
    private function readLinks(): array
    {
        if (!is_file($this->linksPath)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($this->linksPath), true);

        return is_array($json) ? $json : [];
    }

    /** @param array<string, string> $links */
    private function writeLinks(array $links): void
    {
        $dir = dirname($this->linksPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents($this->linksPath, json_encode($links, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
