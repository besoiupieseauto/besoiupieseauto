<?php declare(strict_types=1);

namespace Besoiu\DTOs;

/**
 * Data Transfer Object pentru joburi asincrone
 * 
 * Responsabilități:
 * - Definirea structurii joburilor în sistem
 * - Tracking progres și status în timp real
 * - Management retry logic și error handling
 * - Metadata pentru monitoring și raportare
 * - Configurare job-uri cu prioritate și restricții
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class JobDTO extends BaseDTO
{
    // Identificare job
    public string $id;
    public string $type; // 'import_process', 'tecdoc_enrichment', 'image_pipeline', etc.
    public string $queue; // 'import', 'tecdoc', 'images', 'reports'

    // Status și progres
    public string $status = 'pending'; // 'pending', 'running', 'completed', 'failed', 'cancelled'
    public float $progress = 0.0; // 0-100
    public ?string $current_step = null;
    public ?string $progress_message = null;

    // Configurare job
    public array $payload = [];
    public array $options = [];
    public string $priority = 'normal'; // 'low', 'normal', 'high', 'urgent'

    // Timing și execuție
    public ?int $scheduled_at = null;
    public ?int $started_at = null;
    public ?int $completed_at = null;
    public ?int $failed_at = null;
    public int $created_at;

    // Retry și error handling
    public int $attempts = 0;
    public int $max_attempts = 3;
    public ?int $retry_at = null;
    public int $retry_delay = 60; // seconds
    public ?string $error_message = null;
    public ?array $error_details = null;

    // Rezultate și output
    public ?array $result = null;
    public ?array $metadata = null;

    // Performance și resurse
    public ?int $processing_time = null; // seconds
    public ?int $memory_peak = null; // bytes
    public ?int $api_calls_count = null;

    // Context și tracking
    public ?int $created_by = null;
    public ?string $session_id = null;
    public ?string $user_ip = null;
    public ?array $dependencies = null; // array de job IDs care trebuie completate primul

    /**
     * Câmpurile obligatorii
     */
    protected function getRequiredFields(): array
    {
        return ['id', 'type', 'queue', 'created_at'];
    }

    /**
     * Tipurile de date pentru validare
     */
    protected function getFieldTypes(): array
    {
        return [
            'id' => 'string',
            'type' => 'string',
            'queue' => 'string',
            'status' => 'string',
            'progress' => 'float',
            'payload' => 'array',
            'options' => 'array',
            'priority' => 'string',
            'scheduled_at' => 'int',
            'started_at' => 'int',
            'completed_at' => 'int',
            'failed_at' => 'int',
            'created_at' => 'int',
            'attempts' => 'int',
            'max_attempts' => 'int',
            'retry_at' => 'int',
            'retry_delay' => 'int',
            'result' => 'array',
            'metadata' => 'array',
            'processing_time' => 'int',
            'memory_peak' => 'int',
            'api_calls_count' => 'int',
            'created_by' => 'int',
            'dependencies' => 'array',
            'error_details' => 'array'
        ];
    }

    /**
     * Validatori custom
     */
    protected function getFieldValidators(): array
    {
        return [
            'status' => fn($value) => in_array($value, ['pending', 'running', 'completed', 'failed', 'cancelled', 'paused']),
            'priority' => fn($value) => in_array($value, ['low', 'normal', 'high', 'urgent']),
            'progress' => fn($value) => $value >= 0 && $value <= 100,
            'attempts' => fn($value) => $value >= 0,
            'max_attempts' => fn($value) => $value >= 1 && $value <= 10,
            'retry_delay' => fn($value) => $value >= 0 && $value <= 86400, // max 24h
            'type' => fn($value) => in_array($value, [
                'import_process', 'tecdoc_enrichment', 'image_pipeline', 'validation_batch',
                'report_generation', 'cleanup_task', 'backup_task', 'email_notification'
            ]),
            'queue' => fn($value) => in_array($value, [
                'import', 'tecdoc', 'images', 'reports', 'email', 'cleanup', 'backup'
            ])
        ];
    }

    /**
     * Creează un job nou cu ID generat automat
     */
    public static function create(string $type, string $queue, array $payload = [], array $options = []): static
    {
        $jobId = 'job_' . uniqid() . '_' . random_int(1000, 9999);
        
        return new static([
            'id' => $jobId,
            'type' => $type,
            'queue' => $queue,
            'payload' => $payload,
            'options' => $options,
            'created_at' => time(),
            'scheduled_at' => time() + ($options['delay'] ?? 0)
        ]);
    }

    /**
     * Marchează job-ul ca pornit
     */
    public function markAsStarted(): static
    {
        $this->status = 'running';
        $this->started_at = time();
        $this->attempts++;
        
        return $this;
    }

    /**
     * Marchează job-ul ca completat cu succes
     */
    public function markAsCompleted(array $result = []): static
    {
        $this->status = 'completed';
        $this->completed_at = time();
        $this->progress = 100.0;
        $this->result = $result;
        
        if ($this->started_at) {
            $this->processing_time = time() - $this->started_at;
        }
        
        return $this;
    }

    /**
     * Marchează job-ul ca eșuat
     */
    public function markAsFailed(string $errorMessage, array $errorDetails = []): static
    {
        $this->status = 'failed';
        $this->failed_at = time();
        $this->error_message = $errorMessage;
        $this->error_details = $errorDetails;
        
        if ($this->started_at) {
            $this->processing_time = time() - $this->started_at;
        }
        
        return $this;
    }

    /**
     * Programează job-ul pentru retry
     */
    public function scheduleRetry(int $delay = null): static
    {
        if ($this->attempts >= $this->max_attempts) {
            throw new \RuntimeException('Maximum retry attempts exceeded');
        }

        $this->status = 'pending';
        $this->retry_at = time() + ($delay ?? $this->retry_delay);
        $this->scheduled_at = $this->retry_at;
        
        // Reset fields pentru retry
        $this->started_at = null;
        $this->processing_time = null;
        $this->error_message = null;
        $this->error_details = null;
        
        return $this;
    }

    /**
     * Actualizează progresul job-ului
     */
    public function updateProgress(float $progress, string $message = null, string $step = null): static
    {
        $this->progress = max(0, min(100, $progress));
        
        if ($message !== null) {
            $this->progress_message = $message;
        }
        
        if ($step !== null) {
            $this->current_step = $step;
        }
        
        return $this;
    }

    /**
     * Adaugă metadata la job
     */
    public function addMetadata(string $key, mixed $value): static
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Obține o valoare din metadata
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Verifică dacă job-ul este în progres
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Verifică dacă job-ul este completat
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Verifică dacă job-ul a eșuat
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Verifică dacă job-ul poate fi reluat
     */
    public function canRetry(): bool
    {
        return $this->isFailed() && $this->attempts < $this->max_attempts;
    }

    /**
     * Verifică dacă job-ul este gata pentru procesare
     */
    public function isReady(): bool
    {
        return $this->status === 'pending' && 
               ($this->scheduled_at === null || $this->scheduled_at <= time());
    }

    /**
     * Calculează timpul scurs de la crearea job-ului
     */
    public function getAge(): int
    {
        return time() - $this->created_at;
    }

    /**
     * Calculează timpul de rulare (dacă job-ul rulează)
     */
    public function getRuntime(): ?int
    {
        if (!$this->started_at) {
            return null;
        }
        
        $endTime = $this->completed_at ?? $this->failed_at ?? time();
        return $endTime - $this->started_at;
    }

    /**
     * Obține estimarea timpului rămas
     */
    public function getEstimatedTimeRemaining(): ?int
    {
        if (!$this->isRunning() || $this->progress <= 0) {
            return null;
        }
        
        $runtime = $this->getRuntime();
        if (!$runtime) {
            return null;
        }
        
        $totalEstimate = ($runtime / $this->progress) * 100;
        return max(0, (int)($totalEstimate - $runtime));
    }

    /**
     * Calculează throughput-ul (elemente procesate pe secundă)
     */
    public function getThroughput(): ?float
    {
        $runtime = $this->getRuntime();
        if (!$runtime || !isset($this->metadata['items_processed'])) {
            return null;
        }
        
        return (float)$this->metadata['items_processed'] / $runtime;
    }

    /**
     * Verifică dacă job-ul depinde de alte joburi
     */
    public function hasDependencies(): bool
    {
        return !empty($this->dependencies);
    }

    /**
     * Adaugă o dependență către alt job
     */
    public function addDependency(string $jobId): static
    {
        if ($this->dependencies === null) {
            $this->dependencies = [];
        }
        
        if (!in_array($jobId, $this->dependencies)) {
            $this->dependencies[] = $jobId;
        }
        
        return $this;
    }

    /**
     * Verifică dacă toate dependențele sunt completate
     */
    public function areDependenciesSatisfied(array $completedJobIds): bool
    {
        if (!$this->hasDependencies()) {
            return true;
        }
        
        foreach ($this->dependencies as $depId) {
            if (!in_array($depId, $completedJobIds)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Generează raport sumar pentru job
     */
    public function getSummaryReport(): array
    {
        $report = [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'progress' => $this->progress,
            'created_at' => date('c', $this->created_at),
            'age_seconds' => $this->getAge()
        ];
        
        if ($this->started_at) {
            $report['started_at'] = date('c', $this->started_at);
            $report['runtime_seconds'] = $this->getRuntime();
        }
        
        if ($this->completed_at || $this->failed_at) {
            $endTime = $this->completed_at ?? $this->failed_at;
            $report['finished_at'] = date('c', $endTime);
            $report['processing_time'] = $this->processing_time;
        }
        
        if ($this->error_message) {
            $report['error'] = $this->error_message;
        }
        
        if ($this->metadata) {
            $report['metadata'] = $this->metadata;
        }
        
        return $report;
    }

    /**
     * Exportă job-ul pentru logging
     */
    public function toLogEntry(): array
    {
        return [
            'job_id' => $this->id,
            'type' => $this->type,
            'queue' => $this->queue,
            'status' => $this->status,
            'progress' => $this->progress,
            'attempts' => $this->attempts,
            'created_by' => $this->created_by,
            'processing_time' => $this->processing_time,
            'error' => $this->error_message,
            'timestamp' => date('c')
        ];
    }

    /**
     * Creează job din entry de log
     */
    public static function fromLogEntry(array $entry): static
    {
        return new static([
            'id' => $entry['job_id'],
            'type' => $entry['type'] ?? 'unknown',
            'queue' => $entry['queue'] ?? 'default',
            'status' => $entry['status'] ?? 'unknown',
            'progress' => $entry['progress'] ?? 0,
            'attempts' => $entry['attempts'] ?? 0,
            'created_by' => $entry['created_by'] ?? null,
            'processing_time' => $entry['processing_time'] ?? null,
            'error_message' => $entry['error'] ?? null,
            'created_at' => strtotime($entry['timestamp']) ?: time()
        ]);
    }
}