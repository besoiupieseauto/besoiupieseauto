<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\Client;
use App\Models\Localitate;
use App\Services\AnafService;
//use DataTables;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class ClientController extends Controller
{
    protected $anafService;

    public function __construct(AnafService $anafService)
    {
        $this->anafService = $anafService;
    }

    // Display a list of clients
    public function index()
    {
        $counties = Localitate::select('judet')->distinct()->orderBy('judet')->get();
        return view('clients.index', compact('counties'));
    }

    // Get data for DataTables
    public function getData(Request $request)
    {
        if ($request->ajax()) {
            $data = Client::select('*');
            
            return Datatables::of($data)
                ->addIndexColumn()
				->addColumn('nume', function ($row) {
					$company = $row->companie ?? '';
					$user    = $row->nume ?? '';

					return '
						<div style="display:flex;flex-flow:column;text-align:center;">
							<p style="font-size:15px;margin:0;">' . ($user ?: '') . '</p>
							<p style="font-size:11px;margin:0;color:#737373;">' . ($company ?: '') . '</p>
						</div>
					';
				})
				->filterColumn('nume', function($query, $keyword) {
					$keyword = "%{$keyword}%";
					$query->where(function($q) use ($keyword) {
						$q->where('companie', 'like', $keyword)
						  ->orWhere('nume', 'like', $keyword);
					});
				})
                ->addColumn('action', function($row){
                    $actionBtn = '<div class="btn-group">
                        <button type="button" onclick="editClient('.$row->idclienti.')" class="btn btn-default btn-sm" style="background-color: #f8f8f8;">
                            <i class="glyphicon glyphicon-pencil text-primary"></i>
                        </button>
                        <button type="button" onclick="deleteClient('.$row->idclienti.')" class="btn btn-default btn-sm" style="background-color: #f8f8f8;">
                            <i class="glyphicon glyphicon-trash text-danger"></i>
                        </button>
                    </div>';
                    return $actionBtn;
                })
                ->rawColumns(['action','nume'])
                ->make(true);
        }
    }

    // Store a new client via AJAX
    public function store(Request $request)
    {
       // idlocalitate
        $validatedData = $request->validate([
            'nume_nou_cl'       => 'required|string|max:255',
            'telefon_nou'       => 'nullable|string',
            'marca_masina'      => 'nullable|string',
            'sasiu_masina'      => 'nullable|string',
            'nrmat_masina'      => 'nullable|string',
            'companie_nou_cl'   => 'nullable|string',
            'cif_nou_cl'        => 'nullable|string',
            'cont_banca'        => 'nullable|string',
            'nume_banca'        => 'nullable|string',
            'regcom'            => 'nullable|string',
			
			'judet_nou_cl'      => 'nullable|string',
			'localitate_nou_cl' => 'nullable|integer',
			'adresa_nou'        => 'required|string',
			
			'judet_facturare'        => 'nullable|string',
			'localitate_facturare'   => 'nullable|integer',
			'adresa_facturare'       => 'nullable|string',
			'billing_same_as_delivery' => 'nullable|boolean',
        ]);
		
		if (!empty($validatedData['billing_same_as_delivery'])) {
			$validatedData['localitate_facturare'] = $validatedData['localitate_nou_cl'] ?? null;
			$validatedData['adresa_facturare'] = $validatedData['adresa_nou'] ?? null;
		}

        $client = Client::create([
            'nume'       => $validatedData['nume_nou_cl'],
            'adresa'     => $validatedData['adresa_nou'],
            'telefon'    => $validatedData['telefon_nou'] ?? null,
            'marca'      => $validatedData['marca_masina'] ?? null,
            'sasiu'      => $validatedData['sasiu_masina'] ?? null,
            'nr_inmat'   => $validatedData['nrmat_masina'] ?? null,
            'companie'   => $validatedData['companie_nou_cl'] ?? null,
            'cif'        => $validatedData['cif_nou_cl'] ?? null,
            'regcom'     => $validatedData['regcom'] ?? null,
            'cont_banca' => $validatedData['cont_banca'] ?? null,
            'nume_banca' => $validatedData['nume_banca'] ?? null,
            'idlocalitate' => $validatedData['localitate_nou_cl'] ?? null,
            'idmasina' => 0, // Assuming idmasina is not provided in this request
			
			'localitate_facturare'   => $validatedData['localitate_facturare'] ?? null,
			'adresa_facturare'       => $validatedData['adresa_facturare'] ?? null,
			'created_at'  => Carbon::now()->timestamp + (2 * 3600),
        ]);

        return response()->json(['success' => true, 'client' => $client]);
    }


    
    // Store a new client via AJAX
    public function saveClient(Request $request)
    {
        $validatedData = $request->validate([
            'nume_nou_cl'       => 'required|string|max:255',
            'telefon_nou'       => 'nullable|string',
            'marca_masina'      => 'nullable|string',
            'sasiu_masina'      => 'nullable|string',
            'nrmat_masina'      => 'nullable|string',
            'companie_nou_cl'   => 'nullable|string',
            'cif_nou_cl'        => 'nullable|string',
            'cont_banca'        => 'nullable|string',
            'nume_banca'        => 'nullable|string',
            'regcom'            => 'nullable|string',
			
			'judet_nou_cl'      => 'nullable|string',
			'localitate_nou_cl' => 'nullable|integer',
			'adresa_nou'        => 'required|string',
			
			'judet_facturare'        => 'nullable|string',
			'localitate_facturare'   => 'nullable|integer',
			'adresa_facturare'       => 'nullable|string',
			'billing_same_as_delivery' => 'nullable|boolean',
        ]);
		
		if (!empty($validatedData['billing_same_as_delivery'])) {
			$validatedData['localitate_facturare'] = $validatedData['localitate_nou_cl'] ?? null;
			$validatedData['adresa_facturare'] = $validatedData['adresa_nou'] ?? null;
		}

        $client = Client::create([
            'nume'       => $validatedData['nume_nou_cl'],
            'adresa'     => $validatedData['adresa_nou'],
            'telefon'    => $validatedData['telefon_nou'] ?? null,
            'marca'      => $validatedData['marca_masina'] ?? null,
            'sasiu'      => $validatedData['sasiu_masina'] ?? null,
            'nr_inmat'   => $validatedData['nrmat_masina'] ?? null,
            'companie'   => $validatedData['companie_nou_cl'] ?? null,
            'cif'        => $validatedData['cif_nou_cl'] ?? null,
            'regcom'     => $validatedData['regcom'] ?? null,
            'cont_banca' => $validatedData['cont_banca'] ?? null,
            'nume_banca' => $validatedData['nume_banca'] ?? null,
            'idlocalitate' => $validatedData['localitate_nou_cl'] ?? null,
            'idmasina' => 0, // Assuming idmasina is not provided in this request
			
			'localitate_facturare'   => $validatedData['localitate_facturare'] ?? null,
			'adresa_facturare'       => $validatedData['adresa_facturare'] ?? null,
			'created_at'  => Carbon::now()->timestamp + (2 * 3600),
        ]);

        return response()->json(['success' => true, 'client' => $client]);
    }

    // Return client data as JSON for editing
    public function edit($id)
    {
        // $client = Client::findOrFail($id);
        // return response()->json($client);

        // Get client data with locality join
        $client = DB::table('clienti')
            ->leftJoin('localitati', 'clienti.idlocalitate', '=', 'localitati.idlocatie')
			->leftJoin('localitati as livrare', 'clienti.localitate_livrare', '=', 'livrare.idlocatie')
			->leftJoin('localitati as facturare', 'clienti.localitate_facturare', '=', 'facturare.idlocatie')
			->select(
				'clienti.*',
				'localitati.judet as judet_nou_cl',
				'localitati.localitate as localitate_nou_cl',
				'livrare.judet as judet_livrare',
				'livrare.localitate as localitate_livrare_nume',
				'facturare.judet as judet_facturare',
				'facturare.localitate as localitate_facturare_nume'
			)
            ->where('clienti.idclienti', $id)
            ->first();
        
        return response()->json($client);
    }

    // Update an existing client via AJAX
    public function update(Request $request, $id)
    {
        $client = Client::findOrFail($id);
        $validatedData = $request->validate([
            'nume_nou_cl'       => 'required|string|max:255',
            'telefon_nou'       => 'nullable|string',
            'marca_masina'      => 'nullable|string',
            'sasiu_masina'      => 'nullable|string',
            'nrmat_masina'      => 'nullable|string',
            'companie_nou_cl'   => 'nullable|string',
            'cif_nou_cl'        => 'nullable|string',
            'cont_banca'        => 'nullable|string',
            'nume_banca'        => 'nullable|string',
            'regcom'            => 'nullable|string',
			
			'judet_nou_cl'      => 'nullable|string',
			'localitate_nou_cl' => 'nullable|integer',
			'adresa_nou'        => 'required|string',
			
			'judet_facturare'        => 'nullable|string',
			'localitate_facturare'   => 'nullable|integer',
			'adresa_facturare'       => 'nullable|string',
			'billing_same_as_delivery' => 'nullable|boolean',
        ]);
		
		if (!empty($validatedData['billing_same_as_delivery'])) {
			$validatedData['localitate_facturare'] = $validatedData['localitate_nou_cl'] ?? null;
			$validatedData['adresa_facturare'] = $validatedData['adresa_nou'] ?? null;
		}

        $client->update([
            'nume'       => $validatedData['nume_nou_cl'],
            'adresa'     => $validatedData['adresa_nou'],
            'telefon'    => $validatedData['telefon_nou'] ?? null,
            'marca'      => $validatedData['marca_masina'] ?? null,
            'sasiu'      => $validatedData['sasiu_masina'] ?? null,
            'nr_inmat'   => $validatedData['nrmat_masina'] ?? null,
            'companie'   => $validatedData['companie_nou_cl'] ?? null,
            'cif'        => $validatedData['cif_nou_cl'] ?? null,
            'regcom'     => $validatedData['regcom'] ?? null,
            'cont_banca' => $validatedData['cont_banca'] ?? null,
            'nume_banca' => $validatedData['nume_banca'] ?? null,
            'idlocalitate' => $validatedData['localitate_nou_cl'] ?? 0,
			
			'localitate_facturare'   => $validatedData['localitate_facturare'] ?? null,
			'adresa_facturare'       => $validatedData['adresa_facturare'] ?? null,
        ]);

        return response()->json(['success' => true, 'client' => $client]);
    }

    // Delete a client
    public function destroy($id)
    {
        try {
            $client = Client::findOrFail($id);
            $client->delete();
        
            return response()->json(['success' => true, 'message' => 'Client deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => 'A apărut o eroare la ștergere.', 'details' => $e->getMessage()]);
        }
    }
    
    // Get localities for a county
    public function getLocalities($judet)
    {
        $localities = Localitate::where('judet', $judet)->orderBy('localitate', 'asc')->get();
        return response()->json($localities);
    }

    /**
     * Get company information from ANAF API
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAnafInfo(Request $request)
    {
        $cui = $request->input('cui');
        
        if (empty($cui)) {
            return response()->json([
                'message' => 'ERROR',
                'error' => 'CUI is required'
            ]);
        }

        // Get company info from ANAF API
        $result = $this->anafService->getCompanyInfo($cui);
		
		if (!empty($result['found'])) {
			foreach ($result['found'] as &$company) {
				$company['coduri_postale'] = [];

				// For social address
				$scod = $company['adresa_sediu_social']['scod_Postal'] ?? null;
				if ($scod) {
					$scodData = \DB::table('coduri_postale')
						->where('cod', $scod)
						->first();
					$company['coduri_postale']['scod_Postal'] = $scodData;
				}

				// For fiscal address
				$dcod = $company['adresa_domiciliu_fiscal']['dcod_Postal'] ?? null;
				if ($dcod) {
					$dcodData = \DB::table('coduri_postale')
						->where('cod', $dcod)
						->first();
					$company['coduri_postale']['dcod_Postal'] = $dcodData;
				}
			}
		}
        
        // Return the response directly as in the original anaf.php
        return response()->json($result);
    }
}
