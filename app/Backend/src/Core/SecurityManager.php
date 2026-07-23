<?php declare(strict_types=1);

namespace Besoiu\Core;

/**
 * Security Manager pentru hardening sistem
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class SecurityManager
{
    private static array $trustedHosts = [
        'besoiupieseauto.ro',
        'www.besoiupieseauto.ro',
        'localhost'
    ];

    public static function validateRequest(): void
    {
        self::checkHttpHost();
        self::validateUserAgent();
        self::checkRateLimit();
    }

    public static function sanitizeInput(array $input): array
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeInput($value);
            } else {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            }
        }
        
        return $sanitized;
    }

    public static function generateCSRFToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function encryptData(string $data, string $key = null): string
    {
        $key = $key ?? $_ENV['APP_KEY'] ?? 'default_key_change_me';
        $cipher = 'AES-256-CBC';
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decryptData(string $encryptedData, string $key = null): ?string
    {
        $key = $key ?? $_ENV['APP_KEY'] ?? 'default_key_change_me';
        $cipher = 'AES-256-CBC';
        
        $data = base64_decode($encryptedData);
        if ($data === false) {
            return null;
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
    }

    private static function checkHttpHost(): void
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if (!in_array($host, self::$trustedHosts) && !self::isLocalhost($host)) {
            http_response_code(400);
            die('Invalid host header');
        }
    }

    private static function validateUserAgent(): void
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Block known bad user agents
        $blockedAgents = ['bot', 'crawler', 'scanner'];
        
        foreach ($blockedAgents as $blocked) {
            if (stripos($userAgent, $blocked) !== false) {
                http_response_code(403);
                die('Access denied');
            }
        }
    }

    private static function checkRateLimit(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $key = 'rate_limit_' . md5($ip);
        
        $requests = $_SESSION[$key] ?? 0;
        $lastRequest = $_SESSION[$key . '_time'] ?? 0;
        
        // Reset counter every minute
        if (time() - $lastRequest > 60) {
            $requests = 0;
        }
        
        $requests++;
        
        if ($requests > 100) { // Max 100 requests per minute
            http_response_code(429);
            die('Rate limit exceeded');
        }
        
        $_SESSION[$key] = $requests;
        $_SESSION[$key . '_time'] = time();
    }

    private static function isLocalhost(string $host): bool
    {
        return $host === 'localhost' || 
               $host === '127.0.0.1' || 
               str_starts_with($host, '192.168.') ||
               str_starts_with($host, '10.') ||
               str_starts_with($host, '172.');
    }
}