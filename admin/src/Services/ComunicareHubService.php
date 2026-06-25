<?php

declare(strict_types=1);

namespace Evasystem\Services;

use Evasystem\Core\Comunicare\ReplyTemplateModel;
use Evasystem\Core\Comunicare\ReplyTemplateRenderer;
use Evasystem\Core\Messages\MessagesModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\ValidationException;

final class ComunicareHubService
{
    public function __construct(
        private readonly ReplyTemplateModel $templates = new ReplyTemplateModel(),
        private readonly MessagesModel $messages = new MessagesModel(),
    ) {
    }

    /** @return array<string, mixed> */
    public function hubStats(): array
    {
        $tpl = $this->templates->stats();
        $unread = $this->countUnreadMessages();
        $channels = $this->channelBreakdown();

        return [
            'templates_total' => $tpl['total'],
            'templates_quick' => $tpl['quick'],
            'templates_uses' => $tpl['uses'],
            'messages_unread' => $unread,
            'channels' => $channels,
            'ideas' => $this->hubIdeas(),
        ];
    }

    public function countUnreadMessages(): int
    {
        try {
            $pdo = \Config\Database::getDB();
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM messages WHERE (is_read = 0 OR is_read IS NULL) AND direction = 'inbound'"
            );

            return (int) ($stmt?->fetchColumn() ?: 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<array<string, mixed>> */
    public function channelBreakdown(): array
    {
        try {
            $pdo = \Config\Database::getDB();
            $rows = $pdo->query(
                "SELECT channel, COUNT(*) AS cnt FROM messages GROUP BY channel ORDER BY cnt DESC LIMIT 12"
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return array_map(static fn (array $r): array => [
                'channel' => (string) ($r['channel'] ?? 'manual'),
                'count' => (int) ($r['cnt'] ?? 0),
            ], $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, string>> */
    private function hubIdeas(): array
    {
        return [
            ['title' => 'Chat mesagerie', 'desc' => 'Inbox unificat WhatsApp, OLX, site, email.', 'url' => '/admin/messages', 'icon' => 'messages-square'],
            ['title' => 'Template-uri răspuns', 'desc' => 'Formulare pentru oferte, stoc, livrare.', 'url' => '/admin/reply-templates', 'icon' => 'file-text'],
            ['title' => 'Răspunsuri rapide', 'desc' => 'Snippet-uri de 1 click în conversație.', 'url' => '/admin/reply-templates?tab=quick', 'icon' => 'zap'],
            ['title' => 'Variabile dinamice', 'desc' => '{client_name}, {order_number}, {total_amount}…', 'url' => '/admin/reply-templates?tab=variables', 'icon' => 'braces'],
            ['title' => 'Preview live', 'desc' => 'Vezi mesajul final înainte de trimitere.', 'url' => '/admin/reply-templates', 'icon' => 'eye'],
            ['title' => 'Inserare în chat', 'desc' => 'Aplică template direct din Mesagerie.', 'url' => '/admin/messages', 'icon' => 'send'],
            ['title' => 'Canale active', 'desc' => 'Statistici per WhatsApp, OLX, Facebook.', 'url' => '/admin/comunicare-canale', 'icon' => 'share-2'],
            ['title' => 'Lead-uri contact', 'desc' => 'Mesaje noi și formulare site.', 'url' => '/admin/comunicare-leads', 'icon' => 'user-plus'],
            ['title' => 'Broadcast', 'desc' => 'Compune mesaj în masă din template.', 'url' => '/admin/comunicare-broadcast', 'icon' => 'megaphone'],
            ['title' => 'Arhivă conversații', 'desc' => 'Istoric închis / rezolvat.', 'url' => '/admin/comunicare-archive', 'icon' => 'archive'],
        ];
    }
}

final class ReplyTemplateService
{
    public function __construct(
        private readonly ReplyTemplateModel $model = new ReplyTemplateModel(),
    ) {
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function list(array $filters = []): array
    {
        return $this->model->findAll($filters);
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $body = trim((string) ($payload['body_text'] ?? ''));
        if ($title === '' || $body === '') {
            throw new ValidationException('Titlul și textul sunt obligatorii.');
        }

        $randomnId = ReplyTemplateRenderer::newRandomId();
        $row = [
            'randomn_id' => $randomnId,
            'title' => $title,
            'slug' => ReplyTemplateRenderer::slugify((string) ($payload['slug'] ?? $title)),
            'category' => trim((string) ($payload['category'] ?? 'general')),
            'channel' => trim((string) ($payload['channel'] ?? 'all')),
            'body_text' => $body,
            'body_html' => (string) ($payload['body_html'] ?? ''),
            'is_quick' => !empty($payload['is_quick']) ? 1 : 0,
            'status' => 1,
        ];

        if (!$this->model->insert($row)) {
            throw new ValidationException('Nu s-a putut salva template-ul.');
        }

        return $this->model->findByRandomId($randomnId) ?? $row;
    }

    /** @param array<string, mixed> $payload */
    public function update(string $randomnId, array $payload): array
    {
        if ($this->model->findByRandomId($randomnId) === null) {
            throw new NotFoundException('Template inexistent.');
        }

        $data = [];
        foreach (['title', 'slug', 'category', 'channel', 'body_text', 'body_html'] as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = trim((string) $payload[$key]);
            }
        }
        if (array_key_exists('is_quick', $payload)) {
            $data['is_quick'] = !empty($payload['is_quick']) ? 1 : 0;
        }

        $this->model->updateByRandomId($randomnId, $data);

        return $this->model->findByRandomId($randomnId) ?? [];
    }

    public function delete(string $randomnId): void
    {
        if ($this->model->findByRandomId($randomnId) === null) {
            throw new NotFoundException('Template inexistent.');
        }
        $this->model->updateByRandomId($randomnId, ['status' => 0]);
    }

    /** @param array<string, string> $variables */
    public function apply(string $randomnId, array $variables = []): array
    {
        $row = $this->model->findByRandomId($randomnId);
        if ($row === null) {
            throw new NotFoundException('Template inexistent.');
        }

        $this->model->incrementUseCount($randomnId);
        $rendered = ReplyTemplateRenderer::render((string) ($row['body_text'] ?? ''), $variables);

        return [
            'template' => $row,
            'rendered_text' => $rendered,
        ];
    }
}
