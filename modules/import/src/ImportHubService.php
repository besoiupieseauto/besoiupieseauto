<?php
declare(strict_types=1);

namespace Besoiu\Modules\Import;

/** Serviciu hub al modulului Import produse — legături către coadă, Import Pro, CSV. */
final class ImportHubService
{
    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $bridgeOk = BesoiupieseimportBridge::isAvailable();

        return [
            'module' => 'import',
            'name' => 'Import produse',
            'version' => '1.2.0',
            'ok' => true,
            'bridge' => $bridgeOk,
            'package' => ['src' => true, 'pages' => true, 'assets' => true, 'api' => true],
            'features' => [
                [
                    'id' => 'import-pro',
                    'label' => 'Import & Matching Pro',
                    'href' => '/admin/import-pro',
                    'desc' => 'Motor MatchingPro — carduri, TecDoc, scraper',
                ],
                [
                    'id' => 'import-cards',
                    'label' => 'Produse generate — filtrare',
                    'href' => '/admin/import-cards',
                    'desc' => 'Carduri complete cu ultra-filtrare din besoiupieseimport',
                ],
                [
                    'id' => 'import-bovsoft',
                    'label' => 'Procesare Base TecDoc',
                    'href' => '/admin/import-bovsoft',
                    'desc' => 'Upload Base CSV + liste preț, export XLSX',
                ],
                [
                    'id' => 'import',
                    'label' => 'Import CSV / Excel (admin)',
                    'href' => '/admin/import',
                    'desc' => 'Upload și mapare liste furnizor → coadă importreview',
                ],
                [
                    'id' => 'importreview',
                    'label' => 'Coadă import',
                    'href' => '/admin/importreview',
                    'desc' => 'Review conflicte și publish în magazin (modul coada_import)',
                ],
            ],
            'at' => date('c'),
        ];
    }

    public function healthLine(): string
    {
        $d = $this->dashboard();
        $bridge = $d['bridge'] ? 'besoiupieseimport conectat' : 'besoiupieseimport offline';

        return sprintf('%s v%s — %d zone · %s', $d['name'], $d['version'], count($d['features']), $bridge);
    }
}
