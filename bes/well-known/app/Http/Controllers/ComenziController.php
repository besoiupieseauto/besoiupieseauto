<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MessageTemplate;
use App\Models\Client;
use App\Models\Localitate;
use App\Models\Produse;
use App\Models\ComenziExt;
use App\Models\Tmp;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Sameday\Requests\SamedayGetServicesRequest;
use Sameday\Requests\SamedayGetOohLocationsRequest;
use App\Models\ApiCredential;

use DataTables;
use Carbon\Carbon;

use Sameday\Sameday;
use Sameday\SamedayClient;
use Sameday\Objects\PostAwbRecipientObject;
use Sameday\Objects\ParcelDimensionsObject;
use Sameday\Objects\Types\PackageType;
use Sameday\Objects\Types\AwbPaymentType;
use Sameday\Requests\SamedayPostAwbRequest;

use \Sameday\Objects\PostAwbSenderObject;
use Sameday\Objects\PostAwb\Request\EntityObject;
use Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject;
use SeniorProgramming\FanCourier\Facades\FanCourier;

use App\Services\FanCourier\FanCourierService;

//use Fancourier\Fancourier;
use Fancourier\Request\GetCosts;
use Fancourier\Objects\AwbIntern;
use Fancourier\Request\CreateAwb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;


class ComenziController extends Controller
{
    protected $client;
	protected $fanCourier;

    public function __construct(FanCourierService $fanCourier)
    {
		$this->fanCourier = $fanCourier;
        // Initialize Sameday client with credentials from .env
/*         $this->client = new Sameday(
            new SamedayClient(
                env('SAMEDAY_USERNAME'), // set this in your .env file
                env('SAMEDAY_PASSWORD'),
                null,
                env('SAMEDAY_TESTING', 'testing') === 'production'
            )
        ); */
		
		$username = $this->getApiCredential('sameday', 'username');
		$password = $this->getApiCredential('sameday', 'password');
		$testing  = $this->getApiCredential('sameday', 'testing');

		$this->client = new Sameday(
			new SamedayClient(
				$username,
				$password,
				null,
				$testing === 'production'
			)
		);
			
		// Fetch SMS credentials
		$this->smsApiKey = $this->getApiCredential('sms', 'api_key');
		$this->smsApiUrl = $this->getApiCredential('sms', 'api_url');
    }

    /**
     * Display the main orders page
     */
    public function index(Request $request)
    {
        $clients = Client::orderBy('nume')->get();
        $counties = Localitate::select('judet')->distinct()->orderBy('judet')->get();

        // Get date from request, session, or use current date
        $currentDate = $request->get('date');

        if (!$currentDate && session()->has('current_date')) {
            $currentDate = session('current_date');
        }

        if (!$currentDate) {
            $currentDate = date('d/m/Y');
        }

        session(['current_date' => $currentDate]);

        Log::info('Index loading with date', [
            'date' => $currentDate
        ]);

        return view('comenzi.index', compact('clients', 'counties', 'currentDate'));
    }


    /**
     * Get data for the orders table
     */
    public function getData(Request $request)
    {
        try {
            // Add a cache-busting parameter to avoid stale data
            $cacheBuster = $request->get('cache_buster', Carbon::now()->timestamp + (2 * 3600));

            // Pagination parameters
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 50); // Default 50 records per page
            $offset = ($page - 1) * $perPage;

            $date = $request->get('date', date('d/m/Y'));
			
			if($request->from_date == $request->to_date){
				$request->offsetUnset('to_date');
			}

			$fromDate = null;
			$toDate = null;
			if ($request->filled('from_date')) {
				try {
					$parts = explode('/', $request->from_date);
					if(count($parts) === 3){
						$fromDate = Carbon::createFromDate($parts[2], $parts[1], $parts[0])->startOfDay();
					}
				} catch (\Exception $e) {
					$fromDate = Carbon::today()->startOfDay();
					Log::warning('Could not parse from_date: ' . $request->from_date);
				}
			}

			if ($request->filled('to_date')) {
				try {
					$parts = explode('/', $request->to_date);
					if(count($parts) === 3){
						$toDate = Carbon::createFromDate($parts[2], $parts[1], $parts[0])->endOfDay();
					}
				} catch (\Exception $e) {
					$toDate = Carbon::today()->endOfDay();
					Log::warning('Could not parse to_date: ' . $request->to_date);
				}
			}


            // Main query to get order IDs - use write PDO to avoid caching
            $query = DB::table('comenzi_ext')
                ->select('comenzi_ext.idcomanda')
                ->leftJoin('clienti', 'comenzi_ext.idclient', '=', 'clienti.idclienti');
				
			
			$today = now()->toDateString();
			if ($fromDate && $toDate) {
				$query->whereDate('comenzi_ext.data', '>=', $fromDate->toDateString())
					  ->whereDate('comenzi_ext.data', '<=', $toDate->toDateString());
			} elseif ($fromDate) {
				if ($fromDate->toDateString() === $today && $request->has('search_value') && $request->search_value) {
					$fiftyDaysAgo = now()->subDays(50)->toDateString();
					$query->whereDate('comenzi_ext.data', '>=', $fiftyDaysAgo)
						  ->whereDate('comenzi_ext.data', '<=', $today);
				} else {
					$query->whereDate('comenzi_ext.data', '=', $fromDate->toDateString());
				}
			} elseif ($toDate) {
				if ($toDate->toDateString() === $today && $request->has('search_value') && $request->search_value) {
					$fiftyDaysAgo = now()->subDays(50)->toDateString();
					$query->whereDate('comenzi_ext.data', '>=', $fiftyDaysAgo)
						  ->whereDate('comenzi_ext.data', '<=', $today);
				} else {
					$query->whereDate('comenzi_ext.data', '=', $toDate->toDateString());
				}
			}		
		
            // Apply search filter
            if ($request->has('search_value') && $request->search_value) {
                $searchTerm = $request->search_value;
				// Subquery to get orders matching product codes
                $query->where(function($q) use ($searchTerm) {
                    $q->where('clienti.nume', 'like', "%{$searchTerm}%")
                    ->orWhere('clienti.telefon', 'like', "%{$searchTerm}%")
                    ->orWhere('clienti.companie', 'like', "%{$searchTerm}%")
                    ->orWhere('clienti.adresa', 'like', "%{$searchTerm}%")
                    ->orWhere('clienti.marca', 'like', "%{$searchTerm}%")
                    ->orWhere('comenzi_ext.awb', 'like', "%{$searchTerm}%")
					// Subquery for product codes
					  ->orWhereIn('comenzi_ext.idcomanda', function($subquery) use ($searchTerm) {
						  $subquery->select('detaliu_ext.idcomanda')
								   ->from('detaliu_ext')
								   ->join('produse', 'detaliu_ext.idprodus', '=', 'produse.idprodus')
								   ->where('produse.cod_produs', 'like', "%{$searchTerm}%");
					  });
                });
            }
			
			if ($request->filled('filtered_statuses')) {
				$statuses = $request->filtered_statuses; // array of selected statuses
				$query->whereIn('comenzi_ext.stare', $statuses);
			}

            // Get total count for pagination (before applying limit)
            $totalQuery = clone $query;
            $totalRecords = $totalQuery->useWritePdo()
                ->groupBy('comenzi_ext.idcomanda')
                ->distinct()
                ->count();

            $query->useWritePdo() // This helps avoid query caching
            ->groupBy('comenzi_ext.idcomanda')
			->distinct()
            ->orderBy('comenzi_ext.idcomanda', 'desc')
            ->offset($offset)
            ->limit($perPage);

            $orderIds = $query->get()->pluck('idcomanda');

            // Calculate pagination info
            $totalPages = ceil($totalRecords / $perPage);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;

            // Prepare orders data
            $orders = [];
            $orderCreatedAtOffsetHours = $this->getOrderCreatedAtOffsetHours();
            foreach ($orderIds as $orderId) {
                try {
                    // Base order information - also use write PDO here
                    $orderBase = DB::table('comenzi_ext')
                        ->select('comenzi_ext.idcomanda', 'comenzi_ext.idclient', 'comenzi_ext.data', 'comenzi_ext.stare', 'comenzi_ext.awb', 'comenzi_ext.swap_awb', 'comenzi_ext.cont_awb', 'comenzi_ext.observations', 'comenzi_ext.created_at'
                        , 'comenzi_ext.id_factura', 'comenzi_ext.total', 'comenzi_ext.whatsapp_sent', 'comenzi_ext.whatsapp_sent_at', 'comenzi_ext.courier_status', 'clienti.nume as client_name', 'clienti.companie', 'clienti.marca', 'clienti.telefon'
                        , 'clienti.adresa','clienti.idlocalitate', 'sms.idcomanda_ext', 'users.username as user_name')
						->leftJoin('users', 'comenzi_ext.userid', '=', 'users.Id')
                        ->leftJoin('clienti', 'comenzi_ext.idclient', '=', 'clienti.idclienti')
                        ->leftJoin('sms', 'comenzi_ext.idcomanda', '=', 'sms.idcomanda_ext')
                        ->where('comenzi_ext.idcomanda', $orderId)
                        ->useWritePdo() // Avoid caching here as well
                        ->first();

                    if (!$orderBase) {
                         Log::warning("Order data not found", ['order_id' => $orderId]);
                        continue;
                    }

                    // Get products with more complete fields - also use write PDO
                    $products = DB::table('detaliu_ext')
                        ->select('detaliu_ext.idprodus', 'detaliu_ext.cantitate', 'detaliu_ext.pret', 'detaliu_ext.furnizor', 'detaliu_ext.culoare',
                            'produse.denumire', 'produse.cod_produs')
                        ->leftJoin('produse', 'detaliu_ext.idprodus', '=', 'produse.idprodus')
                        ->where('detaliu_ext.idcomanda', $orderId)
                        ->useWritePdo() // Avoid caching
                        ->get();

                    if(!empty($orderBase->idlocalitate)) {
                        $localitate = DB::table('localitati')->where('idlocatie', $orderBase->idlocalitate)->value('localitate');
                    }
                    else {
                        $localitate = '';
                    }
                    
                    $orders[$orderId] = [
                        'order' => [
                            'idcomanda' => $orderBase->idcomanda,
                            'client_name' => $orderBase->client_name ?? 'Client necunoscut',
                            'companie' => $orderBase->companie ?? '',
                            'marca' => $orderBase->marca ?? '',
                            'telefon' => $orderBase->telefon,
                            'adresa' => $orderBase->adresa,
                            'data' => $orderBase->data,
                            'total' => $orderBase->total,
                            'swap_awb' => $orderBase->swap_awb,
                            'awb' => $orderBase->awb,
                            'cont_awb' => $orderBase->cont_awb,
                            'id_factura' => $orderBase->id_factura,
                            'stare' => $orderBase->stare,
                            'localitate' => $localitate,
							'observations' => $orderBase->observations,
                            'idcomanda_ext' => $orderBase->idcomanda_ext ?? 0,
                            'whatsapp_sent' => $orderBase->whatsapp_sent,
                            'whatsapp_sent_at' => $orderBase->whatsapp_sent_at,
                            'created_at' => Carbon::parse($orderBase->created_at)->addHours($orderCreatedAtOffsetHours)->toDateTimeString(),
                            'user_name' => $orderBase->user_name,
                        ],
                        'products' => $products
                    ];
                }
                catch (\Exception $e) {
                     Log::error("Error processing order {$orderId}", ['message' => $e->getMessage()]);
                    // Continue to next order instead of failing everything
                    continue;
                }
            }

            // Monthly total calculation - calculate directly from detaliu_ext
            try {
                //Total zi
                //$totalZi = DB::table('comenzi_ext')->whereBetween(DB::raw('DATE(comenzi_ext.data)'), [$fromDate, $toDate])->where('stare', '!=', 6)->sum('total');
				if (!empty($fromDate) && !empty($toDate)) {
					$starts = $ends = Carbon::today();
				} else {
					$starts = $fromDate ?? $toDate ?? Carbon::today();
					$ends = $toDate ?? $fromDate ?? Carbon::today();
				}
				$totalZi = DB::table('comenzi_ext')->whereBetween(DB::raw('DATE(comenzi_ext.data)'), [$starts, $ends])->where('stare', '!=', 6)->sum('total');

                //Total luna
				$now = Carbon::now();
				$dateForMonth = ($fromDate && $toDate) || (!$fromDate && !$toDate) ? $now : ($fromDate ?? $toDate);
                $totalLuna = DB::table('comenzi_ext')->whereMonth('data', $dateForMonth->month)->whereYear('data', $dateForMonth->year)->where('stare', '!=', 6)->sum('total');
			
				$filteredTotal = 0;
				$filteredTotal = collect($orders)
					->filter(fn($order) => !in_array($order['order']['stare'], [5, 8]))
					->sum(fn($order) => (float) $order['order']['total']);
					
				if (!$request->has('search_value') || empty($request->search_value)) {
					$filteredTotal = 0;
				}
			}
            catch (\Exception $e) {
                Log::error("Error calculating monthly total", ['message' => $e->getMessage()]);
                $totalLuna = 0;
                $totalZi = 0;
                $filteredTotal = 0;
            }

            // Return view with data including pagination
            return view('comenzi.partials.results', [
                'orders' => $orders, 
                'totalZi' => $totalZi, 
                'totalLuna' => $totalLuna, 
                'filteredTotal' => $filteredTotal, 
                'fromDate' => $fromDate ?? date('d/m/Y'), 
                'toDate' => $toDate ?? date('d/m/Y'),
                'stariLabels' => $this->getStatusLabels(), 
                'cacheBuster' => $cacheBuster,
                // Pagination data
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalRecords' => $totalRecords,
                'perPage' => $perPage,
                'hasNextPage' => $hasNextPage,
                'hasPrevPage' => $hasPrevPage
            ]);
        }
        catch (\Exception $e) {
            // Log the full error with additional details
            Log::error('getData Fatal Error', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()]);

