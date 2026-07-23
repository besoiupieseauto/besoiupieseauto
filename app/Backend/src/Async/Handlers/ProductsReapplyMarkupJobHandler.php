<?php



declare(strict_types=1);



namespace Besoiu\Async\Handlers;



use Besoiu\Async\JobHandlerInterface;

use Besoiu\Core\Produse\ProduseModel;

use Besoiu\Services\AdaosComercial\AdaosComercialService;

use Besoiu\Services\Products\ProduseService;



/**

 * Reaplică adaos comercial pe set filtrat server-side — chunk keyset, fără limită pagină UI.

 */

final class ProductsReapplyMarkupJobHandler implements JobHandlerInterface

{

    public function handle(array $payload, callable $reportProgress): array

    {

        $filters = self::normalizeFilters(is_array($payload['filters'] ?? null) ? $payload['filters'] : []);

        $chunkSize = max(1, min(500, (int) ($payload['chunk_size'] ?? 200)));



        $service = new ProduseService();

        $model = new ProduseModel();

        $markupService = new AdaosComercialService();



        $total = $service->countAdminList($filters);

        if ($total <= 0) {

            return [

                'message' => 'Nu există produse care să corespundă filtrelor.',

                'updated' => 0,

                'total_filtered' => 0,

            ];

        }



        $updated = 0;

        $processed = 0;

        $cursorId = null;



        while (true) {

            $batch = $model->listRandomIdsByFilters($filters, $chunkSize, $cursorId);

            $ids = $batch['ids'] ?? [];

            if ($ids === []) {

                break;

            }



            $result = $markupService->reapplyAutomaticMarkupToProductIds($ids);

            $updated += (int) ($result['updated_count'] ?? 0);

            $processed += count($ids);

            $cursorId = $batch['last_id'] ?? null;



            $pct = (int) min(99, round(($processed / max(1, $total)) * 100));

            $reportProgress(

                $pct,

                'Adaos reaplicat ' . $processed . ' / ' . $total . ' (' . $updated . ' actualizate)'

            );



            if ($cursorId === null || count($ids) < $chunkSize) {

                break;

            }

        }



        $reportProgress(100, 'Reaplicare adaos finalizată.');



        return [

            'message' => 'Adaosul a fost reaplicat pentru ' . $updated . ' produse (din ' . $total . ' filtrate).',

            'updated' => $updated,

            'total_filtered' => $total,

            'processed' => $processed,

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


