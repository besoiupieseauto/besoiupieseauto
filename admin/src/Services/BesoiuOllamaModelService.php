<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Model custom besoiu-llama — detectare, Modelfile, deploy Ollama.
 */
final class BesoiuOllamaModelService
{
    public const DEFAULT_MODEL_NAME = 'besoiu-llama';

    private string $projectRoot;
    private OllamaLlmClient $ollama;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->ollama = new OllamaLlmClient($this->projectRoot);
    }

    public function modelName(): string
    {
        $name = trim((string) ($_ENV['OLLAMA_BESOIU_MODEL_NAME'] ?? getenv('OLLAMA_BESOIU_MODEL_NAME') ?: self::DEFAULT_MODEL_NAME));

        return $name !== '' ? $name : self::DEFAULT_MODEL_NAME;
    }

    public static function useBesoiuModel(): bool
    {
        $raw = strtolower(trim((string) ($_ENV['OLLAMA_USE_BESOIU_MODEL'] ?? getenv('OLLAMA_USE_BESOIU_MODEL') ?: '1')));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $name = $this->modelName();
        $tags = $this->ollama->listInstalledModels();
        $installed = $this->modelInstalled($tags, $name);
        $gguf = $this->findGgufFile();

        return [
            'model_name' => $name,
            'installed' => $installed,
            'use_enabled' => self::useBesoiuModel(),
            'active_for_agents' => $installed && self::useBesoiuModel(),
            'gguf_found' => $gguf !== null,
            'gguf_path' => $gguf,
            'modelfile_path' => $this->modelfilePath(),
            'deploy_dir' => $this->deployDir(),
            'ollama_ready' => $this->ollama->readiness()['reachable'] ?? false,
            'recommended_env' => [
                'OLLAMA_MODEL' => $name,
                'OLLAMA_MODEL_PRODUSE' => $name,
                'OLLAMA_MODEL_CLIENTI' => $name,
                'OLLAMA_USE_BESOIU_MODEL' => '1',
            ],
        ];
    }

    /**
     * Generează Modelfile și rulează `ollama create` dacă GGUF există.
     *
     * @return array<string, mixed>
     */
    public function deploy(?string $ggufPath = null): array
    {
        $gguf = $ggufPath ?? $this->findGgufFile();
        if ($gguf === null || !is_file($gguf)) {
            return [
                'ok' => false,
                'error' => 'Fișier GGUF negăsit. Pune modelul în finetuning/output/ sau setează OLLAMA_BESOIU_GGUF_PATH.',
            ];
        }

        $deployDir = $this->deployDir();
        if (!is_dir($deployDir)) {
            @mkdir($deployDir, 0775, true);
        }

        $targetGguf = $deployDir . '/' . basename($gguf);
        if (!realpath($gguf) || realpath($gguf) !== realpath($targetGguf)) {
            if (!@copy($gguf, $targetGguf)) {
                return ['ok' => false, 'error' => 'Nu am putut copia GGUF în ' . $deployDir];
            }
        }

        $modelfile = $this->writeModelfile($targetGguf);
        $name = $this->modelName();
        $cmd = 'ollama create ' . escapeshellarg($name) . ' -f ' . escapeshellarg($modelfile);
        $output = [];
        $code = 0;
        @exec($cmd . ' 2>&1', $output, $code);

        $installed = $this->modelInstalled($this->ollama->listInstalledModels(), $name);

        return [
            'ok' => $code === 0 || $installed,
            'model_name' => $name,
            'command' => $cmd,
            'output' => implode("\n", $output),
            'exit_code' => $code,
            'installed' => $installed,
            'modelfile' => $modelfile,
            'gguf' => $targetGguf,
        ];
    }

    public function resolveAgentModel(string $agentSlug): ?string
    {
        if (!self::useBesoiuModel()) {
            return null;
        }
        if (!in_array($agentSlug, ['agent-produse', 'agent-clienti'], true)) {
            return null;
        }
        $name = $this->modelName();
        if (!$this->modelInstalled($this->ollama->listInstalledModels(), $name)) {
            return null;
        }

        return $name;
    }

    public function findGgufFile(): ?string
    {
        $explicit = trim((string) ($_ENV['OLLAMA_BESOIU_GGUF_PATH'] ?? getenv('OLLAMA_BESOIU_GGUF_PATH') ?: ''));
        if ($explicit !== '' && is_file($explicit)) {
            return $explicit;
        }

        $candidates = [
            $this->projectRoot . '/finetuning/output/model-finetuned',
            $this->projectRoot . '/finetuning/output',
            $this->deployDir(),
        ];

        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $files = glob($dir . '/*.gguf') ?: [];
            if ($files === []) {
                $files = glob($dir . '/**/*.gguf') ?: [];
            }
            if ($files !== []) {
                usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

                return $files[0];
            }
        }

        return null;
    }

    private function deployDir(): string
    {
        return $this->projectRoot . '/finetuning/deploy';
    }

    private function modelfilePath(): string
    {
        return $this->deployDir() . '/Modelfile';
    }

    private function writeModelfile(string $ggufPath): string
    {
        $rel = str_replace('\\', '/', $ggufPath);
        $deployDir = str_replace('\\', '/', $this->deployDir());
        if (str_starts_with($rel, $deployDir)) {
            $from = './' . ltrim(substr($rel, strlen($deployDir)), '/');
        } else {
            $from = $rel;
        }

        $system = 'Ești consultant informativ Besoiu Piese Auto (România). Răspunde în română, prețuri RON, fără invenții de stoc. Nu confirmi comenzi comerciale automat.';

        $content = implode("\n", [
            'FROM ' . $from,
            '',
            'PARAMETER temperature 0.25',
            'PARAMETER top_p 0.9',
            'PARAMETER num_ctx 4096',
            '',
            'SYSTEM """' . $system . '"""',
            '',
        ]);

        $path = $this->modelfilePath();
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $content);

        return $path;
    }

    /** @param list<string> $tags */
    private function modelInstalled(array $tags, string $modelName): bool
    {
        $needle = strtolower($modelName);
        foreach ($tags as $tag) {
            $name = strtolower((string) $tag);
            if ($name === $needle || str_starts_with($name, $needle . ':')) {
                return true;
            }
        }

        return false;
    }
}
