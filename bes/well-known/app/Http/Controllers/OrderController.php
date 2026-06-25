<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\Tmp;
use App\Models\Comenzi;
use App\Models\Client;
use App\Models\Localitate;
use App\Models\Factura;
use App\Models\FacturiDetail; // Assuming you have a model for FacturiDetail
use App\Models\ApiCredential;
use App\Models\MessageTemplate;
use App\Models\OrderStatusHistory;

//use DataTables;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

//use Codedge\Fpdf\Fpdf\Fpdf;
use App\Helpers\RotatedPdf;
use App\Services\SmartBillService;

class OrderController extends Controller
{
    const ICONV_CHARSET_INPUT = 'UTF-8';
    const ICONV_CHARSET_OUTPUT_A = 'ISO-8859-1//TRANSLIT';
    const ICONV_CHARSET_OUTPUT_B = 'windows-1252//TRANSLIT';
    public $font = 'helvetica';
    public $firstColumnWidth = 70;
    public $columns = 6;
    public $columnSpacing = 0.01;
    public $fontSizeProductDescription = 8;
    public $columnOpacity = 0.06;
	
    public function __construct()
    {
		// Fetch SMS credentials
		$this->smsApiKey = $this->getApiCredential('sms', 'api_key');
		$this->smsApiUrl = $this->getApiCredential('sms', 'api_url');
    }

    public function index(Request $request)
    {
		$type = $request->input('type', null);

		// Permission check based on type
		if ($type === 'utvin' && !Auth::user()->hasPermission('comenzi_utvin')) {
			abort(403, 'Nu ai acces la această pagină.');
		}

		if (!$type && !Auth::user()->hasPermission('comenzi_tm')) {
			abort(403, 'Nu ai acces la această pagină.');
		}
	
        $date = $request->input('date', now()->format('d/m/Y'));
        $searchQuery = $request->input('q', '');
        
        // Get the current date in the proper format
        $currentDate = $date;
        
        // Additional data you might need for the view
        $monthlyTotal = 0;
        $dailyTotal = 0;
        
        try {
            // Convert date format for DB query
            $formattedDate = Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
            
            // Calculate monthly total
            $month = Carbon::parse($formattedDate)->month;
            $year = Carbon::parse($formattedDate)->year;
            
            $monthlyTotal = DB::table('comenzi')
                ->whereMonth('data', $month)
                ->whereYear('data', $year)
                ->where('stare', '!=', 5)
                ->sum('total');
                
            // Calculate daily total
            $dailyTotal = DB::table('comenzi')
                ->where('data', $formattedDate)
                ->where('stare', '!=', 5)
                ->sum('total');
        } catch (\Exception $e) {
            Log::error('Error calculating totals:', ['error' => $e->getMessage()]);
            // Defaults will be used if there's an error
        }
		
		if($request->type && $request->type == 'utvin'){
			return view('orders.utvinindex', [
				'currentDate' => $currentDate,
				'monthlyTotal' => $monthlyTotal,
				'dailyTotal' => $dailyTotal
			]);
		}
        
        return view('orders.index', [
            'currentDate' => $currentDate,
            'monthlyTotal' => $monthlyTotal,
            'dailyTotal' => $dailyTotal
        ]);
    }

    
    /**
     * DataTables data source for orders
     */
    public function getOrdersData(Request $request)
    {
        try {
			if($request->from_date == $request->to_date){
				$request->offsetUnset('to_date');
			}
			
            // Parse date from dd/mm/yyyy format with better error handling
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
			
			
			$locatie_magazin = null;
			if ($request->has('locatie_magazin') && $request->locatie_magazin) {
				$locatie_magazin = $request->locatie_magazin;
			}

            Log::info('Loading orders data for date:', ['fromDate' => $fromDate, 'toDate' => $toDate, 'requested_date' => $request->date]);

            // Base query - using DB facade instead of Eloquent Model
            $query = DB::table('comenzi')
                ->join('clienti', 'comenzi.idclient', '=', 'clienti.idclienti')
				->leftJoin('users', 'comenzi.userid', '=', 'users.Id')
				->leftJoin('detaliu', 'comenzi.idcomanda', '=', 'detaliu.idcomanda')
				->leftJoin('produse', 'detaliu.idprodus', '=', 'produse.idprodus')
                ->select(
                    'comenzi.*',
                    'clienti.nume',
                    'clienti.companie',
                    'clienti.telefon',
                    'clienti.adresa',
					'users.username as user_name',
					DB::raw("(SELECT old_status 
					  FROM order_status_history osh 
					  WHERE osh.order_id = comenzi.idcomanda 
					  ORDER BY osh.created_at DESC 
					  LIMIT 1) as last_old_status")
                )
				->distinct()
                ->orderBy('comenzi.idcomanda', 'desc'); // Add sorting by ID in descending order
			
			$query->where('comenzi.locatie_mgz', $locatie_magazin);
			
			if ($request->filled('filtered_statuses')) {
				$statuses = $request->filtered_statuses; // array of selected statuses
				$query->whereIn('comenzi.stare', $statuses);
			}

			$today = now()->toDateString();
			if ($fromDate && $toDate) {
				$query->whereDate('comenzi.data', '>=', $fromDate->toDateString())
					  ->whereDate('comenzi.data', '<=', $toDate->toDateString());
			} elseif ($fromDate) {
				if ($fromDate->toDateString() === $today && $request->has('search') && $request->search) {
					$fiftyDaysAgo = now()->subDays(50)->toDateString();
					$query->whereDate('comenzi.data', '>=', $fiftyDaysAgo)
						  ->whereDate('comenzi.data', '<=', $today);
				} else {
					$query->whereDate('comenzi.data', '=', $fromDate->toDateString());
				}
			} elseif ($toDate) {
				if ($toDate->toDateString() === $today && $request->has('search') && $request->search) {
					$fiftyDaysAgo = now()->subDays(50)->toDateString();
					$query->whereDate('comenzi.data', '>=', $fiftyDaysAgo)
						  ->whereDate('comenzi.data', '<=', $today);
				} else {
					$query->whereDate('comenzi.data', '=', $toDate->toDateString());
				}
			}

            // Apply search filter
            if ($request->has('search') && $request->search) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('clienti.nume', 'like', "%{$searchTerm}%")
                    ->orWhere('clienti.telefon', 'like', "%{$searchTerm}%")
                    ->orWhere('clienti.companie', 'like', "%{$searchTerm}%")
                    ->orWhere('clienti.adresa', 'like', "%{$searchTerm}%")
                    ->orWhere('clienti.marca', 'like', "%{$searchTerm}%")
                    ->orWhere('produse.cod_produs', 'like', "%{$searchTerm}%");
                });
            }


            // Calculate totals
			$now = Carbon::now();
			$dateForMonth = ($fromDate && $toDate) || (!$fromDate && !$toDate) ? $now : ($fromDate ?? $toDate);
            $monthlyTotal = DB::table('comenzi')
				->whereMonth('data', $dateForMonth->month)
				->whereYear('data', $dateForMonth->year)
                ->whereNotIn('stare', [5, 8]) // Exclude returns
                ->where('locatie_mgz', '=', $locatie_magazin)
                ->sum('total');
			if (!empty($fromDate) && !empty($toDate)) {
				$starts = $ends = Carbon::today();
			} else {
				$starts = $fromDate ?? $toDate ?? Carbon::today();
				$ends = $toDate ?? $fromDate ?? Carbon::today();
			}
			$dailyTotal = DB::table('comenzi')
				->whereDate('data', '>=', $starts->toDateString())
				->whereDate('data', '<=', $ends->toDateString())
				->whereNotIn('stare', [5, 8])
				->where('locatie_mgz', $locatie_magazin)
				->sum('total');

            // Convert totals to float to ensure they're numeric (fixes NaN issue)
            $monthlyTotal = (float)$monthlyTotal;
            $dailyTotal = (float)$dailyTotal;

            // Get all orders
            $orders = $query->get();
            $orderIds = $orders->pluck('idcomanda')->toArray();
			
			$filteredTotal = 0;
			$filteredTotal = $orders
			->filter(fn($order) => !in_array($order->stare, [5, 8])) // exclude returns
			->sum(fn($order) => (float) $order->total);
			
			if (!$request->has('search') || empty($request->search)) {
				$filteredTotal = 0;
			}
			

            // Get SMS records for highlighting
            $smsRecords = DB::table('sms')->whereIn('idcomanda', $orderIds)->pluck('idcomanda')->toArray();
                
			if ($request->boolean('is_initial_load')) {
				$filteredTotal = 0;
			}

            $orderCreatedAtOffsetHours = $this->getOrderCreatedAtOffsetHours();

            // Return data for DataTables
            return DataTables::of($orders)
                ->addColumn('data', function ($order) use ($orderCreatedAtOffsetHours) {
                    // Format date from Y-m-d to d/m/Y
					$html = '
						<div style="display:flex;flex-direction:column;line-height:1.5;padding: 4px 0px;">
							<span>'.($order->user_name ? $order->user_name : '').'</span> 
							'.Carbon::parse($order->data)->format('d/m/Y').'
							<span>'.Carbon::parse($order->created_at)->addHours($orderCreatedAtOffsetHours)->format('H:i').'</span>
						</div>';
                    return $html;
                })
                ->addColumn('client', function ($order) {
                    $clientName = $order->nume;
                    if (!empty($order->companie)) {
                        $clientName .= ' / ' . $order->companie;
                    }
					$clientName .= '<span style="display:block;">'.$order->telefon.'</span>';
                    return $clientName;
                })
