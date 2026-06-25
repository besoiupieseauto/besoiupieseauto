<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupplierSearchNew\RunSupplierSearchNewAction;
use App\Services\Autototal\AutototalService;

class SupplierSearchNewController extends Controller
{
    private const SUPPORTED_SUPPLIERS = [
        'autopartner',
        'materom',
        'autonet',
        'autototal',
        'elit',
    ];

	public function __construct(
        protected RunSupplierSearchNewAction $searchAction
    ) {}

    /**
     * Supplier Search New page: same payload as old search, HTTP pool, split logic.
     */
    public function index()
    {		
        $supplierlabels = [
            'materom'     => 'MA',
            'elit'        => 'EL',
            'intercars'   => 'IN',
            'autototal'   => 'AT',
            'autonet'     => 'AN',
            'autopartner' => 'AP',
        ];
        return view('searching.index_new', compact('supplierlabels'));
    }

    /**
     * Run search via action; return same payload as old supplier search.
     */
    public function searchSuppliers(Request $request)
    {
        $rawQuery = $request->input('query');
        $selectedSuppliers = $this->normalizeSuppliers($request->input('suppliers', []));
        $query = is_string($rawQuery) ? preg_replace('/[\s\-\/|\\\\]+/', '', $rawQuery) : '';

        if (!$query) {
            return response()->json(['success' => false, 'message' => 'Query is required'], 400);
        }

        if (empty($selectedSuppliers)) {
            return response()->json(['success' => false, 'message' => 'Select at least one supplier'], 400);
        }

        // Keep explicit supplier conditions like the legacy controller.
        $runAutopartner = in_array('autopartner', $selectedSuppliers, true);
        $runMaterom = in_array('materom', $selectedSuppliers, true);
        $runAutonet = in_array('autonet', $selectedSuppliers, true);
        $runAutototal = in_array('autototal', $selectedSuppliers, true);
        $runElit = in_array('elit', $selectedSuppliers, true);

        $effectiveSuppliers = [];
        if ($runAutopartner) {
            $effectiveSuppliers[] = 'autopartner';
        }
        if ($runMaterom) {
            $effectiveSuppliers[] = 'materom';
        }
        if ($runAutonet) {
            $effectiveSuppliers[] = 'autonet';
        }
        if ($runAutototal) {
            $effectiveSuppliers[] = 'autototal';
        }
        if ($runElit) {
            $effectiveSuppliers[] = 'elit';
        }

        if (empty($effectiveSuppliers)) {
            return response()->json([
                'success' => false,
                'message' => 'No supported suppliers selected',
            ], 400);
        }

        // Use pooled engine by default for speed. Legacy remains available via ?engine=legacy.
        $engine = strtolower((string) $request->query('engine', 'pool'));
        // Keep timings enabled by default while we optimize performance bottlenecks.
        $debugTimings = (string) $request->query('debug_timings', '1') !== '0';

        if ($engine !== 'legacy') {
            return $this->runWithPool($query, $effectiveSuppliers, is_string($rawQuery) ? $rawQuery : '', $debugTimings);
        }

        return $this->forwardToLegacySearch($request, is_string($rawQuery) ? $rawQuery : '', $effectiveSuppliers);
    }

    /**
     * Normalize suppliers input while preserving data:
     * - accept only known suppliers
     * - remove duplicates
     * - keep stable output order
     */
    private function normalizeSuppliers(mixed $suppliers): array
    {
        if (!is_array($suppliers)) {
            return [];
        }

        $clean = [];
        foreach ($suppliers as $supplier) {
            if (!is_string($supplier)) {
                continue;
            }
            $key = strtolower(trim($supplier));
            if ($key === '' || !in_array($key, self::SUPPORTED_SUPPLIERS, true)) {
                continue;
            }
            $clean[$key] = true;
        }

        return array_keys($clean);
    }

    private function forwardToLegacySearch(Request $request, string $rawQuery, array $effectiveSuppliers)
    {
        try {
            $legacyRequest = Request::create(
                $request->url(),
                $request->method(),
                [
                    'query' => $rawQuery,
                    'suppliers' => $effectiveSuppliers,
                ]
            );

            // Preserve optional debug mode for easier troubleshooting parity.
            if ((string) $request->query('debug_timings', '') === '1') {
                $legacyRequest->query->set('debug_timings', '1');
            }

            $legacyController = app(SearchingController::class);
            return $legacyController->searchSuppliers($legacyRequest);
        } catch (\Throwable $e) {
            report($e);
			
            return response()->json([
                'success' => false,
                'message' => 'Supplier search failed',
            ], 500);
        }
    }

    private function runWithPool(string $query, array $effectiveSuppliers, string $rawQuery, bool $debugTimings = false)
    {
        try {
            $result = $this->searchAction->run($query, $effectiveSuppliers, $debugTimings, $rawQuery);

            return response()->json(
                $result['payload'],
                200,
                [],
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Supplier search failed',
            ], 500);
        }
    }
}
