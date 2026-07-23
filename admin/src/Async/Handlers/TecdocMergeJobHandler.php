<?php



declare(strict_types=1);



namespace Besoiu\Async\Handlers;



use Besoiu\Async\JobHandlerInterface;



/**

 * Copiază catalogul TecDoc legacy → baza magazin (tecdoc_*), înlocuiește popen/exec din HTTP.

 */

final class TecdocMergeJobHandler implements JobHandlerInterface

{

    public function handle(array $payload, callable $reportProgress): array

    {

        $this->bootTecdocLib();



        $dryRun = !empty($payload['dry_run']);

        $applySchema = !empty($payload['apply_schema']);



        if (besoiupieseimport_tecdoc_merge_in_progress()) {

            return [

                'message' => 'Copierea catalogului rulează deja.',

                'skipped' => true,

                'merging' => true,

            ];

        }



        besoiupieseimport_tecdoc_merge_lock_touch();

        $reportProgress(5, 'Pregătire merge catalog TecDoc…');



        $out = ['dry_run' => $dryRun];



        try {

            if ($applySchema && !$dryRun) {

                $reportProgress(10, 'Aplic schema tecdoc_*…');

                $schema = besoiupieseimport_tecdoc_apply_schema();

                $out['schema'] = $schema;

                if (empty($schema['ok'])) {

                    $errors = is_array($schema['errors'] ?? null) ? $schema['errors'] : [];

                    throw new \RuntimeException('Schema eșuată: ' . implode('; ', $errors));

                }

            }



            $reportProgress(

                20,

                $dryRun ? 'Simulare copiere legacy → shop…' : 'Copiere catalog legacy → shop…'

            );



            $merge = besoiupieseimport_tecdoc_merge_legacy_into_shop($dryRun);

            $out['merge'] = $merge;



            if (empty($merge['ok'])) {

                $errors = is_array($merge['errors'] ?? null) ? $merge['errors'] : ['Merge eșuat'];

                throw new \RuntimeException(implode('; ', $errors));

            }



            $reportProgress(100, (string) ($merge['message'] ?? 'Merge finalizat.'));



            return array_merge($out, [

                'message' => (string) ($merge['message'] ?? 'Catalog TecDoc copiat în baza magazin.'),

                'copied' => $merge['copied'] ?? [],

                'skipped' => $merge['skipped'] ?? [],

                'legacy_db' => $merge['legacy_db'] ?? null,

                'shop_db' => $merge['shop_db'] ?? null,

            ]);

        } catch (\Throwable $e) {

            besoiupieseimport_tecdoc_merge_lock_release();

            throw $e;

        }

    }



    private function bootTecdocLib(): void

    {

        $adminRoot = dirname(__DIR__, 3);

        require_once $adminRoot . '/tools/besoiupieseimport_tecdoc_db.php';



        if (is_file($adminRoot . '/vendor/autoload.php')) {

            require_once $adminRoot . '/vendor/autoload.php';

            if (class_exists(\Dotenv\Dotenv::class) && is_file($adminRoot . '/.env')) {

                \Dotenv\Dotenv::createImmutable($adminRoot)->safeLoad();

            }

        }

    }

}