/*                 ->addColumn('telefon', function ($order) {
                    return $order->telefon;
                }) */
                ->addColumn('marca', function ($order) {
                    return $order->marca ?? '';
                })
                /* ->addColumn('magazin', function ($order) {
                    $magazinName = $order->locatie_mgz == 1 ? 'Timisoara' : 'Utvin';
                    return '<a href="javascript:void(0)" title="Magazin" onclick="obtineAdresa(\'' . $order->idcomanda . '\', \'' . $order->locatie_mgz . '\'); return false;"><b>' . $magazinName . '</b></a>';
                }) */
                ->addColumn('produs', function ($order) {
                    // Get all products for this order
                    $details = DB::table('detaliu')
                        ->join('produse', 'detaliu.idprodus', '=', 'produse.idprodus')
                        ->where('detaliu.idcomanda', $order->idcomanda)
                        ->select('detaliu.*', 'produse.denumire')
                        ->get();
                        
                    if ($details->isEmpty()) {
                        return '-';
                    }
                    
                    $products = [];
                    foreach ($details as $detail) {
                        $products[] = '<div style="padding:9px 0; border-bottom:1px solid #ddd;font-size:12px;" title="' . $detail->denumire . '">' . Str::limit($detail->denumire, 20, '') . '</div>';
                    }
                    
                    return '<div style="min-width:150px;">' . implode('', $products) . '</div>';
                })
                ->addColumn('cod', function ($order) {
                    // Get all products for this order
                    $details = DB::table('detaliu')
                        ->join('produse', 'detaliu.idprodus', '=', 'produse.idprodus')
                        ->where('detaliu.idcomanda', $order->idcomanda)
                        ->select('detaliu.*', 'produse.cod_produs')
                        ->get();
                        
                    if ($details->isEmpty()) {
                        return '-';
                    }
                    
                    $codes = [];
                    foreach ($details as $detail) {
                        if ($order->stare == 2) { // Sosit
                            $bgColor = '';
                            $textColor = 'black';
                        }
                        else if ($order->stare == 3 || $order->stare == 5 || $order->stare == 6 || $order->stare == 7 || $order->stare == 8 || $order->stare == 9) { // FD // cash // card // retur
                            $bgColor = '';
                            $textColor = 'black';
                        }
                        else {
                            $bgColor = ($detail->culoare && strtoupper($detail->culoare) !== 'FFFFFF') ? '#' . $detail->culoare : '';
                            $textColor = 'black';
                        }
                        
                        if ($order->stare < 3 || $order->stare == 4 || $order->stare > 9) {
                            $codes[] = '<div style="padding:6px 0; border-bottom:1px solid #ddd;font-size:12px;background-color:' . $bgColor . '; color: ' . $textColor . ';"  title="' . $detail->cod_produs . '"><a href="javascript:void(0)" onclick="obtineCuloare(\'' . $order->idcomanda . '\', \'' . $detail->idprodus . '\', \'' . $detail->culoare . '\'); return false;" style="padding: 3px 6px; display: inline-block; border-radius: 3px;"><b>' . $detail->cod_produs . '</b></a></div>';
                        }
                        else {
                            $codes[] = '<div style="padding:6px 0; border-bottom:1px solid #ddd;font-size:12px;background-color:' . $bgColor . '; color: ' . $textColor . ';"  title="' . $detail->cod_produs . '"><span style="padding: 3px 6px; display: inline-block; border-radius: 3px;"><b>' . Str::limit($detail->cod_produs, 12, '') . '</b></span></div>';
                        }
                    }
                    
                    return '<div style="min-width:120px;" data-ed="'.$order->stare.'" id="codDiv" data-value="' . count($codes) . '" background-color="' . $bgColor . '" color="' . $textColor . '">' . implode('', $codes) . '</div>';
                })
                ->addColumn('furnizor', function ($order) {
                    // Get all products for this order
                    $details = DB::table('detaliu')
                        ->join('produse', 'detaliu.idprodus', '=', 'produse.idprodus')
                        ->where('detaliu.idcomanda', $order->idcomanda)
                        ->select('detaliu.*')
                        ->get();
                        
                    if ($details->isEmpty()) {
                        return '-';
                    }
                    
                    $suppliers = [];
                    foreach ($details as $detail) {
                        $furnizor = $detail->furnizor ?? '__';
                        
                        if ($order->stare < 3 || $order->stare == 4) {
                            $suppliers[] = '<div style="padding:6px 0; border-bottom:1px solid #ddd;font-size:12px;"><a href="javascript:void(0)" onclick="obtineFurnizor(\'' . $order->idcomanda . '\', \'' . $detail->idprodus . '\', \'' . $furnizor . '\'); return false;" style="padding: 3px 6px; display: inline-block;"><b>' . $furnizor . '</b></a></div>';
                        } else {
                            $suppliers[] = '<div style="padding:9px 0; border-bottom:1px solid #ddd;font-size:12px;"><b>' . $furnizor . '</b></div>';
                        }
                    }
                    
                    return '<div style="min-width:70px;">' . implode('', $suppliers) . '</div>';
                })
                ->addColumn('cantitate', function ($order) {
                    // Get all products for this order
                    $details = DB::table('detaliu')
                        ->where('idcomanda', $order->idcomanda)
                        ->select('cantitate')
                        ->get();
                        
                    if ($details->isEmpty()) {
                        return '-';
                    }
                    
                    $quantities = [];
                    foreach ($details as $detail) {
                        $quantities[] = '<div style="padding:9px 0; border-bottom:1px solid #ddd; text-align:center;font-size:12px;">' . $detail->cantitate . '</div>';
                    }
                    
                    return '<div style="min-width:60px;">' . implode('', $quantities) . '</div>';
                })
                ->addColumn('pret', function ($order) {
                    // Get all products for this order
                    $details = DB::table('detaliu')
                        ->where('idcomanda', $order->idcomanda)
                        ->select('pret')
                        ->get();
                        
                    if ($details->isEmpty()) {
                        return '-';
                    }
                    
                    $prices = [];
                    foreach ($details as $detail) {
                        // Convert to float to avoid string concatenation issues
                        $price = (float)$detail->pret;
                        $prices[] = '<div style="padding:9px 0; border-bottom:1px solid #ddd;font-size:12px;">' . number_format($price, 2, '.', '') . '</div>';
                    }
                    
                    return '<div style="min-width:80px;">' . implode('', $prices) . '</div>';
                })
                ->addColumn('total', function ($order) {
                    // Make sure $order->total is a valid number
                    $total = is_numeric($order->total) ? $order->total : 0;
                    // Add proper spacing and padding
                    if($order->stare < 3) {
                        return '<a href="javascript:void(0)" onclick="obtineTotalComanda(\'' . $order->idcomanda .'\', \'' . $total . '\');"><b>'. $total . '</b></a>';
                    }
                    else {
                        return '<b>' . $total . '</b>';
                    }
                })
                ->addColumn('status', function ($order) {
                    $statusText = '';
                    $labelClass = '';
                    
                    switch($order->stare) {
                        case 0: $statusText = 'Eroare'; $labelClass = 'btn-danger'; break;
                        case 1: $statusText = 'Comandat'; $labelClass = 'btn-warning'; break;
                        case 2: $statusText = 'Sosit'; $labelClass = 'btn-info'; break;
                        case 3: $statusText = 'Cash'; $labelClass = 'btn-success'; break;
                        case 4: $statusText = 'Avans'; $labelClass = 'btn-danger'; break;
                        case 5: $statusText = 'Retur'; $labelClass = 'btn-success'; break;
                        case 6: $statusText = 'Card'; $labelClass = 'btn-success'; break;
                        case 7: $statusText = 'FD'; $labelClass = 'btn-success'; break;
						case 8: $statusText = 'Anulat';   $labelClass = 'btn-danger'; break;
						case 9: $statusText = 'OP';   $labelClass = 'btn-warning'; break;
						case 10: $statusText = 'Avans FD';   $labelClass = 'btn-success'; break;
						case 11: $statusText = 'Avans Cash';   $labelClass = 'btn-success'; break;
						case 12: $statusText = 'Avans Card';   $labelClass = 'btn-success'; break;
						case 13: $statusText = 'Avans OP';   $labelClass = 'btn-warning'; break;
                    }
                    
                    // Pass the status name as second parameter to obtineStare function
                    $statusName = strtolower($statusText);
					/* if($statusName == "anulat"){
						return '<a href="javascript:void(0)" class="btn btn-xs ' . $labelClass . '" title="Stare">' . $statusText . '</a>';
					} */
					
					$lastStatus = DB::table('order_status_history')
						->where('order_id', $order->idcomanda)
						->orderBy('created_at', 'desc')
						->first();
					$lastStatusChange = $lastStatus->old_status ?? 0;
					$userId = $lastStatus->user_id ?? null;
					$changedAt = $lastStatus->created_at ?? null;
					
					$userName = DB::table('users')
					->where('Id', $userId)
					->value('nume_complet');
					
                    //return '<a href="javascript:void(0)" class="btn btn-xs ' . $labelClass . '" title="Stare" onclick="obtineStare(\'' . $order->idcomanda . '\', \'' . $statusName . '\', \'' . $lastStatusChange . '\'); return false;">' . $statusText . '</a>';
                
					$button = '<a href="javascript:void(0)" class="btn btn-xs '.$labelClass.'" style="margin-top:10px;" 
						title="Stare" 
						onclick="obtineStare(\''.$order->idcomanda.'\', \''.$statusName.'\', \''.$lastStatusChange.'\'); return false;">
						'.$statusText.'
					</a>';

					$info = '';

					if($changedAt && in_array($order->stare, [3,6,7,4,8,9,10,11,12,13])){
						$info = '<div style="font-size:11px;margin-top:4px;margin-bottom:10px;">
									'.$userName.'<br>
									'.date('d M Y H:i', strtotime($changedAt)).'
								</div>';
					}

					return $button.$info;				
				})
                ->addColumn('actiune', function ($order) use ($smsRecords) {
                    $html = '<div class="btn-group-vertical btn-group-xs">';
                    
					//SMS Action
                    if ($order->stare == 2 || $order->stare == 4) {
                        $smsClass = in_array($order->idcomanda, $smsRecords) ? 'btn-danger' : 'btn-success';
                        $smsTitle = in_array($order->idcomanda, $smsRecords) ? 'SMS trimis' : 'SMS';
                        $html .= '<a href="javascript:void(0)" class="btn ' . $smsClass . '" title="' . $smsTitle . '" onclick="obtineSms(\'' . $order->idcomanda . '\', \'' . $order->stare . '\'); return false;"><i class="glyphicon glyphicon-earphone" style="color: #fff;"></i></a>';
                    }
					
					//Edit Action
					$type = $order->locatie_mgz == 1 ? 'main' : 'utvin';
					if ($order->stare == 1 || $order->stare == 2 || $order->stare == 4) {
                        $html .= '<a href="' . route('orders.edit', $order->idcomanda) . '?type=' . $type. '" class="btn btn-default" title="Editare"><i class="glyphicon glyphicon-edit"></i></a>';
					}
					
					//Delete Action
					if ($order->stare == 1) {
                        $html .= '<a href="javascript:void(0)" class="btn btn-warning" title="Sterge" onclick="stergeComanda(\'' . $order->idcomanda . '\'); return false;"><i class="glyphicon glyphicon-trash" style="color: #fff;"></i></a>';
                    }
                    
					if ($order->stare == 1 || $order->stare == 2 || $order->stare == 5 || $order->stare == 4 || $order->stare == 3 || $order->stare == 6 || $order->stare == 7 || $order->stare == 9) {
						// Conditionally show PDF/Invoice button
						$type = $order->locatie_mgz == 1 ? 'main' : 'utvin';
						if ($order->id_factura == 0) {
							$html .= '<a href="/edit-factura/' . $order->idcomanda . '?type=' . $type. '" class="btn btn-default" title="Factureaza" target="_blank"><i class="glyphicon glyphicon-share-alt"></i></a>';
						}else {
							$html .= '<a href="' . route('print.invoice', $order->id_factura) . '" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print" style="color: #fff;"></i></a>';
						}
					}
					
					
					//Whatsapp Action
					if ($order->stare == 2 || $order->stare == 4) {
						$html .= '<a href="' . route('orders.sendWhatsapp', $order->idcmd) . '" class="btn '.($order->whatsapp_sent == 0 ? 'btn-success' : 'btn-danger').'" title="Trimite WhatsApp" target="_blank"><i class="glyphicon glyphicon-comment" style="color: #fff;"></i></a>';
                    }
					
					if (!empty($order->id_factura) && $order->stare == 5) {
						$html .= '<a href="facturi/'.$order->id_factura.'/edit-sub" class="btn btn-default btn-sm" target="_blank"><i class="glyphicon glyphicon-minus"></i></a>';
					}
					
					if ($order->stare == 8 || $order->stare == 5 || $order->stare == 3 || $order->stare == 6 || $order->stare == 7 || $order->stare == 4 || $order->stare == 9) {
                        $type = $order->locatie_mgz == 1 ? 'main' : 'utvin';
						$html .= '<a href="' . route('orders.create') . '?type=' . $type. '&duplicate=' .$order->idcomanda. '" class="btn btn-default" title="Duplicate"><i class="glyphicon glyphicon-copy"></i></a>';
					}	
					
                    $html .= '</div>';
                    
                    return $html;
                })
				->addColumn('observations', function ($order) {
					$html = '<b>__</b>';
					if(!empty($order->observations)){
						$html = '<a href="javascript:void(0)" class="btn btn-danger" title="'.$order->observations.'"><i class="fa fa-info-circle" style="color: #fff; font-size: 18px;"></i></a>';
					}
					return $html;
				})
                ->rawColumns(['data', 'client', 'magazin', 'produs', 'cod', 'furnizor', 'cantitate', 'pret', 'total', 'status','observations', 'actiune'])
                ->with([
                    'monthlyTotal' => $monthlyTotal,
                    'dailyTotal' => $dailyTotal,
					'filteredTotal' => $filteredTotal,
					'fromDate' => !empty($fromDate) ? $fromDate->format('d/m/Y') : '',
					'toDate' => !empty($toDate) ? $toDate->format('d/m/Y') : ''
                ])
                ->make(true);
        } catch (\Exception $e) {
            // Log and return error response
            Log::error('DataTables Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            return response()->json([
                'error' => true,
                'message' => 'DataTables processing error: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Format a row for the DataTables response
     */
    private function formatOrderRow($order, $product = null, $smsRecords = [])
    {
        // Client info
        $clientName = $order->nume;
        if (!empty($order->companie)) {
            $clientName .= '/' . $order->companie;
        }
        
        // Status info
        $statusText = '';
        $labelClass = '';
        
        switch($order->stare) {
            case 0: $statusText = 'Eroare'; $labelClass = 'btn-danger'; break;
            case 1: $statusText = 'Comandat'; $labelClass = 'btn-warning'; break;
            case 2: $statusText = 'Sosit'; $labelClass = 'btn-info'; break;
            case 3: $statusText = 'Cash'; $labelClass = 'btn-success'; break;
            case 4: $statusText = 'Avans'; $labelClass = 'btn-danger'; break;
            case 5: $statusText = 'Retur'; $labelClass = 'btn-success'; break;
            case 6: $statusText = 'Card'; $labelClass = 'btn-success'; break;
            case 7: $statusText = 'FD'; $labelClass = 'btn-success'; break;
        }
        
        // Magazin info
        $magazinName = $order->locatie_mgz == 1 ? 'Timisoara' : 'Utvin';
        $magazinHtml = '<a href="javascript:void(0);" title="Magazin" onclick="obtineAdresa(\'' . $order->idcomanda . '\', \'' . $order->locatie_mgz . '\');" data-toggle="modal" data-target="#mod_adresa"><b>' . $magazinName . '</b></a>';
        
        // Action buttons
        $actionHtml = '<div class="btn-group-vertical btn-group-xs">';
        
        if ($order->stare == 2 || $order->stare == 4) {
            $smsClass = in_array($order->idcomanda, $smsRecords) ? 'btn-danger' : 'btn-success';
            $smsTitle = in_array($order->idcomanda, $smsRecords) ? 'SMS trimis' : 'SMS';
            $actionHtml .= '<a href="javascript:void(0);" class="btn ' . $smsClass . '" title="' . $smsTitle . '" onclick="obtineSms(\'' . $order->idcomanda . '\', \'' . $order->stare . '\');" data-toggle="modal" data-target="#mod_sms"><i class="glyphicon glyphicon-phone"></i></a>';
        } elseif ($order->stare == 1) {
            $actionHtml .= '<a href="javascript:void(0);" class="btn btn-default" title="Editare" onclick="alert(\'Edit functionality temporarily disabled\')"><i class="glyphicon glyphicon-pencil"></i></a>';
            $actionHtml .= '<a href="javascript:void(0);" class="btn btn-warning" title="Sterge" onclick="stergeComanda(\'' . $order->idcomanda . '\')"><i class="glyphicon glyphicon-trash"></i></a>';
        }
        
        if ($order->id_factura == 0) {
            $actionHtml .= '<a href="editare_factura.php?id_comanda=' . $order->idcomanda . '&tip_comanda=0" class="btn btn-default" title="Factureaza" target="_blank"><i class="glyphicon glyphicon-share-alt"></i></a>';
        } else {
            $actionHtml .= '<a href="facturi/print_ff.php?id_factura=' . $order->id_factura . '" target="_blank" class="btn btn-info" title="Tipareste factura"><i class="glyphicon glyphicon-print"></i></a>';
        }
        
        $actionHtml .= '</div>';
        
        // Status button
        $statusHtml = '<a href="javascript:void(0);" class="btn btn-xs ' . $labelClass . '" title="Stare" onclick="obtineStare(\'' . $order->idcomanda . '\');" data-toggle="modal" data-target="#mod_status">' . $statusText . '</a>';
        
        // Format product-related columns
        $produsName = '-';
        $codProdus = '-';
        $furnizor = '-';
        $cantitate = '-';
        $pret = '-';
        
        if ($product) {
            $produsName = $product->denumire;
            
            // Format cod produs with color modal if applicable
            if ($order->stare < 3) {
                $codProdus = '<a href="javascript:void(0);" title="cod produs" onclick="obtineCuloare(\'' . $order->idcomanda . '\', \'' . $product->idprodus . '\');" data-toggle="modal" data-target="#mod_culoare"><b>' . $product->cod_produs . '</b></a>';
            } else {
                $codProdus = '<b>' . $product->cod_produs . '</b>';
            }
            
            // Format furnizor with modal if applicable
            if ($order->stare < 3) {
                $furnizor = '<a href="javascript:void(0);" title="furnizor" onclick="obtineFurnizor(\'' . $order->idcomanda . '\', \'' . $product->idprodus . '\', \'' . ($product->furnizor ?? '__') . '\');" data-toggle="modal" data-target="#mod_furnizor"><b>' . ($product->furnizor ?? '__') . '</b></a>';
            } else {
                $furnizor = '<b>' . ($product->furnizor ?? '__') . '</b>';
            }
            
            $cantitate = $product->cantitate;
            $pret = number_format($product->pret, 2, '.', '');
        }
        
        // Return formatted row
        return [
            'client' => $clientName,
            'telefon' => $order->telefon,
            'marca' => $order->marca ?? '',
            'magazin' => $magazinHtml,
            'produs' => $produsName,
            'cod' => $codProdus,
            'furnizor' => $furnizor,
            'cantitate' => $cantitate,
            'pret' => $pret,
            'total' => number_format($order->total, 2, '.', ''),
            'status' => $statusHtml,
            'actiune' => $actionHtml,
            'DT_RowId' => 'row_' . $order->idcomanda,
            'stare' => $order->stare  // Hidden field for styling
        ];
    }
    

    /**
     * Process child rows for products in the same order.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderProductsData(Request $request)
    {
        $orderId = $request->order_id;
        // Get order products
        $products = DB::table('detaliu')
            ->join('produse', 'detaliu.idprodus', '=', 'produse.idprodus')
            ->where('id_comanda', $orderId)
            ->skip(1) // Skip the first product (already displayed in the main row)
            ->get();
            
        return response()->json(['data' => $products]);
    }


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
			$duplicateOrder = DB::table('comenzi')
			->join('clienti', 'comenzi.idclient', '=', 'clienti.idclienti')
			->where('comenzi.idcomanda', $duplicate)
			->select('comenzi.idcomanda', 'comenzi.data', 'comenzi.idmasina', 'comenzi.stare', 'comenzi.total', 'comenzi.observations', 'comenzi.locatie_mgz', 'clienti.idclienti', 'clienti.nume'
			, 'clienti.marca', 'clienti.telefon', 'clienti.adresa', 'clienti.idmasina as clientidmasina')
			->first();
			
			$duplicateOrderDetails = DB::table('detaliu')
				->join('produse', 'detaliu.idprodus', '=', 'produse.idprodus')
				->where('detaliu.idcomanda', $duplicate)
				->select('detaliu.cantitate', 'detaliu.pret', 'detaliu.furnizor','detaliu.culoare', 'produse.idprodus'
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
		
		$Ordtype = $request->query('type');
        
        return view('orders.create', compact('clients', 'counties', 'currentDate', 'Ordtype', 'duplicateOrder', 'duplicateOrderDetails'));
    }
    
    
    public function addTempProduct(Request $request)
    {
        try {
            Log::info('Received product data:', $request->all());
            
            // फॉर्म वैलिडेशन
            $request->validate([
                'id_produs' => 'required|exists:produse,idprodus',
                'cantitate' => 'required|numeric|min:1',
                'pret' => 'required|numeric|min:0',
				'furnizor' => 'nullable|string|max:255',
                'disponibilitate' => 'nullable|string|max:255'
            ]);
          
            
            $session_id = session()->getId();
            $productId = $request->id_produs;
            $quantity = $request->cantitate;
            $price = $request->pret;
            $furnizor = $request->furnizor ?? '__';
            $disponibilitate = $request->disponibilitate ?? null;
            //echo $session_id; die('as');
            Log::info('Using session ID:', ['session_id' => $session_id]);
            
            // check if product already exists in temporary table
            $existingProduct = DB::table('tmp')
                ->where('session_id', $session_id)
                ->where('id_produs', $productId)
                ->first();
            
            Log::info('Existing product check:', ['exists' => !is_null($existingProduct)]);
            
            if ($existingProduct) {
                // update existing product in temporary table
                $updated = DB::table('tmp')
                    ->where('session_id', $session_id)
                    ->where('id_produs', $productId)
                    ->update([
                        'cantitate_tmp' => $quantity,
                        'pret_tmp' => $price,
                        'furnizor' => $furnizor,
                        'culoare' => $disponibilitate,
                    ]);
                
                Log::info('Updated existing product:', ['updated' => $updated]);
            }
            else {
                // add new product to temporary table
               
                $inserted = Tmp::create([
                    'session_id'    => $session_id,
                    'id_produs'     => $productId,
                    'cantitate_tmp' => $quantity,
                    'pret_tmp'      => $price,
                    'furnizor'      => $furnizor,
					'culoare' => $disponibilitate,
                ]);
                Log::info('Inserted new product:', ['inserted' => $inserted]);
            }
            
            // success response
            Log::info('Successfully added product');
            return response()->json([
                'success' => true,
                'message' => 'Produsul a fost adăugat în coș'
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding product:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }


    // Method to get temporary products for displaying in the form
    public function getTempProducts()
    {
        try {
            $session_id = session()->getId();
            
            // Get temp products with product details
            $products = DB::table('tmp')
                ->leftJoin('produse', 'tmp.id_produs', '=', 'produse.idprodus')
                ->where('tmp.session_id', $session_id)
                ->whereNotNull('tmp.id_produs')
                ->select(
                    'tmp.*',
                    'produse.denumire as ProductName',
                    'produse.cod_produs',
                    'produse.TVA as tva'
                )
                ->orderBy('tmp.id_tmp', 'asc') // Add sorting by id_tmp in descending order
                ->get();
            
            // Calculate total
            $total = 0;
            foreach ($products as $product) {
                $total += $product->cantitate_tmp * $product->pret_tmp;
            }
            
            return response()->json([
                'success' => true,
                'products' => $products,
                'total' => $total
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Method to delete temporary product
    public function deleteTempProduct(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            
            $session_id = session()->getId();
            
            DB::table('tmp')
                ->where('session_id', $session_id)
                ->where('id_tmp', $request->id)
                ->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Product removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }
    

    public function store(Request $request)
    {
		$locatie = (int) $request->input('locatie_mgz', 1); // default to 1
		if ($locatie == 3) {
			// We redirect or forward internally
			app(\App\Http\Controllers\ComenziController::class)->store($request);
			return redirect()->route('comenzi.index')
				->with('success', 'Comanda externă a fost creată cu succes!');
		}
	
        try {
            Log::info('Starting order creation with data:', $request->all());
            DB::beginTransaction();
            
            $validated = $request->validate([
                'id_client' => 'required|integer',
                'data' => 'required|string',
                'idstare' => 'required|integer',
            ]);
            
            $dateObj = \DateTime::createFromFormat('d/m/Y', $request->data);
            $orderDate = $dateObj->format('Y-m-d');
            
            $session_id = session()->getId();
            Log::info('Using session ID for order:', ['session_id' => $session_id]);
            
            // Initial values for marca and idmasina
            $marca = '';
            $idmasina = 1; // Default to 1 instead of NULL
            
            // Get marca value from form
            if ($request->filled('marca')) {
                $marca = $request->marca;
                Log::info('Using marca from form:', ['marca' => $marca]);
            }
            
            // Get machine ID from idmasina_cmd
            if ($request->filled('idmasina_cmd') && $request->idmasina_cmd > 0) {
                $idmasina = $request->idmasina_cmd;
                Log::info('Using idmasina_cmd from form:', ['idmasina_cmd' => $idmasina]);
            }
            
            // If we have marca but no idmasina, search the database
            if (!empty($marca) && $idmasina <= 1) {
                $masina = DB::table('masina')
                    ->where('marca', 'like', '%' . $marca . '%')
                    ->first();
                
                if ($masina) {
                    $idmasina = $masina->idmasina;
                    Log::info('Found idmasina from database for marca:', ['marca' => $marca, 'idmasina' => $idmasina]);
                } else {
                    // If machine not found, create a new one
                    try {
                        $idmasina = DB::table('masina')->insertGetId([
                            'marca' => $marca,
                            'sasiu' => '',
                            'nrmat' => ''
                        ]);
                        Log::info('Created new masina record:', ['marca' => $marca, 'idmasina' => $idmasina]);
                    } catch (\Exception $e) {
                        Log::warning('Could not create masina record:', ['error' => $e->getMessage()]);
                        // If there's an error, fall back to default
                        $idmasina = 1;
                    }
                }
            }
            
            // If we have idmasina but no marca, get the marca
            if (empty($marca) && $idmasina > 1) {
                $masina = DB::table('masina')
                    ->where('idmasina', $idmasina)
                    ->first();
                
                if ($masina && !empty($masina->marca)) {
                    $marca = $masina->marca;
                    Log::info('Found marca from database for idmasina:', ['idmasina' => $idmasina, 'marca' => $marca]);
                }
            }
            
            // Final safety check: if still empty
            if (empty($idmasina) || $idmasina <= 0) {
                $idmasina = 1; // Set default 1
                Log::warning('Setting default idmasina value of 1');
            }
            
            // Get all temporary products
            $tmpProducts = DB::table('tmp')
                ->where('session_id', $session_id)
                ->whereNotNull('id_produs')
                ->get();
            
            if ($tmpProducts->isEmpty()) {
                Log::warning('No products in order!');
                return redirect()->back()
                    ->with('error', 'Nu există produse în comandă!')
                    ->withInput();
            }
            
            // Calculate order total
            $totalOrder = $tmpProducts->sum(function($item) {
                return $item->cantitate_tmp * $item->pret_tmp;
            });
            
            // Get the next available ID for comenzi table
            $maxId = DB::table('comenzi')->max('idcomanda');
            $orderId = $maxId ? $maxId + 1 : 1;
            
            Log::info('Final order data:', [
                'idcomanda' => $orderId,
                'idclient' => $request->id_client,
                'data' => $orderDate,
                'marca' => $marca,
                'idmasina' => $idmasina,
                'total' => $totalOrder,
                'stare' => $request->idstare
            ]);

            // Insert order with explicit idcomanda
           
            $data = Comenzi::create([
                'idcomanda' => $orderId,
                'idclient'  => $request->id_client,
                'userid'  => Auth::user()->Id,
                //'locatie_mgz' => $request->locatie_mgz ?? 1,
                'data'      => $orderDate,
                // 'marca' => $marca,
                'idmasina'  => $idmasina,
                'total'     => $totalOrder,
                'stare'     => $request->idstare,
                'cont_awb'  => $request->cont_awb ?? '',
                'observations'  => $request->observations,
				'locatie_mgz' => $request->locatie_mgz ?? 1,
				'created_at'  => Carbon::now()->timestamp + (2 * 3600),
            ]);

            Log::info('Order created with ID:', ['orderId' => $orderId]);
            Log::info('Adding ' . count($tmpProducts) . ' products to order');
            			           
            // Now insert all products with the same orderId
            foreach ($tmpProducts as $index => $product) {
                $productData = [
                    'idcomanda' => $orderId,
                    'idprodus' => $product->id_produs,
                    'cantitate' => $product->cantitate_tmp,
                    'pret' => $product->pret_tmp,
                    'furnizor' => $product->furnizor ?? '__',
                    'culoare' => $product->culoare ?? 'FFFFFF',
					'created_at'  => Carbon::now()->timestamp + (2 * 3600),
                ];

                $insertId = DB::table('detaliu')->insertGetId($productData);
                
                Log::info('Added product to order:', [
                    'index' => $index + 1,
                    'detaliu_id' => $insertId,
                    'product_data' => $productData
                ]);
            }

            // Double-check that all products were inserted
            $insertedCount = DB::table('detaliu')
                ->where('idcomanda', $orderId)
                ->count();
                
            Log::info('Verification: ' . $insertedCount . ' products inserted for order ' . $orderId);
    //My comment End        ----------
            // Send SMS if applicable
            /*
            if (in_array($request->idstare, [2, 4])) {
                $client = DB::table('clienti')->where('idclienti', $request->id_client)->first();
                if ($client && $client->telefon) {
                    $messageTemplate = $request->locatie_mgz == 1
                        ? config('app.mesaj_tm_sms')
                        : config('app.mesaj_utvin_sms');
                    
                    DB::table('sms')->insert([
                        'idcomanda' => $orderId,
                        'telefon' => $client->telefon,
                        'mesaj' => $messageTemplate,
                        'data' => now(),
                        'status' => 'pending'
                    ]);
                    
                    try {
                        // SMS sending logic here
                        DB::table('sms')
                            ->where('idcomanda', $orderId)
                            ->update(['status' => 'sent']);
                    } catch (\Exception $smsException) {
                        Log::error('SMS sending failed: ' . $smsException->getMessage());
                        DB::table('sms')
                            ->where('idcomanda', $orderId)
                            ->update(['status' => 'failed']);
                    }
                }
            }
            */
            
            //Clean up temporary products
			$deletedCount = DB::table('tmp')->where('session_id', $session_id)->delete();
			Log::info('Deleted ' . $deletedCount . ' temporary products');
				
			DB::commit();
            
			$ordertype = $request->locatie_mgz ?? 1;
			if($ordertype == 2){
				return redirect()->route('orders.index',['type' => 'utvin'])->with('success', 'Comanda a fost creată cu succes!');
			}
			
            return redirect()->route('orders.index')->with('success', 'Comanda a fost creată cu succes!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating order:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Eroare la crearea comenzii: ' . $e->getMessage());
        }
    }


    public function edit($orderId)
    {
        //get session id
        $session_id = session()->getId();

        // Find order or return 404
        $order = DB::table('comenzi')
        ->join('clienti', 'comenzi.idclient', '=', 'clienti.idclienti')
        ->where('comenzi.idcomanda', $orderId)
        ->select('comenzi.idcomanda', 'comenzi.data', 'comenzi.idmasina', 'comenzi.stare', 'comenzi.total', 'comenzi.observations', 'comenzi.locatie_mgz', 'clienti.idclienti', 'clienti.nume'
        , 'clienti.marca', 'clienti.telefon', 'clienti.adresa')
        ->first();
        
        if (!$order) {
            return redirect()->route('orders.index')
                ->with('error', 'Comanda nu a fost găsită!');
        }
        
        // Get client details
        //$client = DB::table('clienti')->where('idclienti', $order->idclient)->first();
        
        // Get order details
        /*$orderDetails = DB::table('detaliu')
            ->join('produse', 'detaliu.idprodus', '=', 'produse.idprodus')
            ->where('detaliu.idcomanda', $orderId)
            ->select('detaliu.cantitate', 'detaliu.pret', 'produse.idprodus', 'produse.denumire', 'produse.cod_produs', 'produse.TVA', 'produse.um')
            ->get();*/

        // delete tmp invoice products to prevent loading dummy data
        DB::table('tmp')->where('session_id', $session_id)->delete();

        // Get order details
        $orderDetails = DB::table('detaliu')
            ->join('produse', 'detaliu.idprodus', '=', 'produse.idprodus')
            ->where('detaliu.idcomanda', $orderId)
            ->select('detaliu.cantitate', 'detaliu.pret', 'detaliu.furnizor','detaliu.culoare', 'produse.idprodus'
                , 'produse.denumire', 'produse.cod_produs', 'produse.TVA', 'produse.um')
            ->get();

        // Add products to temporary detaliu table
        foreach ($orderDetails as $detail) {
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

        // Get counties for client modal
        $counties = Localitate::select('judet')->distinct()->orderBy('judet')->get();
		

		$hasRelations =
			DB::table('facturi')->where('id_comanda', $orderId)->exists() ||
			DB::table('facturidetails')->where('OrderID', $orderId)->exists() ||
			DB::table('sms')
				->where(function ($q) use ($orderId) {
					$q->where('idcomanda', $orderId);
				})
				->exists();
        
        return view('orders.edit', [
            'order' => $order,
            //'client' => $client,
            'orderDetails' => $orderDetails,
            'counties' => $counties,
			'hasRelations' => $hasRelations
        ]);
    }

    /**
     * Update an existing order.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        try {
            // Log::info('Starting order update with data:', $request->all());
            DB::beginTransaction();
			
			if ($request->filled('locatie_mgz') && $request->locatie_mgz == 3) {
				try {
					$order = DB::table('comenzi')->where('idcomanda', $id)->first();
					if (!$order) {
						throw new \Exception('Comanda nu a fost găsită!');
					}
					
					// 1. Generate new ID for comenzi_ext
					$lastOrder = DB::table('comenzi_ext')->orderBy('idcomanda', 'desc')->first();
					$newOrderId = $lastOrder ? $lastOrder->idcomanda + 1 : 1;

					// Insert into comenzi_ext
					$orderData = (array) $order;
					unset($orderData['idcomanda']);
					unset($orderData['locatie_mgz']);
					$orderData['idcomanda'] = $newOrderId;
					DB::table('comenzi_ext')->insert($orderData);

					// Copy detalii → detaliu_ext
					$details = DB::table('detaliu')->where('idcomanda', $id)->get();
					foreach ($details as $d) {
						DB::table('detaliu_ext')->insert([
							'idcomanda' => $newOrderId,
							'idprodus' => $d->idprodus,
							'pret' => $d->pret,
							'cantitate' => $d->cantitate,
							'culoare' => $d->culoare,
							'furnizor' => $d->furnizor,
							'created_at' => $d->created_at,
						]);
					}

					// Delete old order & details
					DB::table('detaliu')->where('idcomanda', $id)->delete();
					DB::table('comenzi')->where('idcomanda', $id)->delete();

				} catch (\Exception $e) {
					DB::rollBack();
					Log::error('Error moving order to Externe', [
						'order_id' => $id,
						'message' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					]);
					
					return redirect()->back()->with('error', 'Eroare la mutarea comenzii: ' . $e->getMessage());
				}

				DB::commit();
				return redirect()->route('comenzi.index')->with('success', 'Comanda a fost mutată la Externe cu succes!');
			}
 
            //get session id
            $session_id = session()->getId();

            // Validate request
            $validated = $request->validate([
                'id_client' => 'required|integer',
                'idstare' => 'required|integer',
                'marca' => 'nullable|string',
                'observations' => 'nullable|string',
            ]);

            // Find order or return 404
            $order = DB::table('comenzi')->where('idcomanda', $id)->first();

            if (!$order) {
                return redirect()->route('orders.index')->with('error', 'Comanda nu a fost găsită!');
            }

            // Initial values for idmasina
            $idmasina = $request->idmasina_cmd ?? 1;

            //sterg din detalii comanda
            DB::table('detaliu')->where('idcomanda', $id)->delete();

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

                DB::table('detaliu')->insert($orderDetailData);
            }

            // Update the main order information
            DB::table('comenzi')->where('idcomanda', $id)
                ->update([
                    'idclient' => $request->id_client,
                    'idmasina' => $idmasina,
                    'stare' => $request->idstare,
                    'total' => $totalAmount,
                    'observations' => $request->observations,
					'locatie_mgz' => $request->locatie_mgz ?? 1,
                ]);

            // Delete tmp invoice products
            DB::table('tmp')->where('session_id', $session_id)->delete();

            DB::commit();
			
			$ordertype = $request->locatie_mgz ?? 1;
			if($ordertype == 2){
				return redirect()->route('orders.index', ['type' => 'utvin'])->with('success', 'Comanda a fost creată cu succes!');
			}

            return redirect()->route('orders.index')->with('success', 'Comanda a fost actualizată cu succes!');
        }
        catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating order:', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->withInput()->with('error', 'Eroare la actualizarea comenzii: ' . $e->getMessage());
        }
    }


    /**
     * Update a product in a order.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProduct(Request $request, $id)
    {
        try {
            Log::info('Updating product in order:', $request->all());
            DB::beginTransaction();
            
            // Validate request
            $validated = $request->validate([
                'id_produs' => 'required|exists:produse,idprodus',
                'cantitate' => 'required|numeric|min:1',
                'pret' => 'required|numeric|min:0',
                'tva' => 'nullable|numeric',
                'furnizor' => 'nullable|string|max:255'
            ]);
            
            // Check if order exists
            $order = DB::table('comenzi')->where('idcomanda', $id)->first();
            if (!$order) {
                throw new \Exception('Comanda nu a fost găsită');
            }
            
            // Check if the product already exists in the order
            $existingProduct = DB::table('detaliu')
                ->where('idcomanda', $id)
                ->where('idprodus', $request->id_produs)
                ->first();
            
            if ($existingProduct) {
                // Update existing product
                DB::table('detaliu')
                    ->where('idcomanda', $id)
                    ->where('idprodus', $request->id_produs)
                    ->update([
                        'cantitate' => $request->cantitate,
                        'pret' => $request->pret,
                        'furnizor' => $request->furnizor ?? '__'
                    ]);
                
                Log::info('Updated existing product in order:', [
                    'order_id' => $id,
                    'product_id' => $request->id_produs
                ]);
            } else {
                // Add new product to order
                DB::table('detaliu')->insert([
                    'idcomanda' => $id,
                    'idprodus' => $request->id_produs,
                    'cantitate' => $request->cantitate,
                    'pret' => $request->pret,
                    'furnizor' => $request->furnizor ?? '__',
                    'culoare' => 'FFFFFF'
                ]);
                
                Log::info('Added new product to order:', [
                    'order_id' => $id,
                    'product_id' => $request->id_produs
                ]);
            }
            
            // Recalculate order total
            $orderTotal = DB::table('detaliu')
                ->where('idcomanda', $id)
                ->sum(DB::raw('cantitate * pret'));
            
            // Update order total
            DB::table('comenzi')
                ->where('idcomanda', $id)
                ->update([
                    'total' => $orderTotal ?? 0
                ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Produsul a fost actualizat în comandă',
                'total' => $orderTotal ?? 0
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating product in order:', [
                'order_id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare la actualizarea produsului: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Delete a product from an order.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteProduct(Request $request, $id)
    {
        try {
            Log::info('Deleting product from order:', $request->all());
            DB::beginTransaction();
            
            // Validate request
            $validated = $request->validate([
                'id_produs' => 'required|exists:produse,idprodus'
            ]);
            
            // Check if order exists
            $order = DB::table('comenzi')->where('idcomanda', $id)->first();
            if (!$order) {
                throw new \Exception('Comanda nu a fost găsită');
            }
            
            // Delete product from order
            $deleted = DB::table('detaliu')
                ->where('idcomanda', $id)
                ->where('idprodus', $request->id_produs)
                ->delete();
            
            if (!$deleted) {
                throw new \Exception('Produsul nu a fost găsit în comandă');
            }
            
            Log::info('Deleted product from order:', [
                'order_id' => $id,
                'product_id' => $request->id_produs
            ]);
            
            // Recalculate order total
            $orderTotal = DB::table('detaliu')
                ->where('idcomanda', $id)
                ->sum(DB::raw('cantitate * pret'));
            
            // Update order total
            DB::table('comenzi')
                ->where('idcomanda', $id)
                ->update([
                    'total' => $orderTotal ?? 0
                ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Produsul a fost șters din comandă',
                'total' => $orderTotal ?? 0
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting product from order:', [
                'order_id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare la ștergerea produsului: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Update a product in a temporary order.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProductTmp(Request $request, $orderId)
    {
        try {
            //get session id
            $session_id = session()->getId();

            // Log::info('Updating product in order:', $request->all());
            DB::beginTransaction();

            // Validate request
            $validated = $request->validate([
                'id_produs' => 'required|exists:produse,idprodus',
                'cantitate' => 'required|numeric|min:1',
                'pret' => 'required|numeric',
                'tva' => 'nullable|numeric',
                'furnizor' => 'nullable|string|max:255',
                'disponibilitate' => 'nullable|string|max:255'
            ]);

            // Check if order exists
            $order = DB::table('comenzi')->where('idcomanda', $orderId)->first();
            if (!$order) {
                throw new \Exception('Comanda nu a fost găsită');
            }

            // Add product to temorary order
            DB::table('tmp')->insert([
                'id_produs' => $request->id_produs,
                'cantitate_tmp' => $request->cantitate,
                'pret_tmp' => $request->pret,
				'furnizor' => $request->furnizor ?? '__',
                'culoare' => $request->disponibilitate ?? null,
                'session_id' => $session_id,
            ]);

            // Log::info('Added new product to order:', [
            //     'order_id' => $orderId,
            //     'product_id' => $request->id_produs
            // ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produsul a fost actualizat în comandă',
            ]);
        }
        catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating product in order:', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Eroare la actualizarea produsului: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Delete a product from a temporary order.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteProductTmp(Request $request, $orderId)
    {
        try {
            //get session id
            $session_id = session()->getId();
            DB::beginTransaction();
            
            // Validate request
            $validated = $request->validate([
                'id_produs' => 'required|exists:produse,idprodus'
            ]);
            
            // Check if order exists
            $order = DB::table('comenzi')->where('idcomanda', $orderId)->first();
            if (!$order) {
                throw new \Exception('Comanda nu a fost găsită');
            }

            // Delete product from order
            $deleted = DB::table('tmp')->where('session_id', $session_id)->where('id_produs', $request->id_produs)->delete();
            
            if (!$deleted) {
                throw new \Exception('Produsul nu a fost găsit în comandă');
            }
                        
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Produsul a fost șters din comandă',
                'total' => 0
            ]);
            
        }
        catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting product from order:', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare la ștergerea produsului: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Search for products based on query and paginate results
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
                    'total_products' => $totalProducts,
                    'per_page' => $perPage
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


    public function destroy($id)
    {
        // Start transaction for data integrity
        DB::beginTransaction();
        
        try {
            // Delete related records
            DB::table('detaliu')->where('idcomanda', $id)->delete();
            DB::table('sms')->where('idcomanda', $id)->delete();
            
            // Delete order
            DB::table('comenzi')->where('idcomanda', $id)->delete();
			
            DB::table('incasari')->where('idcmd', $id)->delete();
            
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
	

	public function sendWhatsapp($id)
	{
		$order = DB::table('comenzi')
                ->join('clienti', 'comenzi.idclient', '=', 'clienti.idclienti')
				->leftJoin('detaliu', 'comenzi.idcomanda', '=', 'detaliu.idcomanda')
				->leftJoin('produse', 'detaliu.idprodus', '=', 'produse.idprodus')
                ->select(
                    'comenzi.*',
                    'clienti.nume',
                    'clienti.companie',
                    'clienti.telefon',
                    'clienti.adresa'
                )
				->distinct()
                ->orderBy('comenzi.idcomanda', 'desc')->where('comenzi.idcmd', $id)->first();

		$phoneNumber = $order->telefon;
		$phoneNumber = preg_replace('/\D+/', '', $phoneNumber);
		if (!str_starts_with($phoneNumber, '40')) {
			$phoneNumber = '+40' . ltrim($phoneNumber, '0');
		}

		$magazinName = $order->locatie_mgz == 1 ? 'Timisoara' : 'Utvin';
		$clientName  = $order->nume;
		$total       = is_numeric($order->total) ? $order->total : 0;
		
		if($order->stare == 4){
			$total = 0;
		}

        $storeUrl = $order->locatie_mgz == 1
            ? 'https://tinyurl.com/BesoiuPieseAutoTimisoara'
            : 'https://tinyurl.com/BesoiuPieseAutoUtvin';

        $templateBody = MessageTemplate::getTemplate('order_pickup', 'whatsapp');

        $messageText = strtr($templateBody, [
            '{{client_name}}' => $clientName,
            '{{store_name}}'  => $magazinName,
            '{{total}}'       => $total,
            '{{store_url}}'   => $storeUrl,
        ]);

        $message = rawurlencode($messageText);

		$whatsappUrl = "https://web.whatsapp.com/send/?phone={$phoneNumber}&text={$message}";

		// save to DB
		DB::table('comenzi')
		->where('idcmd', $id)
		->update([
			'whatsapp_sent' => 1,
			'whatsapp_sent_at' => now(),
		]);

		// redirect to WhatsApp
		return redirect()->away($whatsappUrl);
	}
    

    /**
     * Update location for an order
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateLocation(Request $request)
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|integer',
                'location' => 'required|in:1,2'
            ]);
            
            // Check if order exists first
            $order = DB::table('comenzi')
                ->where('idcomanda', $request->order_id)
                ->first();
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comanda nu există!'
                ], 404);
            }
            
            // Check if location is already set to this value
            if ($order->locatie_mgz == $request->location) {
                return response()->json([
                    'success' => true,
                    'message' => 'Locația este deja setată la această valoare.',
                    'location_name' => $request->location == 1 ? 'Timisoara' : 'Utvin'
                ]);
            }
            
            // Perform update
            $updated = DB::table('comenzi')
                ->where('idcomanda', $request->order_id)
                ->update(['locatie_mgz' => $request->location]);
            
            $locationName = $request->location == 1 ? 'Timisoara' : 'Utvin';
            
            return response()->json([
                'success' => true,
                'message' => 'Locația a fost actualizată cu succes!',
                'location_name' => $locationName
            ]);
            
        } catch (\Exception $e) {
            Log::error('Location Update Error', [
                'message' => $e->getMessage(),
                'order_id' => $request->order_id,
                'location' => $request->location
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare tehnică la actualizare: ' . $e->getMessage()
            ], 500);
        }
    }
 

    /**
     * Update the status of an order
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'order_id' => 'required|integer',
                'stare' => 'required|in:1,2,3,4,5,6,7,8,9,10,11,12,13'
            ]);

            $orderID = $request->order_id;
            $statusCode = intval($request->stare);

            // Get order object first to check current status
            $order = DB::table('comenzi')->where('idcomanda', $orderID)->first();
			
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Comanda nu există!'], 404);
            }

            // Get old status before updating
            $oldStatus = $order->stare ?? null;

            DB::beginTransaction();

            // Log status change in history table
            OrderStatusHistory::create([
                'order_id' => $orderID,
                'old_status' => $oldStatus,
                'new_status' => $statusCode,
                'user_id' => Auth::user()->Id,
            ]);

            // Update status in the database
            DB::table('comenzi')->where('idcomanda', $orderID)->update(['stare' => $statusCode]);

            if ($statusCode === 2) {
                //color update in case status is sosit
                // Update color in detaliu table
                $color_code = "FFFFFF";
                DB::table('detaliu')->where('idcomanda', $orderID)->update(['culoare' => $color_code]);
            } elseif ($statusCode === 8) {
				// Anulat → draft grey
				DB::table('detaliu')->where('idcomanda', $orderID)->update(['culoare' => '808080']);

			} elseif (in_array($statusCode, [3,4,5,6,7,9,10,11,12,13], true)) {

                // we record the receipt
                if ($order->idclient > 0) {
                    // Set suma to 0 for all statuses to prevent Incasari from showing amounts
                    $total = $order->total ? $order->total : 0;
                    // Current date in Y-m-d format
                    $currentDate = date('Y-m-d');
                    $data_time = now()->format('H:i:s');

                    //we search if the receipt exists and update it, otherwise we insert it
                    $existingClient = DB::table('incasari')->where('idcmd', $orderID)->value('idclient');
                    if ($existingClient) {
                        // Update existing record
                        DB::table('incasari')->where('idcmd', $orderID)->update(['idstare' => $statusCode, 'userid' => Auth::user()->Id, 'suma' => $total, 'locatie_mgz' => $order->locatie_mgz]);
                    }
                    else {
                        // Insert new record
                        DB::table('incasari')->insert(['idcmd' => $orderID, 'userid' => Auth::user()->Id, 'idstare' => $statusCode,
                        'suma' => $total,'idclient' => $order->idclient,'locatie_mgz' => $order->locatie_mgz, 'data' => $currentDate, 'data_time' => $data_time]);
                    }
                }
            }

            DB::commit();

            // Log::info('Status updated successfully:', [
            //     'order_id' => $request->order_id,
            //     'stare' => $newStatus,
            //     'status_text' => $statusMap[$newStatus]
            // ]);

            return response()->json([
                'success' => true,
                'message' => 'Status actualizat cu succes!',
            ]);
        }
        catch (\Exception $e) {
            DB::rollBack();

            // Log::error('Error updating status:', [
            //     'message' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString()
            // ]);

            return response()->json([
                'success' => false,
                'message' => 'Eroare la actualizarea statusului: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Update color for a product in an order
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateColor(Request $request)
    {
        try {
            Log::info('Updating color:', $request->all());
            
            // Validate request
            $validated = $request->validate([
                'order_id' => 'required|integer',
                'product_id' => 'required|integer',
                'color' => 'required|string|max:7'  // Increased to 7 to accommodate hashtags if needed
            ]);
            
            // First, verify if the record exists
            $record = DB::table('detaliu')
                ->where('idcomanda', $request->order_id)
                ->where('idprodus', $request->product_id)
                ->first();
                
            if (!$record) {
                Log::warning('Record not found for color update:', [
                    'order_id' => $request->order_id,
                    'product_id' => $request->product_id
                ]);
                
                // Check if there's any record for this order
                $orderDetails = DB::table('detaliu')
                    ->where('idcomanda', $request->order_id)
                    ->get();
                    
                if ($orderDetails->isEmpty()) {
                    Log::warning('No details found for order:', [
                        'order_id' => $request->order_id
                    ]);
                } else {
                    Log::info('Available product IDs for this order:', [
                        'order_id' => $request->order_id,
                        'product_ids' => $orderDetails->pluck('idprodus')->toArray()
                    ]);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Nu s-a putut actualiza culoarea. Produsul nu există în comandă.'
                ], 404);
            }
            
            // Update color in detaliu table
            $updated = DB::table('detaliu')
                ->where('idcomanda', $request->order_id)
                ->where('idprodus', $request->product_id)
                ->update([
                    'culoare' => $request->color
                ]);
            
            Log::info('Color updated successfully:', [
                'order_id' => $request->order_id,
                'product_id' => $request->product_id,
                'color' => $request->color
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Culoare actualizată cu succes!',
                'color' => $request->color
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating color:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare la actualizarea culorii: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Update supplier for a product in an order
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSupplier(Request $request)
    {
        try {
            // Log::info('Updating supplier:', $request->all());
            DB::beginTransaction();

            // Validate request
            $validated = $request->validate([
                'order_id' => 'required|integer',
                'product_id' => 'required|integer',
                'supplier' => 'required|string|max:4'
            ]);

            // Update supplier in detaliu table
            DB::table('detaliu')
                ->where('idcomanda', $request->order_id)
                ->where('idprodus', $request->product_id)
                ->update([
                    'furnizor' => $request->supplier
                ]);
            DB::commit();

            // Log::info('Supplier updated successfully:', [
            //     'order_id' => $request->order_id,
            //     'product_id' => $request->product_id,
            //     'supplier' => $request->supplier
            // ]);

            return response()->json([
                'success' => true,
                'message' => 'Furnizor actualizat cu succes!',
                'supplier' => $request->supplier
            ]);
        }
        catch (\Exception $e) {
            DB::rollBack();

            // Log::error('Error updating supplier:', [
            //     'message' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString()
            // ]);

            return response()->json([
                'success' => false,
                'message' => 'Eroare la actualizarea furnizorului: ' . $e->getMessage()
            ], 500);
        }
    }


    public function updateTotal(Request $request)
    {
        $request->validate([
            'order_id' => 'required',
            'total' => 'required|numeric'
        ]);
        
        DB::table('comenzi')
            ->where('idcomanda', $request->order_id)
            ->update(['total' => $request->total]);
        
        return response()->json(['success' => true]);
    }
    

    /**
     * Get customer information for SMS
     */
    public function getCustomerInfo(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            
            if (!$orderId) {
                return response()->json(['success' => false,'message' => 'Order ID is required'], 400);
            }
            
            // Get order with client info with complete details
            $order = DB::table('comenzi')
                ->join('clienti', 'comenzi.idclient', '=', 'clienti.idclienti')
                ->where('comenzi.idcomanda', $orderId)
                ->select('clienti.nume', 'clienti.companie', 'clienti.telefon', 'comenzi.total', 'comenzi.locatie_mgz', 'comenzi.stare')
                ->first();
                
            if (!$order) {
                return response()->json(['success' => false,'message' => 'Order not found'], 404);
            }
            
            // Format client name with company if available
            $clientName = $order->nume;
            if (!empty($order->companie)) {
                $clientName .= ' / ' . $order->companie;
            }
            
            // Format total for display
            $total = $order->total ? number_format($order->total, 2, '.', '') : "0.00";
            
            // Determine store name and URL based on location
            $storeName = $order->locatie_mgz == 1 ? 'Timisoara' : 'Utvin';
            $storeUrl = $order->locatie_mgz == 1 
                ? 'http://tinyurl.com/BesoiuPieseAutoTimisoara' 
                : 'http://tinyurl.com/BesoiuPieseAutoUtvin';
            
            // Get appropriate SMS template based on status
            $templateCode = ($order->stare == 4) ? 'order_pickup_no_total' : 'order_pickup_with_total';
            $templateBody = MessageTemplate::getTemplate($templateCode, 'sms');
            
            // Replace template variables
            $defaultMessage = strtr($templateBody, [
                '{{client_name}}' => $clientName,
                '{{store_name}}'  => $storeName,
                '{{total}}'       => $total,
                '{{store_url}}'   => $storeUrl,
            ]);
            
            return response()->json([
                'success' => true,
                'client_name' => $clientName,
                'phone' => $order->telefon,
                'location' => $order->locatie_mgz,
                'status' => $order->stare,
                'total' => $total,
                'default_message' => $defaultMessage
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting customer info:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Send SMS to customer
     */
    public function sendSms(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'order_id' => 'required|integer',
                'phone' => 'required|string',
                'message' => 'required|string'
            ]);

            // Get order info for logging
            $order = DB::table('comenzi')->where('idcomanda', $request->order_id)->first();
            if (!$order) {
                return response()->json(['success' => false,'message' => 'Order not found'], 404);
            }

            // Format phone number (remove any non-numeric characters)
            $phoneNumber = preg_replace('/\s+/', '', $request->phone);
            $phoneNumber = str_replace(['+', '-', '(', ')', '.'], '', $phoneNumber);
			if (substr($phoneNumber, 0, 1) !== '4') {
				$phoneNumber = '+4' . $phoneNumber;
			} else {
				$phoneNumber = '+' . $phoneNumber;
			}

            // SMS API credentials
			$apiEndpoint = $this->smsApiUrl;
			$authorization = "Bearer ".$this->smsApiKey;
            
            // Prepare API request data
            $mesaj_sms = $request->message ?? '';
            $mesaj = (strlen(trim($mesaj_sms)) > 160) ? (substr(trim($mesaj_sms), 0, 159)) : trim($mesaj_sms);

			$data = [
				"from"    => "3737",
				"to"      => $phoneNumber,
				"message" => $mesaj,
			];

            // Convert to JSON
            $dataJson = json_encode($data);

            // Set headers
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

				// Save SMS record
				DB::table('sms')->insert([
					'idcomanda'     => $request->order_id,
					'idcomanda_ext' => 0,
					'status'        => 'Trimis',
					'cost'          => $cost,
					'data_exp'          => now(),
					'idprimit'      => $messageId,
				]);

				return response()->json([
					'success' => true,
					'message' => 'SMS-ul a fost trimis cu succes!',
					'details' => [
						'msg_id' => $messageId,
						'cost'   => $cost,
					],
				]);
			} else {
				// Extract error code
				$errorCode = str_replace("ERROR:", "", $response);
				return response()->json([
					'success' => false,
					'message' => "Eroare la trimiterea SMS-ului (cod $errorCode)",
				], 500);
			}
        } catch (\Exception $e) {
            Log::error('Error sending SMS:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Eroare: ' . $e->getMessage()
            ], 500);
        }
    }


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
     * show edit invoice page
     */
    public function showEditFactura($id)
    {
        //get session id
        $session_id = session()->getId();

        // Find order or return 404
        $order = DB::table('comenzi')->where('idcomanda', $id)->first();
        
        if (!$order) {
            return redirect()->route('orders.index')
                ->with('error', 'Comanda nu a fost găsită!');
        }
        
        // Get client details
        $client = DB::table('clienti')->where('idclienti', $order->idclient)->first();
        
        // delete tmp invoice products to prevent loading dummy data
        DB::table('tmp')->where('session_id', $session_id)->delete();

        // Get order details
        $orderDetails = DB::table('detaliu')
            ->join('produse', 'detaliu.idprodus', '=', 'produse.idprodus')
            ->where('detaliu.idcomanda', $id)
            ->select('detaliu.cantitate', 'detaliu.pret', 'produse.idprodus', 'produse.denumire', 'produse.cod_produs', 'produse.TVA', 'produse.um')
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
        $facturiData = ['EmployeeID' => 2, 'tip_incas' => 1];

        //get employees details
        $employees = DB::table('employees')->orderBy('LastName')->select('*')->get();

        //id_plata
        $tipPlatas = DB::table('tip_plata')->orderBy('id_plata')->select('*')->get();

        // localitati
        $counties = DB::table('localitati')
            ->select('judet')
            ->distinct()
            ->orderBy('judet')
            ->get();

        $date = Carbon::now()->format('d/m/Y');
        $dateObj = \DateTime::createFromFormat('d/m/Y', $date);
        $currentDate = $dateObj->format('d/m/Y');
        $dueDate = $dateObj->format('d/m/Y');

        return view('orders.edit_factura', [
            'order' => $order,
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
     * invoice products from tmp table
     */
    public function orderFacturaProduct($quantity_multiplier)
    {
        //get session id
        $session_id = session()->getId();

        // Get order details from temporary table
        $orderDetails = DB::table('tmp')
            ->join('produse', 'tmp.id_produs', '=', 'produse.idprodus')
            ->where('tmp.session_id', $session_id)
            ->select('tmp.cantitate_tmp as cantitate', 'tmp.pret_tmp as pret', 'produse.idprodus', 'produse.denumire', 'produse.cod_produs', 'produse.TVA', 'produse.um')
            ->orderBy('tmp.id_tmp', 'asc') // Add sorting by id_tmp in descending order
            ->get();

        return view('orders.editare_facturare', ['orderDetails' => $orderDetails, 'quantity_multiplier' => $quantity_multiplier, 'cache_buster' => Carbon::now()->timestamp + (2 * 3600)]);
    }


    /**
     * get products from tmp table
     */
    public function tempOrderProduct()
    {
        //get session id
        $session_id = session()->getId();

        // Get order details from temporary table
        $orderDetails = DB::table('tmp')
            ->join('produse', 'tmp.id_produs', '=', 'produse.idprodus')
            ->where('tmp.session_id', $session_id)
            ->select('tmp.cantitate_tmp as cantitate', 'tmp.pret_tmp as pret', 'produse.idprodus', 'produse.denumire', 'produse.cod_produs', 'produse.TVA')
            ->orderBy('tmp.id_tmp', 'asc') // Add sorting by id_tmp in descending order
            ->get();

        return view('orders.editare_order', ['orderDetails' => $orderDetails, 'cache_buster' => Carbon::now()->timestamp + (2 * 3600)]);
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
            $order = DB::table('comenzi')
                ->join('clienti', 'comenzi.idclient', '=', 'clienti.idclienti')
                ->where('comenzi.idcomanda', $orderId)
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
            $totalOrderPrice = 0;
            foreach ($orderDetails as $detail) {
                $totalOrderPrice += $detail->cantitate * $detail->pret;
            }
            
            // Check if total price is 0 or less - prevent invoice generation
            if ($totalOrderPrice <= 0) {
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

                $invoiceData = [
                    'OrderID' => $invoiceNumber,
                    'CustomerID' => $id_client,
                    'EmployeeID' => $id_vanzator,
                    'OrderDate' => $currentDate,
                    'RequiredDate' => $data_scadenta,
                    'seria' => $request->invoice_type == "internal" ? 'BPA_C' : 'BPA_CAI',
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
                DB::table('comenzi')->where('idcomanda', $orderId)->update(['id_factura' => $invoiceNumber]);

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

            Log::error('Error generating invoice PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => ' Failed to generate invoice: ' . $e->getMessage()
            ]);
        }
    }


    /**
     * print invice
     */
    public function printInvoice($invoice_id)
    {
        try {
            // Get invoice data with client information
            $factura = DB::table('facturi')
                ->where('OrderID', $invoice_id)
                ->first();
                
            if (!$factura) {
                return redirect()->route('orders.index')
                    ->with('error', 'Invoice not found');
            }
            
            // Get client data - make sure it's working correctly
            $client = DB::table('clienti')
                ->where('idclienti', $factura->CustomerID)
                ->first();
                
            // Add client to factura object for the template
            $factura->client = $client;
            
            // Get invoice details
            $details = DB::table('facturidetails')
                ->join('produse', 'facturidetails.ProductID', '=', 'produse.idprodus')
                ->where('facturidetails.OrderID', $invoice_id)
                ->select(
                    'facturidetails.*',
                    'produse.denumire as produs',
                    DB::raw('facturidetails.UnitPrice as pret_unitar'),
                    DB::raw('facturidetails.Quantity as cantitate')
                )
                ->get();
            
            // Log for debugging
            Log::info('Client data:', [
                'customer_id' => $factura->CustomerID,
                'client' => $client,
                'factura' => $factura
            ]);
            
            // Determine payment type name
            $numeTipPlata = "ACHITAT";
            if ($factura->tip_incas == 1) {
                $numeTipPlata = "ACHITAT";
            } elseif ($factura->tip_incas == 2) {
                $numeTipPlata = "CARD";
            } elseif ($factura->tip_incas == 3) {
                $numeTipPlata = "OP";
            }
            
            // Render view
            return view('orders.print', [
                'factura' => $factura,
                'client' => $client, // Pass client separately as well
                'details' => $details,
                'numeTipPlata' => $numeTipPlata
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading invoice: ' . $e->getMessage());
            return redirect()->route('orders.index')
                ->with('error', 'Error loading invoice: ' . $e->getMessage());
        }
    }


    /**
     * generate invoice PDF
     *
     * @param $invoice_id
     * @return nothing
     */
    public function generatePdf($invoice_id, SmartBillService $smartBillService)
    {
        try {
            // margins
            $margins = [
                'l' => 15,
                't' => 15,
                'r' => 15,
            ]; /* l: Left Side , t: Top Side , r: Right Side */

            // A4 width and height in mm
            $document = ['w' => 210,'h' => 297];

            $maxImageDimensions = [230, 130];

            // Get invoice data with client information
            $factura = DB::table('facturi')
                    ->join('employees', 'employees.EmployeeId', '=', 'facturi.EmployeeID')
                    ->join('clienti', 'clienti.idclienti', '=', 'facturi.CustomerID')
                    ->leftJoin('localitati', 'localitati.idlocatie', '=', 'clienti.idlocalitate')
                    ->join('tip_plata', 'tip_plata.id_plata', '=', 'facturi.tip_incas')
                    ->where('facturi.OrderID', $invoice_id)
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
                ->where('facturidetails.OrderID', $invoice_id)
                ->select('facturidetails.UnitPrice','facturidetails.Quantity','facturidetails.tva','facturidetails.total','produse.denumire','produse.um')
                ->get();
					
			$invoiceType = $factura->generation_method ?? 'manual';
			if ($invoiceType === 'smartbill') {
				if (!empty($factura->smartbill_invoice_id)) {
					$pdfContent = $smartBillService->getInvoice(
						'RO31298897',
						'BPA_CAI',
						$factura->smartbill_invoice_id
					);


					if (!$pdfContent) {
						return response()->json(['error' => 'Invoice PDF not found'], 404);
					}

					return response($pdfContent, 200)
						->header('Content-Type', 'application/pdf')
						->header('Content-Disposition', "inline; filename=\"BPA_CAI_{$factura->smartbill_invoice_id}.pdf\"");
				} else {
					return $this->generateSmartBillInvoice($factura, $details, $smartBillService);
				}
			}else{
				if (empty($factura->smartbill_invoice_id)) {
					$lastSmart = Factura::whereIn('generation_method', ['internal', 'manual'])->where('OrderID', '!=', $invoice_id)->max('smartbill_invoice_id');
					$newInvID = $lastSmart ? $lastSmart + 1 : 1;
					Factura::where('OrderID', $invoice_id)->update([
						'smartbill_invoice_id' => $newInvID,
					]);
				}
			}
			
			$freshFactura = DB::table('facturi')
				->where('OrderID', $invoice_id)
				->select('smartbill_invoice_id')
				->first();


            $localitate = !empty($factura->localitate) ? $factura->localitate : '';
            $adresa_client1 = !empty($factura->adresa) ? $factura->adresa : '';
            $judet = !empty($factura->judet) ? $factura->judet : '';
            $cont_client = !empty($factura->cont_banca) ? $factura->cont_banca : '';
            $regcom = !empty($factura->regcom) ? $factura->regcom : '';
            $banca_client = !empty($factura->nume_banca) ? $factura->nume_banca : '';
            $cif = !empty($factura->cif) ? $factura->cif : '';
            $client = !empty($factura->companie) ? $factura->companie : (!empty($factura->nume) ? $factura->nume : '');
            $den_incas = !empty($factura->denumire1) ? $factura->denumire1 : '';
            $FirstName = !empty($factura->FirstName) ? $factura->FirstName : '';
            $LastName = !empty($factura->LastName) ? $factura->LastName : '';
            $CNP = !empty($factura->CNP) ? $factura->CNP : '';
            $CI = !empty($factura->CI) ? $factura->CI : '';
            $CiNr = !empty($factura->CiNr) ? $factura->CiNr : '';
            $tip_incas = !empty($factura->tip_incas) ? $factura->tip_incas : '';
            $nr_chit = !empty($factura->id_chitanta) ? $factura->id_chitanta : '';
            $datacrt = !empty($factura->OrderDate) ? $factura->OrderDate : '';


            $adresa_client = 'Adresa: Localitatea ' . $localitate;
            $adresa_client2 = 'Judet: ' . $judet . ' Romania';
            $agent = $FirstName . $LastName;
            $agent_detalii = "CNP: " . $CNP . ",CI " . $CI . $CiNr;

            /*Oferta 7, proforma 8, aviz 9*/
            if ($tip_incas === 7) {
                $invoice_title = 'OFERTA DE PRET';
                $serai_nr = 'O' . $factura->id_oferta;
            }
            else if ($tip_incas === 8) {
                $invoice_title = 'FACTURA PROFORMA';
                $serai_nr = 'P'. $factura->id_proforma;
            }
            else if ($tip_incas === 9) {
                $invoice_title = 'AVIZ DE INSOTIRE A MARFII';
                $serai_nr = 'A'. $factura->id_aviz;
            }
            else {
                $invoice_title = 'FACTURA FISCALA';
				$number = !empty($freshFactura->smartbill_invoice_id) ? $freshFactura->smartbill_invoice_id : $freshFactura->OrderID;
                $serai_nr = $factura->seria . $number;
            }

            // added for testing purposes
            // Log::info('Incasare value ' . $tip_incas . ' invoice title ' . $invoice_title . ' for invoice ID: ' . $invoice_id . ' with series and number: ' . $serai_nr);

            if (isset($regcom) && strlen($regcom) > 0) {
                $regcom = "Reg.Com: " . $regcom;
            }

            if (isset($cont_client) && strlen($cont_client) > 0) {
                $cont_client = "Cont: " . $cont_client;
            }

            if (isset($banca_client) && strlen($banca_client) > 0) {
                $banca_client = "Banca: " . $banca_client;
            }

            if (isset($cif) && strlen($cif) > 0) {
                $cif = "CUI/CNP " . $cif;
            }

            $from = ['BESOIU PIESE AUTO SRL', 'CUI: RO 31298897', 'ROONRC: J2013000544351', 'Adresa: Utvin, nr. 489, jud. Timis, Romania'
            , 'BANCA: Raiffeisen Bank', 'CONT: RO32 RZBR 0000 0600 2191 4930'];

            if (isset($cif) && strlen($cif) > 0) {
                if (isset($regcom) && strlen($regcom) > 0) {
                    $to = [$client, $cif, $regcom, $adresa_client, $adresa_client1, $adresa_client2, $cont_client, $banca_client];
                }
                else {
                    $to = [$client, $cif, $adresa_client, $adresa_client1, $adresa_client2, $cont_client, $banca_client];
                }
            }
            else {
                if (isset($regcom) && strlen($regcom) > 0) {
                    $to = [$client, $regcom, $adresa_client, $adresa_client1, $adresa_client2, $cont_client, $banca_client];
                }
                else {
                    $to = [$client, $adresa_client, $adresa_client1, $adresa_client2, $cont_client, $banca_client];
                }
            }


            $pdf = new RotatedPdf('P', 'mm', 'A4');
            $pdf->AliasNbPages();
            $pdf->AddPage();


            $pdf->SetMargins($margins['l'], $margins['t'], $margins['r']);

            list($width, $height) = getimagesize(public_path('assets/image/Capture.jpg'));
            $newWidth = $maxImageDimensions[0] / $width;
            $newHeight = $maxImageDimensions[1] / $height;
            $scale = min($newWidth, $newHeight);

            $dimensions = [
                round($this->pixelsToMM($scale * $width)),
                round($this->pixelsToMM($scale * $height)),
            ];

            // Insert image inside that cell
            $pdf->Image(public_path('assets/image/Capture.jpg'), $margins['l'], $margins['t'], $dimensions[0], $dimensions[1]);

            
            //Title
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont($this->font, 'B', 20);
            $pdf->SetY($margins['t']); // Move down from top
            //$pdf->SetX($margins['l'] + $dimensions[0] + 50); // Move left after the image
            $pdf->Cell(0, 5, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper($invoice_title, self::ICONV_CHARSET_INPUT)), 0, 1, 'R');
            $pdf->SetFont($this->font, '', 9);
            $pdf->Ln(5);


            $lineheight = 3;
            //Calculate position of strings
            $positionX = $document['w'] - $margins['l'] - $margins['r'] - max(
                    $pdf->GetStringWidth(mb_strtoupper('NUMAR', self::ICONV_CHARSET_INPUT)),
                    $pdf->GetStringWidth(mb_strtoupper('Data', self::ICONV_CHARSET_INPUT)),
                    $pdf->GetStringWidth(mb_strtoupper('Scadenta ', self::ICONV_CHARSET_INPUT))
            ) - max(
                    $pdf->GetStringWidth(mb_strtoupper($factura->seria . $factura->OrderID, self::ICONV_CHARSET_INPUT)),
                    $pdf->GetStringWidth(mb_strtoupper(date('d.m.Y', strtotime($factura->OrderDate)), self::ICONV_CHARSET_INPUT))
            )-4;

            //Number
            $pdf->Cell($positionX, $lineheight);
            $color = $this->hex2rgb('#AA3939'); // Default color
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->SetFont($this->font, 'B', 9);
            $pdf->Cell(32, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('NUMAR', self::ICONV_CHARSET_INPUT) . ':'), 0, 0, 'L');
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont($this->font, '', 9);
            $pdf->Cell(0, $lineheight, $serai_nr, 0, 1, 'R');

            //Date
            $pdf->Cell($positionX, $lineheight);
            $pdf->SetFont($this->font, 'B', 9);
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->Cell(32, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Data', self::ICONV_CHARSET_INPUT)) . ':', 0, 0, 'L');
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont($this->font, '', 9);
            $pdf->Cell(0, $lineheight, date('d.m.Y', strtotime($factura->OrderDate)), 0, 1, 'R');

            //Due date
            $pdf->Cell($positionX, $lineheight);
            $pdf->SetFont($this->font, 'B', 9);
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->Cell(32, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Scadenta ', self::ICONV_CHARSET_INPUT)) . ':', 0, 0, 'L');
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont($this->font, '', 9);
            $pdf->Cell(0, $lineheight, date('d.m.Y', strtotime($factura->RequiredDate)), 0, 1, 'R');

            // Client information
            $dimensions = $dimensions[1] ?: 0;
            if (($margins['t'] + $dimensions) > $pdf->GetY()) {
                $pdf->SetY($margins['t'] + $dimensions + 5);
            }
            else {
                $pdf->SetY($pdf->GetY() + 10);
            }
            $pdf->Ln(2);
            $pdf->SetFillColor($color[0], $color[1], $color[2]);
            $pdf->SetTextColor($color[0], $color[1], $color[2]);

            $pdf->SetDrawColor($color[0], $color[1], $color[2]);
            $pdf->SetFont($this->font, 'B', 10);

            $width = ($document['w'] - $margins['l'] - $margins['r']) / 2;

            $pdf->Cell($width, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Furnizor', self::ICONV_CHARSET_INPUT)), 0, 0, 'L');
            $pdf->Cell(0, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Client', self::ICONV_CHARSET_INPUT)), 0, 0, 'L');
            $pdf->Ln(7);
            $pdf->SetLineWidth(0.4);
            $pdf->Line($margins['l'], $pdf->GetY(), $margins['l'] + $width - 10, $pdf->GetY());
            $pdf->Line($margins['l'] + $width, $pdf->GetY(),$margins['l'] + $width + $width, $pdf->GetY());


            //To and From Information
            $pdf->Ln(5);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont($this->font, 'B', 10);
            $pdf->Cell($width, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, $from[0] ?: 0), 0, 0, 'L');
            $pdf->Cell(0, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, $to[0] ?: 0), 0, 0, 'L');
            $pdf->SetFont($this->font, '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Ln(7);

            for ($i = 1, $iMax = max($from === null ? 0 : count($from), $to === null ? 0 : count($to)); $i < $iMax; $i++) {
                // avoid undefined error if TO and FROM array lengths are different
                if (!empty($from[$i]) || !empty($to[$i])) {
                    $pdf->Cell($width, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, empty($from[$i]) ? '' : $from[$i]), 0, 0, 'L');
                    $pdf->Cell(0, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, empty($to[$i]) ? '' : $to[$i]), 0, 0, 'L');
                }
                $pdf->Ln(5);
            }
            $pdf->Ln(-20);
            $pdf->Ln(2);


            //Table header
            $width_other = ($document['w'] - $margins['l'] - $margins['r'] - $this->firstColumnWidth - ($this->columns * $this->columnSpacing)) / ($this->columns - 1);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Ln(14);
            $pdf->SetFont($this->font, 'B', 9);
            $pdf->Cell(1, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($this->firstColumnWidth, 10,
                    iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Produs', self::ICONV_CHARSET_INPUT)), 0, 0, 'L', 0);
            $pdf->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($width_other, 10, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('UM', self::ICONV_CHARSET_INPUT)), 0, 0, 'C', 0);
            $pdf->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($width_other, 10, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Cantitate', self::ICONV_CHARSET_INPUT)), 0, 0, 'C', 0);
            $pdf->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($width_other, 10,
                    iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Pret Unitar', self::ICONV_CHARSET_INPUT)),
                    0, 0, 'C', 0);
            $pdf->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($width_other, 10,
                    iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Valoare', self::ICONV_CHARSET_INPUT)),
                    0, 0, 'C', 0);
            $pdf->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($width_other, 10,
                    iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('T.V.A.', self::ICONV_CHARSET_INPUT)),
                    0, 0, 'C', 0);
            $pdf->Ln();
            $pdf->SetLineWidth(0.3);
            $pdf->SetDrawColor($color[0], $color[1], $color[2]);
            $pdf->Line($margins['l'], $pdf->GetY(), $document['w'] - $margins['r'], $pdf->GetY());
            $pdf->Ln(2);

            //products
            $width_other = ($document['w'] - $margins['l'] - $margins['r'] - $this->firstColumnWidth - ($this->columns * $this->columnSpacing)) / ($this->columns - 1);
            $cellHeight = 8;
            $bgcolor = (1 - $this->columnOpacity) * 255;
            if ($details->count() > 0) {
                $totals = true;

                $suma_total = 0;
                $suma_tva = 0;
                $suma_valoare = 0;
                foreach ($details as $index => $detail) {
                    $nume_produs = $index + 1 . '. ' . $detail->denumire;
                    $um = $detail->um;
                    $cantitate = $detail->Quantity;
                    $cantitate_f = number_format($cantitate, 2);
                    $pret_unitar = $detail->UnitPrice;
                    $pret_unitar_f = number_format($pret_unitar, 2);
                    $valoare = $pret_unitar * $cantitate;
                    $valoare_f = number_format($valoare, 2);
                    $ctva = $detail->tva;
                    $ctva_f = number_format($ctva, 2);
                    $tot_prod = $detail->total;

                    $suma_valoare += $valoare; //Suma valoare
                    $suma_tva += $ctva;
                    $suma_total += $tot_prod;
                    $description = '';

                    //$user->status_label;
                    //$invoice->addItem($nume_produs,"", "$um", $cantitate_f, $pret_unitar_f, $valoare_f, $ctva_f);
                    if ((empty($nume_produs)) || (empty($description))) {
                        $pdf->Ln($this->columnSpacing);
                    }

                    if ($description) {
                        //Precalculate height
                        $calculateHeight = new self();
                        $calculateHeight->addPage();
                        $calculateHeight->setXY(0, 0);
                        $calculateHeight->SetFont($this->font, '', 7);
                        $calculateHeight->MultiCell($this->firstColumnWidth, 3,iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, ""),
                                0, 'L', 1);
                        $descriptionHeight = $calculateHeight->getY() + $cellHeight + 2;
                        $pageHeight = $document['h'] - $pdf->GetY() - $margins['t'] - $margins['t'];
                        if ($pageHeight < 35) {
                            $this->AddPage();
                        }
                    }

                    $cHeight = $cellHeight;
                    $pdf->SetFont($this->font, 'b', 8);
                    $pdf->SetTextColor(50, 50, 50);
                    $pdf->SetFillColor($bgcolor, $bgcolor, $bgcolor);
                    $pdf->Cell(1, $cHeight, '', 0, 0, 'L', 1);
                    $x = $pdf->GetX();
                    $pdf->Cell($this->firstColumnWidth, $cHeight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, $nume_produs),
                            0, 0, 'L', 1);

                    if ($description) {
                        $resetX = $pdf->GetX();
                        $resetY = $pdf->GetY();
                        $pdf->SetTextColor(120, 120, 120);
                        $pdf->SetXY($x, $pdf->GetY() + 8);
                        $pdf->SetFont($this->font, '', $this->fontSizeProductDescription);
                        $pdf->MultiCell($this->firstColumnWidth,floor($this->fontSizeProductDescription / 2)
                        , iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, ""), 0, 'L', 1);

                        //Calculate Height
                        $newY = $pdf->GetY();
                        $cHeight = $newY - $resetY + 2;

                        //Make our spacer cell the same height
                        $pdf->SetXY($x - 1, $resetY);
                        $pdf->Cell(1, $cHeight, '', 0, 0, 'L', 1);

                        //Draw empty cell
                        $pdf->SetXY($x, $newY);
                        $pdf->Cell($this->firstColumnWidth, 2, '', 0, 0, 'L', 1);
                        $pdf->SetXY($resetX, $resetY);
                    }

                    $pdf->SetTextColor(50, 50, 50);
                    $pdf->SetFont($this->font, '', 8);
                    $pdf->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    $pdf->Cell($width_other, $cHeight, $um, 0, 0, 'C', 1);
                    $pdf->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    $pdf->Cell($width_other, $cHeight, $cantitate_f, 0, 0, 'C', 1);

                    $pdf->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    $pdf->Cell($width_other, $cHeight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_B, $pret_unitar_f), 0, 0, 'C', 1);

                    $pdf->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    $pdf->Cell($width_other, $cHeight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_B, $valoare_f), 0, 0, 'C', 1);

                    
                    $pdf->Cell($this->columnSpacing, $cHeight, '', 0, 0, 'L', 0);
                    $pdf->Cell($width_other, $cHeight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_B, $ctva_f), 0, 0, 'C', 1);
                    
                    $pdf->Ln();
                    $pdf->Ln($this->columnSpacing);
                }
                
                /* Add totals */
                $suma_total_f = number_format($suma_total, 2);

                $totals = [
                    ['name' => 'Subtotal', 'value' => $suma_valoare, 'colored' => false]
                    , ['name' => 'TVA 21%', 'value' => $suma_tva, 'colored' => false]
                    , ['name' => 'Total', 'value' => $suma_total_f, 'colored' => true]
                ];
            }
            $badgeX = $pdf->getX();
            $badgeY = $pdf->getY();

            //Add totals
            if (!empty($totals)) {
                foreach ($totals as $total) {
                    $pdf->SetTextColor(50, 50, 50);
                    $pdf->SetFillColor($bgcolor, $bgcolor, $bgcolor);
                    $pdf->Cell(1 + $this->firstColumnWidth, $cellHeight, '', 0, 0, 'L', 0);
                    for ($i = 0; $i < $this->columns - 3; $i++) {
                        $pdf->Cell($width_other, $cellHeight, '', 0, 0, 'L', 0);
                        $pdf->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
                    }
                    $pdf->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
                    if ($total['colored']) {
                        $pdf->SetTextColor(255, 255, 255);
                        $pdf->SetFillColor($color[0], $color[1], $color[2]);
                    }
                    $pdf->SetFont($this->font, 'b', 8);
                    $pdf->Cell(1, $cellHeight, '', 0, 0, 'L', 1);
                    $pdf->Cell($width_other - 1, $cellHeight,
                            iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_B, $total['name']),
                            0, 0, 'L', 1);
                    $pdf->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
                    $pdf->SetFont($this->font, 'b', 8);
                    $pdf->SetFillColor($bgcolor, $bgcolor, $bgcolor);
                    if ($total['colored']) {
                        $pdf->SetTextColor(255, 255, 255);
                        $pdf->SetFillColor($color[0], $color[1], $color[2]);
                    }
                    $pdf->Cell($width_other, $cellHeight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_B, $total['value']), 0, 0, 'C', 1);
                    $pdf->Ln();
                    $pdf->Ln($this->columnSpacing);
                }
            }
            $pdf->Ln();
            $pdf->Ln(3);


            //Badge
            /* Set badge */
            $badge = $den_incas;

            if ($badge) {
                $badge = ' ' . $badge . ' ';
                $resetX = $pdf->getX();
                $resetY = $pdf->getY();
                $pdf->setXY($badgeX, $badgeY + 15);
                $pdf->SetLineWidth(0.4);
                $pdf->SetDrawColor($color[0], $color[1], $color[2]);
                $pdf->setTextColor($color[0], $color[1], $color[2]);
                $pdf->SetFont($this->font, 'b', 12);
                $pdf->Rotate(10, $pdf->getX(), $pdf->getY());
                $pdf->Rect($pdf->GetX(), $pdf->GetY(), $pdf->GetStringWidth($badge) + 2, 10);
                $pdf->Write(10, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_B, $badge));
                $pdf->Rotate(0);
                if ($resetY > $pdf->getY() + 20) {
                    $pdf->setXY($resetX, $resetY);
                }
                else {
                    $pdf->Ln(18);
                }
            }
            
            $addText[] = ['paragraph', $this->br2nl("Circula fara semnatura si stampila conform art. 319(29) Legea 227/2015 privind Codul Fiscal <br>Intocmita de " . $agent . " " . $agent_detalii)];

            /* Add title */
            if ($tip_incas === 1) {
                $cifre = $pdf->transforma($suma_total_f);
                $addText[] = ['title', $this->br2nl('CHITANTA <br>' . "Seria/Numar BPA_C" . $nr_chit . " din data:" . date('d.m.Y', strtotime(str_replace('/', '-', $datacrt))))];

                /* Add Paragraph */
                $addText[] = ['paragraph', $this->br2nl("BESOIU PIESE AUTO SRL <br>CUI: RO 31298897, Reg. Com.: J35/544/2013 <br>Adresa: Utvin, nr. 489, jud. Timis, Romania")];
                $addText[] = ['paragraph', $this->br2nl("Am primit de la " . $client . ", " . $cif . ", " . $adresa_client . " \nsuma de " . $suma_total_f . " adica " . $cifre
                . " reprezentand C/V factura " . $serai_nr . "/" . date('d.m.Y', strtotime(str_replace('/', '-', $datacrt))))];
            }

            //Add information
            foreach ($addText as $text) {
                if ($text[0] == 'title') {
                    //$pdf->Ln(2);
                    $pdf->SetLineWidth(0.3);
                    $pdf->SetDrawColor($color[0], $color[1], $color[2]);
                    $pdf->Line($margins['l'], $pdf->GetY(), $document['w'] - $margins['r'], $pdf->GetY());
                    $pdf->Ln(2);
                    $pdf->SetTextColor(50, 50, 50);
                    $pdf->SetFont($this->font, 'b', 10);
                    $pdf->MultiCell(0, 4, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, $text[1]), 0, 'C', 0);
                    $pdf->Ln(2);
                }
                else if ($text[0] == 'paragraph') {
                    $pdf->SetTextColor(80, 80, 80);
                    $pdf->SetFont($this->font, '', 8);
                    $pdf->MultiCell(0, 4, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, $text[1]), 0, 'J', 0);
                    $pdf->Ln(2);
                }
            }

            $pdf->SetLineWidth(0.3);
            $pdf->SetDrawColor($color[0], $color[1], $color[2]);
            $pdf->Line($margins['l'], $pdf->GetY(), $document['w'] - $margins['r'], $pdf->GetY());

            //Footer
            $pdf->SetY(-25);
            $pdf->SetFont($this->font, '', 8);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Cell(0, 3, 'BESOIU PIESE AUTO', 0, 0, 'L');
            $pdf->Cell(0, 3, iconv('UTF-8', 'ISO-8859-1', 'Pagina') . ' ' . $pdf->PageNo() . ' ' . 'din' . ' {nb}', 0, 0, 'R');
            
			$number = !empty($freshFactura->smartbill_invoice_id) ? $freshFactura->smartbill_invoice_id : $freshFactura->OrderID;
            $pdf->Output($factura->seria . $number . '.pdf', 'I');
            exit; // Stop further processing after output
        }
        catch (\Exception $e) {
            Log::error('Error printing invoice' . $e->getMessage(), [
                'invoice_id' => $invoice_id ?? 'unknown',
                'user_id' => auth()->id() ?? 'unknown'
            ]);
            
            return redirect()->route('facturi.index')->with('error', 'Error printing invoice: ' . $e->getMessage());
        }
    }


    private function br2nl($string) {
        return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
    }


    /**
     * PDF generation helper for pixelsToMM
     */
    private function pixelsToMM($val) {
        $mm_inch = 25.4;
        $dpi = 96;

        return ($val * $mm_inch) / $dpi;
    }


    /**
     * PDF generation helper for hex2rgb
     */
    private function hex2rgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        $rgb = [$r, $g, $b];

        return $rgb;
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
	
	private function getStatusText($status){
		switch($status){
			case 0: return 'Eroare';
			case 1: return 'Comandat';
			case 2: return 'Sosit';
			case 3: return 'Cash';
			case 4: return 'Avans';
			case 5: return 'Retur';
			case 6: return 'Card';
			case 7: return 'FD';
			case 8: return 'Anulat';
			case 9: return 'OP';
			case 10: return 'Avans FD';
			case 11: return 'Avans Cash';
			case 12: return 'Avans Card';
			case 13: return 'Avans OP';
			default: return 'Unknown';
		}
	}
}
