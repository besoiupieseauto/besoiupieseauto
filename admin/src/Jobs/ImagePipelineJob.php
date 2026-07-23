<?php declare(strict_types=1);

namespace Besoiu\Jobs;

use Besoiu\Core\ImportLogger;
use Besoiu\Core\ImportRuntime;
use Besoiu\Services\Import\ImagePipelineService;
use Config\Database;

/**
 * Job pentru procesarea și optimizarea imaginilor produselor
 * 
 * Responsabilități:
 * - Descărcare imagini din surse multiple (TecDoc, URL-uri directe)
 * - Optimizare și redimensionare imagini
 * - Aplicare watermark și procesări personalizate
 * - Generare thumbnail-uri în multiple dimensiuni
 * - Actualizare legături imagini în baza de date
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class ImagePipelineJob
{
    private ImagePipelineService $imageService;
    private \PDO $pdo;
    private array $config;

    public function __construct()
    {
        $this->imageService = new ImagePipelineService();
        $this->pdo = Database::getDB('default');
        $this->config = [
            'max_image_size' => 5 * 1024 * 1024, // 5MB
            'supported_formats' => ['jpg', 'jpeg', 'png', 'webp'],
            'quality' => 85,
            'max_width' => 1200,
            'max_height' => 1200,
            'thumbnails' => [
                'small' => ['width' => 150, 'height' => 150],
                'medium' => ['width' => 300, 'height' => 300],
                'large' => ['width' => 600, 'height' => 600]
            ],
            'watermark' => [
                'enabled' => false,
                'file' => '',
                'position' => 'bottom-right',
                'opacity' => 50
            ],
            'storage_path' => 'uploads/products'
        ];
    }

    /**
     * Execută jobul de procesare imagini
     */
    public function handle(array $payload): array
    {
        ImportRuntime::configureForLargeFiles();
        ImportLogger::info('Starting image pipeline job', $payload);

        $startTime = microtime(true);

        try {
            // Validare payload
            $this->validatePayload($payload);

            // Configurare job
            $jobConfig = array_merge([
                'product_ids' => [],
                'source_type' => 'mixed', // tecdoc, url, local, mixed
                'download_missing' => true,
                'optimize_existing' => false,
                'generate_thumbnails' => true,
                'apply_watermark' => false,
                'batch_size' => 10
            ], $payload);

            // Procesare imagini
            $result = $this->processImages($jobConfig);

            $processingTime = microtime(true) - $startTime;
            $result['processing_time'] = round($processingTime, 2);

            ImportLogger::info('Image pipeline job completed', [
                'products_count' => count($jobConfig['product_ids']),
                'processed_images' => $result['processed_images'],
                'processing_time' => $result['processing_time']
            ]);

            return [
                'success' => true,
                'result' => $result,
                'processing_time' => $processingTime
            ];

        } catch (\Exception $e) {
            $processingTime = microtime(true) - $startTime;
            
            ImportLogger::error('Image pipeline job failed', $e, [
                'payload' => $payload,
                'processing_time' => round($processingTime, 2)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => $processingTime
            ];
        }
    }

    /**
     * Funcții private pentru procesare
     */
    private function validatePayload(array $payload): void
    {
        if (empty($payload['product_ids']) || !is_array($payload['product_ids'])) {
            throw new \InvalidArgumentException('product_ids array is required');
        }

        if (count($payload['product_ids']) > 500) {
            throw new \InvalidArgumentException('Maximum 500 products per job');
        }
    }

    private function processImages(array $jobConfig): array
    {
        $productIds = $jobConfig['product_ids'];
        $batchSize = $jobConfig['batch_size'];
        
        $results = [
            'total_products' => count($productIds),
            'processed_images' => 0,
            'downloaded_images' => 0,
            'optimized_images' => 0,
            'generated_thumbnails' => 0,
            'failed_images' => 0,
            'total_size_processed' => 0,
            'errors' => [],
            'image_details' => []
        ];

        // Procesare în batches
        $batches = array_chunk($productIds, $batchSize);
        
        ImportLogger::info('Starting image processing', [
            'total_products' => $results['total_products'],
            'batch_size' => $batchSize,
            'total_batches' => count($batches)
        ]);

        foreach ($batches as $batchIndex => $batch) {
            try {
                $batchResult = $this->processBatch($batch, $batchIndex, $jobConfig);
                
                // Agregare rezultate
                $results['processed_images'] += $batchResult['processed'];
                $results['downloaded_images'] += $batchResult['downloaded'];
                $results['optimized_images'] += $batchResult['optimized'];
                $results['generated_thumbnails'] += $batchResult['thumbnails'];
                $results['failed_images'] += $batchResult['failed'];
                $results['total_size_processed'] += $batchResult['size_processed'];
                $results['errors'] = array_merge($results['errors'], $batchResult['errors']);
                $results['image_details'] = array_merge($results['image_details'], $batchResult['details']);

                // Progress logging
                $progress = (($batchIndex + 1) / count($batches)) * 100;
                ImportLogger::progress('image_pipeline', $progress, 
                    "Processed batch " . ($batchIndex + 1) . "/" . count($batches)
                );

                // Cleanup memorie după fiecare batch
                ImportRuntime::forceGarbageCollection();

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'batch' => $batchIndex,
                    'error' => $e->getMessage(),
                    'type' => 'batch_error'
                ];
                
                ImportLogger::error('Image batch processing failed', $e, [
                    'batch_index' => $batchIndex,
                    'batch_size' => count($batch)
                ]);
            }
        }

        return $results;
    }

    private function processBatch(array $productIds, int $batchIndex, array $jobConfig): array
    {
        $batchResult = [
            'batch_index' => $batchIndex,
            'processed' => 0,
            'downloaded' => 0,
            'optimized' => 0,
            'thumbnails' => 0,
            'failed' => 0,
            'size_processed' => 0,
            'errors' => [],
            'details' => []
        ];

        // Încărcare produse cu informații despre imagini
        $products = $this->loadProductsWithImages($productIds);

        foreach ($products as $product) {
            try {
                $imageResult = $this->processProductImages($product, $jobConfig);
                
                $batchResult['processed'] += $imageResult['processed'];
                $batchResult['downloaded'] += $imageResult['downloaded'];
                $batchResult['optimized'] += $imageResult['optimized'];
                $batchResult['thumbnails'] += $imageResult['thumbnails'];
                $batchResult['size_processed'] += $imageResult['size_processed'];
                
                if (!empty($imageResult['errors'])) {
                    $batchResult['failed'] += count($imageResult['errors']);
                    $batchResult['errors'] = array_merge($batchResult['errors'], $imageResult['errors']);
                }
                
                if ($imageResult['success']) {
                    $batchResult['details'][] = [
                        'product_id' => $product['id'],
                        'product_code' => $product['pCode'],
                        'images_processed' => $imageResult['processed'],
                        'final_images' => $imageResult['final_images']
                    ];

                    // Actualizare baza de date cu imaginile procesate
                    $this->updateProductImages($product['id'], $imageResult['final_images']);
                }

            } catch (\Exception $e) {
                $batchResult['failed']++;
                $batchResult['errors'][] = [
                    'product_id' => $product['id'],
                    'error' => $e->getMessage(),
                    'type' => 'processing_error'
                ];
                
                ImportLogger::error('Product image processing failed', $e, [
                    'product_id' => $product['id'],
                    'product_code' => $product['pCode'] ?? 'unknown'
                ]);
            }
        }

        ImportLogger::info('Image batch processed', [
            'batch_index' => $batchIndex,
            'products' => count($products),
            'images_processed' => $batchResult['processed'],
            'size_processed' => $this->formatBytes($batchResult['size_processed'])
        ]);

        return $batchResult;
    }

    private function processProductImages(array $product, array $jobConfig): array
    {
        $result = [
            'success' => false,
            'processed' => 0,
            'downloaded' => 0,
            'optimized' => 0,
            'thumbnails' => 0,
            'size_processed' => 0,
            'errors' => [],
            'final_images' => []
        ];

        try {
            // Parse imagini existente
            $existingImages = $this->parseProductImages($product);
            
            // Obținere imagini din surse multiple
            $sourceImages = $this->gatherImageSources($product, $jobConfig);
            
            // Procesare fiecare imagine
            foreach ($sourceImages as $imageSource) {
                try {
                    $imageResult = $this->processImage($imageSource, $product, $jobConfig);
                    
                    if ($imageResult['success']) {
                        $result['processed']++;
                        
                        if ($imageResult['downloaded']) {
                            $result['downloaded']++;
                        }
                        
                        if ($imageResult['optimized']) {
                            $result['optimized']++;
                        }
                        
                        $result['thumbnails'] += $imageResult['thumbnails_generated'];
                        $result['size_processed'] += $imageResult['size_processed'];
                        $result['final_images'][] = $imageResult['final_path'];
                        
                    } else {
                        $result['errors'][] = [
                            'source' => $imageSource['url'] ?? $imageSource['path'] ?? 'unknown',
                            'error' => $imageResult['error']
                        ];
                    }

                } catch (\Exception $e) {
                    $result['errors'][] = [
                        'source' => $imageSource['url'] ?? $imageSource['path'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $result['success'] = $result['processed'] > 0 || empty($sourceImages);
            return $result;

        } catch (\Exception $e) {
            $result['errors'][] = ['error' => $e->getMessage(), 'type' => 'general'];
            return $result;
        }
    }

    private function processImage(array $imageSource, array $product, array $jobConfig): array
    {
        $result = [
            'success' => false,
            'downloaded' => false,
            'optimized' => false,
            'thumbnails_generated' => 0,
            'size_processed' => 0,
            'final_path' => '',
            'error' => ''
        ];

        try {
            $productCode = $product['pCode'] ?? 'unknown';
            $storageDir = dirname(__DIR__, 2) . '/storage/' . $this->config['storage_path'];
            
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0775, true);
            }

            // Descărcare sau copiere imagine
            $localPath = $this->obtainLocalImage($imageSource, $storageDir, $productCode);
            
            if (!$localPath) {
                $result['error'] = 'Failed to obtain local image';
                return $result;
            }

            $result['downloaded'] = isset($imageSource['url']);
            $originalSize = filesize($localPath);

            // Validare imagine
            $imageInfo = getimagesize($localPath);
            if (!$imageInfo) {
                $result['error'] = 'Invalid image format';
                @unlink($localPath);
                return $result;
            }

            // Verificare dimensiuni și format
            if ($originalSize > $this->config['max_image_size']) {
                $result['error'] = 'Image too large: ' . $this->formatBytes($originalSize);
                @unlink($localPath);
                return $result;
            }

            // Optimizare imagine
            if ($jobConfig['optimize_existing'] || $result['downloaded']) {
                $optimizedPath = $this->optimizeImage($localPath, $imageInfo);
                
                if ($optimizedPath) {
                    if ($optimizedPath !== $localPath) {
                        @unlink($localPath);
                        $localPath = $optimizedPath;
                        $result['optimized'] = true;
                    }
                }
            }

            // Aplicare watermark
            if ($jobConfig['apply_watermark'] && $this->config['watermark']['enabled']) {
                $watermarkedPath = $this->applyWatermark($localPath);
                if ($watermarkedPath && $watermarkedPath !== $localPath) {
                    @unlink($localPath);
                    $localPath = $watermarkedPath;
                }
            }

            // Generare thumbnail-uri
            if ($jobConfig['generate_thumbnails']) {
                $result['thumbnails_generated'] = $this->generateThumbnails($localPath, $productCode);
            }

            // Calcul dimensiune finală
            $finalSize = filesize($localPath);
            $result['size_processed'] = max($originalSize, $finalSize);

            // Calea finală relativă
            $result['final_path'] = str_replace(dirname(__DIR__, 2) . '/', '', $localPath);
            $result['success'] = true;

            ImportLogger::info('Image processed successfully', [
                'product_code' => $productCode,
                'original_size' => $this->formatBytes($originalSize),
                'final_size' => $this->formatBytes($finalSize),
                'optimized' => $result['optimized'],
                'thumbnails' => $result['thumbnails_generated']
            ]);

            return $result;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            ImportLogger::error('Image processing failed', $e, [
                'product_code' => $product['pCode'] ?? 'unknown',
                'source' => $imageSource['url'] ?? $imageSource['path'] ?? 'unknown'
            ]);
            
            return $result;
        }
    }

    private function obtainLocalImage(array $imageSource, string $storageDir, string $productCode): ?string
    {
        // Generare nume fișier unic
        $timestamp = time();
        $extension = $imageSource['extension'] ?? 'jpg';
        $filename = $productCode . '_' . $timestamp . '_' . uniqid() . '.' . $extension;
        $localPath = $storageDir . '/' . $filename;

        if (isset($imageSource['url'])) {
            // Descărcare de la URL
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Besoiu Import Bot/2.0'
                ]
            ]);

            $imageData = file_get_contents($imageSource['url'], false, $context);
            
            if ($imageData === false) {
                return null;
            }

            file_put_contents($localPath, $imageData);
            
        } elseif (isset($imageSource['path'])) {
            // Copiere din path local
            if (!file_exists($imageSource['path'])) {
                return null;
            }

            copy($imageSource['path'], $localPath);
            
        } else {
            return null;
        }

        return file_exists($localPath) ? $localPath : null;
    }

    private function optimizeImage(string $imagePath, array $imageInfo): ?string
    {
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];

        // Verificare dacă e nevoie de redimensionare
        $needsResize = $width > $this->config['max_width'] || $height > $this->config['max_height'];
        
        if (!$needsResize) {
            return $imagePath; // Nu e nevoie de optimizare
        }

        try {
            // Calculare dimensiuni noi păstrând aspectul
            $ratio = min($this->config['max_width'] / $width, $this->config['max_height'] / $height);
            $newWidth = intval($width * $ratio);
            $newHeight = intval($height * $ratio);

            // Încărcare imagine sursă
            $sourceImage = null;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($imagePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($imagePath);
                    break;
                case IMAGETYPE_WEBP:
                    $sourceImage = imagecreatefromwebp($imagePath);
                    break;
                default:
                    return null;
            }

            if (!$sourceImage) {
                return null;
            }

            // Creare imagine destinație
            $destImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Păstrare transparență pentru PNG
            if ($type === IMAGETYPE_PNG) {
                imagealphablending($destImage, false);
                imagesavealpha($destImage, true);
                $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
                imagefill($destImage, 0, 0, $transparent);
            }

            // Redimensionare
            imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Salvare imagine optimizată
            $optimizedPath = $imagePath . '.opt';
            $saved = false;

            switch ($type) {
                case IMAGETYPE_JPEG:
                    $saved = imagejpeg($destImage, $optimizedPath, $this->config['quality']);
                    break;
                case IMAGETYPE_PNG:
                    $saved = imagepng($destImage, $optimizedPath, 9);
                    break;
                case IMAGETYPE_WEBP:
                    $saved = imagewebp($destImage, $optimizedPath, $this->config['quality']);
                    break;
            }

            // Cleanup
            imagedestroy($sourceImage);
            imagedestroy($destImage);

            if ($saved) {
                // Înlocuire fișier original cu cel optimizat
                @unlink($imagePath);
                rename($optimizedPath, $imagePath);
                return $imagePath;
            }

            return null;

        } catch (\Exception $e) {
            ImportLogger::error('Image optimization failed', $e, ['path' => $imagePath]);
            return null;
        }
    }

    private function applyWatermark(string $imagePath): ?string
    {
        // Implementare simplificată watermark
        // În implementarea completă ar aplica watermark conform configurației
        return $imagePath;
    }

    private function generateThumbnails(string $imagePath, string $productCode): int
    {
        $generated = 0;
        $imageInfo = getimagesize($imagePath);
        
        if (!$imageInfo) {
            return 0;
        }

        $thumbnailDir = dirname($imagePath) . '/thumbnails';
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0775, true);
        }

        foreach ($this->config['thumbnails'] as $size => $dimensions) {
            try {
                $thumbnailPath = $thumbnailDir . '/' . $productCode . '_' . $size . '.jpg';
                
                if ($this->createThumbnail($imagePath, $thumbnailPath, $dimensions)) {
                    $generated++;
                }

            } catch (\Exception $e) {
                ImportLogger::error('Thumbnail generation failed', $e, [
                    'path' => $imagePath,
                    'size' => $size
                ]);
            }
        }

        return $generated;
    }

    private function createThumbnail(string $sourcePath, string $thumbPath, array $dimensions): bool
    {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];

        // Încărcare imagine sursă
        $sourceImage = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        if (!$sourceImage) {
            return false;
        }

        // Calculare dimensiuni thumbnail (crop center)
        $thumbWidth = $dimensions['width'];
        $thumbHeight = $dimensions['height'];
        
        $sourceRatio = $width / $height;
        $thumbRatio = $thumbWidth / $thumbHeight;

        if ($sourceRatio > $thumbRatio) {
            // Imagine mai lată - crop width
            $cropHeight = $height;
            $cropWidth = $height * $thumbRatio;
            $cropX = ($width - $cropWidth) / 2;
            $cropY = 0;
        } else {
            // Imagine mai înaltă - crop height
            $cropWidth = $width;
            $cropHeight = $width / $thumbRatio;
            $cropX = 0;
            $cropY = ($height - $cropHeight) / 2;
        }

        // Creare thumbnail
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagecopyresampled(
            $thumbImage, $sourceImage,
            0, 0, $cropX, $cropY,
            $thumbWidth, $thumbHeight, $cropWidth, $cropHeight
        );

        // Salvare thumbnail
        $saved = imagejpeg($thumbImage, $thumbPath, $this->config['quality']);

        // Cleanup
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        return $saved;
    }

    private function loadProductsWithImages(array $productIds): array
    {
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        
        $stmt = $this->pdo->prepare("
            SELECT id, randomn_id, pCode, pName, pImages, pImageSource, raw_json
            FROM produse 
            WHERE id IN ($placeholders)
        ");
        
        $stmt->execute($productIds);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function parseProductImages(array $product): array
    {
        $images = [];
        
        if (!empty($product['pImages'])) {
            $imageData = json_decode($product['pImages'], true);
            if (is_array($imageData)) {
                $images = $imageData;
            }
        }

        return $images;
    }

    private function gatherImageSources(array $product, array $jobConfig): array
    {
        $sources = [];
        
        // Imagini existente din baza de date
        $existingImages = $this->parseProductImages($product);
        
        foreach ($existingImages as $imageUrl) {
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $sources[] = [
                    'type' => 'url',
                    'url' => $imageUrl,
                    'extension' => pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg'
                ];
            }
        }

        return $sources;
    }

    private function updateProductImages(int $productId, array $finalImages): void
    {
        if (empty($finalImages)) {
            return;
        }

        $imagesJson = json_encode($finalImages);
        
        $stmt = $this->pdo->prepare("
            UPDATE produse 
            SET pImages = ?, pImageSource = 'processed', updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$imagesJson, $productId]);
        
        ImportLogger::info('Product images updated', [
            'product_id' => $productId,
            'images_count' => count($finalImages)
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}