<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class LKQImportService
{
    protected string $zipFile = 'upload/LKQRO_pricelist_1888856.zip';

    public function run(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        try {
            Log::info('LKQ Import started');

            // Paths
            $localDir     = storage_path('app/lkq');
            $zipPath      = $localDir . '/' . $this->zipFile;
            $extractPath  = $localDir . '/extracted';
			
			// Ensure directories exist
			foreach ([$localDir, dirname($zipPath), $extractPath] as $dir) {
				if (!is_dir($dir)) {
					mkdir($dir, 0755, true);
				}
			}


            // 1️⃣ Download ZIP from SFTP
            if (!Storage::disk('sftp_lkq')->exists($this->zipFile)) {
                throw new \Exception('ZIP file not found on SFTP');
            }

            file_put_contents(
                $zipPath,
                Storage::disk('sftp_lkq')->get($this->zipFile)
            );

            // 2️⃣ Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \Exception('Unable to open ZIP file');
            }

            $zip->extractTo($extractPath);
            $zip->close();

            // 3️⃣ Find CSV file automatically
            $csvFile = $this->findCsv($extractPath);

            if (!$csvFile) {
                throw new \Exception('No CSV file found in ZIP');
            }

            // 4️⃣ Import CSV to DB (chunked)
            $this->importCsv($csvFile);

            Log::info('LKQ Import completed successfully');

        } catch (\Throwable $e) {
            Log::error('LKQ Import failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function findCsv(string $path): ?string
    {
        foreach (scandir($path) as $file) {
            if (str_ends_with(strtolower($file), '.csv')) {
                return $path . '/' . $file;
            }
        }
        return null;
    }

	protected function importCsv(string $filePath): void
	{
		DB::disableQueryLog();

		$handle = fopen($filePath, 'r');

		if (!$handle) {
			throw new \Exception('Unable to read CSV file');
		}

		// 1️⃣ Clear table first
		DB::table('lkq_prices')->truncate();

		$batch = [];
		$batchSize = 1000;
		$rowCount = 0;

		// 2️⃣ Read header row
		$header = fgetcsv($handle, 0, ';');

		while (($row = fgetcsv($handle, 0, ';')) !== false) {

			$batch[] = [
				'item_nr'             => trim($row[0]),
				'supplier_catalog_nr' => trim($row[1]),
				'description_ro'      => $row[2] ?? null,
				'description_en'      => $row[3] ?? null,
				'brand_name'          => $row[4] ?? null,
				'net_price'           => (float) ($row[5] ?? 0),
				'updated_at'          => now(),
				'created_at'          => now(),
			];

			$rowCount++;

			if (count($batch) >= $batchSize) {
				DB::table('lkq_prices')->insert($batch);
				$batch = [];
			}
		}

		if (!empty($batch)) {
			DB::table('lkq_prices')->insert($batch);
		}

		fclose($handle);

		Log::info("LKQ CSV imported rows: {$rowCount}");
	}

    protected function upsertBatch(array $data): void
    {
        DB::table('lkq_prices')->upsert(
            $data,
            ['product_code'], // unique key
            ['description', 'price', 'stock', 'updated_at']
        );
    }
}