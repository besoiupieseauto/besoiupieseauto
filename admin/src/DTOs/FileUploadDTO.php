<?php declare(strict_types=1);

namespace Besoiu\DTOs;

/**
 * Data Transfer Object pentru fișiere încărcate
 * 
 * Responsabilități:
 * - Validare și metadata fișiere încărcate
 * - Tracking progres upload chunked
 * - Detectare automată format și supplier
 * - Preview și analiză conținut fișier
 * - Management fișiere temporare și cleanup
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class FileUploadDTO extends BaseDTO
{
    // Identificare fișier
    public string $file_id;
    public string $original_name;
    public string $safe_name;
    public ?string $display_name = null;

    // Proprietăți fișier
    public int $size;
    public string $mime_type;
    public string $extension;
    public ?string $encoding = null;
    public ?string $hash_md5 = null;
    public ?string $hash_sha256 = null;

    // Status upload
    public string $upload_type = 'simple'; // 'simple', 'chunked'
    public bool $completed = false;
    public float $progress = 0.0; // 0-100
    public ?int $uploaded_at = null;
    public ?int $expires_at = null;

    // Upload chunked
    public int $total_chunks = 1;
    public int $uploaded_chunks = 0;
    public ?array $chunk_info = null;

    // Procesare și validare
    public bool $processed = false;
    public ?array $processing_result = null;
    public ?string $format_detected = null;
    public ?string $supplier_detected = null;
    public ?string $encoding_detected = null;

    // Preview conținut
    public ?array $preview_data = null;
    public int $preview_rows = 0;
    public ?array $column_headers = null;
    public ?array $sample_rows = null;

    // Statistici și analiză
    public int $total_rows = 0;
    public int $data_rows = 0;
    public int $empty_rows = 0;
    public int $error_rows = 0;
    public ?array $data_quality = null;

    // Configurare procesare
    public ?array $parse_options = null;
    public ?array $validation_rules = null;
    public ?array $column_mapping = null;

    // Paths și storage
    public ?string $temp_path = null;
    public ?string $storage_path = null;
    public ?string $backup_path = null;

    // Context și tracking
    public ?int $uploaded_by = null;
    public ?string $session_id = null;
    public ?string $user_ip = null;
    public int $created_at;
    public ?int $updated_at = null;

    // Erori și probleme
    public ?array $errors = null;
    public ?array $warnings = null;
    public bool $has_critical_errors = false;

    /**
     * Câmpurile obligatorii
     */
    protected function getRequiredFields(): array
    {
        return ['file_id', 'original_name', 'size', 'created_at'];
    }

    /**
     * Tipurile de date pentru validare
     */
    protected function getFieldTypes(): array
    {
        return [
            'file_id' => 'string',
            'original_name' => 'string',
            'safe_name' => 'string',
            'size' => 'int',
            'mime_type' => 'string',
            'extension' => 'string',
            'upload_type' => 'string',
            'completed' => 'bool',
            'progress' => 'float',
            'uploaded_at' => 'int',
            'expires_at' => 'int',
            'total_chunks' => 'int',
            'uploaded_chunks' => 'int',
            'chunk_info' => 'array',
            'processed' => 'bool',
            'processing_result' => 'array',
            'preview_data' => 'array',
            'preview_rows' => 'int',
            'column_headers' => 'array',
            'sample_rows' => 'array',
            'total_rows' => 'int',
            'data_rows' => 'int',
            'empty_rows' => 'int',
            'error_rows' => 'int',
            'data_quality' => 'array',
            'parse_options' => 'array',
            'validation_rules' => 'array',
            'column_mapping' => 'array',
            'uploaded_by' => 'int',
            'created_at' => 'int',
            'updated_at' => 'int',
            'errors' => 'array',
            'warnings' => 'array',
            'has_critical_errors' => 'bool'
        ];
    }

    /**
     * Validatori custom
     */
    protected function getFieldValidators(): array
    {
        return [
            'upload_type' => fn($value) => in_array($value, ['simple', 'chunked']),
            'progress' => fn($value) => $value >= 0 && $value <= 100,
            'size' => fn($value) => $value > 0 && $value <= 1073741824, // Max 1GB
            'extension' => fn($value) => in_array(strtolower($value), ['csv', 'xlsx', 'xls', 'txt', 'xml']),
            'total_chunks' => fn($value) => $value >= 1 && $value <= 10000,
            'uploaded_chunks' => fn($value) => $value >= 0 && $value <= ($this->total_chunks ?? 1),
            'preview_rows' => fn($value) => $value >= 0 && $value <= 1000,
            'total_rows' => fn($value) => $value >= 0,
            'data_rows' => fn($value) => $value >= 0,
            'format_detected' => fn($value) => $value === null || in_array($value, ['csv', 'excel', 'xml', 'txt', 'json']),
            'supplier_detected' => fn($value) => $value === null || in_array($value, [
                'autonet', 'autototal', 'materom', 'elit', 'tecdoc', 'generic', 'unknown'
            ])
        ];
    }

    /**
     * Creează DTO pentru upload simplu
     */
    public static function createSimple(array $uploadedFile, ?int $uploadedBy = null): static
    {
        $fileId = uniqid('upload_', true);
        $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        
        return new static([
            'file_id' => $fileId,
            'original_name' => $uploadedFile['name'],
            'safe_name' => self::generateSafeName($uploadedFile['name']),
            'size' => $uploadedFile['size'],
            'mime_type' => $uploadedFile['type'] ?? 'application/octet-stream',
            'extension' => $extension,
            'upload_type' => 'simple',
            'completed' => true,
            'progress' => 100.0,
            'uploaded_by' => $uploadedBy,
            'created_at' => time(),
            'uploaded_at' => time()
        ]);
    }

    /**
     * Creează DTO pentru upload chunked
     */
    public static function createChunked(string $fileName, int $fileSize, int $totalChunks, ?int $uploadedBy = null): static
    {
        $fileId = uniqid('upload_', true);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        return new static([
            'file_id' => $fileId,
            'original_name' => $fileName,
            'safe_name' => self::generateSafeName($fileName),
            'size' => $fileSize,
            'extension' => $extension,
            'upload_type' => 'chunked',
            'total_chunks' => $totalChunks,
            'uploaded_chunks' => 0,
            'completed' => false,
            'progress' => 0.0,
            'uploaded_by' => $uploadedBy,
            'created_at' => time()
        ]);
    }

    /**
     * Generează nume sigur pentru fișier
     */
    private static function generateSafeName(string $originalName): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        // Curăță numele
        $name = preg_replace('/[^a-zA-Z0-9\-_.]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        
        // Limitează lungimea
        $name = substr($name, 0, 50);
        
        return $name . '.' . $extension;
    }

    /**
     * Actualizează progresul pentru upload chunked
     */
    public function updateChunkProgress(int $uploadedChunks): static
    {
        $this->uploaded_chunks = min($uploadedChunks, $this->total_chunks);
        $this->progress = ($this->uploaded_chunks / $this->total_chunks) * 100;
        $this->updated_at = time();
        
        if ($this->uploaded_chunks >= $this->total_chunks) {
            $this->completed = true;
            $this->uploaded_at = time();
        }
        
        return $this;
    }

    /**
     * Marchează fișierul ca procesat
     */
    public function markAsProcessed(array $processingResult): static
    {
        $this->processed = true;
        $this->processing_result = $processingResult;
        $this->updated_at = time();
        
        // Extrage informații din rezultatul procesării
        if (isset($processingResult['format_detected'])) {
            $this->format_detected = $processingResult['format_detected'];
        }
        
        if (isset($processingResult['supplier_detected'])) {
            $this->supplier_detected = $processingResult['supplier_detected'];
        }
        
        if (isset($processingResult['encoding_detected'])) {
            $this->encoding_detected = $processingResult['encoding_detected'];
        }
        
        if (isset($processingResult['preview_data'])) {
            $this->setPreviewData($processingResult['preview_data']);
        }
        
        return $this;
    }

    /**
     * Setează datele de preview
     */
    public function setPreviewData(array $previewData): static
    {
        $this->preview_data = $previewData;
        
        if (isset($previewData['headers'])) {
            $this->column_headers = $previewData['headers'];
        }
        
        if (isset($previewData['sample_rows'])) {
            $this->sample_rows = $previewData['sample_rows'];
            $this->preview_rows = count($this->sample_rows);
        }
        
        if (isset($previewData['statistics'])) {
            $stats = $previewData['statistics'];
            $this->total_rows = $stats['total_rows'] ?? 0;
            $this->data_rows = $stats['data_rows'] ?? 0;
            $this->empty_rows = $stats['empty_rows'] ?? 0;
            $this->error_rows = $stats['error_rows'] ?? 0;
        }
        
        return $this;
    }

    /**
     * Adaugă o eroare
     */
    public function addError(string $error, string $type = 'general', bool $critical = false): static
    {
        if ($this->errors === null) {
            $this->errors = [];
        }
        
        $this->errors[] = [
            'message' => $error,
            'type' => $type,
            'critical' => $critical,
            'timestamp' => time()
        ];
        
        if ($critical) {
            $this->has_critical_errors = true;
        }
        
        return $this;
    }

    /**
     * Adaugă un warning
     */
    public function addWarning(string $warning, string $type = 'general'): static
    {
        if ($this->warnings === null) {
            $this->warnings = [];
        }
        
        $this->warnings[] = [
            'message' => $warning,
            'type' => $type,
            'timestamp' => time()
        ];
        
        return $this;
    }

    /**
     * Verifică dacă fișierul este valid pentru procesare
     */
    public function isValidForProcessing(): bool
    {
        return $this->completed && 
               !$this->has_critical_errors && 
               $this->size > 0 && 
               in_array($this->extension, ['csv', 'xlsx', 'xls', 'txt']);
    }

    /**
     * Verifică dacă upload-ul a expirat
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at <= time();
    }

    /**
     * Setează timp de expirare
     */
    public function setExpirationTime(int $hours = 24): static
    {
        $this->expires_at = time() + ($hours * 3600);
        return $this;
    }

    /**
     * Calculează hash pentru fișier
     */
    public function calculateHashes(string $filePath): static
    {
        if (file_exists($filePath)) {
            $this->hash_md5 = md5_file($filePath);
            $this->hash_sha256 = hash_file('sha256', $filePath);
        }
        
        return $this;
    }

    /**
     * Verifică dacă fișierul este duplicat (bazat pe hash)
     */
    public function isDuplicateOf(FileUploadDTO $other): bool
    {
        return $this->hash_md5 !== null && 
               $other->hash_md5 !== null && 
               $this->hash_md5 === $other->hash_md5;
    }

    /**
     * Obține scorul de calitate al datelor
     */
    public function getDataQualityScore(): float
    {
        if ($this->total_rows === 0) {
            return 0.0;
        }
        
        $validRows = $this->data_rows;
        $totalRows = $this->total_rows;
        $errorRate = $this->error_rows / $totalRows;
        
        $score = ($validRows / $totalRows) * 100;
        $score -= $errorRate * 20; // Penalizare pentru erori
        
        return max(0.0, min(100.0, $score));
    }

    /**
     * Generează raport sumar pentru fișier
     */
    public function getSummaryReport(): array
    {
        return [
            'file_id' => $this->file_id,
            'original_name' => $this->original_name,
            'size' => $this->formatFileSize(),
            'format' => $this->format_detected ?? 'unknown',
            'supplier' => $this->supplier_detected ?? 'unknown',
            'completed' => $this->completed,
            'processed' => $this->processed,
            'valid_for_import' => $this->isValidForProcessing(),
            'data_quality_score' => $this->getDataQualityScore(),
            'rows' => [
                'total' => $this->total_rows,
                'data' => $this->data_rows,
                'empty' => $this->empty_rows,
                'errors' => $this->error_rows
            ],
            'has_errors' => !empty($this->errors),
            'has_warnings' => !empty($this->warnings),
            'uploaded_at' => $this->uploaded_at ? date('c', $this->uploaded_at) : null,
            'expires_at' => $this->expires_at ? date('c', $this->expires_at) : null
        ];
    }

    /**
     * Formatează dimensiunea fișierului
     */
    private function formatFileSize(): string
    {
        $size = $this->size;
        
        if ($size >= 1073741824) {
            return number_format($size / 1073741824, 2) . ' GB';
        } elseif ($size >= 1048576) {
            return number_format($size / 1048576, 2) . ' MB';
        } elseif ($size >= 1024) {
            return number_format($size / 1024, 2) . ' KB';
        } else {
            return $size . ' B';
        }
    }

    /**
     * Exportă pentru logging
     */
    public function toLogEntry(): array
    {
        return [
            'file_id' => $this->file_id,
            'original_name' => $this->original_name,
            'size' => $this->size,
            'format' => $this->format_detected,
            'supplier' => $this->supplier_detected,
            'upload_type' => $this->upload_type,
            'completed' => $this->completed,
            'processed' => $this->processed,
            'data_rows' => $this->data_rows,
            'error_rows' => $this->error_rows,
            'uploaded_by' => $this->uploaded_by,
            'timestamp' => date('c', $this->created_at)
        ];
    }

    /**
     * Cleanup - pregătire pentru ștergere
     */
    public function prepareForCleanup(): array
    {
        $filesToDelete = [];
        
        if ($this->temp_path && file_exists($this->temp_path)) {
            $filesToDelete[] = $this->temp_path;
        }
        
        if ($this->storage_path && file_exists($this->storage_path)) {
            $filesToDelete[] = $this->storage_path;
        }
        
        if ($this->backup_path && file_exists($this->backup_path)) {
            $filesToDelete[] = $this->backup_path;
        }
        
        return $filesToDelete;
    }
}