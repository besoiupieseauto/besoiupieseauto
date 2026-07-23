<?php declare(strict_types=1);

namespace Besoiu\Core;

/**
 * Sistem notificări în timp real
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class NotificationManager
{
    private array $channels = [];
    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = dirname(__DIR__, 2) . '/storage/notifications';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0775, true);
        }
    }

    public function send(string $channel, array $notification): bool
    {
        $notification['id'] = uniqid('notif_');
        $notification['timestamp'] = time();
        $notification['channel'] = $channel;

        // Store notification
        $this->storeNotification($notification);

        // Send via available channels
        foreach ($this->getChannelHandlers($channel) as $handler) {
            try {
                $handler($notification);
            } catch (\Exception $e) {
                ImportLogger::error('Notification handler failed', $e);
            }
        }

        return true;
    }

    public function addChannel(string $name, callable $handler): void
    {
        $this->channels[$name] = $handler;
    }

    public function getNotifications(string $userId, int $limit = 50): array
    {
        $userFile = $this->storageDir . "/user_{$userId}.json";
        
        if (!file_exists($userFile)) {
            return [];
        }

        $notifications = json_decode(file_get_contents($userFile), true) ?: [];
        return array_slice($notifications, -$limit);
    }

    public function markAsRead(string $userId, string $notificationId): bool
    {
        $userFile = $this->storageDir . "/user_{$userId}.json";
        
        if (!file_exists($userFile)) {
            return false;
        }

        $notifications = json_decode(file_get_contents($userFile), true) ?: [];
        
        foreach ($notifications as &$notification) {
            if ($notification['id'] === $notificationId) {
                $notification['read'] = true;
                break;
            }
        }

        return file_put_contents($userFile, json_encode($notifications)) !== false;
    }

    private function storeNotification(array $notification): void
    {
        $targetUsers = $notification['target_users'] ?? ['all'];
        
        foreach ($targetUsers as $userId) {
            $userFile = $this->storageDir . "/user_{$userId}.json";
            
            $notifications = [];
            if (file_exists($userFile)) {
                $notifications = json_decode(file_get_contents($userFile), true) ?: [];
            }
            
            $notifications[] = $notification;
            
            // Keep only last 1000 notifications
            if (count($notifications) > 1000) {
                $notifications = array_slice($notifications, -1000);
            }
            
            file_put_contents($userFile, json_encode($notifications));
        }
    }

    private function getChannelHandlers(string $channel): array
    {
        $handlers = [];
        
        // Email handler
        if (isset($this->channels['email'])) {
            $handlers[] = $this->channels['email'];
        }
        
        // WebSocket handler
        if (isset($this->channels['websocket'])) {
            $handlers[] = $this->channels['websocket'];
        }
        
        // Browser notification handler
        if (isset($this->channels['browser'])) {
            $handlers[] = $this->channels['browser'];
        }

        return $handlers;
    }
}