            // Return error view or message
            return view('comenzi.partials.results', [
                'orders' => [], 
                'totalZi' => 0, 
                'totalLuna' => 0, 
                'filteredTotal' => 0, 
                'fromDate' => $fromDate ?? date('d/m/Y'), 
                'toDate' => $toDate ?? date('d/m/Y'), 
                'stariLabels' => $this->getStatusLabels(),
                'error' => 'A apărut o eroare la încărcarea datelor: ' . $e->getMessage(),
                // Default pagination data for error case
                'currentPage' => 1,
                'totalPages' => 1,
                'totalRecords' => 0,
                'perPage' => 50,
                'hasNextPage' => false,
                'hasPrevPage' => false
            ]);
        }
    }
	
	public function fetchCourierStatus(Request $request)
	{
		$validated = $request->validate([
			'orderId' => 'required|integer|exists:comenzi_ext,idcomanda',
		]);
		
		$orderBase = DB::table('comenzi_ext')
			->select('comenzi_ext.idcomanda', 'comenzi_ext.idclient', 'comenzi_ext.data', 'comenzi_ext.stare', 'comenzi_ext.awb', 'comenzi_ext.cont_awb', 'comenzi_ext.observations'
			, 'comenzi_ext.id_factura', 'comenzi_ext.total', 'comenzi_ext.whatsapp_sent', 'comenzi_ext.whatsapp_sent_at', 'comenzi_ext.courier_status', 'clienti.nume as client_name', 'clienti.companie', 'clienti.marca', 'clienti.telefon'
			, 'clienti.adresa','clienti.idlocalitate', 'sms.idcomanda_ext')
			->leftJoin('clienti', 'comenzi_ext.idclient', '=', 'clienti.idclienti')
			->leftJoin('sms', 'comenzi_ext.idcomanda', '=', 'sms.idcomanda_ext')
			->where('comenzi_ext.idcomanda', $request->orderId)
			->useWritePdo() // Avoid caching here as well
			->first();

		if ($orderBase) {
			$courier_status = "";
			if(!empty($orderBase->awb) && $orderBase->awb != "___"){
				$courier_status = $this->getAWBStatus($orderBase->awb);
			}
		
			return response()->json([
				'success' => true,
				'courier_status' => $courier_status ?? 'No status available',
			]);
		}
		
		return response()->json([
			'success' => false,
			'message' => 'Order not found',
		]);
	}

    /**
     * Process date change (previous/next day)
     */
    public function getDate(Request $request)
    {
        $direction = $request->get('direction');
        $currentDate = $request->get('current_date');
        
        $dateObj = \DateTime::createFromFormat('d/m/Y', $currentDate);
        if (!$dateObj) {
            $dateObj = new \DateTime();
        }
        
        $dateObj->modify($direction > 0 ? '+1 day' : '-1 day');
        
        return response()->json([
            'new_date' => $dateObj->format('d/m/Y')
        ]);
    }



    /**
     * Status labels method
     */
    private function getStatusLabels()
    {
        return [
            'COMANDAT' => ['text' => 'Comandat', 'class' => 'btn btn-primary'],
            'IN_LUCRU' => ['text' => 'În Lucru', 'class' => 'btn btn-warning'],
            'LIVRAT' => ['text' => 'Livrat', 'class' => 'btn btn-success'],
            'ANULAT' => ['text' => 'Anulat', 'class' => 'btn btn-danger']
        ];
    }
    
    /**
     * Show create order form
     */
    public function create(Request $request)
    {
        // Clear any temporary products for this session
        $session_id = session()->getId();
		$from = $request->query('from');
		
		if ($from !== 'supplier') {
			Tmp::where('session_id', $session_id)->delete();
		}
		
		$duplicate = $request->input('duplicate', null);
		$duplicateOrder = null; $duplicateOrderDetails = null;
		if(!empty($duplicate)){
			$duplicateOrder = DB::table('comenzi_ext')
			->join('clienti', 'comenzi_ext.idclient', '=', 'clienti.idclienti')
			->where('comenzi_ext.idcomanda', $duplicate)
			->select('comenzi_ext.idcomanda', 'comenzi_ext.data', 'comenzi_ext.idmasina', 'comenzi_ext.stare', 'comenzi_ext.total', 'comenzi_ext.observations', 'clienti.idclienti', 'clienti.nume'
			, 'clienti.marca', 'clienti.telefon', 'clienti.adresa', 'clienti.idmasina as clientidmasina')
			->first();
			
			$duplicateOrderDetails = DB::table('detaliu_ext')
				->join('produse', 'detaliu_ext.idprodus', '=', 'produse.idprodus')
				->where('detaliu_ext.idcomanda', $duplicate)
				->select('detaliu_ext.cantitate', 'detaliu_ext.pret', 'detaliu_ext.furnizor','detaliu_ext.culoare', 'produse.idprodus'
					, 'produse.denumire', 'produse.cod_produs', 'produse.TVA', 'produse.um')
				->get();
				
			foreach ($duplicateOrderDetails as $detail) {
				$furnizor = $detail->furnizor ?? '__';
				$culoare = $detail->culoare ?? null;
				
				$existingProduct = DB::table('tmp')
					->where('session_id', $session_id)
					->where('id_produs', $detail->idprodus)
					->first();
				if ($existingProduct) {
					$updated = DB::table('tmp')
						->where('session_id', $session_id)
						->where('id_produs', $detail->idprodus)
						->update([
							'cantitate_tmp' => $detail->cantitate,
							'pret_tmp' => $detail->pret,
							'furnizor' => $furnizor,
							'culoare' => $culoare,
						]);
				}else{
					$inserted = Tmp::create([
						'session_id'    => $session_id,
						'id_produs'     => $detail->idprodus,
						'cantitate_tmp' => $detail->cantitate,
						'pret_tmp'      => $detail->pret,
						'furnizor'      => $furnizor,
						'culoare' => $culoare,
					]);
				}
			}
		}
        
        $clients = Client::orderBy('nume')->get();
        $counties = Localitate::select('judet')->distinct()->orderBy('judet')->get();

        // Current date
        $currentDate = date('d/m/Y');
        
        return view('comenzi.create', compact('clients', 'counties', 'currentDate', 'duplicateOrder', 'duplicateOrderDetails'));
    }
    

    /**
     * Store a new order
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            Log::info('Starting order creation with data:', $request->all());
            
            // Get session ID to fetch temporary products
            $session_id = session()->getId();
            
            // Validate the request
            $validated = $request->validate([
                'id_client' => 'required|integer',
                'data' => 'required|string',
                'idstare' => 'required|integer',
            ]);

            // Convert date format to MySQL format (dd/mm/yyyy to yyyy-mm-dd)
            $dateObj = \DateTime::createFromFormat('d/m/Y', $request->data);
            $orderDate = $dateObj->format('Y-m-d');
            $originalDate = $dateObj->format('d/m/Y');
            if (!$dateObj) {
                return redirect()->back()->with('error', 'Format dată invalid!')->withInput();
            }

            // Get temporary products
            $tmp_items = Tmp::where('session_id', $session_id)->whereNotNull('id_produs')->get();
            // If no items, return error
            if ($tmp_items->isEmpty()) {
                return redirect()->back()->with('error', 'Nu există produse în comandă!')->withInput();
            }
            
            // Generate new order ID
            $lastOrder = DB::table('comenzi_ext')->orderBy('idcomanda', 'desc')->first();
            $newOrderId = $lastOrder ? $lastOrder->idcomanda + 1 : 1;
            
            Log::info('Generated new order ID:', ['idcomanda' => $newOrderId]);
            
            // Get client details
            $idmasina = !empty($request->idmasina_cmd) ? $request->idmasina_cmd : 0;
            
            // Calculate order total from all items
            $totalOrder = 0;
            
            // Process each product and insert into both tables
            foreach ($tmp_items as $item) {
                // Skip invalid products
                // if (empty($item->id_produs)) {
                //     Log::warning('Skipping item with null product ID');
                //     continue;
                // }
                
                // Calculate row total
                $rowTotal = $item->cantitate_tmp * $item->pret_tmp;
                
                // Add to order total
                $totalOrder += $rowTotal;
                
                // Get furnizor from tmp table if available
                $furnizor = $item->furnizor ?? '__';
                $culoare = $item->culoare ?? 'FFFFFF';

                // 2. Also insert into detaliu_ext table
                DB::table('detaliu_ext')->insert([
                    'idcomanda' => $newOrderId,
                    'idprodus' => $item->id_produs,
                    'cantitate' => $item->cantitate_tmp,
                    'pret' => $item->pret_tmp,
                    'culoare' => $culoare, // Default color
                    'furnizor' => $furnizor, // Using the furnizor from tmp
                    'created_at' => Carbon::now()->timestamp + (2 * 3600)
                ]);
                
                // Log::info('Added records to comenzi_ext and detaliu_ext:', [
                //     'idcmd' => $comenziExt->idcmd ?? 'unknown',
                //     'idcomanda' => $newOrderId,
                //     'idprodus' => $item->id_produs,
                //     'cantitate' => $item->cantitate_tmp,
                //     'total' => $rowTotal,
                //     'furnizor' => $furnizor
                // ]);
            }
            
            // 1. Insert to comenzi_ext table with same order total
            $comenziExt = new ComenziExt();
            $comenziExt->idcomanda = $newOrderId;
            $comenziExt->idclient = $request->id_client;
            $comenziExt->userid = Auth::user()->Id;
            $comenziExt->idprodus = $item->id_produs;
            $comenziExt->cantitate = $item->cantitate_tmp;
            $comenziExt->total = $totalOrder; // order total
            $comenziExt->idmasina = $idmasina;
            $comenziExt->stare = $request->idstare;
            $comenziExt->retur = 1; // Default value
            $comenziExt->data = $orderDate;
            $comenziExt->awb = '___'; // Default empty value for AWB
            $comenziExt->cont_awb = 'Utvin'; // Default value
            $comenziExt->created_at = Carbon::now()->timestamp + (2 * 3600);
			$comenziExt->observations = $request->observations;
            $comenziExt->save();

            // Log::info('Updated all entries with total order amount:', [
            //     'idcomanda' => $newOrderId,
            //     'total_order' => $totalOrder
            // ]);
            
            // Clean up temporary items IMMEDIATELY after processing
            DB::table('tmp')->where('session_id', $session_id)->delete();
            
            DB::commit();

            // Redirect with current date in session to ensure data is loaded properly
            return redirect()->route('comenzi.index', ['date' => $originalDate])
                ->with('success', 'Comanda a fost creată cu succes!')
                ->with('current_date', $originalDate);
        }
        catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating order: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            return redirect()->back()->with('error', 'Eroare: ' . $e->getMessage())->withInput();
        }
    }


    /**
     * Method to print an order
     */
    public function print($id)
    {
        // Get distinct order data (using first record)
        $comanda = ComenziExt::where('idcomanda', $id)
                    ->with('client')
                    ->first();
        
        if (!$comanda) {
            return redirect()->back()->with('error', 'Comanda nu a fost găsită!');
        }
        
        // Get order status name
        $stari = [
            1 => 'Comandat',
            2 => 'Sosit',
            3 => 'Expediat',
            4 => 'Achitat',
            5 => 'Avans',
            6 => 'Retur'
        ];
        $numeStare = $stari[$comanda->stare] ?? 'Necunoscut';
        
        // Get all products for this order from detaliu_ext instead of comenzi_ext
        $details = DB::table('detaliu_ext')
            ->where('detaliu_ext.idcomanda', $id)
            ->leftJoin('produse', 'detaliu_ext.idprodus', '=', 'produse.idprodus')
            ->select(
                'detaliu_ext.*',
                'produse.denumire as produs',
                'produse.cod_produs',
                'produse.TVA as tva_rate'
            )
            ->get()
            ->map(function ($detail) {
                // Ensure product name is set
                $detail->produs = $detail->produs ?? 'Produs necunoscut';
                
                // Set unit price and quantity fields for view
                $detail->pret_unitar = $detail->cantitate > 0 ? $detail->pret : 0;
                $detail->um = 'buc';
                
                return $detail;
            });
        
        // Calculate totals for checking
        $subtotal = $details->sum('total');
        
        // Log for debugging
        Log::info("Order {$id} details for printing:", [
            'details_count' => $details->count(),
            'subtotal' => $subtotal,
            'client' => $comanda->client ? $comanda->client->nume : 'No client'
        ]);
        
        return view('comenzi.print-invoice', compact('comanda', 'details', 'numeStare', 'subtotal'));
    }

    /**
     * Update status via AJAX
     */
    public function updateStatus(Request $request)
    {
        try {
            $order_id = $request->input('mod_id_cmd');
            $new_status = intval($request->input('stare'));
            
            // Update all entries for this order
            DB::table('comenzi_ext')
                ->where('idcomanda', $order_id)
                ->update(['stare' => $new_status]);
            
            // Also update culoare in detaliu_ext
            if ($new_status === 2 || $new_status === 3) {
                $xcul_cmd = "FFFFFF";

                DB::table('detaliu_ext')
                    ->where('idcomanda', $order_id)
                    ->update(['culoare' => $xcul_cmd]);
            }
			
			if($new_status === 3){
				$comanda = DB::table('comenzi_ext')->where('idcomanda', $order_id)->useWritePdo()->first();
				$date = Carbon::now()->format('d/m/Y');
				$dateObj = \DateTime::createFromFormat('d/m/Y', $date);
				$currentDate = $dateObj->format('d/m/Y');
				$dueDate = $dateObj->format('d/m/Y');
				
				
				
				
				
				
				$session_id = session()->getId();

				// Find order
				$order = DB::table('comenzi_ext')
					->join('clienti', 'comenzi_ext.idclient', '=', 'clienti.idclienti')
					->where('comenzi_ext.idcomanda', $order_id)
					->first();
					
				if (!$order) {
					return redirect()->back()->with('error', 'Order not found');
				}

				// Calculate total price from order details to validate before invoice generation
				$orderDetails = DB::table('detaliu_ext')
					->where('idcomanda', $order_id)
					->select('cantitate', 'pret')
					->get();
				
/* 				$totalOrderPrice = 0;
				foreach ($orderDetails as $detail) {
					$totalOrderPrice += $detail->cantitate * $detail->pret;
				} */
				// Check if total price is 0 or less - prevent invoice generation
				if ($order->total <= 0) {
					DB::commit();
					return response()->json([
						'success' => true,
						'message' => 'Status actualizat cu succes. Factura nu a fost generată deoarece totalul comenzii este 0 sau mai mic.'
					]);
				}

				// Get invoice number (either existing or create new)
				$invoiceNumber = $order->id_factura;
				
				if(!empty($invoiceNumber) && $invoiceNumber != 0){
					return response()->json([
						'success' => true,
						'message' => 'Status actualizat cu succes'
					]);
				}

				DB::beginTransaction();

				$invoiceTotal = 0;
				if ($invoiceNumber == 0) {
					$id_incasare = 3;
					$id_client = $comanda->idclient;
					$id_vanzator = 2;
					$datanoua = $currentDate;
					$data_noua = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $datanoua)));
					$datascadenta = $dueDate;
					$numar_chitanta = 0;

					//data_scadenta
					if ($id_incasare <= 2) {
						$data_scadenta = $data_noua;
					}
					else {
						$data_scadenta = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $datascadenta)));
					}

					//chitanta
					if ($id_incasare === 1 and  $numar_chitanta === 0) {
						$lastch = DB::table('facturi')->max('id_chitanta');
						$numar_chitanta = $lastch + 1;
					}

					//oferta
					if ($id_incasare === 7) {
						$lastof = DB::table('facturi')->max('id_oferta');
						$numar_oferta = $lastof + 1;
					}
					else {
						$numar_oferta = 0;
					}

					//proforma
					if ($id_incasare === 8) {
						$lastprof = DB::table('facturi')->max('id_proforma');
						$numar_proforma = $lastprof + 1;
					}
					else {
						$numar_proforma = 0;
					}

					//aviz
					if ($id_incasare === 9) {
						$lastaviz = DB::table('facturi')->max('id_aviz');
						$numar_aviz = $lastaviz + 1;
					}
					else {
						$numar_aviz = 0;
					}

					// Get new invoice number
					$lastInvoice = DB::table('facturi')->orderBy('OrderID', 'desc')->first();

					$invoiceNumber = $lastInvoice ? $lastInvoice->OrderID + 1 : 1;

					// Get next id_fact value
					$lastInvoiceId = DB::table('facturi')->orderBy('id_fact', 'desc')->value('id_fact');

					$invoiceId = $lastInvoiceId ? $lastInvoiceId + 1 : 1;

					// Prepare invoice data
					$currentDate = Carbon::now();

					$invoiceData = [
						'OrderID' => $invoiceNumber,
						'CustomerID' => $id_client,
						'EmployeeID' => $id_vanzator,
						'OrderDate' => $currentDate,
						'RequiredDate' => $data_scadenta,
						'seria' => 'BPA_C',
						'valid' => 1,
						'tip_incas' => $id_incasare,
						'id_chitanta' => $numar_chitanta,
						'id_comanda' => $order_id,
						'tip_comanda' => 0, // Default value
						'id_fact' => $invoiceId,
						'id_oferta' => $numar_oferta,
						'id_proforma' => $numar_proforma,
						'id_aviz' => $numar_aviz,
						'created_at' => Carbon::now()->timestamp + (2 * 3600)
					];

					// Save invoice header
					DB::table('facturi')->insert($invoiceData);

					// Update order with invoice number
					DB::table('comenzi_ext')->where('idcomanda', $order_id)->update(['id_factura' => $invoiceNumber]);

					$orderDetails = DB::table('detaliu_ext')
					->join('produse', 'detaliu_ext.idprodus', '=', 'produse.idprodus')
					->where('detaliu_ext.idcomanda', $order_id)
					->select('detaliu_ext.cantitate', 'detaliu_ext.pret', 'produse.idprodus', 'produse.denumire', 'produse.cod_produs', 'produse.TVA', 'produse.um')
					->useWritePdo()
					->get();
					// Add products to invoice

					foreach ($orderDetails as $detail) {
						$quantity = $detail->cantitate;
						$priceWithVat = $detail->pret;
						$vat = $detail->TVA ?? 21;

						// Calculate price without VAT
						$priceWithoutVat = $priceWithVat / (($vat + 100) / 100);
						$priceWithoutVat = round($priceWithoutVat, 2);

						// Calculate VAT amount
						$vatAmount = $priceWithoutVat * $quantity * $vat / 100;
						$vatAmount = round($vatAmount, 2);

						// Calculate total amount
						$totalAmount = $priceWithoutVat * $quantity + $vatAmount;
						$totalAmount = round($totalAmount, 2);

						$invoiceTotal += $totalAmount;
						
						$invoiceDetailData = [
							'OrderID' => $invoiceNumber,
							'ProductID' => $detail->idprodus,
							'UnitPrice' => $priceWithoutVat,
							'Quantity' => $quantity,
							'tva' => $vatAmount,
							'total' => $totalAmount
						];

						DB::table('facturidetails')->insert($invoiceDetailData);
					}

					// Delete tmp invoice products
					DB::table('tmp')->where('session_id', $session_id)->delete();
				}
				
				if ($invoiceTotal > 0) {
					DB::table('facturi')
						->where('OrderID', $invoiceNumber)
						->update([
							'generation_method' => 'smartbill',
							'payment_method' => $id_incasare,
							'smartbill_in_cash' => 'yes'
						]);
				}

				DB::commit();
				
		
				return response()->json([
					'success' => true,
					'invoice_url' => url('/print-invoice/' . $invoiceNumber),
					'message' => 'Status actualizat cu succes'
				]);
			}
            
            return response()->json([
                'success' => true,
                'message' => 'Status actualizat cu succes'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }

    
    /**
     * Update product color via AJAX
     */
    public function updateColor(Request $request)
    {
        try {
            $order_id = $request->input('mod_id_cmd_fur');
            $product_id = $request->input('mod_id_prod_fur');
            $new_color = $request->input('xcul');
            
            // Update the product color in the order
            DB::table('detaliu_ext')
                ->where('idcomanda', $order_id)
                ->where('idprodus', $product_id)
                ->update(['culoare' => $new_color]);
            
            return response()->json([
                'success' => true,
                'message' => 'Culoare actualizată cu succes'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order total via AJAX
     */
    public function updateTotal(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $order_id = $request->input('mod_id_cmd');
            $transport = floatval($request->input('mod_total_nou_cmd'));
            $total_cmd = floatval($request->input('mod_total_cmd'));
            
            // नया टोटल कैलकुलेट करें (अब positive और negative दोनों values के लिए)
            $newtotal = $total_cmd + $transport;
            
            // यहां से लाइन बदली गई है - transport की value चाहे कुछ भी हो, अपडेट करें
            // बस डायरेक्ट SQL अपडेट स्टेटमेंट चलाएं
            $result = DB::statement("UPDATE comenzi_ext SET total = $newtotal WHERE idcomanda = $order_id");
            
            Log::info('Direct SQL update for order total', [
                'order_id' => $order_id,
                'old_total' => $total_cmd,
                'transport' => $transport,
                'new_total' => $newtotal,
                'result' => $result
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Total actualizat cu succes'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating total', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }
    
 
/* 	public function createSamedayAwb(Request $request)
	{
		try {
			Log::info('Sameday AWB generation starting...', $request->except(['_token']));

			// Default values
			$request['packageNumber'] = 1;
			$request['packageWeight'] = $request->input('packageWeight', 1);
			$request['packageType'] = 1;
			$request['awbPayment'] = 50;

			$rambursValue = floatval($request->input('cashOnDelivery', 0));
			Log::info('Ramburs value:', ['value' => $rambursValue]);
			$request['cashOnDelivery'] = $rambursValue;

			$request['serviceOptions'] = null;
			$request['observation'] = $request->input('observation', 'Atentie-Fragil, Livrare urgenta');

			// Basic validation
			$validated = $request->validate([
				'serviceId' => 'required',
				'pickupPointId' => 'required',
				'recipientName' => 'required',
				'recipientAddress' => 'required',
				'recipientCity' => 'required',
				'recipientCounty' => 'required',
				'recipientPhone' => 'required',
				'plataexpeditie' => 'required|in:expeditor,destinatar'
			]);

			$phoneNumber = $validated['recipientPhone'];

			// -------------------------------
			// ** NEW: Handle payment type **
			// -------------------------------
			$paymentType = $validated['plataexpeditie'];
			$awbPaymentType = null;
			
			// Map dropdown values to Sameday API payment types
			if ($paymentType === 'expeditor') {
				// Sender pays - use CLIENT payment type
				$awbPaymentType = new \Sameday\Objects\Types\AwbPaymentType(\Sameday\Objects\Types\AwbPaymentType::CLIENT);
			} elseif ($paymentType === 'destinatar') {
				// Recipient pays - try using RECIPIENT payment type (if available)
				// Note: This might need to be enabled in the Sameday API
				$awbPaymentType = new \Sameday\Objects\Types\AwbPaymentType(2); // Try RECIPIENT = 2
			}
			
			Log::info('Payment type mapping', [
				'selectedPayment' => $paymentType,
				'paymentTypeValue' => $awbPaymentType ? $awbPaymentType->getType() : 'null'
			]);

			// -------------------------------
			// ** NEW: Handle optional services **
			// -------------------------------
			$extraServiceIds = [];
			$isSameDayService = false;

			// Special handling for known service IDs
			$knownNextDayServices = [57]; // PUDO Next Day
			$knownPudoServices = [57, 58]; // PUDO NextDay (57) and Crossborder PUDO NextDay (58)
			$knownLockerServices = [15]; // Locker NextDay service ID
			$knownSameDayServices = []; // Add same-day service IDs here if needed
			
			// Ensure service ID is an integer for comparison
			$serviceId = (int)$validated['serviceId'];
			
			Log::info('Service detection starting', [
				'requestedServiceId' => $validated['serviceId'],
				'convertedServiceId' => $serviceId,
				'requestedServiceIdType' => gettype($validated['serviceId']),
				'knownNextDayServices' => $knownNextDayServices,
				'knownPudoServices' => $knownPudoServices,
				'knownLockerServices' => $knownLockerServices,
				'knownSameDayServices' => $knownSameDayServices,
				'in_array_result' => in_array($serviceId, $knownNextDayServices),
				'is_pudo_service' => in_array($serviceId, $knownPudoServices),
				'is_locker_service' => in_array($serviceId, $knownLockerServices)
			]);
			
			if (in_array($serviceId, $knownNextDayServices)) {
				$isSameDayService = false;
				Log::info('Service ID detected as next-day service', [
					'serviceId' => $validated['serviceId'],
					'isSameDayService' => $isSameDayService
				]);
			} elseif (in_array($serviceId, $knownPudoServices)) {
				$isSameDayService = false; // PUDO services are next-day services
				Log::info('Service ID detected as PUDO service', [
					'serviceId' => $validated['serviceId'],
					'isSameDayService' => $isSameDayService
				]);
			} elseif (in_array($serviceId, $knownLockerServices)) {
				$isSameDayService = false; // Locker services are next-day services
				Log::info('Service ID detected as locker service', [
					'serviceId' => $validated['serviceId'],
					'isSameDayService' => $isSameDayService
				]);
			} elseif (in_array($serviceId, $knownSameDayServices)) {
				$isSameDayService = true;
				Log::info('Service ID detected as same-day service', [
					'serviceId' => $validated['serviceId'],
					'isSameDayService' => $isSameDayService
				]);
			} else {
				// Fallback to API-based detection
				$getServicesRequest = new \Sameday\Requests\SamedayGetServicesRequest($serviceId);
				$servicesResponse = $this->client->getServices($getServicesRequest);

				if (!empty($servicesResponse->getServices())) {
					foreach ($servicesResponse->getServices() as $service) {
						// Check if this is a same-day service by examining the service name and code
						$serviceName = strtolower($service->getName());
						$serviceCode = strtolower($service->getCode());
						
						Log::info('Service detection', [
							'serviceId' => $service->getId(),
							'serviceName' => $service->getName(),
							'serviceCode' => $service->getCode(),
							'lowercaseName' => $serviceName,
							'lowercaseCode' => $serviceCode
						]);
						
						// Check for PUDO indicators FIRST (these should never be same-day)
						if (strpos($serviceName, 'pudo') !== false || 
							strpos($serviceCode, 'pudo') !== false ||
							strpos($serviceCode, 'pp') !== false) {
							$isSameDayService = false; // PUDO services are next-day services
							Log::info('Detected PUDO service', ['serviceName' => $service->getName()]);
						}
						// Check for locker indicators (these should NOT have lockerLastMile)
						elseif (strpos($serviceName, 'locker') !== false || 
							strpos($serviceName, 'easybox') !== false ||
							strpos($serviceCode, 'locker') !== false ||
							strpos($serviceCode, 'lb') !== false) {
							$isSameDayService = false; // Explicitly set to false for locker services
							Log::info('Detected locker service', ['serviceName' => $service->getName()]);
						}
						// Check for next-day indicators (these should NOT have oohLastMile)
						elseif (strpos($serviceName, 'next') !== false || 
							strpos($serviceCode, 'nd') !== false ||
							strpos($serviceCode, 'next') !== false) {
							$isSameDayService = false; // Explicitly set to false for next-day services
							Log::info('Detected next-day service', ['serviceName' => $service->getName()]);
						}
						// Check for same-day indicators (only if not already identified as next-day)
						elseif (strpos($serviceName, 'same') !== false || 
							strpos($serviceName, 'zi') !== false ||
							strpos($serviceCode, 'same') !== false ||
							strpos($serviceCode, 'sd') !== false) {
							$isSameDayService = true;
							Log::info('Detected same-day service', ['serviceName' => $service->getName()]);
						}
					
						if (!empty($service->getOptionalTaxes())) {
							foreach ($service->getOptionalTaxes() as $extra) {
								$code = $extra->getCode(); // OPCG, SWAP, RDOC, PDO

								if ($request->has('deschidere_colet') && $code === 'OPCG') {
									$extraServiceIds[] = $extra->getId();
								}
								if ($request->has('colet_schimb') && $code === 'SWAP') {
									$extraServiceIds[] = $extra->getId();
								}
							}
						}
					}
				}
			}
			$extraServiceIds = array_values(array_unique($extraServiceIds));

			// Recipient object - add email field which is required
			$recipient = new \Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject(
				$request['recipientCity'],
				$request['recipientCounty'],
				$request['recipientAddress'],
				$request['recipientName'],
				$phoneNumber,
				'client@example.com', // Add required email field
				null
			);

			// Parcels
			$nr_colete = max(1, (int)$request->input('colet_awb_cmd', 1));
			$parcels = [];
			for ($i = 0; $i < $nr_colete; $i++) {
				$parcels[] = new \Sameday\Objects\ParcelDimensionsObject(
					(float)$request['packageWeight']
				);
			}

			// Final safety check: PUDO services should NEVER be same-day services
			if (in_array($serviceId, $knownPudoServices) && $isSameDayService) {
				Log::warning('PUDO service incorrectly detected as same-day, forcing to next-day', [
					'serviceId' => $serviceId,
					'wasSameDay' => $isSameDayService
				]);
				$isSameDayService = false;
			}
			
			// Additional safety check: Locker services should NEVER be same-day services
			if (in_array($serviceId, $knownLockerServices) && $isSameDayService) {
				Log::warning('Locker service incorrectly detected as same-day, forcing to next-day', [
					'serviceId' => $serviceId,
					'wasSameDay' => $isSameDayService
				]);
				$isSameDayService = false;
			}

			Log::info('Preparing Sameday AWB request', [
				'serviceId' => $validated['serviceId'],
				'convertedServiceId' => $serviceId,
				'pickupPointId' => $validated['pickupPointId'],
				'packageWeight' => $request['packageWeight'],
				'rambursValue' => $rambursValue,
				'isSameDayService' => $isSameDayService,
				'isPudoService' => in_array($serviceId, $knownPudoServices),
				'isLockerService' => in_array($serviceId, $knownLockerServices),
				'oohLastMileValue' => $isSameDayService ? 1 : (in_array($serviceId, $knownPudoServices) ? ($validated['pickupPointId'] > 500000 ? $validated['pickupPointId'] : 500001) : null),
				'constructorType' => $isSameDayService ? 'full' : 'minimal',
				'paymentType' => $paymentType,
				'awbPaymentType' => $awbPaymentType ? $awbPaymentType->getType() : 'null',
				'recipient' => [
					'name' => $validated['recipientName'],
					'phone' => $phoneNumber,
					'city' => $validated['recipientCity'],
					'address' => $validated['recipientAddress'],
					'county' => $validated['recipientCounty']
				],
				'extraServiceIds' => $extraServiceIds
			]);

			// AWB request - conditionally set oohLastMile only for same-day services
			// For next-day services, we need to avoid setting any OOH parameters
			
			// CRITICAL: Ensure Locker services are NEVER treated as same-day services
			if (in_array($serviceId, $knownLockerServices)) {
				$isSameDayService = false;
				Log::info('Forcing Locker service to next-day', [
					'serviceId' => $serviceId,
					'wasSameDay' => $isSameDayService
				]);
			}
			
			$oohType = null;
			$oohLastMile = null;
			if (in_array($serviceId, $knownLockerServices)) {
				$oohType = 0; // Locker
				$oohLastMile = $validated['pickupPointId']; // Ensure this is ≤ 500,000
			}

			if (in_array($serviceId, $knownPudoServices)) {
				$oohType = 1; // PUDO
				$oohLastMile = $validated['pickupPointId'] > 500000 ? $validated['pickupPointId'] : 500001; // default valid PUDO ID
			}
			
			if ($isSameDayService) {
				// Same-day service - include oohLastMile
				Log::info('Creating AWB for same-day service with oohLastMile=1');
				$awbRequest = new \Sameday\Requests\SamedayPostAwbRequest(
					$validated['pickupPointId'],
					null,
					new \Sameday\Objects\Types\PackageType(\Sameday\Objects\Types\PackageType::PARCEL),
					$parcels,
					$serviceId,
					$awbPaymentType, // Use dynamic payment type
					$recipient,
					0,
					$rambursValue,
					null,
					null,
					$extraServiceIds,
					null,
					null,
					$request['observation'],
					null, // priceObservation
					null, // clientObservation
					null, // lockerFirstMile
					null, // lockerLastMile - set to null to avoid validation error
					null, // oohFirstMile
					1, // oohLastMile - set to 1 for same-day services
					null  // currency
				);
			} else {
				// Next-day service (including PUDO) - handle different service types
				Log::info('Creating AWB for next-day service', [
					'serviceId' => $validated['serviceId'],
					'isSameDayService' => $isSameDayService
				]);
				
				// Check if this is a PUDO service that needs oohLastMile parameter
				if (in_array($serviceId, $knownPudoServices)) {
					// PUDO services need oohLastMile parameter with valid OOH location ID
					Log::info('Creating AWB for PUDO service with oohLastMile parameter', [
						'serviceId' => $serviceId,
						'pickupPointId' => $validated['pickupPointId'],
						'note' => 'Using OOH parameters for PUDO service'
					]);
					
					// For PUDO services, we need to use a valid OOH location ID
					// For now, we'll use the pickupPointId as the oohLastMile if it's > 500,000 (PUDO location)
					// Otherwise, we'll use a default PUDO location ID
					$oohLastMileId = $validated['pickupPointId'];
					
					// Check if pickupPointId is a PUDO location (ID > 500,000)
					if ($validated['pickupPointId'] <= 500000) {
						// This is not a PUDO location, we need to find a valid PUDO location
						// For now, use a default PUDO location ID (this should be replaced with actual OOH location selection)
						$oohLastMileId = 500001; // Default PUDO location ID
						Log::warning('Using default PUDO location ID', [
							'originalPickupPointId' => $validated['pickupPointId'],
							'usingOohLastMileId' => $oohLastMileId
						]);
					}
					
					$awbRequest = new \Sameday\Requests\SamedayPostAwbRequest(
						$validated['pickupPointId'],
						null,
						new \Sameday\Objects\Types\PackageType(\Sameday\Objects\Types\PackageType::PARCEL),
						$parcels,
						$serviceId,
						$awbPaymentType,
						$recipient,
						0,
						$rambursValue,
						null,
						null,
						[null], // serviceTaxIds
						null,
						null,
						$request['observation'],
						null, // priceObservation
						null, // clientObservation
						null, // lockerFirstMile
						null, // lockerLastMile
						null, // oohFirstMile
						$oohLastMileId, // oohLastMile - use valid OOH location ID for PUDO
						null  // currency
					);
				} else {
					// For other next-day services (like Locker), use basic constructor
					if (in_array($serviceId, $knownLockerServices)) {
						Log::info('Creating AWB for Locker service', [
							'serviceId' => $serviceId,
							'constructorType' => 'locker_basic'
						]);
					} else {
						Log::info('Creating AWB for other next-day service', [
							'serviceId' => $serviceId,
							'constructorType' => 'next_day_basic'
						]);
					}

					$awbRequest = new \Sameday\Requests\SamedayPostAwbRequest(
						$validated['pickupPointId'],   // pickupPointId
						null,                          // lockerLastMile
						new \Sameday\Objects\Types\PackageType(\Sameday\Objects\Types\PackageType::PARCEL),
						$parcels,
						15,                             // serviceId for PUDO
						$awbPaymentType,
						$recipient,
						0,
						$rambursValue,
						null,
						null,
						[],                             // ✅ empty array for serviceTaxIds
						null,                           // deliveryIntervalServiceType
						$request['observation'],
						null, // priceObservation
						null, // clientObservation
						null, // lockerFirstMile
						null, // lockerLastMile
						null, // oohFirstMile
						null  // currency
					);
				}
			}

			// Debug: Log the request details before sending
			$constructorType = 'unknown';
			if ($isSameDayService) {
				$constructorType = 'same_day_with_ooh';
			} elseif (in_array($serviceId, $knownPudoServices)) {
				$constructorType = 'pudo_with_ooh';
			} elseif (in_array($serviceId, $knownLockerServices)) {
				$constructorType = 'locker_basic';
			} else {
				$constructorType = 'next_day_basic';
			}
			
			Log::info('Sending AWB request to Sameday API', [
				'serviceId' => $validated['serviceId'],
				'isSameDayService' => $isSameDayService,
				'isPudoService' => in_array($serviceId, $knownPudoServices),
				'isLockerService' => in_array($serviceId, $knownLockerServices),
				'oohLastMileValue' => $isSameDayService ? 1 : (in_array($serviceId, $knownPudoServices) ? ($validated['pickupPointId'] > 500000 ? $validated['pickupPointId'] : 500001) : null),
				'requestObject' => get_class($awbRequest),
				'constructorUsed' => $constructorType
			]);

			// Send request to Sameday API
			$awbResponse = $this->client->postAwb($awbRequest);

			return response()->json([
				'success' => true,
				'awb_number' => $awbResponse->getAwbNumber()
			]);

		} catch (\Exception $e) {
			$errors = method_exists($e, 'getErrors') ? $e->getErrors() : $e->getMessage();
			Log::error('Sameday AWB creation error', [
				'errors' => $errors,
				'message' => $e->getMessage()
			]);

			return response()->json([
				'success' => false,
				'message' => 'Eroare la generarea AWB: ' . $e->getMessage(),
				'errors' => $errors
			], 500);
		}
	} */

	public function createSamedayAwb(Request $request)
	{
		try {
			Log::info('Sameday AWB generation starting...', $request->except(['_token']));

			// Default values
			$request['packageNumber'] = 1;
			$request['packageWeight'] = $request->input('packageWeight', 1);
			$request['packageType'] = 1;
			$request['awbPayment'] = 50;

			$rambursValue = floatval($request->input('cashOnDelivery', 0));
			Log::info('Ramburs value:', ['value' => $rambursValue]);
			$request['cashOnDelivery'] = $rambursValue;

			$request['serviceOptions'] = null;
			$request['observation'] = $request->input('observation', 'Atentie-Fragil, Livrare urgenta');

			// Basic validation
			$validated = $request->validate([
				'serviceId' => 'required',
				'pickupPointId' => 'required',
				'recipientName' => 'required',
				'recipientAddress' => 'required',
				'recipientCity' => 'required',
				'recipientCounty' => 'required',
				'recipientPhone' => 'required',
				'plataexpeditie' => 'required|in:expeditor,destinatar',
				'pickup_point_id' => 'required_if:serviceId,15,28,57'
			]);

			$phoneNumber = $validated['recipientPhone'];

			// -------------------------------
			// ** NEW: Handle payment type **
			// -------------------------------
			$paymentType = $validated['plataexpeditie'];
			$awbPaymentType = null;
			
			// Map dropdown values to Sameday API payment types
			if ($paymentType === 'expeditor') {
				// Sender pays - use CLIENT payment type
				$awbPaymentType = new \Sameday\Objects\Types\AwbPaymentType(\Sameday\Objects\Types\AwbPaymentType::CLIENT);
			} elseif ($paymentType === 'destinatar') {
				// Recipient pays - try using RECIPIENT payment type (if available)
				// Note: This might need to be enabled in the Sameday API
				$awbPaymentType = new \Sameday\Objects\Types\AwbPaymentType(2); // Try RECIPIENT = 2
			}
			
			Log::info('Payment type mapping', [
				'selectedPayment' => $paymentType,
				'paymentTypeValue' => $awbPaymentType ? $awbPaymentType->getType() : 'null'
			]);

			// -------------------------------
			// ** NEW: Handle optional services **
			// -------------------------------
			$extraServiceIds = [];
			$isSameDayService = false;

			$getServicesRequest = new \Sameday\Requests\SamedayGetServicesRequest($validated['serviceId']);
			$servicesResponse = $this->client->getServices($getServicesRequest);

			if (!empty($servicesResponse->getServices())) {
				foreach ($servicesResponse->getServices() as $service) {
					// Check if this is a same-day service by examining the service name and code
					$serviceName = strtolower($service->getName());
					$serviceCode = strtolower($service->getCode());
					
					Log::info('Service detection', [
						'serviceId' => $service->getId(),
						'serviceName' => $service->getName(),
						'serviceCode' => $service->getCode(),
						'lowercaseName' => $serviceName,
						'lowercaseCode' => $serviceCode
					]);
					
					// Check for same-day indicators
					if (strpos($serviceName, 'same') !== false || 
						strpos($serviceName, 'zi') !== false ||
						strpos($serviceCode, 'same') !== false ||
						strpos($serviceCode, 'sd') !== false) {
						$isSameDayService = true;
						Log::info('Detected same-day service', ['serviceName' => $service->getName()]);
					}
					
					// Check for next-day indicators (these should NOT have oohLastMile)
					if (strpos($serviceName, 'next') !== false || 
						strpos($serviceName, 'pudo') !== false ||
						strpos($serviceCode, 'nd') !== false ||
						strpos($serviceCode, 'next') !== false) {
						$isSameDayService = false; // Explicitly set to false for next-day services
						Log::info('Detected next-day service', ['serviceName' => $service->getName()]);
					}
					
					if (!empty($service->getOptionalTaxes())) {
						foreach ($service->getOptionalTaxes() as $extra) {
							$code = $extra->getCode(); // OPCG, SWAP, RDOC, PDO

							if ($request->has('opt1') && $code === 'OPCG') {
								//$extraServiceIds[] = $extra->getId();
							}
							if ($request->has('opt2') && $code === 'SWAP') {
								//$extraServiceIds[] = $extra->getId();
							}
						}
					}
				}
			}
			
			if($request->has('opt1')){
				$extraServiceIds[] = "OPCG";
			}
			if($request->has('opt2')){
				$extraServiceIds[] = "SWAP";
			}
			$extraServiceIds = array_values(array_unique($extraServiceIds));

			// Recipient object - add email field which is required
			$recipient = new \Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject(
				$request['recipientCity'],
				$request['recipientCounty'],
				$request['recipientAddress'],
				$request['recipientName'],
				$phoneNumber,
				'client@example.com', // Add required email field
				null
			);

			// Parcels
			$nr_colete = max(1, (int)$request->input('colet_awb_cmd', 1));
			$parcels = [];
			for ($i = 0; $i < $nr_colete; $i++) {
				$parcels[] = new \Sameday\Objects\ParcelDimensionsObject(
					(float)$request['packageWeight']
				);
			}

			Log::info('Preparing Sameday AWB request', [
				'serviceId' => $validated['serviceId'],
				'pickupPointId' => $validated['pickupPointId'],
				'packageWeight' => $request['packageWeight'],
				'rambursValue' => $rambursValue,
				'isSameDayService' => $isSameDayService,
				'oohLastMileValue' => $isSameDayService ? 1 : 0,
				'constructorType' => $isSameDayService ? 'full' : 'alternative_service',
				'paymentType' => $paymentType,
				'awbPaymentType' => $awbPaymentType ? $awbPaymentType->getType() : 'null',
				'recipient' => [
					'name' => $validated['recipientName'],
					'phone' => $phoneNumber,
					'city' => $validated['recipientCity'],
					'address' => $validated['recipientAddress'],
					'county' => $validated['recipientCounty']
				],
				'extraServiceIds' => $extraServiceIds
			]);

			$oohList = $this->getOohLocations();
			//$selectedLockerId = array_key_first($oohList);
			$selectedLockerId = $validated['pickup_point_id'] ?? null;
		
			//$oohLastMile = $validated['serviceId'] == 15 ?  $selectedLockerId : $selectedLockerId;
			$oohLastMile = $selectedLockerId;

			$awbRequest = new \Sameday\Requests\SamedayPostAwbRequest(
				$validated['pickupPointId'],
				null,
				new \Sameday\Objects\Types\PackageType(\Sameday\Objects\Types\PackageType::PARCEL),
				$parcels,
				$validated['serviceId'],
				$awbPaymentType, // Use dynamic payment type
				$recipient,
				0,
				$rambursValue,
				null,
				null,
				$extraServiceIds,
				null,
				null,
				$request['observation'],
				null, // priceObservation
				null, // clientObservation
				null, // lockerFirstMile
				null, // lockerLastMile - set to null to avoid validation error
				null, // oohFirstMile
				$oohLastMile, // oohLastMile - set to 1 for same-day services
				null  // currency
			);

			// Send request to Sameday API
			$awbResponse = $this->client->postAwb($awbRequest);
			
			$returnAwb = null;
			$raw = $awbResponse->getRawResponse();
			$body = json_decode($raw->getBody(), true);
			$returnAwbs = $body['returnAwbs'] ?? [];
			if (!empty($returnAwbs) && isset($returnAwbs[0])) {
				if($request->has('opt2')){
					$returnAwb = $returnAwbs[0]['awbNumber'];
				}
			}

			return response()->json([
				'success' => true,
				'awb_number' => $awbResponse->getAwbNumber(),
				'swap_awb' => $returnAwb ?? ''
			]);
		} catch (\Exception $e) {
			$errors = method_exists($e, 'getErrors') ? $e->getErrors() : $e->getMessage();
			Log::error('Sameday AWB creation error', [
				'errors' => $errors,
				'message' => $e->getMessage()
			]);

			return response()->json([
				'success' => false,
				'message' => 'Eroare la generarea AWB: ' . $e->getMessage(),
				'errors' => $errors
			], 500);
		}
	}

    public function createFanCourierAwb(Request $request)
    {
        try {
        
            Log::info('FanCourier AWB Request Received', [
                'data' => array_merge(
                    $request->except(['_token']),
                    ['sensitive_data_masked' => true]
                ),
            ]);
                        
            $validated = $request->validate([
                'tipserviciu' => 'sometimes|string|max:50',
                'contfan' => 'sometimes|string|max:50',
                'tel_awb_cmd' => 'required|string|max:20',
                'judet_awb_cmd' => 'required|string|max:50',
                'local_awb_cmd' => 'required|string|max:100',
                'adresa_awb_cmd' => 'required|string|max:255',
                'mod_id_awb' => 'sometimes|numeric',
                'ramburs_awb_cmd' => 'sometimes|numeric|min:0',
                'greutate_awb_cmd' => 'sometimes|numeric|min:0.2|max:100'
            ]);
            
            // अकाउंट टाइप (डिफ़ॉल्ट के साथ सुरक्षित इनपुट)
            $accountType = $request->input('contfan', 'Utvin');
            
            // संपर्क व्यक्ति और गंतव्य नाम (अधिक सुरक्षित हैंडलिंग)
            $destinationName = trim($request->input('nume_awb_cmd', 'Destinatar'));
            $contactPerson = trim($request->input('nume_awb_cmd', $destinationName));
            
            // अवलोकन/टिप्पणियां (अधिक सुव्यवस्थित)
            $observatii = collect([
                $request->input('obs1', ''),
                $request->input('obs2', '')
            ])->filter()->implode(' ');
            
            // सर्विस टाइप (डिफ़ॉल्ट के साथ)
            $serviceType = $request->input('tipserviciu', 'Standard');
            
            // COD handling for Cont Colector services
            $rambursValue = (float)$request->input('ramburs_awb_cmd', 0);
            $serviceKey = strtolower($serviceType);
            if (in_array($serviceKey, ['cont colector', 'collectpoint cont colector', 'fanbox cont colector'])) {
                // Cont Colector services REQUIRE COD - ensure minimum value of 1
                $rambursValue = max(1, $rambursValue);
            } else {
                $rambursValue = max(0, $rambursValue);
            }
            
            // AWB डेटा (अधिक विस्तृत और सुरक्षित)
            $awbData = [
                'fisier' => [
                    [
                        'tip_serviciu' => $serviceType,
                        'nr_plicuri' => max(0, (int)$request->input('plic_awb_cmd', 0)),
                        'nr_colete' => max(1, (int)$request->input('colet_awb_cmd', 1)),
                        'greutate' => max(0.1, (float)$request->input('greutate_awb_cmd', 1)),
                        'plata_expeditie' => $request->input('plataexpeditie', 'expeditor'),
                        'ramburs_bani' => $rambursValue,
                        'plata_ramburs_la' => 'expeditor',
                        'valoare_declarata' => 0,
                        'persoana_contact_expeditor' => 'Besoiu Piese Auto',
                        'observatii' => $observatii ?: 'Piese auto',
                        'continut' => 'Piese auto',
                        'nume_destinar' => $destinationName,
                        'persoana_contact' => $contactPerson,
                        'telefon' => $validated['tel_awb_cmd'],
                        'judet' => $validated['judet_awb_cmd'],
                        'localitate' => $validated['local_awb_cmd'],
                        'strada' => $validated['adresa_awb_cmd'],
                    ]
                ]
            ];
            
            
            $options = [];
            if ($request->has('opt1')) $options[] = 'A';  // Deschidere la livrare
            if ($request->has('opt2')) $options[] = 'S';  // Livrare sambata
            
            
            if ($request->has('opt3')) {
                $options[] = 'D';  // Livrare din sediul FAN Courier
                
                if ($request->filled('agentie_awb_cmd')) {
                    $awbData['fisier'][0]['localitate'] = $request->input('agentie_awb_cmd');
                    $awbData['fisier'][0]['strada'] = 'Sediul FAN ' . $request->input('agentie_awb_cmd');
                }
            }
            
            
            if (!empty($options)) {
                $awbData['fisier'][0]['optiuni'] = implode(',', $options);
            }
            
            
            if ($request->filled('restit_awb_cmd')) {
                $awbData['fisier'][0]['restituire'] = $request->input('restit_awb_cmd');
            }
            
            
            Log::info('FanCourier AWB Request Data', [
                'awb_data' => array_merge(
                    $awbData,
                    ['sensitive_details_masked' => true]
                )
            ]);
            
            // FanCourier API Call
            $result = FanCourier::generateAwb($awbData);
            
            Log::info('FanCourier AWB Result Raw', [
                'type' => gettype($result),
                'has_data' => !empty($result)
            ]);
            
            
            $awbNumber = $this->extractAwbNumber($result);
            
            
            if (!empty($awbNumber)) {
            
                if ($request->filled('mod_id_awb')) {
                    try {
                        DB::table('comenzi_ext')
                            ->where('idcomanda', $request->input('mod_id_awb'))
                            ->update([
                                'awb' => $awbNumber,
                                'cont_awb' => $accountType,
                                'updated_at' => now()
                            ]);
                    } catch (\Exception $dbError) {
                    
                        Log::error('FanCourier AWB Database Update Failed', [
                            'error' => $dbError->getMessage(),
                            'order_id' => $request->input('mod_id_awb')
                        ]);
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'awb_number' => $awbNumber,
                    'message' => 'AWB generat cu succes'
                ]);
            } else {
                
                $errorMessage = 'Eroare la generarea AWB: Format de răspuns neașteptat';
                Log::error('FanCourier returned unexpected format', [
                    'response' => is_array($result) ? 'Array Response' : $result,
                    'response_type' => gettype($result)
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'raw_response' => 'Response format could not be processed'
                ], 500);
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            
            Log::error('FanCourier AWB Validation Failed', [
                'errors' => $e->errors(),
                'failed_fields' => array_keys($e->errors())
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            
            Log::error('FanCourier AWB Generation Complete Failure', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'error_class' => get_class($e)
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare critică la generarea AWB: ' . $e->getMessage()
            ], 500);
        }
    }


    private function extractAwbNumber($result)
    {
        $awbNumber = null;
        
        
        if (is_array($result) && !empty($result)) {
            $firstResult = reset($result);
            
            
            if (is_object($firstResult)) {
                
                $awbNumber =
                    $firstResult->awb ??
                    $firstResult->{'stdClass'}->awb ??
                    null;
            }
            
            elseif (is_array($firstResult)) {
                $awbNumber =
                    $firstResult['awb'] ??
                    $firstResult['stdClass']['awb'] ??
                    null;
            }
        }
        elseif (is_string($result)) {
            
            $patterns = [
                '/(\d+,\d+,\d+)/',
                '/(\d{6,15})/',
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $result, $matches)) {
                    if ($pattern === '/(\d+,\d+,\d+)/') {
                        $parts = explode(',', $matches[1]);
                        $awbNumber = $parts[2] ?? null;
                    } else {
                        $awbNumber = $matches[1] ?? null;
                    }
                    
                    if ($awbNumber) break;
                }
            }
        }
        
        return $awbNumber;
    }



    /**
     * Update AWB number via AJAX
     */
    public function updateAwb(Request $request)
    {
        try {
            $orderId = $request->input('mod_id_awb');
            $awbNumber = $request->input('mod_awb_cmd');
            $swapAwbNumber = $request->input('swapAwbNumber') ?? '___';
            $awbAccount = $request->input('cont_awb', 'Utvin');
            
            Log::info('Updating AWB', [
                'order_id' => $orderId,
                'awb' => $awbNumber,
                'account' => $awbAccount,
                'request_data' => $request->all()
            ]);
            
            $update = [
                'awb' => $awbNumber,
                'cont_awb' => $awbAccount,
                'swap_awb' => $swapAwbNumber
            ];
            
            DB::table('comenzi_ext')
                ->where('idcomanda', $orderId)
                ->update($update);
            
            Log::info('AWB updated successfully', [
                'order_id' => $orderId,
                'awb' => $awbNumber,
                'account' => $awbAccount
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'AWB is Update!'
            ]);
        }
        catch (\Exception $e) {
            Log::error('Error updating AWB', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
        

    public function getClientForAwb($id)
    {
        try {
            // Get order data
            $order = DB::table('comenzi_ext')
                ->where('idcomanda', $id)
                ->first();
                
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }
            
            // Log complete client record without join
            $basicClient = DB::table('clienti')
                ->where('idclienti', $order->idclient)
                ->first();
            Log::info('Basic client data (no join):', (array)$basicClient);
            
            // Check if idlocalitate exists and has a value
            if (!property_exists($basicClient, 'idlocalitate') || is_null($basicClient->idlocalitate)) {
                Log::warning('idlocalitate field missing or null in clienti table');
                // Alternative approach: Parse address field
                //$address = !empty($basicClient->adresa_facturare) ? $basicClient->adresa_facturare : $basicClient->adresa ?? '';
				$address = !empty($basicClient->adresa_facturare) ? $basicClient->adresa_facturare : (!empty($basicClient->adresa) ? $basicClient->adresa : '');
                $county = '';
                $city = '';
                
                // Try to extract county from address
                if (preg_match('/JUD\.\s+(\w+)/i', $address, $countyMatches)) {
                    $county = $countyMatches[1];
                }
                
                // Try to extract city/locality from address
                if (preg_match('/SAT\s+(\w+)/i', $address, $cityMatches)) {
                    $city = $cityMatches[1];
                } elseif (preg_match('/COM\.\s+(\w+)/i', $address, $cityMatches)) {
                    $city = $cityMatches[1];
                }
                
                // Ensure contact person and company are set consistently
                $contactPerson = $basicClient->nume ?? '';
                $company = $basicClient->companie ?? '';
                
                // If one is empty, use the other
                if (empty($contactPerson) && !empty($company)) {
                    $contactPerson = $company;
                } elseif (empty($company) && !empty($contactPerson)) {
                    $company = $contactPerson;
                }
                
                // Client data with parsed address
                $clientData = [
                    'name' => $basicClient->nume ?? '',
                    'company' => $company,
                    'contact_person' => $contactPerson,
                    'county' => $county,
                    'city' => $city,
                    'address' => $basicClient->adresa ?? '',
                    'phone' => $basicClient->telefon ?? '',
                    'fragile' => true,
                    'urgent' => true,
                    'km_loc' => 0,
                    'agentie_loc' => ''
                ];
            } else {
				//print_R($clientData);die('adf');
                $km_loc = 0;
                $agentie_loc = '';

                // Get client data with locality join
                $client = DB::table('clienti')
                    ->leftJoin('localitati', 'clienti.idlocalitate', '=', 'localitati.idlocatie')
                    ->leftJoin('localitati as l2', 'clienti.localitate_facturare', '=', 'l2.idlocatie')
                    ->select('clienti.*', 'localitati.judet', 'localitati.localitate', 'l2.judet as l_judet', 'l2.localitate as l_localitate')
                    ->where('clienti.idclienti', $order->idclient)
                    ->first();

                if (!is_null($client->judet) && !is_null($client->localitate)) {
                    // Get client data with locality join
                    $agentiifan = DB::table('agentiifan')
                        ->select('agentiifan.km', 'agentiifan.agentie')
                        ->where('judet', '=', $client->judet)
                        ->where('localitate', '=', $client->localitate)
                        ->first();

                    if ($agentiifan !== null) {
                        $km_loc = $agentiifan->km ?? 0;
                        $agentie_loc = $agentiifan->agentie ?? '';
                    }
                }

                Log::info('Client data with join:', (array)$client);
                
                // Ensure contact person and company are set consistently
                $contactPerson = $client->nume ?? '';
                $company = $client->companie ?? '';
                
                // If one is empty, use the other
                if (empty($contactPerson) && !empty($company)) {
                    $contactPerson = $company;
                } elseif (empty($company) && !empty($contactPerson)) {
                    $company = $contactPerson;
                }
				//print_R($client);die('adf');
                
                // Client data with join
                $clientData = [
                    'name' => $client->nume ?? '',
                    'company' => $company,
                    'contact_person' => $contactPerson,
                    'county' => !empty($client->l_judet) ? $client->l_judet : (!empty($client->judet) ? $client->judet : ''),
                    'city' => !empty($client->l_localitate) ? $client->l_localitate : (!empty($client->localitate) ? $client->localitate : ''),
                    'address' => !empty($client->adresa_facturare) ? $client->adresa_facturare : (!empty($client->adresa) ? $client->adresa : ''),
                    'phone' => $client->telefon ?? '',
                    'fragile' => true,
                    'urgent' => true,
                    'km_loc' => $km_loc,
                    'agentie_loc' => $agentie_loc
                ];
				//print_R([$client->judet_facturare,$client->judet]);die('adf');
            }
            
            // Add order data
            $orderData = [
                'awb' => $order->awb ?? '',
                'courier_type' => $order->cont_awb ?? 'Utvin'
            ];
            
            return response()->json([
                'success' => true,
                'client_data' => $clientData,
                'order' => $orderData
            ]);
        }
        catch (\Exception $e) {
            Log::error('Error getting client data for AWB: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get client data for SMS modal
     */
    public function getClientForSms($OrderID)
    {
        try {
            // Get client data with locality join
            $order = DB::table('comenzi_ext')
            ->join('clienti', 'comenzi_ext.idclient', '=', 'clienti.idclienti')
            ->select('clienti.nume', 'clienti.telefon', 'comenzi_ext.awb', 'comenzi_ext.stare', 'comenzi_ext.cont_awb', 'comenzi_ext.total')
            //->where('clienti.idclienti', $OrderID)
            ->where('comenzi_ext.idcomanda', $OrderID)
            ->first();
            
            if ($order) {
                // Generate SMS message using template
                $total = number_format($order->total, 2);
                $awb = $order->awb ?? '';
                
                // Determine template based on courier
                $templateCode = ($order->cont_awb == 'same') 
                    ? 'external_order_sameday' 
                    : 'external_order_fancourier';
                
                $templateBody = MessageTemplate::getTemplate($templateCode, 'sms');
                
                // Replace template variables
                $defaultMessage = strtr($templateBody, [
                    '{{total}}' => $total,
                    '{{awb}}'   => $awb,
                ]);
                
                return response()->json([
                    'success' => true,
                    'client_name' => $order->nume,
                    'client_phone' => $order->telefon,
                    'awb' => $awb,
                    'total' => $total,
                    'stare' => $order->stare,
                    'cont_awb' => $order->cont_awb,
                    'default_message' => $defaultMessage
                ]);
            }
            else {
                return response()->json(['success' => false,'message' => 'Order not found']);
            }
        }
        catch (\Exception $e) {
            // Log::error('Error getting client data for SMS: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
    
    

    /**
     * Send SMS via AJAX
     */

    public function sendSms(Request $request)
    {
        try {
            // Use MSGHub API (which was successful in your logs)
			$apiEndpoint = $this->smsApiUrl;
			$authorization = "Bearer ".$this->smsApiKey;

            // Get form data
            $orderId = $request->input('mod_id_sms');
            $phoneNumber = $request->input('mod_tel_sms');
            $message = $request->input('mod_mesaj');

            // Format phone number (remove any non-numeric characters)
            $phoneNumber = preg_replace('/\s+/', '', $phoneNumber);
            $phoneNumber = str_replace(['+', '-', '(', ')', '.'], '', $phoneNumber);
			if (substr($phoneNumber, 0, 1) !== '4') {
				$phoneNumber = '+4' . $phoneNumber;
			} else {
				$phoneNumber = '+' . $phoneNumber;
			}

            // Log SMS attempt
            // Log::info('Sending SMS', [
            //     'order_id' => $orderId,
            //     'phone' => $phoneNumber,
            //     'message' => $message,
            //     'awb' => $awb,
            // ]);
            
            // Validate phone number
            if (empty($phoneNumber) || strlen($phoneNumber) < 10) {
                return response()->json(['success' => false,'message' => 'Număr de telefon invalid'], 400);
            }
            // Prepare API request data
			$data = [
				"from"    => "3737",
				"to"      => $phoneNumber,
				"message" => $message,
			];
            
            // Convert to JSON
            $dataJson = json_encode($data);
            
            // Create HMAC signature
            $headers = [
				'Content-Type: application/json',
				'Authorization: ' . $authorization,
			];
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
            
            // Execute request
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Check for cURL errors
			if (curl_errno($ch)) {
				$error = curl_error($ch);
				curl_close($ch);
				return response()->json(['success' => false, 'message' => 'cURL error: ' . $error], 500);
			}
			curl_close($ch);
			
			$response = trim($response);
			
            if (str_starts_with($response, "OK:")) {
				$parts = explode(":", $response); // [ "OK", "message_id", "cost" ]
				$messageId = $parts[1] ?? null;
				$cost      = $parts[2] ?? 0;

				$status = "Trimis";
				$id_sms = $messageId;
				// Save SMS record to database
				DB::table('sms')->insert(['status' => $status, 'idcomanda' => 0, 'idcomanda_ext' => $orderId, 'idprimit' => $id_sms, 'cost' => $cost, 'data_exp' => now()]);
			
				return response()->json(['success' => true,'message' => 'SMS trimis cu succes']);
            } else {
				// Extract error code
				$errorCode = str_replace("ERROR:", "", $response);
				return response()->json([
					'success' => false,
					'message' => "Eroare la trimiterea SMS-ului (cod $errorCode)",
				], 500);
			}
        }
        catch (\Exception $e) {
            Log::error('Error sending SMS: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['success' => false,'message' => 'Eroare: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Send SMS via MSGHub API
     */
    public function sendSmsMsghub(Request $request)
    {
        try {
            // Get form data
            $orderId = $request->input('mod_id_sms');
            $phoneNumber = $request->input('mod_tel_sms');
            $message = $request->input('mod_mesaj');
            
            // Use your actual MSGHub credentials from your PHP code
            $api_endpoint = "https://api.msghub.cloud/send";  // From your defined constants
            $api_key = '$2y$10$3kZirQDAi61bKfGoK00GaOeEoo/FgFCg24v.XKkRveAVHWG9EBPkW';  // From your defined constants
            $api_secret = '$2y$10$a2Aok0g8Nu/VmkbphNkFleYtl/pd3pontI1dd98.UQ.7LYmOrNi2e'; // From your defined constants
            
            // Format phone number - for MSGHub, do NOT add country code if already present
            $phoneNumber = preg_replace('/\s+/', '', $phoneNumber);
            $phoneNumber = str_replace(['+', '-', '(', ')', '.'], '', $phoneNumber);
            
            // Prepare data for API request
            $data = [
                'msisdn'      => $phoneNumber,
                'sc'          => '3737',
                'text'        => $message,
                'service_id'  => '2219',
            ];
            
            // Convert data to JSON
            $data_json = json_encode($data);
            
            // Create signature
            $signature = hash_hmac('sha512', $data_json, $api_secret);
            
            // Set headers
            $headers = [
                "Content-Type: application/json",
                "x-api-key: {$api_key}",
                "x-api-sign: {$signature}",
                "Expect: ",
            ];
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_endpoint);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
            
            // Log request data
            Log::info('MSGHub API request', [
                'endpoint' => $api_endpoint,
                'phone' => $phoneNumber,
                'message' => $message
            ]);
            
            // Execute cURL request
            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Check for cURL errors
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                Log::error('cURL error in MSGHub API', ['error' => $error]);
            }
            
            // Close cURL
            curl_close($ch);
            
            // Parse JSON response
            $result = json_decode($response, true);
            
            // Log response
            Log::info('MSGHub API response', [
                'status_code' => $status_code,
                'response' => $result
            ]);
            
            // Check for success - MSGHub returns a different structure
            $success = false;
            if ($status_code == 200 && isset($result['meta']) && $result['meta']['code'] == 200) {
                $success = true;
                $msgId = $result['data']['msg_id'] ?? 'unknown';
                Log::info('MSGHub SMS sent successfully', ['msg_id' => $msgId]);
            }
            
            // Save SMS record
            DB::table('sms')->insert([
                'idcomanda' => $orderId,
                'telefon' => $phoneNumber,
                'mesaj' => $message,
                'status' => $success ? 1 : 0,  // Set correct status based on API response
                'cost' => 0, // MSGHub might not provide cost info
                'data' => now(),
                'data_exp' => now(),
                'idprimit' => 0,
                'idcomanda_ext' => $orderId
            ]);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'SMS trimis cu succes (MSGHub)',
                    'details' => [
                        'id' => $result['data']['msg_id'] ?? null,
                        'sms_id' => $result['data']['sms_id'] ?? null
                    ]
                ]);
            } else {
                // Both services failed
                $errorMessage = isset($result['meta']['text']) ? $result['meta']['text'] : 'Unknown error';
                if (isset($result['errors']) && !empty($result['errors'])) {
                    $errorMessage = implode(', ', $result['errors']);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Eroare la trimiterea SMS-ului: ' . $errorMessage
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Exception in sendSmsMsghub', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check SMS status for an order
     */
    public function checkSmsStatus($id)
    {
        try {
            $smsList = DB::table('sms')
                ->where('idcomanda', $id)
                ->orderBy('data', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'sms_count' => $smsList->count(),
                'sms_list' => $smsList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update supplier via AJAX
     */
    public function updateSupplier(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $order_id = $request->input('mod_id_cmd_fur');
            $product_id = $request->input('mod_id_prod_fur');
            $supplier = $request->input('xfur');
            
            // Log::info('Updating supplier:', [
            //     'order_id' => $order_id,
            //     'product_id' => $product_id,
            //     'supplier' => $supplier
            // ]);
            
            // 2. Update in detaliu_ext table
            DB::table('detaliu_ext')
                ->where('idcomanda', $order_id)
                ->where('idprodus', $product_id)
                ->update(['furnizor' => $supplier]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Furnizor actualizat cu succes'
            ]);
        }
        catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating supplier: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete order via AJAX
     */
    public function deleteOrder($id)
    {
        try {
            DB::beginTransaction();
            
            // First delete all entries from detaliu_ext
            DB::table('detaliu_ext')
                ->where('idcomanda', $id)
                ->delete();
                
            // Then delete all entries from comenzi_ext
            DB::table('comenzi_ext')
                ->where('idcomanda', $id)
                ->delete();
				
			DB::table('incasari')->where('idcmd', $id)->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Comanda ștearsă cu succes'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting order: ' . $e->getMessage(), [
                'order_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Show the edit order form
     */
    public function edit($id)
    {
        // Get order data
        $comanda = ComenziExt::where('idcomanda', $id)->first();

        if (!$comanda) {
            return redirect()->route('comenzi.index')->with('error', 'Comanda nu a fost găsită!');
        }

        //get session id
        $session_id = session()->getId();
        
        // delete tmp invoice products to prevent loading dummy data
        DB::table('tmp')->where('session_id', $session_id)->delete();

        // Get products from detaliu_ext table instead of comenzi_ext
        $products = DB::table('detaliu_ext')
            ->select('detaliu_ext.idprodus', 'detaliu_ext.cantitate', 'detaliu_ext.pret', 'detaliu_ext.furnizor',
                'detaliu_ext.culoare', 'produse.denumire', 'produse.cod_produs')
            ->leftJoin('produse', 'detaliu_ext.idprodus', '=', 'produse.idprodus')
            ->where('detaliu_ext.idcomanda', $id)
            ->get();

        // Add products to temporary detaliu table
        foreach ($products as $detail) {
            $invoiceDetailData = [
                'id_produs' => $detail->idprodus,
                'cantitate_tmp' => number_format($detail->cantitate, 2),
                'pret_tmp' => $detail->pret,
                'culoare' => $detail->culoare,
                'furnizor' => $detail->furnizor,
                'session_id' => $session_id,
            ];

            DB::table('tmp')->insert($invoiceDetailData);
        }

        // Get client data
        $client = Client::find($comanda->idclient);
        
        // Get counties for client form
        $counties = Localitate::select('judet')->distinct()->orderBy('judet')->get();
        
        // Add current date in d/m/Y format
        $currentDate = date('d/m/Y');
		
		$hasRelations =
			DB::table('facturi')->where('id_comanda', $id)->exists() ||
			DB::table('facturidetails')->where('OrderID', $id)->exists() ||
			DB::table('sms')
				->where(function ($q) use ($id) {
					$q->where('idcomanda', $id);
				})
				->exists();
        
        return view('comenzi.edit', compact('comanda', 'client', 'counties', 'currentDate', 'hasRelations'));
    }


    /**
     * show edit invoice page
     */
    public function editExtreme($orderId)
    {
        // clear recent cache
        Cache::forget('order_' . $orderId);

        //get session id
        $session_id = session()->getId();
        
        // Find order data
        $comanda = DB::table('comenzi_ext')->where('idcomanda', $orderId)->useWritePdo()->first();

        // return 404
        if (!$comanda) {
            return redirect()->route('comenzi.index')->with('error', 'Comanda nu a fost găsită!');
        }
        
        // Get client details
        $client = DB::table('clienti')->where('idclienti', $comanda->idclient)->useWritePdo()->first();

        // delete tmp invoice products to prevent loading dummy data
        DB::table('tmp')->where('session_id', $session_id)->delete();

        // Get order details
        $orderDetails = DB::table('detaliu_ext')
            ->join('produse', 'detaliu_ext.idprodus', '=', 'produse.idprodus')
            ->where('detaliu_ext.idcomanda', $orderId)
            ->select('detaliu_ext.cantitate', 'detaliu_ext.pret', 'produse.idprodus', 'produse.denumire', 'produse.cod_produs', 'produse.TVA', 'produse.um')
            ->useWritePdo()
            ->get();

        // Add products to temporary detaliu table
        foreach ($orderDetails as $detail) {
            $invoiceDetailData = [
                'id_produs' => $detail->idprodus,
                'cantitate_tmp' => number_format($detail->cantitate, 2),
                'pret_tmp' => $detail->pret,
                'session_id' => $session_id,
            ];

            DB::table('tmp')->insert($invoiceDetailData);
        }

        // Get facturi details
        $facturiData = ['EmployeeID' => 2, 'tip_incas' => 3];

        //get employees details
        $employees = DB::table('employees')->orderBy('LastName')->select('*')->get();

        //id_plata
        $tipPlatas = DB::table('tip_plata')->orderBy('id_plata')->select('*')->get();

        // localitati
        $counties = DB::table('localitati')->select('judet')->distinct()->orderBy('judet')->get();

        $date = Carbon::now()->format('d/m/Y');
        $dateObj = \DateTime::createFromFormat('d/m/Y', $date);
        $currentDate = $dateObj->format('d/m/Y');
        $dueDate = $dateObj->format('d/m/Y');

        return view('comenzi.edit_extreme', [
            'comanda' => $comanda,
            'client' => $client,
            'orderDetails' => $orderDetails,
            'counties' => $counties,
            'facturiData' => $facturiData,
            'employees' => $employees,
            'tipPlatas' => $tipPlatas,
            'currentDate' => $currentDate,
            'dueDate' => $dueDate,
        ]);
    }


    /**
     * Show the extreme edit form
     */
    public function edit_print_extreme($id)
    {
        // Get order data
        $comanda = ComenziExt::where('idcomanda', $id)
                    ->first();
        
        if (!$comanda) {
            return redirect()->route('comenzi.index')
                            ->with('error', 'Comanda nu a fost găsită!');
        }
        
        // Get client data
        $client = Client::find($comanda->idclient);
        
        // Get counties for client form
        $counties = Localitate::select('judet')->distinct()->orderBy('judet')->get();
        
        // Add current date in d/m/Y format
        $currentDate = date('d/m/Y');
        
        return view('comenzi.edit_extreme', compact('comanda', 'client', 'counties', 'currentDate'));
    }

    
    /**
     * Generate invoice from order
     */
    public function generateInvoiceFromOrder($orderId, $forceCreate = false)
    {
        try {
            // Check if invoice already exists
            $existingInvoice = DB::table('facturi')
                ->where('id_comanda', $orderId)
                ->first();
            
            // If invoice exists and force create is false, return existing invoice
            if ($existingInvoice && !$forceCreate) {
                return $existingInvoice->OrderID;
            }
            
            // Get order data
            $comanda = ComenziExt::where('idcomanda', $orderId)->first();
            if (!$comanda) {
                Log::error("Order not found for invoice generation", ['order_id' => $orderId]);
                return null;
            }
            
            // Get order details to calculate total price
            $orderDetails = DB::table('detaliu_ext')
                ->where('idcomanda', $orderId)
                ->select('cantitate', 'pret')
                ->get();
            
            $totalOrderPrice = 0;
            foreach ($orderDetails as $detail) {
                $totalOrderPrice += $detail->cantitate * $detail->pret;
            }
            
            // Check if total price is 0 or less - prevent invoice generation
            if ($totalOrderPrice <= 0) {
                Log::info("Invoice generation prevented due to zero or negative total", ['order_id' => $orderId, 'total' => $totalOrderPrice]);
                return null;
            }
            
            // Next invoice number generation
            $lastInvoice = DB::table('facturi')->orderBy('OrderID', 'desc')->first();
            $newInvoiceId = $lastInvoice ? $lastInvoice->OrderID + 1 : 1;
            
            // Next invoice fact ID generation
            $lastInvoiceId = DB::table('facturi')->orderBy('id_fact', 'desc')->first();
            $newInvoiceFactId = $lastInvoiceId ? $lastInvoiceId->id_fact + 1 : 1;
            
            $currentDateTime = now()->format('Y-m-d H:i:s');
            
            // Insert main invoice into facturi table
            $invoiceId = DB::table('facturi')->insertGetId([
                'OrderID' => $newInvoiceId,
                'CustomerID' => $comanda->idclient,
                'EmployeeID' => 2, // Default employee ID
                'OrderDate' => $currentDateTime,
                'RequiredDate' => $currentDateTime,
                'seria' => 'BPA_C',
                'valid' => 1,
                'tip_incas' => 3, // External order
                'id_chitanta' => 0,
                'id_comanda' => $orderId,
                'tip_comanda' => 1, // External order
                'id_fact' => $newInvoiceFactId,
                'created_at' => now()
            ]);
            
            // Update order with invoice ID
            DB::table('comenzi_ext')
                ->where('idcomanda', $orderId)
                ->update([
                    'id_factura' => $newInvoiceId,
                    'updated_at' => now()
                ]);
            
            // Get products from detaliu_ext
            $products = DB::table('detaliu_ext')
                ->where('idcomanda', $orderId)
                ->join('produse', 'detaliu_ext.idprodus', '=', 'produse.idprodus')
                ->select(
                    'detaliu_ext.*',
                    'produse.denumire',
                    'produse.cod_produs',
                    'produse.TVA',
                    DB::raw('produse.um as um_produse') // Use alias to avoid expression issues
                )
                ->get();
            
            // Log product count for debugging
            Log::info("Invoice generation - found products", [
                'order_id' => $orderId,
                'product_count' => $products->count()
            ]);
            
            // Insert products into facturidetails
            foreach ($products as $product) {
                // Make sure all values are properly cast to float for calculations
                $quantity = floatval($product->cantitate);
                $priceWithVAT = floatval($product->pret);
                $tvaRate = floatval($product->TVA ?? 21); // Default 19%
                
                // Calculate unit price without VAT
                $priceWithoutVAT = $priceWithVAT / (1 + ($tvaRate/100));
                
                // Calculate values
                $subtotal = $priceWithoutVAT * $quantity;
                $tvaAmount = $subtotal * $tvaRate / 100;
                $total = $subtotal + $tvaAmount;
                
                // Log for debugging
                Log::info("Product calculation", [
                    'id' => $product->idprodus,
                    'qty' => $quantity,
                    'price_with_vat' => $priceWithVAT,
                    'price_without_vat' => $priceWithoutVAT,
                    'subtotal' => $subtotal,
                    'tva_amount' => $tvaAmount,
                    'total' => $total
                ]);
                
                // Insert into facturidetails
                DB::table('facturidetails')->insert([
                    'OrderID' => $newInvoiceId,
                    'ProductID' => $product->idprodus,
                    'UnitPrice' => $priceWithoutVAT,
                    'Quantity' => $quantity,
                    'tva' => $tvaAmount,
                    'total' => $total
                ]);
            }
            
            return $newInvoiceId;
            
        } catch (\Exception $e) {
            Log::error('Invoice generation error', [
                'message' => $e->getMessage(),
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }


    /**
     * Update order
     */
    public function checkOrderInvoice($id)
    {
        try {
            $order = DB::table('comenzi_ext')
                ->where('idcomanda', $id)
                ->first();
                
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }
                
            $hasInvoice = !empty($order->id_factura);
            
            return response()->json([
                'success' => true,
                'has_invoice' => $hasInvoice,
                'invoice_id' => $order->id_factura ?? null
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking invoice: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error checking invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update method में update करें
    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            //get session id
            $session_id = session()->getId();
			
			if ($request->filled('locatie_mgz') && ($request->locatie_mgz == 1 || $request->locatie_mgz == 2)) {
				try {
					// 1. Get the external order
					$order = DB::table('comenzi_ext')->where('idcomanda', $id)->first();
					if (!$order) {
						throw new \Exception('Comanda nu a fost găsită!');
					}

					// 2. Insert into comenzi
					$orderData = (array) $order;
					unset($orderData['idcomanda']); // remove PK
					$newOrderId = DB::table('comenzi')->insertGetId($orderData);

					// 3. Copy detalii_ext → detaliu
					$details = DB::table('detaliu_ext')->where('idcomanda', $id)->get();
					foreach ($details as $d) {
						DB::table('detaliu')->insert([
							'idcomanda' => $newOrderId,
							'idprodus' => $d->idprodus,
							'pret' => $d->pret,
							'cantitate' => $d->cantitate,
							'culoare' => $d->culoare,
							'furnizor' => $d->furnizor,
							'created_at' => $d->created_at,
						]);
					}

					// 4. Delete old external order & details
					DB::table('detaliu_ext')->where('idcomanda', $id)->delete();
					DB::table('comenzi_ext')->where('idcomanda', $id)->delete();

					DB::commit();
					return redirect()->route('orders.index')->with('success', 'Comanda a fost mutată la magazin cu succes!');

				} catch (\Exception $e) {
					DB::rollBack();
					Log::error('Error moving order to Comenzi', [
						'order_id' => $id,
						'message' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					]);
					return redirect()->back()->with('error', 'Eroare la mutarea comenzii: ' . $e->getMessage());
				}
			}

            Log::info('Starting order update with data:', $request->all());
            
            // Validate request
            $validated = $request->validate([
                'id_client' => 'required|integer',
                'data' => 'required|string',
                'idstare' => 'required|integer',
            ]);
            
            // Date conversion
            $dateObj = \DateTime::createFromFormat('d/m/Y', $request->data);
            if (!$dateObj) {
                return redirect()->back()->with('error', 'Format dată invalid!')->withInput();
            }
            
            $idmasina = !empty($request->idmasina_cmd) ? $request->idmasina_cmd : 0;

            //sterg din detalii comanda
            DB::table('detaliu_ext')->where('idcomanda', $id)->delete();

            // Get order products
            $orderDetails = DB::table('tmp')
                ->join('produse', 'tmp.id_produs', '=', 'produse.idprodus')
                ->where('tmp.session_id', $session_id)
                ->select('tmp.cantitate_tmp as cantitate', 'tmp.pret_tmp as pret', 'tmp.culoare', 'tmp.furnizor',  'produse.idprodus')
                ->get();

            // Add products to invoice
            $totalAmount = 0;
            foreach ($orderDetails as $detail) {
                $quantity = $detail->cantitate;
                $priceWithVat = $detail->pret;

                // Calculate total amount
                $valoare = $priceWithVat * $quantity;
                $valoare_f = round($valoare, 2);
                $totalAmount +=$valoare_f; //Suma valoare

                $orderDetailData = [
                    'idcomanda' => $id,
                    'idprodus' => $detail->idprodus,
                    'pret' => $priceWithVat,
                    'cantitate' => $quantity,
                    'culoare' => $detail->culoare,
                    'furnizor' => $detail->furnizor,
					'created_at' => Carbon::now()->timestamp + (2 * 3600)
                ];

                DB::table('detaliu_ext')->insert($orderDetailData);
            }

            // Basic info update
            DB::table('comenzi_ext')
                ->where('idcomanda', $id)
                ->update([
                    'idclient' => $request->id_client,
                    'stare' => $request->idstare,
                    'total' => $totalAmount,
                    'idmasina' => $idmasina,
                    'observations' => $request->observations,
                ]);

            DB::commit();
  
            // Standard redirect
            return redirect()->route('comenzi.index')->with('success', 'Comanda a fost actualizată cu succes!');
        }
        catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating order: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            return redirect()->back()->with('error', 'Eroare: ' . $e->getMessage())->withInput();
        }
    }


    /**
     * save invice data
     */
    public function generateInvoicePdf(Request $request, $orderId)
    {
        try {
            //get session id
            $session_id = session()->getId();

            // Find order
            $order = DB::table('comenzi_ext')
                ->join('clienti', 'comenzi_ext.idclient', '=', 'clienti.idclienti')
                ->where('comenzi_ext.idcomanda', $orderId)
                ->first();

            // Get order products
            $orderDetails = DB::table('tmp')
                ->join('produse', 'tmp.id_produs', '=', 'produse.idprodus')
                ->where('tmp.session_id', $session_id)
                ->select('tmp.cantitate_tmp as cantitate', 'tmp.pret_tmp as pret',  'produse.idprodus', 'produse.TVA')
                ->get();
                
            if (!$order || !$orderDetails) {
                return redirect()->back()->with('error', 'Order not found');
            }

            // Calculate total price from order details to validate before invoice generation
/*             $totalOrderPrice = 0;
            foreach ($orderDetails as $detail) {
                $totalOrderPrice += $detail->cantitate * $detail->pret;
            } */
            
            // Check if total price is 0 or less - prevent invoice generation
            if ($order->total <= 0) {
                return redirect()->back()->with('error', 'Factura nu poate fi generată deoarece totalul comenzii este 0 sau mai mic.');
            }

            // Get invoice number (either existing or create new)
            $invoiceNumber = $order->id_factura;

            DB::beginTransaction();

            if ($invoiceNumber == 0) {
                $id_incasare = intval($request->id_incasare);
                $id_client = $request->id_client;
                $id_vanzator = $request->vanzator_nou;
                $datanoua = $request->data;
                $data_noua = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $datanoua)));
                $datascadenta = $request->datascadenta;
                $numar_chitanta = 0;

                //data_scadenta
                if ($id_incasare <= 2) {
                    $data_scadenta = $data_noua;
                }
                else {
                    $data_scadenta = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $datascadenta)));
                }

                //chitanta
                if ($id_incasare === 1 and  $numar_chitanta === 0) {
                    $lastch = DB::table('facturi')->max('id_chitanta');
                    $numar_chitanta = $lastch + 1;
                }

                //oferta
                if ($id_incasare === 7) {
                    $lastof = DB::table('facturi')->max('id_oferta');
                    $numar_oferta = $lastof + 1;
                }
                else {
                    $numar_oferta = 0;
                }

                //proforma
                if ($id_incasare === 8) {
                    $lastprof = DB::table('facturi')->max('id_proforma');
                    $numar_proforma = $lastprof + 1;
                }
                else {
                    $numar_proforma = 0;
                }

                //aviz
                if ($id_incasare === 9) {
                    $lastaviz = DB::table('facturi')->max('id_aviz');
                    $numar_aviz = $lastaviz + 1;
                }
                else {
                    $numar_aviz = 0;
                }

                // Get new invoice number
                $lastInvoice = DB::table('facturi')->orderBy('OrderID', 'desc')->first();

                $invoiceNumber = $lastInvoice ? $lastInvoice->OrderID + 1 : 1;

                // Get next id_fact value
                $lastInvoiceId = DB::table('facturi')->orderBy('id_fact', 'desc')->value('id_fact');

                $invoiceId = $lastInvoiceId ? $lastInvoiceId + 1 : 1;

                // Prepare invoice data
                $currentDate = Carbon::now();
				//echo "<pre>";print_R('asd');die('asd');

                $invoiceData = [
                    'OrderID' => $invoiceNumber,
                    'CustomerID' => $id_client,
                    'EmployeeID' => $id_vanzator,
                    'OrderDate' => $currentDate,
                    'RequiredDate' => $data_scadenta,
                    'seria' => 'BPA_CAI',
                    'valid' => 1,
                    'tip_incas' => $id_incasare,
                    'id_chitanta' => $numar_chitanta,
                    'id_comanda' => $orderId,
                    'tip_comanda' => 0, // Default value
                    'id_fact' => $invoiceId,
                    'id_oferta' => $numar_oferta,
                    'id_proforma' => $numar_proforma,
                    'id_aviz' => $numar_aviz,
                    'generation_method' => $request->invoice_type ? $request->invoice_type : "manual",
                    'payment_method' => $id_incasare ? $id_incasare : null,
					'smartbill_in_cash' => 'no',
					'created_at' => Carbon::now()->timestamp + (2 * 3600)
                ];

                // Save invoice header
                DB::table('facturi')->insert($invoiceData);

                // Update order with invoice number
                DB::table('comenzi_ext')->where('idcomanda', $orderId)->update(['id_factura' => $invoiceNumber]);

                // Add products to invoice
                foreach ($orderDetails as $detail) {
                    $quantity = $detail->cantitate;
                    $priceWithVat = $detail->pret;
                    $vat = $detail->TVA ?? 21;

                    // Calculate price without VAT
                    $priceWithoutVat = $priceWithVat / (($vat + 100) / 100);
                    $priceWithoutVat = round($priceWithoutVat, 2);

                    // Calculate VAT amount
                    $vatAmount = $priceWithoutVat * $quantity * $vat / 100;
                    $vatAmount = round($vatAmount, 2);

                    // Calculate total amount
                    $totalAmount = $priceWithoutVat * $quantity + $vatAmount;
                    $totalAmount = round($totalAmount, 2);

                    $invoiceDetailData = [
                        'OrderID' => $invoiceNumber,
                        'ProductID' => $detail->idprodus,
                        'UnitPrice' => $priceWithoutVat,
                        'Quantity' => $quantity,
                        'tva' => $vatAmount,
                        'total' => $totalAmount
                    ];

                    DB::table('facturidetails')->insert($invoiceDetailData);
                }

                // Delete tmp invoice products
                DB::table('tmp')->where('session_id', $session_id)->delete();
            }

            DB::commit();

            // redirect on new route
            // Redirect to the print route instead of using JavaScript
            return response()->json([
                'success' => true,
                'message' => '<div class="alert alert-success" role="alert"><button type="button" class="close" data-dismiss="alert">&times;</button><strong>OK!</strong>Invoice generated successfully!.</div>',
                'invoice_url' => url('/print-invoice/' . $invoiceNumber)
            ]);
        }
        catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate invoice: ' . $e->getMessage()
            ]);
        }
    }
	
	
	public function sendWhatsapp($id)
	{
		$order = DB::table('comenzi_ext')
                ->join('clienti', 'comenzi_ext.idclient', '=', 'clienti.idclienti')
                ->select(
                    'comenzi_ext.*',
                    'clienti.nume',
                    'clienti.companie',
                    'clienti.telefon',
                    'clienti.adresa'
                )
				->distinct()
                ->orderBy('comenzi_ext.idcomanda', 'desc')->where('comenzi_ext.idcomanda', $id)->first();

		$phoneNumber = $order->telefon;
		if (!str_starts_with($phoneNumber, '+40')) {
			$phoneNumber = '+40' . ltrim($phoneNumber, '0');
		}

		$magazinName = $order->cont_awb == 'same' ? 'Sameday' : 'FanCourier';
		$clientName  = $order->nume;
		$total = is_numeric($order->total) ? $order->total : 0;
		
		if($order->stare == 5){
			$total = 0;
		}
		
		$awb = $order->awb;
		$awb_url = $order->cont_awb == 'same'
			? 'https://sameday.ro/#awb=' . $awb
			: 'https://www.fancourier.ro/awb-tracking/?tracking=' . $awb;

        $templateBody = MessageTemplate::getTemplate('external_order_shipped', 'whatsapp');

        $messageText = strtr($templateBody, [
            '{{client_name}}'  => $clientName,
            '{{courier_name}}' => $magazinName,
            '{{total}}'        => $total,
            '{{awb}}'          => $awb,
            '{{awb_url}}'      => $awb_url,
        ]);

        $message = rawurlencode($messageText);

		$whatsappUrl = "https://web.whatsapp.com/send/?phone={$phoneNumber}&text={$message}";

		// save to DB
		DB::table('comenzi_ext')
		->where('idcomanda', $id)
		->update([
			'whatsapp_sent' => 1,
			'whatsapp_sent_at' => now(),
		]);

		// redirect to WhatsApp
		return redirect()->away($whatsappUrl);
	}


    /**
     * Get order products via AJAX
     */
    public function getOrderProducts($id)
    {
        try {
            // Get products from detaliu_ext table instead of comenzi_ext
            $products = DB::table('detaliu_ext')
                ->select('detaliu_ext.idprodus', 'detaliu_ext.cantitate', 'detaliu_ext.pret', 'detaliu_ext.furnizor',
                    'detaliu_ext.culoare', 'produse.denumire', 'produse.cod_produs')
                ->leftJoin('produse', 'detaliu_ext.idprodus', '=', 'produse.idprodus')
                ->where('detaliu_ext.idcomanda', $id)
                ->get();
            
            Log::info("Retrieved products for order", [
                'order_id' => $id,
                'product_count' => $products->count()
            ]);
            
            return response()->json([
                'success' => true,
                'products' => $products
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching products: ' . $e->getMessage(), [
                'order_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching products: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add product to an existing order
     */
    public function addProductToOrder(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $orderId = $request->input('id_comanda');
            $productId = $request->input('id_produs');
            $quantity = $request->input('cantitate', 1);
            $price = $request->input('pret', 0);
            $furnizor = $request->input('furnizor', '__');
            
            // Calculate row total
            $rowTotal = $quantity * $price;
            
            Log::info('Adding product to order', [
                'order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $rowTotal
            ]);
            
            // Check if the product already exists in the order (in detaliu_ext)
            $exists = DB::table('detaliu_ext')
                ->where('idcomanda', $orderId)
                ->where('idprodus', $productId)
                ->exists();
                
            if ($exists) {
                // Update existing product in detaliu_ext
                DB::table('detaliu_ext')
                    ->where('idcomanda', $orderId)
                    ->where('idprodus', $productId)
                    ->update([
                        'cantitate' => $quantity,
                        'pret' => $price,
                        'total' => $rowTotal,
                        'furnizor' => $furnizor
                    ]);
                    
                Log::info('Updated existing product in detaliu_ext');
            } else {
                // Add new product to detaliu_ext
                DB::table('detaliu_ext')->insert([
                    'idcomanda' => $orderId,
                    'idprodus' => $productId,
                    'cantitate' => $quantity,
                    'pret' => $price,
                    'total' => $rowTotal,
                    'culoare' => 'FFFFFF', // Default color
                    'furnizor' => $furnizor,
                    'created_at' => now()
                ]);
                
                Log::info('Added new product to detaliu_ext');
            }
            
            // Also check if there's an entry in comenzi_ext and update or create it
            $existsInComenzi = DB::table('comenzi_ext')
                ->where('idcomanda', $orderId)
                ->where('idprodus', $productId)
                ->exists();
                
            if ($existsInComenzi) {
                // Update existing product in comenzi_ext
                DB::table('comenzi_ext')
                    ->where('idcomanda', $orderId)
                    ->where('idprodus', $productId)
                    ->update([
                        'cantitate' => $quantity,
                        'total' => $rowTotal,
                        'furnizor' => $furnizor,
                        'updated_at' => now()
                    ]);
                    
                Log::info('Updated existing product in comenzi_ext');
            } else {
                // Get order info to duplicate
                $orderInfo = DB::table('comenzi_ext')
                    ->where('idcomanda', $orderId)
                    ->first();
                    
                if ($orderInfo) {
                    // Add new product to comenzi_ext
                    DB::table('comenzi_ext')->insert([
                        'idcomanda' => $orderId,
                        'idclient' => $orderInfo->idclient,
                        'idprodus' => $productId,
                        'cantitate' => $quantity,
                        'total' => $rowTotal,
                        'idmasina' => $orderInfo->idmasina ?? 0,
                        'stare' => $orderInfo->stare ?? 1,
                        'retur' => 1, // Default value
                        'data' => $orderInfo->data ?? now()->format('Y-m-d'),
                        'awb' => $orderInfo->awb ?? '___',
                        'cont_awb' => $orderInfo->cont_awb ?? 'Utvin',
                        'furnizor' => $furnizor,
                        'created_at' => now()
                    ]);
                    
                    Log::info('Added new product to comenzi_ext');
                } else {
                    Log::warning('Could not find order info for adding product', [
                        'order_id' => $orderId
                    ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Produs adăugat cu succes'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error adding product: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error adding product: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete product from order
     */
    public function deleteOrderProduct(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $orderId = $request->input('id_comanda');
            $productId = $request->input('id_produs');
            
            Log::info('Deleting product from order', [
                'order_id' => $orderId,
                'product_id' => $productId
            ]);
            
            // Delete from detaliu_ext
            $deletedFromDetaliu = DB::table('detaliu_ext')
                ->where('idcomanda', $orderId)
                ->where('idprodus', $productId)
                ->delete();
            
            Log::info('Deleted from detaliu_ext', [
                'deleted_count' => $deletedFromDetaliu
            ]);
            
            // Delete from comenzi_ext if it exists
            $deletedFromComenzi = DB::table('comenzi_ext')
                ->where('idcomanda', $orderId)
                ->where('idprodus', $productId)
                ->delete();
            
            Log::info('Deleted from comenzi_ext', [
                'deleted_count' => $deletedFromComenzi
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Produs șters cu succes'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting product: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error deleting product: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate invoice from order
     */
    public function invoice($id)
    {
        try {
            DB::beginTransaction();
            
            // Get order data
            $comanda = ComenziExt::where('idcomanda', $id)->first();
            if (!$comanda) {
                return redirect()->route('comenzi.index')
                    ->with('error', 'Comanda nu a fost găsită!');
            }
            
            // Get client data
            $client = Client::find($comanda->idclient);
            
            // Get order details to calculate total price
            $orderDetails = DB::table('detaliu_ext')
                ->where('idcomanda', $id)
                ->select('cantitate', 'pret')
                ->get();
            
            $totalOrderPrice = 0;
            foreach ($orderDetails as $detail) {
                $totalOrderPrice += $detail->cantitate * $detail->pret;
            }
            
            // Check if total price is 0 or less - prevent invoice generation
            if ($totalOrderPrice <= 0) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Factura nu poate fi generată deoarece totalul comenzii este 0 sau mai mic.');
            }
            
            // Get next invoice number
            $lastInvoice = DB::table('facturi')->orderBy('OrderID', 'desc')->first();
            $newInvoiceId = $lastInvoice ? $lastInvoice->OrderID + 1 : 1;
            
            // Get next invoice ID
            $lastInvoiceId = DB::table('facturi')->orderBy('id_fact', 'desc')->first();
            $newInvoiceFactId = $lastInvoiceId ? $lastInvoiceId->id_fact + 1 : 1;
            
            // Get current date and time
            $currentDateTime = now()->format('Y-m-d H:i:s');
            
            // Insert main invoice into facturi table
            DB::table('facturi')->insert([
                'OrderID' => $newInvoiceId,
                'CustomerID' => $comanda->idclient,
                'EmployeeID' => 2, // Default employee ID
                'OrderDate' => $currentDateTime,
                'RequiredDate' => $currentDateTime, // Same as order date for now
                'seria' => 'BPA_C',
                'valid' => 1,
                'tip_incas' => 3, // External order
                'id_chitanta' => 0,
                'id_comanda' => $id,
                'tip_comanda' => 1, // External order
                'id_fact' => $newInvoiceFactId,
                'created_at' => now()
            ]);
            
            // Update order with invoice ID
            DB::table('comenzi_ext')
                ->where('idcomanda', $id)
                ->update([
                    'id_factura' => $newInvoiceId,
                    'updated_at' => now()
                ]);
            
            // Get products from detaliu_ext
            $products = DB::table('detaliu_ext')
                ->where('idcomanda', $id)
                ->join('produse', 'detaliu_ext.idprodus', '=', 'produse.idprodus')
                ->select(
                    'detaliu_ext.*',
                    'produse.denumire',
                    'produse.cod_produs',
                    'produse.TVA',
                    'produse.um'
                )
                ->get();
            
            // Insert products into facturidetails
            foreach ($products as $product) {
                // Calculate values (with VAT handling)
                $quantity = $product->cantitate;
                $priceWithVAT = $product->pret;
                $tvaRate = $product->TVA ?? 21; // Default 19%
                
                // Calculate unit price without VAT
                $priceWithoutVAT = $priceWithVAT / (($tvaRate + 100) / 100);
                
                // Calculate values
                $subtotal = $priceWithoutVAT * $quantity;
                $tvaAmount = $subtotal * $tvaRate / 100;
                $total = $subtotal + $tvaAmount;
                
                // Insert into facturidetails
                DB::table('facturidetails')->insert([
                    'OrderID' => $newInvoiceId,
                    'ProductID' => $product->idprodus,
                    'UnitPrice' => $priceWithoutVAT,
                    'Quantity' => $quantity,
                    'tva' => $tvaAmount,
                    'total' => $total,
                    'created_at' => now()
                ]);
            }
            
            DB::commit();
            
            // Redirect to invoice edit page using proper Laravel routing
            return redirect()->route('factura.editare', [
                'id_factura' => $newInvoiceId,
                'tip_comanda' => 1
            ])->with('success', 'Factura a fost creată cu succes!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating invoice: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            return redirect()->back()
                ->with('error', 'Eroare la crearea facturii: ' . $e->getMessage());
        }
    }

    /**
     * Print an invoice
     */
    public function printInvoice($id)
    {
        try {
            // Debug the invoice data first
            $invoice = DB::table('facturi')
                ->where('OrderID', $id)
                ->first();
                    
            if (!$invoice) {
                Log::error('Invoice not found', ['id' => $id]);
                return redirect()->route('comenzi.index')
                    ->with('error', 'Factura nu a fost găsită!');
            }
            
            // Get client data with more detailed logging
            $client = Client::find($invoice->CustomerID);
            if (!$client) {
                Log::error('Client not found for invoice', ['invoice_id' => $id, 'customer_id' => $invoice->CustomerID]);
            }
            
            // Get invoice details with better error handling
            $details = DB::table('facturidetails')
                ->where('OrderID', $id)
                ->leftJoin('produse', 'facturidetails.ProductID', '=', 'produse.idprodus')
                ->select(
                    'facturidetails.*',
                    'produse.denumire',
                    'produse.cod_produs',
                    'produse.um'
                )
                ->get();
            
            // Calculate totals manually to ensure accuracy
            $subtotal = 0;
            $totalVAT = 0;
            $total = 0;
            
            foreach ($details as $detail) {
                // Make sure numeric values are properly cast
                $unitPrice = floatval($detail->UnitPrice);
                $quantity = floatval($detail->Quantity);
                $tva = floatval($detail->tva);
                
                $rowSubtotal = $unitPrice * $quantity;
                $subtotal += $rowSubtotal;
                $totalVAT += $tva;
                $total += $rowSubtotal + $tva;
            }
            
            // Set payment method based on order status
            $numeTipPlata = 'ACHITAT';
            
            // Find the related order to get status
            $order = DB::table('comenzi_ext')
                ->where('id_factura', $id)
                ->first();
            
            if ($order) {
                switch ($order->stare) {
                    case 1: $numeTipPlata = 'COMANDAT'; break;
                    case 2: $numeTipPlata = 'SOSIT'; break;
                    case 3: $numeTipPlata = 'EXPEDIAT'; break;
                    case 4: $numeTipPlata = 'ACHITAT'; break;
                    case 5: $numeTipPlata = 'AVANS'; break;
                    case 6: $numeTipPlata = 'RETUR'; break;
                    default: $numeTipPlata = 'ACHITAT';
                }
            }
            
            // Log for debugging
            Log::info('Invoice totals calculated', [
                'invoice_id' => $id,
                'subtotal' => $subtotal,
                'totalVAT' => $totalVAT,
                'total' => $total
            ]);
            
            // Return print view
            return view('comenzi.print-invoice', compact('invoice', 'client', 'details', 'subtotal', 'totalVAT', 'total', 'numeTipPlata'));
        } catch (\Exception $e) {
            Log::error('Error showing invoice: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            return redirect()->route('comenzi.index')
                ->with('error', 'Eroare la afișarea facturii: ' . $e->getMessage());
        }
    }
	
	protected function getAWBStatus($awb)
	{
		$courier_status = null;
		
		if (preg_match('/^1ONB/i', $awb)) {
			try {
				$SamedayGetStatusSyncRequest = new \Sameday\Requests\SamedayGetAwbStatusHistoryRequest($awb);
				$couierStatusResponse = $this->client->getAwbStatusHistory($SamedayGetStatusSyncRequest);
				$summary = $couierStatusResponse->getSummary();
				$history = $couierStatusResponse->getHistory();
				
				if (!empty($history)) {
					usort($history, function($a, $b) {
						return $a->getDate() <=> $b->getDate();
					});
					$latest = end($history);
					$name     = $latest->getLabel();
					$location = $latest->getCounty(); // location
					$date     = $latest->getDate()->format('Y-m-d H:i:s'); // formatted date

					$courier_status = $name . "\n" . $location . " - " . $date;
				}
				
				$status = $summary->isCanceled() ? 'Anulat' : ($summary->isDelivered() ? 'Livrat' : 'În tranzit');
				
				$updateData = ['courier_status' => $courier_status ?? null];
				if($status == "Livrat"){
					$updateData['stare'] = 4;
				}
				
				DB::table('comenzi_ext')
					->where('awb', $awb)
					->update($updateData);
			} catch (\Sameday\Exceptions\SamedayNotFoundException $e) {
			} catch (\Exception $e) {
			}
		}else{
			$services = $this->fanCourier->trackMultipleAwbs([$awb]);
			if (!empty($services['data'])) {
				foreach ($services['data'] as $tracking) {
					if (!empty($tracking['awbNumber']) && !empty($tracking['events'])) {
						$lastEvent = end($tracking['events']); // Get last status
						$courier_status = $lastEvent['name'] . "\n" . $lastEvent['location'] . " - " . $lastEvent['date'];
						
						$updateData = ['courier_status' => $courier_status ?? null];
						if($lastEvent['name'] == "Livrat"){
							$updateData['stare'] = 4;
						}
						if($lastEvent['name'] == "Retur"){
							$updateData['stare'] = 6;
						}
						
						DB::table('comenzi_ext')
							->where('awb', $awb)
							->update($updateData);
					}
				}
			}
		}
		return $courier_status;
	}
	
	private function getOohLocations(array $lockerIds = []): array
	{
		try {
			// Pass an array directly, empty array if no filter
			$request = new \Sameday\Requests\SamedayGetOohLocationsRequest($lockerIds);
			$response = $this->client->getOohLocations($request);

			$oohList = [];
			if (!empty($response->getLocations())) {
				foreach ($response->getLocations() as $location) {
					$oohList[$location->getId()] = $location->getName();
				}
			}

			return $oohList;
		} catch (\Exception $e) {
			Log::error('Error fetching OOH locations', [
				'message' => $e->getMessage(),
			]);
			return [];
		}
	}
	
	private function getApiCredential(string $service, string $key): string
	{
		$record = ApiCredential::where('service_name', $service)
			->where('data_key', $key)
			->first();

		// fallback to .env
		$envKey = strtoupper($service . '_' . $key);
		return $record ? ($record->data_value ?? '') : env($envKey, '');
	}

	private function getOrderCreatedAtOffsetHours(): int
	{
		return (int) $this->getApiCredential('orders', 'created_at_offset_hours');
	}
}
