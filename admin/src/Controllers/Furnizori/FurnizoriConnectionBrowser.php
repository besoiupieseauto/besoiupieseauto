<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

/**
 * Listare si previzualizare fisiere sincronizate local pentru furnizori.
 */
final class FurnizoriConnectionBrowser
{
    /** @param array<string, mixed> $furnizor @param array<string, mixed> $options @return array<string, mixed> */
    public function browse(array $furnizor, string $requestedPath = '', array $options = []): array
    {
        $this->bootImportLibrary();

        return (new SupplierImportFilesBrowser())->browse($furnizor, $requestedPath, $options);
    }

    private function bootImportLibrary(): void
    {
        if (!function_exists('list_uploaded_import_files')) {
            require_once dirname(__DIR__) . '/Produse/import_uploaded_files_lib.php';
        }
        if (!function_exists('import_supplier_file_matches_code')) {
            require_once dirname(__DIR__) . '/Produse/import_supplier_lib.php';
        }
    }
}
