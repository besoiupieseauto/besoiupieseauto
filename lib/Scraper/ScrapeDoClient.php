<?php
declare(strict_types=1);

require_once __DIR__ . '/ScrapeDoConfig.php';

final class ScrapeDoClient
{
    private string $token;
    private string $apiBase;

    public function __construct(?string $token = null, string $apiBase = 'https://api.scrape.do')
    {
        $this->token = trim($token ?? ScrapeDoConfig::token());
        $this->apiBase = rtrim($apiBase, '/');
    }

    public function hasToken(): bool
    {
        return $this->token !== '';
    }

    public function fetch(string $targetUrl, int $timeoutSec = 90, bool $super = false, bool $render = false): string
    {
        if (!$this->hasToken()) {
            throw new RuntimeException('Lipsește SCRAPE_DO_TOKEN în .env (admin).');
        }

        $apiUrl = $this->apiBase . '/?url=' . rawurlencode($targetUrl) . '&token=' . rawurlencode($this->token);
        if ($super) {
            $apiUrl .= '&super=true';
        }
        if ($render) {
            $apiUrl .= '&render=true';
        }

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_TIMEOUT        => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => ['Accept: */*'],
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            throw new RuntimeException('scrape.do cURL: ' . ($err ?: 'eșec'));
        }
        if ($code >= 400) {
            $hint = '';
            if ($code === 502 || $code === 403) {
                $hint = ' Autodoc/eMAG: activează render JS + super=true în tab Testare.';
            }
            $snippet = trim(mb_substr((string) $body, 0, 200, 'UTF-8'));
            $message = 'scrape.do HTTP ' . $code . ' — verifică token/URL.' . $hint
                . ($snippet !== '' ? ' Răspuns: ' . $snippet : '');
            ScrapeDoConfig::noteQuotaExceededFromMessage($message);

            throw new RuntimeException($message);
        }

        $budgetPath = dirname(__DIR__, 2) . '/admin/system/api_token_budget.php';
        if (is_file($budgetPath)) {
            require_once $budgetPath;
            api_token_budget_log('scrape_do', 1, 'scrape_do_client', 'fetch ok');
        }

        return (string) $body;
    }

    /**
     * Fetch cu retry automat la erori tranzitorii (502 rotation, timeout).
     */
    public function fetchWithRetry(string $targetUrl, int $timeoutSec = 90, bool $super = false, bool $render = false, int $maxAttempts = 3): string
    {
        $last = null;
        for ($attempt = 1; $attempt <= max(1, $maxAttempts); $attempt++) {
            try {
                return $this->fetch($targetUrl, $timeoutSec, $super, $render);
            } catch (RuntimeException $e) {
                $last = $e;
                $msg = $e->getMessage();
                $retryable = str_contains($msg, '502')
                    || str_contains($msg, '503')
                    || str_contains($msg, 'ROTATION')
                    || str_contains($msg, 'timeout')
                    || str_contains($msg, 'cURL');
                if (!$retryable || $attempt >= $maxAttempts) {
                    throw $e;
                }
                usleep(500000 * $attempt);
            }
        }

        throw $last ?? new RuntimeException('scrape.do: eșec necunoscut');
    }
}
