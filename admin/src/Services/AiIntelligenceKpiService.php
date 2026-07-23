<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Dashboard KPI unificat — produse, import, chat, Ollama, escaladări.
 */
final class AiIntelligenceKpiService
{
    private string $projectRoot;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
    }

    /** @return array<string, mixed> */
    public function snapshot(?PDO $pdo = null, int $searchMissDays = 7): array
    {
        $pdo = $pdo ?? Database::getDB();
        $data = [
            'generated_at' => date('c'),
            'products' => $this->productsBlock($pdo),
            'import' => $this->importBlock($pdo),
            'chat' => $this->chatBlock($pdo, $searchMissDays),
            'ollama' => $this->ollamaBlock(),
            'followup' => $this->followupBlock($pdo),
        ];

        $this->saveDailySnapshot($data);

        return $data;
    }

    /** @return array<string, mixed> */
    private function productsBlock(PDO $pdo): array
    {
        return [
            'active' => $this->scalar($pdo, "SELECT COUNT(*) FROM produse WHERE status <> '0'"),
            'without_image' => $this->scalar($pdo, "SELECT COUNT(*) FROM produse WHERE status <> '0' AND (pImages IS NULL OR pImages = '' OR pImages = '[]')"),
            'categories' => $this->scalar($pdo, "SELECT COUNT(DISTINCT pCategory) FROM produse WHERE status <> '0' AND pCategory <> ''"),
        ];
    }

    /** @return array<string, mixed> */
    private function importBlock(PDO $pdo): array
    {
        return [
            'staging_pending' => $this->scalar($pdo, "SELECT COUNT(*) FROM import_produse WHERE status = 'pending'"),
            'conflict_live' => $this->scalar($pdo, "SELECT COUNT(*) FROM import_produse WHERE status = 'conflict_live'"),
        ];
    }

    /** @return array<string, mixed> */
    private function chatBlock(PDO $pdo, int $days): array
    {
        $missSvc = new ShopSearchMissLogService();
        $escSvc = new ShopChatEscalationService();
        $supervisor = [];

        try {
            $orch = PublicShopChatOrchestrator::create();
            $supervisor = $orch->supervisorStats($days);
        } catch (Throwable) {
            $supervisor = [];
        }

        return [
            'escalations_open' => $escSvc->countOpen($pdo),
            'search_miss_top' => $missSvc->getTopMisses($days, 8, $pdo),
            'supervisor' => $supervisor,
            'orders_week' => $this->scalar($pdo, "SELECT COUNT(*) FROM comenzi WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", true),
        ];
    }

    /** @return array<string, mixed> */
    private function ollamaBlock(): array
    {
        try {
            $runner = new AiOllamaAgentRunnerService($this->projectRoot);
            $block = $runner->status();
            $besoiu = new BesoiuOllamaModelService($this->projectRoot);
            $block['besoiu_model'] = $besoiu->status();

            return $block;
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private function followupBlock(PDO $pdo): array
    {
        $pendingChat = 0;
        $sentChat = 0;
        $openAbandon = 0;

        try {
            $pendingChat = (int) $pdo->query("SELECT COUNT(*) FROM shop_chat_followups WHERE status = 'pending'")->fetchColumn();
            $sentChat = (int) $pdo->query("SELECT COUNT(*) FROM shop_chat_followups WHERE status = 'sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        } catch (Throwable) {
            // optional tables
        }

        if (CartAbandonmentService::tableExists($pdo)) {
            try {
                $openAbandon = CartAbandonmentService::countOpen();
            } catch (Throwable) {
                $openAbandon = 0;
            }
        }

        $waSent = 0;
        $logFile = $this->projectRoot . '/admin/storage/whatsapp_send/send.log';
        if (is_file($logFile)) {
            $waSent = substr_count((string) file_get_contents($logFile), ' ok=1 ');
        }

        $smsSent = 0;
        $smsLog = $this->projectRoot . '/admin/storage/sms_send/send.log';
        if (is_file($smsLog)) {
            $smsSent = substr_count((string) file_get_contents($smsLog), ' ok=1 ');
        }

        return [
            'chat_followups_pending' => $pendingChat,
            'chat_followups_sent_7d' => $sentChat,
            'cart_abandon_open' => $openAbandon,
            'whatsapp_sent_log_hits' => $waSent,
            'whatsapp_configured' => WhatsAppUltraMsgService::isConfigured(),
            'whatsapp_send_enabled' => WhatsAppUltraMsgService::sendEnabled(),
            'sms_configured' => SmsNotificationService::isConfigured(),
            'sms_followup_enabled' => SmsNotificationService::followupSendEnabled(),
            'sms_escalation_enabled' => SmsNotificationService::escalationSendEnabled(),
            'sms_sent_log_hits' => $smsSent,
        ];
    }

    /** @param array<string, mixed> $data */
    private function saveDailySnapshot(array $data): void
    {
        $dir = $this->projectRoot . '/admin/storage/intelligence_kpi';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/daily_' . date('Y-m-d') . '.json';
        @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function scalar(PDO $pdo, string $sql, bool $optional = false): int
    {
        try {
            $val = $pdo->query($sql)?->fetchColumn();

            return (int) (false === $val ? 0 : $val);
        } catch (Throwable) {
            return $optional ? 0 : 0;
        }
    }
}
