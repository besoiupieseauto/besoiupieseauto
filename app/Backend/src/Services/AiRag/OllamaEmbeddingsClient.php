<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

/**
 * Embeddings Ollama — nomic-embed-text (local, fără cloud).
 */
final class OllamaEmbeddingsClient
{
    public function __construct(
        private readonly string $projectRoot = '',
    ) {
    }

    private function baseUrl(): string
    {
        $helper = $this->root() . '/app/Backend/system/ollama_llm.php';
        if (is_file($helper)) {
            require_once $helper;

            return besoiu_ollama_base_url();
        }

        return rtrim((string) (getenv('OLLAMA_BASE_URL') ?: 'http://127.0.0.1:11434'), '/');
    }

    private function root(): string
    {
        return $this->projectRoot !== ''
            ? $this->projectRoot
            : (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    public function defaultModel(): string
    {
        if (function_exists('besoiu_ollama_embed_model')) {
            return besoiu_ollama_embed_model();
        }

        $m = trim((string) (getenv('OLLAMA_EMBED_MODEL') ?: $_ENV['OLLAMA_EMBED_MODEL'] ?? 'nomic-embed-text'));

        return $m !== '' ? $m : 'nomic-embed-text';
    }

    /** @return array{ok:bool,vector:list<float>,model:string,error:string} */
    public function embed(string $text, ?string $model = null): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'vector' => [], 'model' => '', 'error' => 'Text gol'];
        }

        $model = $model ?? $this->defaultModel();
        $textSlice = mb_substr($text, 0, 8000);

        $attempts = [
            ['/api/embed', ['model' => $model, 'input' => $textSlice]],
            ['/api/embeddings', ['model' => $model, 'prompt' => $textSlice]],
        ];

        foreach ($attempts as [$path, $body]) {
            $parsed = $this->requestEmbedding($path, $body, $model);
            if (!empty($parsed['ok'])) {
                return $parsed;
            }
            $lastError = (string) ($parsed['error'] ?? 'Eșec embedding');
        }

        return ['ok' => false, 'vector' => [], 'model' => $model, 'error' => $lastError ?? 'Eșec embedding'];
    }

    /** @param array<string, mixed> $body @return array{ok:bool,vector:list<float>,model:string,error:string} */
    private function requestEmbedding(string $path, array $body, string $model): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return ['ok' => false, 'vector' => [], 'model' => $model, 'error' => 'Payload invalid'];
        }

        $ch = curl_init($this->baseUrl() . $path);
        if ($ch === false) {
            return ['ok' => false, 'vector' => [], 'model' => $model, 'error' => 'curl_init eșuat'];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($raw) || $raw === '' || $code >= 400) {
            return ['ok' => false, 'vector' => [], 'model' => $model, 'error' => 'HTTP ' . $code];
        }

        $json = json_decode($raw, true);
        $vec = $json['embedding'] ?? null;
        if (!is_array($vec) || $vec === []) {
            $embeddings = $json['embeddings'] ?? null;
            if (is_array($embeddings) && isset($embeddings[0]) && is_array($embeddings[0])) {
                $vec = $embeddings[0];
            }
        }
        if (!is_array($vec) || $vec === []) {
            return ['ok' => false, 'vector' => [], 'model' => $model, 'error' => 'Embedding lipsă în răspuns'];
        }

        $out = [];
        foreach ($vec as $v) {
            $out[] = (float) $v;
        }

        return ['ok' => true, 'vector' => $out, 'model' => $model, 'error' => ''];
    }
}
