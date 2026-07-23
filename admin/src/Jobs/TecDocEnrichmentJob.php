<?php declare(strict_types=1);

namespace Besoiu\Jobs;

use Besoiu\Core\ImportLogger;
use Besoiu\Core\ImportRuntime;
use Besoiu\Services\Import\TecDocIntegrationService;
use Config\Database;

/**
 * Job pentru îmbogățirea produselor cu date TecDoc
 * 
 * Responsabilități:
 * - Căutare și matching articole TecDoc
 * - Descărcare descrieri și specificații
 * - Procesare imagini și compatibilitate
 * - Actualizare produse în baza de date
 * - Rate limiting și retry pentru API TecDoc
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class TecDocEnrichmentJob
{
    private TecDocIntegrationService $tecdoc;
    private \PDO $pdo;

    public function __construct()
    {
        $this->tecdoc = new TecDocIntegrationService();
        $this->pdo = Database::getDB('default');
    }

    /**
     * Execută jobul de îmbogățire TecDoc
     */
    public function handle(array $payload): array
    {
        ImportRuntime::configureForLargeFiles();
        ImportLogger::info('Starting TecDoc enrichment job', $payload);

        $startTime = microtime(true);

        try {
            // Validare payload
            $this->validatePayload($payload);

            // Configurare job
            $config = array_merge([
                'product_ids' => [],
                'batch_size' => 20,
                'include_descriptions' => true,
                'include_images' => true,
                'include_compatibility' => false,
                'download_images' => false,
                'rate_limit_delay' => 200, // ms
                'max_retries' => 2
            ], $payload);

            // Procesare produse
            $result = $this->processProducts($config);

            $processingTime = microtime(true) - $startTime;
            $result['processing_time'] = round($processingTime, 2);

            ImportLogger::info('TecDoc enrichment job completed', [
                'products_count' => count($config['product_ids']),
                'enriched_count' => $result['enriched_count'],
                'processing_time' => $result['processing_time']
            ]);

            return [
                'success' => true,
                'result' => $result,
                'processing_time' => $processingTime
            ];

        } catch (\Exception $e) {
            $processingTime = microtime(true) - $startTime;
            
            ImportLogger::error('TecDoc enrichment job failed', $e, [
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

        if (count($payload['product_ids']) > 1000) {
            throw new \InvalidArgumentException('Maximum 1000 products per job');
        }
    }

    private function processProducts(array $config): array
    {
        $productIds = $config['product_ids'];
        $batchSize = $config['batch_size'];
        
        $results = [
            'total_products' => count($productIds),
            'enriched_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'api_calls_count' => 0,
            'errors' => [],
            'enrichment_details' => []
        ];

        // Procesare în batches
        $batches = array_chunk($productIds, $batchSize);
        
        ImportLogger::info('Starting product enrichment', [
            'total_products' => $results['total_products'],
            'batch_size' => $batchSize,
            'total_batches' => count($batches)
        ]);

        foreach ($batches as $batchIndex => $batch) {
            try {
                $batchResult = $this->processBatch($batch, $batchIndex, $config);
                
                // Agregare rezultate
                $results['enriched_count'] += $batchResult['enriched'];
                $results['failed_count'] += $batchResult['failed'];
                $results['skipped_count'] += $batchResult['skipped'];
                $results['api_calls_count'] += $batchResult['api_calls'];
                $results['errors'] = array_merge($results['errors'], $batchResult['errors']);
                $results['enrichment_details'] = array_merge($results['enrichment_details'], $batchResult['details']);

                // Progress logging
                $progress = (($batchIndex + 1) / count($batches)) * 100;
                ImportLogger::progress('tecdoc_enrichment', $progress, 
                    "Enriched batch " . ($batchIndex + 1) . "/" . count($batches)
                );

                // Rate limiting între batches
                if ($config['rate_limit_delay'] > 0) {
                    usleep($config['rate_limit_delay'] * 1000);
                }

                // Cleanup memorie
                ImportRuntime::forceGarbageCollection();

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'batch' => $batchIndex,
                    'error' => $e->getMessage(),
                    'type' => 'batch_error'
                ];
                
                ImportLogger::error('TecDoc batch enrichment failed', $e, [
                    'batch_index' => $batchIndex,
                    'batch_size' => count($batch)
                ]);
            }
        }

        // Statistici finale
        $results['success_rate'] = $results['total_products'] > 0 
            ? round(($results['enriched_count'] / $results['total_products']) * 100, 2)
            : 0;

        return $results;
    }

    private function processBatch(array $productIds, int $batchIndex, array $config): array
    {
        $batchResult = [
            'batch_index' => $batchIndex,
            'enriched' => 0,
            'failed' => 0,
            'skipped' => 0,
            'api_calls' => 0,
            'errors' => [],
            'details' => []
        ];

        // Încărcare produse din baza de date
        $products = $this->loadProducts($productIds);

        foreach ($products as $product) {
            try {
                $enrichResult = $this->enrichProduct($product, $config);
                
                $batchResult['api_calls'] += $enrichResult['api_calls'];
                
                if ($enrichResult['success'] && $enrichResult['enriched']) {
                    $batchResult['enriched']++;
                    
                    // Salvare modificări în baza de date
                    $this->updateProduct($product['id'], $enrichResult['enriched_data']);
                    
                    $batchResult['details'][] = [
                        'product_id' => $product['id'],
                        'product_code' => $product['pCode'],
                        'enrichments' => $enrichResult['enrichments']
                    ];
                    
                } elseif ($enrichResult['success']) {
                    $batchResult['skipped']++;
                    
                } else {
                    $batchResult['failed']++;
                    $batchResult['errors'][] = [
                        'product_id' => $product['id'],
                        'product_code' => $product['pCode'],
                        'error' => $enrichResult['error']
                    ];
                }

                // Rate limiting între produse
                if ($config['rate_limit_delay'] > 0) {
                    usleep(($config['rate_limit_delay'] / 2) * 1000);
                }

            } catch (\Exception $e) {
                $batchResult['failed']++;
                $batchResult['errors'][] = [
                    'product_id' => $product['id'],
                    'error' => $e->getMessage(),
                    'type' => 'processing_error'
                ];
                
                ImportLogger::error('Product enrichment failed', $e, [
                    'product_id' => $product['id'],
                    'product_code' => $product['pCode'] ?? 'unknown'
                ]);
            }
        }

        ImportLogger::info('TecDoc batch processed', [
            'batch_index' => $batchIndex,
            'products' => count($products),
            'enriched' => $batchResult['enriched'],
            'failed' => $batchResult['failed'],
            'api_calls' => $batchResult['api_calls']
        ]);

        return $batchResult;
    }

    private function enrichProduct(array $product, array $config): array
    {
        $result = [
            'success' => false,
            'enriched' => false,
            'api_calls' => 0,
            'enrichments' => [],
            'enriched_data' => [],
            'error' => null
        ];

        // Verificare dacă produsul are date necesare pentru îmbogățire
        if (empty($product['pCode']) || empty($product['pBrand'])) {
            $result['success'] = true; // Nu e eroare, doar skip
            $result['error'] = 'Missing code or brand';
            return $result;
        }

        // Verificare dacă produsul este deja îmbogățit recent
        if ($this->isRecentlyEnriched($product)) {
            $result['success'] = true;
            $result['error'] = 'Recently enriched';
            return $result;
        }

        try {
            // Căutare articol TecDoc
            $searchResult = $this->tecdoc->searchArticle([
                'code' => $product['pCode'],
                'brand' => $product['pBrand'],
                'name' => $product['pName'] ?? ''
            ]);
            
            $result['api_calls']++;

            if (!$searchResult['success'] || empty($searchResult['articles'])) {
                $result['success'] = true; // Nu e eroare critică
                $result['error'] = 'No TecDoc article found';
                return $result;
            }

            $article = $searchResult['articles'][0]; // Primul rezultat
            $enrichedData = [];

            // Îmbogățire descriere
            if ($config['include_descriptions']) {
                $descResult = $this->tecdoc->getArticleDescription($article['article_id']);
                $result['api_calls']++;
                
                if ($descResult['success']) {
                    $enrichedData['pNote'] = $descResult['description'];
                    $result['enrichments'][] = 'description';
                }
            }

            // Îmbogățire imagini
            if ($config['include_images']) {
                $imgResult = $this->tecdoc->getArticleImages($article['article_id']);
                $result['api_calls']++;
                
                if ($imgResult['success'] && !empty($imgResult['images'])) {
                    $imageUrls = array_column($imgResult['images'], 'url');
                    $enrichedData['pImages'] = json_encode($imageUrls);
                    $result['enrichments'][] = 'images';
                    
                    // Descărcare imagini (opțional)
                    if ($config['download_images']) {
                        $this->downloadProductImages($imageUrls, $product['pCode']);
                    }
                }
            }

            // Îmbogățire compatibilitate
            if ($config['include_compatibility']) {
                $compatResult = $this->tecdoc->getArticleCompatibility($article['article_id']);
                $result['api_calls']++;
                
                if ($compatResult['success'] && !empty($compatResult['vehicles'])) {
                    $compatText = $this->formatCompatibilityText($compatResult['vehicles']);
                    $enrichedData['pCompatibilitati'] = $compatText;
                    $result['enrichments'][] = 'compatibility';
                }
            }

            // Îmbogățire cross-references (coduri OEM)
            $crossRefResult = $this->tecdoc->getArticleCrossReferences($article['article_id']);
            $result['api_calls']++;
            
            if ($crossRefResult['success'] && !empty($crossRefResult['references'])) {
                $oemCodes = array_column($crossRefResult['references'], 'reference_number');
                $existingOem = explode(',', $product['pOem'] ?? '');
                $allOemCodes = array_unique(array_merge($existingOem, $oemCodes));
                $enrichedData['pOem'] = implode(',', array_filter($allOemCodes));
                $result['enrichments'][] = 'cross_references';
            }

            // Adăugare metadata îmbogățire
            $enrichmentMeta = [
                'tecdoc_enrichment' => [
                    'article_id' => $article['article_id'],
                    'enriched_at' => date('c'),
                    'enrichments' => $result['enrichments'],
                    'api_calls' => $result['api_calls']
                ]
            ];

            $existingRawJson = json_decode($product['raw_json'] ?? '{}', true);
            $enrichedData['raw_json'] = json_encode(array_merge($existingRawJson, $enrichmentMeta));

            // Marcare surse imagine
            if (isset($enrichedData['pImages'])) {
                $enrichedData['pImageSource'] = 'tecdoc_api';
            }

            $result['success'] = true;
            $result['enriched'] = !empty($enrichedData);
            $result['enriched_data'] = $enrichedData;

            return $result;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            ImportLogger::error('TecDoc enrichment failed for product', $e, [
                'product_id' => $product['id'],
                'product_code' => $product['pCode']
            ]);
            
            return $result;
        }
    }

    private function loadProducts(array $productIds): array
    {
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        
        $stmt = $this->pdo->prepare("
            SELECT id, randomn_id, pName, pCode, pBrand, pNote, pImages, pOem, 
                   pCompatibilitati, pImageSource, raw_json, updated_at
            FROM produse 
            WHERE id IN ($placeholders)
        ");
        
        $stmt->execute($productIds);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function isRecentlyEnriched(array $product): bool
    {
        // Verificare dacă produsul a fost îmbogățit în ultimele 24h
        $rawJson = json_decode($product['raw_json'] ?? '{}', true);
        
        if (isset($rawJson['tecdoc_enrichment']['enriched_at'])) {
            $enrichedAt = strtotime($rawJson['tecdoc_enrichment']['enriched_at']);
            return (time() - $enrichedAt) < 86400; // 24 hours
        }

        return false;
    }

    private function updateProduct(int $productId, array $enrichedData): void
    {
        if (empty($enrichedData)) {
            return;
        }

        $fields = [];
        $values = [];
        
        foreach ($enrichedData as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
        }
        
        $values[] = $productId;
        
        $sql = "UPDATE produse SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        
        ImportLogger::info('Product updated with TecDoc enrichment', [
            'product_id' => $productId,
            'fields_updated' => array_keys($enrichedData)
        ]);
    }

    private function downloadProductImages(array $imageUrls, string $productCode): void
    {
        $downloadDir = dirname(__DIR__, 2) . '/storage/images/products';
        if (!is_dir($downloadDir)) {
            mkdir($downloadDir, 0775, true);
        }

        foreach ($imageUrls as $index => $url) {
            try {
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $filename = $productCode . '_' . $index . '.' . $extension;
                $localPath = $downloadDir . '/' . $filename;

                if (!file_exists($localPath)) {
                    $imageData = file_get_contents($url);
                    if ($imageData !== false) {
                        file_put_contents($localPath, $imageData);
                        
                        ImportLogger::info('Product image downloaded', [
                            'product_code' => $productCode,
                            'filename' => $filename,
                            'size' => strlen($imageData)
                        ]);
                    }
                }

            } catch (\Exception $e) {
                ImportLogger::error('Product image download failed', $e, [
                    'product_code' => $productCode,
                    'url' => $url
                ]);
            }
        }
    }

    private function formatCompatibilityText(array $vehicles): string
    {
        if (empty($vehicles)) {
            return '';
        }

        $formatted = [];
        foreach ($vehicles as $vehicle) {
            $line = [];
            
            if (!empty($vehicle['manufacturer'])) {
                $line[] = $vehicle['manufacturer'];
            }
            
            if (!empty($vehicle['model'])) {
                $line[] = $vehicle['model'];
            }
            
            if (!empty($vehicle['type'])) {
                $line[] = $vehicle['type'];
            }
            
            if (!empty($vehicle['year_from']) || !empty($vehicle['year_to'])) {
                $years = ($vehicle['year_from'] ?? '') . '-' . ($vehicle['year_to'] ?? '');
                $line[] = '(' . trim($years, '-') . ')';
            }
            
            if (!empty($line)) {
                $formatted[] = implode(' ', $line);
            }
        }

        return implode("\n", array_unique($formatted));
    }
}