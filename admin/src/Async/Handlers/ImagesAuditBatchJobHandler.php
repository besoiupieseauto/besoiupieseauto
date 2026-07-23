<?php



declare(strict_types=1);



namespace Besoiu\Async\Handlers;



use Besoiu\Async\JobHandlerInterface;

use Besoiu\Core\Produse\ProduseModel;

use Besoiu\Services\ProductImageAuditService;

use Besoiu\Services\Products\ProduseService;

use Config\Database;



/**

 * Audit imagini în lot — chunk keyset, suport OpenAI vision sau pregătire lot Cursor.

 */

final class ImagesAuditBatchJobHandler implements JobHandlerInterface

{

    public function handle(array $payload, callable $reportProgress): array

    {

        $projectRoot = dirname(__DIR__, 4);

        $pdo = Database::getDB();

        $auditService = new ProductImageAuditService($projectRoot);

        $produseService = new ProduseService();

        $produseModel = new ProduseModel();



        $chunkSize = max(1, min(100, (int) ($payload['chunk_size'] ?? 25)));

        $engine = strtolower(trim((string) ($payload['engine'] ?? ProductImageAuditService::auditEngine())));

        $explicitIds = array_values(array_filter(array_map('strval', (array) ($payload['ids'] ?? []))));

        $selectAll = !empty($payload['all']);

        $filters = self::normalizeFilters(is_array($payload['filters'] ?? null) ? $payload['filters'] : []);



        if ($explicitIds !== []) {

            return $this->processIdList($auditService, $pdo, $explicitIds, $chunkSize, $engine, $reportProgress);

        }



        if (!$selectAll && $filters === []) {

            throw new \RuntimeException('Specifică ids, all:true sau filters pentru audit imagini.');

        }



        $total = $produseService->countAdminList($filters);

        if ($total <= 0) {

            return [

                'message' => 'Nu există produse pentru audit imagini.',

                'audited' => 0,

                'total' => 0,

                'engine' => $engine,

            ];

        }



        $processed = 0;

        $audited = 0;

        $cursorId = null;

        $batchPaths = [];

        $errors = [];



        while (true) {

            $batch = $produseModel->listRandomIdsByFilters($filters, $chunkSize, $cursorId);

            $ids = $batch['ids'] ?? [];

            if ($ids === []) {

                break;

            }



            $chunkResult = $this->auditChunk($auditService, $pdo, $ids, $engine);

            $audited += (int) ($chunkResult['audited'] ?? 0);

            if (!empty($chunkResult['batch_path'])) {

                $batchPaths[] = (string) $chunkResult['batch_path'];

            }

            if (!empty($chunkResult['error'])) {

                $errors[] = (string) $chunkResult['error'];

            }



            $processed += count($ids);

            $cursorId = $batch['last_id'] ?? null;



            $pct = (int) min(99, round(($processed / max(1, $total)) * 100));

            $reportProgress($pct, 'Audit imagini ' . $processed . ' / ' . $total . ' (' . $audited . ' analizate)');



            if ($cursorId === null || count($ids) < $chunkSize) {

                break;

            }

        }



        $reportProgress(100, 'Audit imagini finalizat.');



        return [

            'message' => 'Audit imagini: ' . $audited . ' produse procesate din ' . $total . '.',

            'audited' => $audited,

            'processed' => $processed,

            'total' => $total,

            'engine' => $engine,

            'batch_paths' => $batchPaths,

            'errors' => $errors,

        ];

    }



    /**

     * @param array<int, string> $ids

     * @return array<string, mixed>

     */

    private function processIdList(

        ProductImageAuditService $auditService,

        \PDO $pdo,

        array $ids,

        int $chunkSize,

        string $engine,

        callable $reportProgress

    ): array {

        $total = count($ids);

        $processed = 0;

        $audited = 0;

        $batchPaths = [];

        $errors = [];



        foreach (array_chunk($ids, $chunkSize) as $chunk) {

            $chunkResult = $this->auditChunk($auditService, $pdo, $chunk, $engine);

            $audited += (int) ($chunkResult['audited'] ?? 0);

            if (!empty($chunkResult['batch_path'])) {

                $batchPaths[] = (string) $chunkResult['batch_path'];

            }

            if (!empty($chunkResult['error'])) {

                $errors[] = (string) $chunkResult['error'];

            }



            $processed += count($chunk);

            $pct = (int) min(99, round(($processed / max(1, $total)) * 100));

            $reportProgress($pct, 'Audit imagini ' . $processed . ' / ' . $total);



            if (!empty($chunkResult['stop'])) {

                break;

            }

        }



        $reportProgress(100, 'Audit imagini finalizat.');



        return [

            'message' => 'Audit imagini: ' . $audited . ' produse procesate din ' . $total . '.',

            'audited' => $audited,

            'processed' => $processed,

            'total' => $total,

            'engine' => $engine,

            'batch_paths' => $batchPaths,

            'errors' => $errors,

        ];

    }



    /**

     * @param array<int, string> $ids

     * @return array{audited:int,batch_path?:string,error?:string,stop?:bool}

     */

    private function auditChunk(

        ProductImageAuditService $auditService,

        \PDO $pdo,

        array $ids,

        string $engine

    ): array {

        $products = $auditService->loadProductsByPublicIds($pdo, $ids);

        if ($products === []) {

            return ['audited' => 0];

        }



        if ($engine === 'openai') {

            $apiKey = ProductImageAuditService::normalizeOpenAiKey(

                (string) ($_ENV['OPENAI_KEY'] ?? getenv('OPENAI_KEY') ?: '')

            );

            if ($apiKey === '') {

                return ['audited' => 0, 'error' => 'OPENAI_KEY lipsă.', 'stop' => true];

            }



            $model = trim((string) ($_ENV['IMAGE_AUDIT_MODEL'] ?? getenv('IMAGE_AUDIT_MODEL') ?: 'gpt-4o-mini'));

            $sync = $auditService->runOpenAiAuditSync($products, $ids, $apiKey, $model);



            return [

                'audited' => count($sync['results'] ?? []),

                'error' => ($sync['ok'] ?? false) ? null : (string) ($sync['message'] ?? 'Eroare OpenAI'),

                'stop' => !($sync['ok'] ?? false),

            ];

        }



        $prep = $auditService->prepareCursorAuditBatch($products, [

            'source' => 'async_images_audit_batch',

            'ids' => $ids,

        ]);



        return [

            'audited' => count($products),

            'batch_path' => (string) ($prep['batch_path'] ?? ''),

        ];

    }



    /** @return array<string, string> */

    private static function normalizeFilters(array $input): array

    {

        $allowed = ['q', 'category', 'subcategory', 'marca', 'brand', 'supplier', 'image', 'markup', 'status', 'origin'];

        $filters = [];

        foreach ($allowed as $key) {

            $value = trim((string) ($input[$key] ?? ''));

            if ($value !== '') {

                $filters[$key] = mb_substr($value, 0, 255, 'UTF-8');

            }

        }



        return $filters;

    }

}


