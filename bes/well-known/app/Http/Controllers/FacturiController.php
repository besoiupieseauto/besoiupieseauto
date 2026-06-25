<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Employee;
use App\Models\TipPlata;
use App\Models\Factura;
use App\Models\Localitate;
use App\Models\FacturiDetail;
use App\Models\Produse;
use App\Models\Tmp;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

//use DataTables;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;
use App\Services\SmartBillService;


/**
 * FacturiController handles the management of invoices in the application.
 * It includes methods for creating, updating, and displaying invoices,
 * as well as managing temporary products and invoice details.
 */
class FacturiController extends Controller
{
    public function index()
    {
        $clients = Client::orderBy('nume')->get();
        $tipuriPlata = TipPlata::orderBy('denumire')->get();
        $counties = Localitate::select('judet')->distinct()->orderBy('judet')->get();

        return view('facturi.index', compact('clients', 'tipuriPlata','counties'));
    }
    
    public function create()
    {
        // Clear any temporary products for this session
        $session_id = session()->getId();
        Tmp::where('session_id', $session_id)->delete();
        
        $clients = Client::orderBy('nume')->get();
        $tipuriPlata = TipPlata::orderBy('denumire')->get();
        $employees = Employee::orderBy('LastName')->get();
        $counties = Localitate::select('judet')->distinct()->orderBy('judet')->get();

        // Current date and due date (14 days later)
        $currentDate = date('d/m/Y');
        $dueDate = date('d/m/Y', strtotime('+14 days'));
        
        return view('facturi.create', compact('clients', 'tipuriPlata', 'employees', 'currentDate', 'dueDate', 'counties'));
    }

      
    public function store(Request $request, SmartBillService $smartBillService)
    {
        try {
            DB::beginTransaction();
            
            Log::info('Starting invoice creation with data:', $request->all());
            
            // Form validation
            $validated = $request->validate([
                'id_client' => 'required|integer',
                'agent' => 'required|integer',
                'data_factura' => 'required|string',
                'data_scadenta' => 'required|string',
                'tip_incasare' => 'required|integer',
                'tip_factura' => 'required|string',
            ]);

            // Convert date format to MySQL format (dd/mm/yyyy to yyyy-mm-dd)
            $orderDate = \DateTime::createFromFormat('d/m/Y', $request->data_factura)->format('Y-m-d');
            $requiredDate = \DateTime::createFromFormat('d/m/Y', $request->data_scadenta)->format('Y-m-d');
            
            // Generate new invoice number
            $lastFactura = Factura::orderBy('OrderID', 'desc')->first();
            $newOrderID = $lastFactura ? $lastFactura->OrderID + 1 : 1;
            
            Log::info('Creating new invoice with ID:', ['OrderID' => $newOrderID]);
            
            // Create invoice record
            $factura = new Factura();
            $factura->OrderID = $newOrderID;
            $factura->CustomerID = $request->id_client;
            $factura->EmployeeID = $request->agent;
            $factura->OrderDate = $orderDate;
            $factura->RequiredDate = $requiredDate;
            $factura->tip_incas = $request->tip_incasare;
            $factura->generation_method = $request->tip_factura;
            $factura->seria = 'BPA_C'; // Default series
            
            // Set series based on payment type
            if ($request->tip_incasare == 7) {
                $factura->seria = 'BPA_O';
            } elseif ($request->tip_incasare == 8) {
                $factura->seria = 'BPA_P';
            }
			
			if($request->tip_factura == "smartbill"){
				$factura->seria = 'BPA_CAI';
			}
            
			$factura->created_at = Carbon::now()->timestamp + (2 * 3600);
            $factura->save();
            
            // Get temporary items and save to invoice details
            $session_id = session()->getId();
			
            // Log::info('Using session ID to get tmp products:', ['session_id' => $session_id]);
            
            $tmp_items = Tmp::where('session_id', $session_id)->get();
            // Get order products
            $tmp_items = DB::table('tmp')
                ->join('produse', 'tmp.id_produs', '=', 'produse.idprodus')
                ->where('tmp.session_id', $session_id)
                ->select('tmp.cantitate_tmp', 'tmp.pret_tmp', 'tmp.id_produs', 'produse.TVA')
                ->get();

            // Log::info('Found temporary items:', ['count' => count($tmp_items), 'items' => $tmp_items]);
            
            // Calculate total price from temporary items to validate before invoice generation
            $totalOrderPrice = 0;
            foreach($tmp_items as $item) {
                $totalOrderPrice += $item->cantitate_tmp * $item->pret_tmp;
            }
            
            // Check if total price is 0 or less - prevent invoice generation
/*             if ($totalOrderPrice <= 0) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Factura nu poate fi generată deoarece totalul comenzii este 0 sau mai mic.');
            } */
            
            $grand_total = 0;
            
            foreach($tmp_items as $item) {
                $vat = $item->TVA ?? 21;
				
				$priceWithVat = $item->pret_tmp;
				$quantity = $item->cantitate_tmp;

				// Calculate price without VAT
				$priceWithoutVat = $priceWithVat / (($vat + 100) / 100);
				$priceWithoutVat = round($priceWithoutVat, 2);

				// Calculate total before VAT
				$row_subtotal = $priceWithoutVat * $quantity;

				// Calculate VAT amount
				$row_tva = $row_subtotal * ($vat / 100);
				$row_tva = round($row_tva, 2);

				// Calculate total amount (including VAT)
				$totalAmount = $row_subtotal + $row_tva;
				$totalAmount = round($totalAmount, 2);
                
                $detail = new FacturiDetail();
                $detail->OrderID = $newOrderID;
                $detail->ProductID = $item->id_produs;
                $detail->UnitPrice = $priceWithoutVat;
                $detail->Quantity = $item->cantitate_tmp;
                $detail->tva = $row_tva;
                $detail->total = $totalAmount;
                
                Log::info('Saving facturi detail item:', [
                    'OrderID' => $detail->OrderID,
                    'ProductID' => $detail->ProductID,
                    'UnitPrice' => $detail->UnitPrice,
                    'Quantity' => $detail->Quantity,
                    'tva' => $row_tva,
                ]);
                
                $detail->save();
                
                //$grand_total += $detail->total;
            }
			
			$dataResponse = null;
			if($request->tip_factura == "smartbill"){
				// Get invoice data with client information
				$factura = DB::table('facturi')
						->join('employees', 'employees.EmployeeId', '=', 'facturi.EmployeeID')
						->join('clienti', 'clienti.idclienti', '=', 'facturi.CustomerID')
						->join('localitati', 'localitati.idlocatie', '=', 'clienti.idlocalitate')
						->join('tip_plata', 'tip_plata.id_plata', '=', 'facturi.tip_incas')
						->where('facturi.OrderID', $newOrderID)
						->select('facturi.seria', 'facturi.OrderID', 'facturi.id_fact', 'facturi.OrderDate', 'facturi.RequiredDate'
						, 'facturi.id_chitanta', 'facturi.id_oferta', 'facturi.id_proforma', 'facturi.id_aviz','facturi.tip_incas'
						, 'facturi.generation_method', 'facturi.smartbill_invoice_id', 'facturi.payment_method', 'facturi.smartbill_in_cash'
						, 'clienti.idclienti', 'clienti.idlocalitate', 'clienti.nume', 'clienti.adresa'
						,'clienti.companie', 'clienti.cif', 'clienti.regcom', 'clienti.cont_banca', 'clienti.nume_banca'
						, 'employees.LastName', 'employees.FirstName', 'employees.CI', 'employees.CiNr', 'employees.CNP'
						, 'localitati.judet','localitati.localitate'
						, 'tip_plata.denumire As denumire1')
						->first();
						
				// Get invoice details
				$details = DB::table('facturidetails')
					->join('produse', 'facturidetails.ProductID', '=', 'produse.idprodus')
					->where('facturidetails.OrderID', $newOrderID)
					->select('facturidetails.UnitPrice','facturidetails.Quantity','facturidetails.tva','facturidetails.total','produse.denumire','produse.um')
					->get();
				if (empty($factura->smartbill_invoice_id)) {
					$dataResponse = $this->generateSmartBillInvoice($factura, $details, $smartBillService);
				}		
			}else{
				$factura = DB::table('facturi')
						->join('employees', 'employees.EmployeeId', '=', 'facturi.EmployeeID')
						->join('clienti', 'clienti.idclienti', '=', 'facturi.CustomerID')
						->join('localitati', 'localitati.idlocatie', '=', 'clienti.idlocalitate')
						->join('tip_plata', 'tip_plata.id_plata', '=', 'facturi.tip_incas')
						->where('facturi.OrderID', $newOrderID)
						->select('facturi.seria', 'facturi.OrderID', 'facturi.id_fact', 'facturi.OrderDate', 'facturi.RequiredDate'
						, 'facturi.id_chitanta', 'facturi.id_oferta', 'facturi.id_proforma', 'facturi.id_aviz','facturi.tip_incas'
						, 'facturi.generation_method', 'facturi.smartbill_invoice_id', 'facturi.payment_method', 'facturi.smartbill_in_cash'
						, 'clienti.idclienti', 'clienti.idlocalitate', 'clienti.nume', 'clienti.adresa'
						,'clienti.companie', 'clienti.cif', 'clienti.regcom', 'clienti.cont_banca', 'clienti.nume_banca'
						, 'employees.LastName', 'employees.FirstName', 'employees.CI', 'employees.CiNr', 'employees.CNP'
						, 'localitati.judet','localitati.localitate'
						, 'tip_plata.denumire As denumire1')
						->first();
				if (empty($factura->smartbill_invoice_id)) {
					$lastSmart = Factura::whereIn('generation_method', ['internal', 'manual'])->where('OrderID', '!=', $newOrderID)->max('smartbill_invoice_id');
					$newInvID = $lastSmart ? $lastSmart + 1 : 1;
					Factura::where('OrderID', $newOrderID)->update([
						'smartbill_invoice_id' => $newInvID,
					]);
				}
			}
            
            // Log::info('Grand total calculated for invoice:', ['grand_total' => $grand_total]);
            
            // Clear temporary items
            Tmp::where('session_id', $session_id)->delete();
            
            DB::commit();
            
            return redirect()->route('facturi.index')->with('success', 'Factura a fost creată cu succes!');
        }
        catch (\Exception $e) {
            DB::rollBack();
			
			dd($e->getMessage());
            Log::error('Error creating invoice: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Eroare: ' . $e->getMessage())->withInput();
        }
    }
      
      
    public function addTmpProduct(Request $request)
    {
        try {
            Log::info('Received product data:', $request->all());

            // Set default TVA percentage if not present
            $request->merge(['tva' => $request->input('tva', 21)]);
            
            $validated = $request->validate([
                'id_produs' => 'required|integer',
                'cantitate' => 'required|numeric',
                'pret' => 'required|numeric',
                'tva' => 'required|numeric',  // Add TVA validation
                'culoare' => 'nullable|string',
                'furnizor' => 'nullable|string',
            ]);
            
            $session_id = session()->getId();
            Log::info('Session ID:', ['session_id' => $session_id]);
            
            // Ensure id_produs is a valid integer and not null
            $productId = (int)$request->id_produs;
            if ($productId <= 0) {
                throw new \Exception("Invalid product ID: $productId");
            }
            
            // Check if product exists
            $product = Produse::find($productId);
            if (!$product) {
                throw new \Exception("Product with ID $productId does not exist");
            }
            
            // Check if product already exists in temp
            $existing = Tmp::where('session_id', $session_id)
                        ->where('id_produs', $productId)
                        ->first();
            
            if ($existing) {
                // If exists, update quantity
                $existing->cantitate_tmp += $request->cantitate;
                $existing->save();
                Log::info('Updated existing product', ['id_tmp' => $existing->id_tmp]);
            } else {
                // Create new temp item
                $tmp = new Tmp();
                $tmp->id_produs = $productId; // Store as integer, not string
                $tmp->cantitate_tmp = $request->cantitate;
                $tmp->pret_tmp = $request->pret;
                $tmp->tva_tmp = $request->tva;  // Store TVA percentage
                $tmp->session_id = $session_id;
                $tmp->culoare = $request->culoare ?? null;
                $tmp->furnizor = $request->furnizor ?? null;
                $tmp->save();
                Log::info('Added new product', ['id_tmp' => $tmp->id_tmp, 'product_id' => $productId]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Produsul a fost adăugat în factură'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error adding product: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTmpProducts()
    {
        try {
            $session_id = session()->getId();
            Log::info('Getting tmp products for session:', ['session_id' => $session_id]);
            
            // Check if there are any tmp products for this session
            $count = DB::table('tmp')->where('session_id', $session_id)->count();
            Log::info('Initial count of tmp products:', ['count' => $count]);
            
            // Make sure the IDs match between Produse and tmp tables
            // Only get products where id_produs is not null
            // Replace this part in your getTmpProducts() function
            $tmp_products = DB::table('tmp')
                ->leftJoin('produse', 'tmp.id_produs', '=', 'produse.idprodus')
                ->select('tmp.*', 'produse.denumire as ProductName', 'produse.cod_produs')
                ->where('tmp.session_id', $session_id)
                ->whereNotNull('tmp.id_produs')
                ->get();
            
            Log::info('Found tmp products after join:', ['count' => count($tmp_products)]);
            
            $subtotal = 0;
            $tva_total = 0;
            
            foreach($tmp_products as $product) {
                $row_subtotal = $product->cantitate_tmp * $product->pret_tmp;
                // Use the product's TVA rate stored in tmp table
                $tva_rate = $product->tva_tmp / 100; // Convert percentage to decimal
                $row_tva = $row_subtotal * $tva_rate;
                
                $subtotal += $row_subtotal;
                $tva_total += $row_tva;
            }
            
            $total = $subtotal + $tva_total;
            
            Log::info('Calculated totals:', [
                'subtotal' => $subtotal,
                'tva' => $tva_total,
                'total' => $total
            ]);
            
            return response()->json([
                'products' => $tmp_products,
                'total' => $total,
                'subtotal' => $subtotal,
                'tva' => $tva_total
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting tmp products: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage(),
                'products' => [],
                'total' => 0,
                'subtotal' => 0,
                'tva' => 0
            ], 500);
        }
    }


    // Add this to FacturiController.php
    public function addDetailToInvoice(Request $request, $id)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'product_id' => 'required|integer|exists:produse,idprodus',
                'quantity' => 'required|numeric|min:0.01',
                'price' => 'required|numeric|min:0.01',
                'tva_rate' => 'required|numeric',
            ]);
            
            $product = Produse::findOrFail($request->product_id);
            
            // Calculate values
            $subtotal = $request->quantity * $request->price;
            $tva = $subtotal * ($request->tva_rate / 100);
            
            // Create new detail
            $detail = new FacturiDetail();
            $detail->OrderID = $id;
            $detail->ProductID = $request->product_id;
            $detail->UnitPrice = $request->price;
            $detail->Quantity = $request->quantity;
            $detail->tva = $tva;
            $detail->total = $subtotal + $tva;
            $detail->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Product added to invoice'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error adding detail to invoice: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }


    public function deleteTmpProduct(Request $request)
    {
        try {
            $tmp = Tmp::where('id_tmp', $request->id)->firstOrFail();
            $tmp->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Produsul a fost șters din factură'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }
    
    
    public function searchClients(Request $request)
    {
        $term = $request->get('term');

        $clients = Client::where('nume', 'LIKE', "%{$term}%")
        ->orWhere('companie', 'LIKE', "%{$term}%")
        ->orWhere('telefon', 'LIKE', "%{$term}%")
        ->select('idclienti', 'nume', 'companie', 'telefon', 'cif', 'adresa', 'marca', 'idmasina', 'nr_inmat')
        ->orderBy('nume', 'asc')
        ->orderBy('companie', 'asc')
        ->limit(50)
        ->get();

        $clientesData = [];
        // prepare the data for each client
        foreach ($clients as $detail) {
            $clienteSingle = [
                'id_client' => $detail->idclienti,
                'value' => $detail->nume . ' / ' . $detail->companie . ' / ' . $detail->marca . ' / ' . $detail->adresa . ' / ' . $detail->nr_inmat,
                'nume_client' => $detail->nume,
                'telefon_client' => $detail->telefon,
                'cif_client' => $detail->cif,
                'adresa_client' => $detail->adresa,
                'marca_client' => $detail->marca,
                'idmasina_client' => $detail->idmasina,
                'companie_nou_cl' => $detail->companie
            ];

            array_push($clientesData, $clienteSingle);
        }

        return response()->json($clientesData);
    }
    
    
    public function getData(Request $request)
    {
        $search = $request->get('search_value');
        $invoiceType = $request->get('invoiceType');
        $tip_incasare = $request->get('tip_incasare');
		
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

        $facturas = DB::table('facturi')
            ->join('clienti', 'facturi.CustomerID', '=', 'clienti.idclienti')
            ->leftJoin('tip_plata', 'facturi.tip_incas', '=', 'tip_plata.id_plata')
            ->leftJoin(DB::raw('(SELECT OrderID, SUM(total) as total_sum FROM facturidetails GROUP BY OrderID) as fd'), 'facturi.OrderID', '=', 'fd.OrderID')
            ->select(
                'facturi.OrderID',
                'facturi.seria',
                'facturi.id_comanda',
                'clienti.nume as client_name',
                'clienti.companie',
                'facturi.OrderDate',
                'facturi.RequiredDate',
                'facturi.smartbill_invoice_id',
                'facturi.generation_method',
                'fd.total_sum as total',
                'tip_plata.denumire as tip_incasare',
                'tip_plata.id_plata',
                'facturi.negative_issued'
            )
            ->orderBy('facturi.OrderID', 'desc');
			
		$today = now()->toDateString();
		if ($fromDate && $toDate) {
			$facturas->whereDate('facturi.OrderDate', '>=', $fromDate->toDateString())
				  ->whereDate('facturi.OrderDate', '<=', $toDate->toDateString());
		} elseif ($fromDate) {
			if ($fromDate->toDateString() === $today && $request->has('search') && $request->search) {
				$fiftyDaysAgo = now()->subDays(50)->toDateString();
				$facturas->whereDate('facturi.OrderDate', '>=', $fiftyDaysAgo)
					  ->whereDate('facturi.OrderDate', '<=', $today);
			} else {
				$facturas->whereDate('facturi.OrderDate', '=', $fromDate->toDateString());
			}
		} elseif ($toDate) {
			if ($toDate->toDateString() === $today && $request->has('search') && $request->search) {
				$fiftyDaysAgo = now()->subDays(50)->toDateString();
				
				$facturas->whereDate('facturi.OrderDate', '>=', $fiftyDaysAgo)
					  ->whereDate('facturi.OrderDate', '<=', $today);
			} else {
				$facturas->whereDate('facturi.OrderDate', '=', $toDate->toDateString());
			}
		}

/*         if ($search) {
            $facturas->where(function($query) use ($search) {
                $query->where('clienti.nume', 'LIKE', "%{$search}%")
                    ->orWhere('clienti.companie', 'LIKE', "%{$search}%")
                    ->orWhere('facturi.OrderID', 'LIKE', "%{$search}%")
                    ->orWhere('facturi.seria', 'LIKE', "%{$search}%");
            });
        } */
if ($search) {
    $facturas->where(function ($query) use ($search) {

        // split "BPA_C 19429" → ["BPA_C", "19429"]
        $parts = preg_split('/\s+/', trim($search));

        foreach ($parts as $part) {
            $query->where(function ($q) use ($part) {
                $q->where('clienti.nume', 'LIKE', "%{$part}%")
                  ->orWhere('clienti.companie', 'LIKE', "%{$part}%")
                  ->orWhere('facturi.OrderID', 'LIKE', "%{$part}%")
                  ->orWhere('facturi.seria', 'LIKE', "%{$part}%")
                  ->orWhere('facturi.smartbill_invoice_id', 'LIKE', "%{$part}%");
            });
        }
    });
}
		
		if($tip_incasare){
            $facturas->where(function($query) use ($tip_incasare) {
                $query->where('facturi.tip_incas', '=', $tip_incasare);
            });
		}
		
		if($invoiceType){
            $facturas->where(function($query) use ($invoiceType) {
				if($invoiceType == 'manual'){
					$query->whereIn('facturi.generation_method', ['manual', 'internal']);
				}else{
					$query->where('facturi.generation_method', '=', $invoiceType);
				}
            });			
		}

        return DataTables::of($facturas)
            ->addColumn('numar_factura', function ($row) {
                $seria = $row->seria;
				//$number = $row->OrderID;
				$number = !empty($row->smartbill_invoice_id) ? $row->smartbill_invoice_id : $row->OrderID;

                if ($row->id_plata == 7) {
                    $seria = 'BPA_O';
                }
                elseif ($row->id_plata == 8) {
                    $seria = 'BPA_P';
                }
				
				if($row->generation_method == 'smartbill'){
					$seria = 'BPA_CAI';
					$number = $row->smartbill_invoice_id;
				}

                return $seria . ' ' . $number;
            })
            ->addColumn('client_display', function ($row) {
                if (empty($row->companie)) {
                    return $row->client_name;
                }else {
                    return $row->client_name.' ('.$row->companie.')';
                }
            })
			->addColumn('client_name', function ($row) {
                if (empty($row->companie)) {
                    return $row->client_name;
                }else {
                    return $row->companie;
                }
            })
            ->addColumn('action', function ($row) {
				$buttons = '
					<div class="action-buttons">
						<a href="/facturi/' . $row->OrderID . '/edit" class="btn btn-default btn-sm">
							<i class="glyphicon glyphicon-edit"></i>
						</a>

						<a href="#" class="btn btn-default btn-sm print-btn" title="Tiparire factura" data-id="'.$row->OrderID.'">
							<i class="glyphicon glyphicon-print"></i>
						</a>
				';
				
				if ($row->total >= 0){
					if($row->negative_issued == 0) {
						$buttons .= '
							<a href="/facturi/' . $row->OrderID . '/edit-sub" class="btn btn-default btn-sm" target="_blank">
								<i class="glyphicon glyphicon-minus"></i>
							</a>
						';
					}else{
						$buttons .= '
							<a href="javascript:void(0);" class="btn btn-default btn-sm">
								<i class="glyphicon glyphicon-thumbs-up"></i>
							</a>
						';
					}
				}
				$buttons .= '</div>';

				return $buttons;
            })
            ->editColumn('OrderDate', function ($row) {
                return date('d/m/Y', strtotime($row->OrderDate));
            })
            ->editColumn('RequiredDate', function ($row) {
                return date('d/m/Y', strtotime($row->RequiredDate));
            })
            ->editColumn('total', function ($row) {
                return number_format($row->total ?? 0, 2, '.', '');
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    /**
     * Show the form for editing the specified invoice.
     *
     * @param  int  $orderId
     */
    public function edit($orderId)
    {
        //get session id
        $session_id = session()->getId();

        // Get client details
        $client = DB::table('facturi')
            ->join('clienti', 'facturi.customerID', '=', 'clienti.idclienti')
            ->where('facturi.OrderID', $orderId)
            ->select('clienti.idclienti', 'clienti.nume', 'clienti.telefon', 'clienti.cif', 'facturi.OrderID', 'facturi.tip_incas', 'clienti.companie'
            , 'facturi.EmployeeID', 'facturi.OrderDate', 'facturi.RequiredDate')
            ->first();

        if (!$client) {
            return redirect()->route('facturi.index')->with('error', 'Comanda nu a fost găsită!');
        }

        // Get invoice details with product names included
        $facturidetails = DB::table('facturidetails')
                    ->leftJoin('produse', 'facturidetails.ProductId', '=', 'produse.idprodus')
                    ->select(
                        'facturidetails.OrderID',
                        'facturidetails.ProductId',
                        'facturidetails.UnitPrice',
                        'facturidetails.Quantity',
                        'facturidetails.tva',
                        'facturidetails.total',
                        'produse.denumire',
                        'produse.TVA as cota_tva',
                    )
                    ->where('facturidetails.OrderID', $orderId)
                    ->orderBy('facturidetails.ProductId', 'asc')
                    ->get();
        
        // delete tmp invoice products to prevent loading dummy data
        DB::table('tmp')->where('session_id', $session_id)->delete();

        // Add products to temporary detaliu table
        foreach ($facturidetails as $detail) {
            $invoiceDetailData = [
                'id_produs' => $detail->ProductId,
                'cantitate_tmp' => number_format($detail->Quantity, 2),
                'pret_tmp' => $detail->total / $detail->Quantity, // Calculate price per unit
                'session_id' => $session_id,
            ];

            DB::table('tmp')->insert($invoiceDetailData);
        }

        //id_plata
        $tipPlatas = DB::table('tip_plata')->orderBy('id_plata')->select('*')->get();

        //get employees details
        $employees = DB::table('employees')->orderBy('LastName')->select('*')->get();
        
        $counties = Localitate::select('judet')->distinct()->orderBy('judet')->get();

        $currentDate = Carbon::parse($client->OrderDate)->format('d/m/Y');
        $datascadenta = Carbon::parse($client->RequiredDate)->format('d/m/Y');;

        return view('facturi.edit', [
            'client' => $client,
            'counties' => $counties,
            'facturidetails' => $facturidetails,
            'employees' => $employees,
            'tipPlatas' => $tipPlatas,
            'data_factura' => $currentDate,
            'datascadenta' => $datascadenta,
        ]);
    }


    public function editSub($orderId)
    {
        //get session id
        $session_id = session()->getId();

        // Get client details
        $client = DB::table('facturi')
            ->join('clienti', 'facturi.customerID', '=', 'clienti.idclienti')
            ->where('facturi.OrderID', $orderId)
            ->select('clienti.idclienti', 'clienti.nume', 'clienti.companie', 'clienti.telefon', 'clienti.cif', 'facturi.OrderID', 'facturi.generation_method')
            ->first();

        if (!$client) {
            return redirect()->route('facturi.index')
                ->with('error', 'Comanda nu a fost găsită!');
        }

        // Get invoice details with product names included
        $facturidetails = DB::table('facturidetails')
                    ->leftJoin('produse', 'facturidetails.ProductId', '=', 'produse.idprodus')
                    ->select(
                        'facturidetails.OrderID',
                        'facturidetails.ProductId',
                        'facturidetails.UnitPrice',
                        'facturidetails.Quantity',
                        'facturidetails.tva',
                        'facturidetails.total',
                        'produse.denumire',
                        'produse.TVA as cota_tva',
                    )
                    ->where('facturidetails.OrderID', $orderId)
                    ->orderBy('facturidetails.ProductId', 'asc')
                    ->get();
        
        // delete tmp invoice products to prevent loading dummy data
        DB::table('tmp')->where('session_id', $session_id)->delete();

        // Add products to temporary detaliu table
        foreach ($facturidetails as $detail) {
            $invoiceDetailData = [
                'id_produs' => $detail->ProductId,
                'cantitate_tmp' => number_format($detail->Quantity, 2),
                'pret_tmp' => $detail->total / $detail->Quantity, // Calculate price per unit
                'session_id' => $session_id,
            ];

            DB::table('tmp')->insert($invoiceDetailData);
        }

        // Get facturi details
        $facturiData = ['EmployeeID' => 2, 'tip_incas' => 1];

        //id_plata
        $tipPlatas = DB::table('tip_plata')->orderBy('id_plata')->select('*')->get();

        //get employees details
        $employees = DB::table('employees')->orderBy('LastName')->select('*')->get();
        
        $counties = Localitate::select('judet')->distinct()->orderBy('judet')->get();

        $date = Carbon::now()->format('d/m/Y');
        $dateObj = \DateTime::createFromFormat('d/m/Y', $date);
        $currentDate = $dateObj->format('d/m/Y');
        $dueDate = $dateObj->format('d/m/Y');

        return view('facturi.edit_sub', [
            'client' => $client,
            'counties' => $counties,
            'facturidetails' => $facturidetails,
            'facturiData' => $facturiData,
            'employees' => $employees,
            'tipPlatas' => $tipPlatas,
            'currentDate' => $currentDate,
            'dueDate' => $dueDate,
        ]);
    }

    //Not using it now ANIL 03 JUNE 2025
    public function updateOLD(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            
            // Log the incoming data for debugging
            Log::info('Updating invoice with data:', $request->all());
            
            // Validate the request
            $validated = $request->validate([
                'client_id' => 'required|exists:clienti,idclienti',
                'agent' => 'required|integer',
                'data_factura' => 'required|string',
                'data_scadenta' => 'required|string',
                'tip_incasare' => 'required|integer',
            ]);
            
            // Convert date format from dd/mm/yyyy to yyyy-mm-dd
            $orderDate = \DateTime::createFromFormat('d/m/Y', $request->data_factura)->format('Y-m-d');
            $requiredDate = \DateTime::createFromFormat('d/m/Y', $request->data_scadenta)->format('Y-m-d');
            
            // Find and update the invoice
            $factura = Factura::findOrFail($id);
            $factura->CustomerID = $request->client_id;
            $factura->EmployeeID = $request->agent;
            $factura->OrderDate = $orderDate;
            $factura->RequiredDate = $requiredDate;
            $factura->tip_incas = $request->tip_incasare;
            
            // Set series based on payment type
            if ($request->tip_incasare == 7) {
                $factura->seria = 'BPA_O';
            } elseif ($request->tip_incasare == 8) {
                $factura->seria = 'BPA_P';
            } else {
                $factura->seria = 'BPA_C'; // Default series
            }
            
            $factura->save();
            
            Log::info('Invoice updated successfully:', ['OrderID' => $id]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Factura a fost actualizată cu succes!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating invoice: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }


    public function update(Request $request, $orderId, SmartBillService $smartBillService)
    {
        try {
            //get session id
            $session_id = session()->getId();

            // Validate the request
            $validated = $request->validate([
                'id_client' => 'required|exists:clienti,idclienti',
                'vanzator_nou' => 'required|integer',
                'data_factura' => 'required|string',
                'data_scadenta' => 'required|string',
                'tip_incasare' => 'required|integer',
            ]);

            // Find order
            $order = DB::table('facturi')->where('facturi.OrderID', $orderId)->first();

            // Get order products
            $orderDetails = DB::table('tmp')
                ->join('produse', 'tmp.id_produs', '=', 'produse.idprodus')
                ->where('tmp.session_id', $session_id)
                ->select('tmp.cantitate_tmp as cantitate', 'tmp.pret_tmp as pret',  'produse.idprodus', 'produse.TVA')
                ->get();

            if (!$order || !$orderDetails) {
                return redirect()->back()->with('error', 'Order not found');
            }
            
            $id_incasare = intval($request->tip_incasare);
            $id_client = $request->id_client;
            $id_vanzator = $request->vanzator_nou;
            $datanoua = $request->data_factura;
            $data_noua = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $datanoua)));
            $datascadenta = $request->data_scadenta;
            $invoice_type = $order->generation_method == "smartbill" ? "smartbill" : "manual";
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
            
            // Prepare invoice data
            $currentDate = Carbon::now();
            
            $invoiceData = [
                'OrderID' => $invoiceNumber,
                'CustomerID' => $id_client,
                'EmployeeID' => $id_vanzator,
                'OrderDate' => $currentDate,
                'RequiredDate' => $data_scadenta,
                'seria' => $invoice_type == "manual" ? 'BPA_C' : 'BPA_CAI',
                'valid' => 1,
                'tip_incas' => $id_incasare,
                'id_chitanta' => $numar_chitanta,
                'id_comanda' => $orderId,
                'tip_comanda' => 0, // Default value
                'id_fact' => $invoiceNumber,
                'id_oferta' => $numar_oferta,
                'id_proforma' => $numar_proforma,
                'id_aviz' => $numar_aviz,
				'generation_method' => $invoice_type,
				'smartbill_in_cash' => 'no',
				'created_at' => Carbon::now()->timestamp + (2 * 3600)
            ];

            // Save invoice header
            DB::table('facturi')->insert($invoiceData);
			DB::table('facturi')->where('OrderID', $orderId)->update(['negative_issued' => 1]);
                
            // Add products to invoice
            foreach ($orderDetails as $detail) {
                $quantity = $detail->cantitate * -1;
                $priceWithVat = $detail->pret;
                $vat = $detail->TVA ?? 21;
                
                // Calculate price without VAT
                $priceWithoutVat = $priceWithVat / (($vat + 100) / 100);
                $priceWithoutVat = round($priceWithoutVat, 2);
                
                // Calculate VAT amount
                $vatAmount = $priceWithoutVat * $quantity * $vat / 100;
                $vatAmount = round($vatAmount, 2);
                
                // Calculate total amount
                $totalAmount = ($priceWithoutVat * $quantity) + $vatAmount;
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
			
			$dataResponse = null;
			//$factura = DB::table('facturi')->where('OrderID', $invoiceNumber)->first();
			if($order->generation_method == "smartbill" && $invoice_type == "smartbill"){
				$factura = DB::table('facturi')->where('OrderID', $invoiceNumber)->first();
				if (empty($factura->smartbill_invoice_id)) {
					$dataResponse = $this->reverseSmartBillInvoice($order, $factura, $smartBillService);
				}				
			}

            // return success message
            return response()->json([
                'success' => true,
                'message' => '<div class="alert alert-success" role="alert"><button type="button" class="close" data-dismiss="alert">&times;</button><strong>OK!</strong> Factura a fost actualizată cu succes.</div>',
				'data' => $dataResponse,
				'pdfurl' => route('print.invoice',['invoice_id'=>$invoiceNumber])
            ]);
        }
        catch (\Exception $e) {
            Log::error('Error generating invoice PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ceva a mers prost încercați din nou. ' . $e->getMessage()
            ]);
        }
    }


    /**
     * Update the details of an existing invoice.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $orderId
     * @return \Illuminate\Http\Response
     */
    public function updateDetails(Request $request, $orderId)
    {
        try {
            //get session id
            $session_id = session()->getId();

             // Validate the request
            $validated = $request->validate([
                'id_client' => 'required|exists:clienti,idclienti',
                'vanzator_nou' => 'required|integer',
                'data_factura' => 'required|string',
                'data_scadenta' => 'required|string',
                'tip_incasare' => 'required|integer',
            ]);

            // Find order
            $order = DB::table('facturi')->where('facturi.OrderID', $orderId)->first();

            // Get order products
            $orderDetails = DB::table('tmp')
                ->join('produse', 'tmp.id_produs', '=', 'produse.idprodus')
                ->where('tmp.session_id', $session_id)
                ->select('tmp.cantitate_tmp as cantitate', 'tmp.pret_tmp as pret',  'produse.idprodus', 'produse.TVA')
                ->get();

            if (!$order || !$orderDetails) {
                return redirect()->back()->with('error', 'Order not found');
            }
            
            DB::beginTransaction();

            $id_incasare = intval($request->tip_incasare);
            $datanoua = $request->data_factura;
            $data_noua = date("Y-m-d H:i:s", strtotime(str_replace('/', '-', $datanoua)));
            $datascadenta = $request->data_scadenta;
            $numar_chitanta = $order->id_chitanta ?? 0;
            
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
            else {
                $numar_chitanta = 0;
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
            
            // Prepare invoice data
            $currentDate = Carbon::now();

            // Find and update the invoice
            $factura = Factura::findOrFail($orderId);
            $factura->CustomerID = $request->id_client;
            $factura->EmployeeID = $request->vanzator_nou;
            $factura->OrderDate = $currentDate;
            $factura->RequiredDate = $data_scadenta;
            $factura->tip_incas = $id_incasare;
            $factura->id_chitanta = $numar_chitanta;
            $factura->id_oferta = $numar_oferta;
            $factura->id_proforma = $numar_proforma;
            $factura->id_aviz = $numar_aviz;
            
            $factura->save();

            //sterg din detalii factura
            DB::table('facturidetails')->where('OrderID', $orderId)->delete();

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
                $totalAmount = ($priceWithoutVat * $quantity) + $vatAmount;
                $totalAmount = round($totalAmount, 2);
                
                $invoiceDetailData = [
                    'OrderID' => $orderId,
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

            DB::commit();

            // return success message
            return response()->json([
                'success' => true,
                'message' => '<div class="alert alert-success" role="alert"><button type="button" class="close" data-dismiss="alert">&times;</button><strong>OK!</strong> Factura a fost actualizată cu succes.</div>',
                'invoice_url' => url('/print-invoice/' . $orderId)
            ]);
        }
        catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '<div class="alert alert-danger" role="alert"><button type="button" class="close" data-dismiss="alert">&times;</button><strong>Eroare!</strong>Ceva a mers prost încercați din nou. ' . $e->getMessage() . '</div>'
            ]);
        }
    }


    public function updateTmpProduct(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'id' => 'required|numeric',
                'quantity' => 'required|numeric|min:0.01',
                'price' => 'required|numeric|min:0',
            ]);
            
            // Find the temporary product
            $tmp = Tmp::where('id_tmp', $request->id)
                    ->where('session_id', session()->getId())
                    ->firstOrFail();
            
            // Update quantity and price
            $tmp->cantitate_tmp = $request->quantity;
            $tmp->pret_tmp = $request->price;
            $tmp->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Produsul a fost actualizat cu succes'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating tmp product: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }


    // Run this once to clean up your database
    public function cleanupTmpProducts()
    {
        try {
            $deleted = Tmp::whereNull('id_produs')->delete();
            return response()->json([
                'success' => true,
                'message' => "$deleted temporary items with null product ID were deleted"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            
            // Delete related details first to maintain referential integrity
            FacturiDetail::where('OrderID', $id)->delete();
            
            // Then delete the main invoice
            $factura = Factura::findOrFail($id);
            $factura->delete();
            
            DB::commit();
            
            return response()->json(['success' => true, 'message' => 'Factura a fost ștearsă cu succes!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Eroare: ' . $e->getMessage()]);
        }
    }


    
    public function getInvoiceDetails($id)
    {
        // Get invoice details with product names
        $details = DB::table('facturidetails as f')
            ->leftJoin('produse as p', 'f.ProductID', '=', 'p.idprodus')
            ->select(
                'f.id', 'f.OrderID', 'f.ProductID',
                'p.denumire as ProductName',
                'f.UnitPrice', 'f.Quantity', 'f.tva', 'f.total',
                DB::raw('(f.UnitPrice * f.Quantity) as subtotal'),
                'p.TVA as tva_rate'
            )
            ->where('f.OrderID', $id)
            ->get();
        
        // Calculate totals
        $totals = DB::table('facturidetails')
            ->select(
                DB::raw('SUM(UnitPrice * Quantity) as subtotal'),
                DB::raw('SUM(tva) as total_tva'),
                DB::raw('SUM((UnitPrice * Quantity) + tva) as grand_total')
            )
            ->where('OrderID', $id)
            ->first();
        
        return response()->json([
            'details' => $details,
            'subtotal' => $totals->subtotal ?? 0,
            'tva' => $totals->total_tva ?? 0,
            'total' => $totals->grand_total ?? 0
        ]);
    }
    
    public function storeclient(Request $request)
    {
        $validatedData = $request->validate([
            'nume_nou_cl'       => 'required|string|max:255',
            'adresa_nou'        => 'required|string',
            'telefon_nou'       => 'nullable|string',
            'marca_masina'      => 'nullable|string',
            'sasiu_masina'      => 'nullable|string',
            'nrmat_masina'      => 'nullable|string',
            'companie_nou_cl'   => 'nullable|string',
            'cif_nou_cl'        => 'nullable|string',
            'cont_banca'        => 'nullable|string',
            'nume_banca'        => 'nullable|string',
            'judet_nou_cl'      => 'nullable|string',
            'localitate_nou_cl' => 'nullable|string',
            'regcom'            => 'nullable|string',
        ]);
        
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
        ]);
        
        return response()->json(['success' => true, 'client' => $client]);
    }
    
    public function getLocalities(Request $request)
    {
        $judet = $request->input('judet');
        
        $localities = DB::table('localitati')
                        ->where('judet', $judet)
                        ->pluck('localitate')
                        ->toArray();
        
        return response()->json($localities);
    }
    
    public function storepro(Request $request)
    {
        try {
            // Directly store in model
            $produs = new Produse();
            $produs->denumire = $request->denumire;
            $produs->cod_produs = $request->cod_produs;
            $produs->pret = $request->pret;
            $produs->TVA = 21; // Default to 19% if not provided
			$produs->created_at = Carbon::now()->timestamp + (2 * 3600);
            $produs->save();
            
            // If AJAX request
            if($request->ajax()) {
                return response()->json(['success' => true]);
            }
            
            // For normal request
            return redirect()->back()->with('success', 'Produs adăugat cu succes');
        } catch (\Exception $e) {
            // Log error
            Log::error('Product save failed: ' . $e->getMessage());
            
            // If AJAX request
            if($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            
            // For normal request
            return redirect()->back()->with('error', 'Produsul nu a fost salvat: ' . $e->getMessage());
        }
    }
 

    /**
     * added for search products
     * */
    public function searchProducts(Request $request)
    {
        try {
            $query = $request->input('query', '');
            $page = $request->input('page', 1);
            $perPage = 5; // Number of products per page
            
            // Base query
            $productsQuery = DB::table('produse')
                ->select('idprodus', 'denumire', 'cod_produs', 'pret', 'TVA')
                ->orderBy('idprodus', 'desc');
            
            // Apply search filter if provided
            if (!empty($query)) {
                $productsQuery->where(function($q) use ($query) {
                    $q->where('denumire', 'like', "%{$query}%")
                    ->orWhere('cod_produs', 'like', "%{$query}%");
                });
            }
            
            // Get total count for pagination
            $totalProducts = $productsQuery->count();
            $totalPages = ceil($totalProducts / $perPage);

            // Get products for current page
            $products = $productsQuery
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            return response()->json([
                'products' => $products,
                'pagination' => [
                    'current_page' => (int)$page,
                    'total_pages' => $totalPages,
                    'total' => $totalProducts
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error searching products:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => true,
                'message' => 'Error searching products: ' . $e->getMessage()
            ], 500);
        }
    }


    //edit wala:
    public function addDetail(Request $request, $id)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'id_produs' => 'required|integer',
                'cantitate' => 'required|numeric',
                'pret' => 'required|numeric',
                'tva' => 'required|numeric',
            ]);
            
            // Check if invoice exists
            $factura = Factura::findOrFail($id);
            
            // Get product to ensure it exists
            $product = Produse::findOrFail($request->id_produs);
            
            // Calculate values
            $rowSubtotal = $request->cantitate * $request->pret;
            $tvaRate = $request->tva / 100; // Convert TVA percentage to decimal
            $rowTva = $rowSubtotal * $tvaRate;
            
            // Create new invoice detail
            $detail = new FacturiDetail();
            $detail->OrderID = $id;
            $detail->ProductID = $request->id_produs;
            $detail->UnitPrice = $request->pret;
            $detail->Quantity = $request->cantitate;
            $detail->tva = $rowTva; // Store TVA amount
            $detail->total = $rowSubtotal + $rowTva; // Store total WITH TVA
            $detail->culoare = $request->culoare ?? null;
            $detail->furnizor = $request->furnizor ?? null;
            $detail->save();
            
            Log::info('Added detail to invoice:', [
                'OrderID' => $id,
                'ProductID' => $request->id_produs,
                'UnitPrice' => $request->pret,
                'Quantity' => $request->cantitate,
                'TVA Rate' => $request->tva,
                'TVA Amount' => $rowTva,
                'Total' => $rowSubtotal + $rowTva
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Product added successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding invoice detail: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteDetail($invoiceId, $detailId)
    {
        try {
            // Find the invoice detail
            $detail = FacturiDetail::where('OrderID', $invoiceId)
                                ->where('id', $detailId)
                                ->firstOrFail();
            
            // Delete the detail
            $detail->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Product removed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting invoice detail: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }


    public function print($id)
    {
        // Invoice fetch करें
        $factura = Factura::findOrFail($id);
        
        // क्लाइंट को सीधे फेच करें
        $client = Client::where('idclienti', $factura->CustomerID)->first();
        
        // अगर क्लाइंट नहीं मिला
        if (!$client) {
            Log::warning("No client found for ID: {$factura->CustomerID}");
            $client = new Client([
                'nume' => 'Necunoscut'
            ]);
        }
        
        // Blade template में पास करने के लिए
        $factura->client = $client;
        
        // Payment method
        $tipPlata = TipPlata::find($factura->tip_incas);
        $numeTipPlata = $tipPlata ? $tipPlata->denumire : 'ACHITAT';
        
        // Invoice details
        $details = DB::table('facturidetails')
            ->leftJoin('produse', 'facturidetails.ProductID', '=', 'produse.idprodus')
            ->select(
                'facturidetails.*',
                'produse.denumire as produs',
                'produse.TVA as tva_rate'
            )
            ->where('facturidetails.OrderID', $id)
            ->get()
            ->map(function ($detail) {
                $detail->produs = $detail->produs ?? 'Produs necunoscut';
                $detail->pret_unitar = $detail->UnitPrice;
                $detail->cantitate = $detail->Quantity;
                $detail->um = 'buc';
                
                // TVA calculation
                if (!isset($detail->tva) || $detail->tva === null) {
                    $tva_rate = $detail->tva_rate ?? 19;
                    $subtotal = $detail->UnitPrice * $detail->Quantity;
                    $detail->tva = $subtotal * ($tva_rate / 100);
                }
                
                return $detail;
            });
        
        // Totals calculation
        $subtotal = $details->sum(function ($item) {
            return $item->UnitPrice * $item->Quantity;
        });
        
        $totalTva = $details->sum('tva');
        
        return view('facturi.print', compact(
            'factura',
            'details',
            'numeTipPlata',
            'subtotal',
            'totalTva'
        ));
    }
	
	protected function reverseSmartBillInvoice($order, $facturi, SmartBillService $smartBillService)
	{
		if (!$order && !$facturi) {
			return response()->json(['error' => 'Invoice data not found'], 404);
		}
		
		// Build SmartBill request payload
		$invoiceData = [
			"companyVatCode" => "RO31298897", // Replace with your company VAT code or config value
			"issueDate"    => date("Y-m-d"),
			"seriesName"   => $order->seria ? "BPA_CAI" : "", // must exist in SmartBill Cloud
			"number"      => $order->smartbill_invoice_id
		];

		// Call SmartBill API
		$response = $smartBillService->reverseInvoice($invoiceData);

		if ($response && isset($response['number']) && isset($response['series'])) {
			// Store SmartBill invoice ID in your DB
			DB::table('facturi')->where('OrderID', $facturi->OrderID)->update([
				'smartbill_invoice_id' => $response['number'],
			]);

			$pdfResponse = $smartBillService->getInvoice(
				'RO31298897',
				$response['series'],
				$response['number']
			);
			
			if (!$pdfResponse) {
				return response()->json(['error' => 'Invoice PDF not found'], 404);
			}

			return $response;
		}

		return response()->json(['error' => 'SmartBill invoice generation failed'], 500);
	}
	
	protected function generateSmartBillInvoice($factura, $details, SmartBillService $smartBillService)
	{
		if (!$factura) {
			return response()->json(['error' => 'Invoice data not found'], 404);
		}
	
		$total = 0;
		foreach ($details as $detail) {
			$lineTotal = ($detail->UnitPrice ?? 0) * ($detail->Quantity ?? 1);
			$total += $lineTotal;
		}
		$vatAmount = $total * 21 / 100; 
		$totalWithVAT = $total + $vatAmount;
		
		$paymentMethodMap = [
			'1'  => 'Chitanta',
			'2'  => 'Bon',
			'3'  => 'Ramburs',
			'4'  => 'Ordin plata',
			'5'  => 'Card'
		];
		
		// Build SmartBill request payload
		$invoiceData = [
			"companyVatCode" => "RO31298897", // Replace with your company VAT code or config value
			"client" => [
				"name"       => !empty($factura->companie) ? $factura->companie : ($factura->nume ?? "Client Name"),
				"vatCode"    => $factura->cif ?? "-", // "-" if client has no CIF/CNP
				"isTaxPayer" => !empty($factura->cif), // true if company, false if individual
				"address"    => $factura->adresa ?? "-",
				//"city"       => $factura->localitate ?? "-",
				"city"		 => ($factura->judet == "Bucuresti") ? "Sector 1" : ($factura->localitate ?? "-"),
				"county"     => $factura->judet ?? "Bucuresti",
				"country"    => "Romania",
				"email"      => $factura->email ?? "client@example.com",
				"saveToDb"   => false
			],
			"issueDate"    => date("Y-m-d", strtotime($factura->OrderDate ?? now())),
			"seriesName"   => $factura->seria ? "BPA_CAI" : "", // must exist in SmartBill Cloud
			"isDraft"      => false,
			"dueDate"      => date("Y-m-d", strtotime($factura->OrderDate ?? now())),
			"deliveryDate" => date("Y-m-d", strtotime($factura->OrderDate ?? now())),
			"products"     => [],
			"payment"	=> [
				"value" => round($totalWithVAT), // Set payment value to 0 to prevent Incasari records
				"type" => $paymentMethodMap[$factura->tip_incas] ?? "Ramburs",
				"isCash" => $factura->smartbill_in_cash == "yes" ? true : false
			]
		];

		foreach ($details as $detail) {
			// SmartBill uses 21% VAT
			$taxPercentage = 21;
			$taxName = 'Normala';
			
			// Your system: UnitPrice = 0.84, VAT = 0.16 (19%), Total = 1.00
			// For SmartBill to show same total (1.00) with 21% VAT:
			// We need to calculate the unit price without VAT for SmartBill
			$unitPrice = $detail->UnitPrice ?? 0;
			$quantity = $detail->Quantity ?? 1;
			
			$unitPriceWithoutVAT = $unitPrice / (1 + ($taxPercentage / 100));
			
/* 			$vatAmount = $unitPrice * $quantity * 21 / 100; // Your system VAT
			$totalWithVAT = $unitPrice * $quantity + $vatAmount; // Your system total (1.00)
			
			// Calculate unit price without VAT for SmartBill to get same total
			$unitPriceWithoutVAT = $totalWithVAT / (1 + ($taxPercentage / 100)); // 1.00 / 1.21 = 0.826 */
		
			$invoiceData["products"][] = [
				"name"              => $detail->denumire ?? "Product",
				"isDiscount"        => false,
				"measuringUnitName" => $detail->um ?? "buc",
				"currency"          => "RON",
				"quantity"          => $quantity,
				"price"             => $unitPrice, // Send unit price without VAT
				"saveToDb"          => false,
				"isService"         => false,
				"isTaxIncluded"     => false, // Price does NOT include VAT
				"taxName"           => $taxName,
				"taxPercentage"     => $taxPercentage,
			];
		}

		// Call SmartBill API
		$response = $smartBillService->createInvoice($invoiceData);

		if ($response && isset($response['number']) && isset($response['series'])) {
			// Store SmartBill invoice ID in your DB
			DB::table('facturi')->where('OrderID', $factura->OrderID)->update([
				'smartbill_invoice_id' => $response['number'],
			]);
				
			// ✅ Create payment immediately
			try {
 				$paymentAmount = $total; // total calculated from $details
				$paymentDate = now()->format('Y-m-d');
				
				$paymentData = [
					"companyVatCode" => "RO31298897", // your company VAT
					"client" => [
						"name"       => $factura->companie ?? $factura->nume,
						"vatCode"    => $factura->cif ?? "-",
						"isTaxPayer" => !empty($factura->cif),
						"address"    => $factura->adresa ?? "-",
						"city"       => $factura->localitate ?? "-",
						"country"    => "Romania",
						"email"      => $factura->email ?? "client@example.com",
					],
					"issueDate"        => $paymentDate,
					"currency"         => "RON",
					"language"         => "RO",
					"exchangeRate"     => 1,
					"precision"        => 2,
					"value"            => $total, // total invoice amount
					"type"             => $paymentMethodMap[$factura->tip_incas] ?? "Ramburs",
					"isCash"           => $factura->smartbill_in_cash == "yes",
					"useInvoiceDetails" => false,
					"invoicesList"     => [
						[
							"seriesName" => $factura->seria,
							"number"     => $response['number'],
						]
					],
				];

				$paymentResponse = $smartBillService->createPayment($paymentData);

				if ($paymentResponse) {
					Log::info('SmartBill payment created successfully', [
						'invoiceNumber'   => $response['number'],
						'paymentResponse' => $paymentResponse
					]);
				} else {
					Log::error('SmartBill payment failed', ['payload' => $paymentData]);
				}

			} catch (\Exception $ex) {
				Log::error('SmartBill payment creation failed: ' . $ex->getMessage());
			}

			// Get invoice PDF
			$pdfResponse = $smartBillService->getInvoice(
				'RO31298897',
				$response['series'],
				$response['number']
			);

			if (!$pdfResponse) {
				return response()->json(['error' => 'Invoice PDF not found'], 404);
			}

			return response($pdfResponse, 200)
				->header('Content-Type', 'application/pdf')
				->header('Content-Disposition', "inline; filename=\"{$response['series']}_{$response['number']}.pdf\"");
		}

		return response()->json(['error' => 'SmartBill invoice generation failed'], 500);
	}
}
