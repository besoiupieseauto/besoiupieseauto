<?php

namespace App\Http\Controllers;

use App\Models\Produse;
use Illuminate\Http\Request;
//use DataTables;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class ProduseController extends Controller
{
    // Display a list of products
    public function index()
    {
        return view('produse.index');
    }

    // Show a single product (required by resource route)
    public function show($id)
    {
        $produs = Produse::findOrFail($id);
        return response()->json($produs);
    }

    // Get data for DataTables
    public function getData(Request $request)
    {
        if ($request->ajax()) {
            $data = Produse::select('*');
            
            return Datatables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function($row){
                    $actionBtn = '<div class="btn-group">' .
                        '<button type="button" onclick="editProduct(' . $row->idprodus . ')" class="btn btn-default btn-sm" style="background-color: #f8f8f8;"><i class="glyphicon glyphicon-pencil text-primary"></i></button>' .
                        '<button type="button" onclick="deleteProduct(' . $row->idprodus . ')" class="btn btn-default btn-sm" style="background-color: #f8f8f8;"><i class="glyphicon glyphicon-trash text-danger"></i></button>' .
                        '</div>';
                    return $actionBtn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
    }

    // Store a new product via AJAX
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'denumire' => 'required|string|max:255',
            'cod_produs' => 'required|unique:produse,cod_produs,' . $request->idprodus . ',idprodus',
            'pret' => 'required|numeric'
        ]);
        if($request->idprodus) {
			$validatedData['created_at'] = Carbon::now()->timestamp + (2 * 3600);
            // Update existing product
            $produs = Produse::findOrFail($request->idprodus);
            $produs->update($validatedData);
        } else {
            // Create new product
			$validatedData['created_at'] = Carbon::now()->timestamp + (2 * 3600);
            $produs = Produse::create($validatedData);
        }
        return response()->json(['success' => true, 'produs' => $produs]);
    }

    // Return product data as JSON for editing
    public function edit($id)
    {
        $produs = Produse::findOrFail($id);
        return response()->json($produs);
    }

    // Update an existing product via AJAX
    public function update(Request $request, $id)
    {
        $produs = Produse::findOrFail($id);
        $validatedData = $request->validate([
            'denumire' => 'required|string|max:255',
            'cod_produs' => 'required|unique:produse,cod_produs,' . $id . ',idprodus',
            'pret' => 'required|numeric'
        ]);
        $produs->update($validatedData);
        return response()->json(['success' => true, 'produs' => $produs]);
    }

    // Delete a product 
    public function destroy($id)
    {
        $produs = Produse::findOrFail($id);
        $produs->delete();
    
        return response()->json(['success' => true]);
    }

    // Get current TVA value from database
    public function getCurrentTVA()
    {
        try {
            // Get the first product's TVA value as a sample
            $product = Produse::select('TVA')->whereNotNull('TVA')->first();
            
            if ($product && $product->TVA !== null) {
                return response()->json([
                    'success' => true,
                    'tva_value' => $product->TVA
                ]);
            }
            
            // If no product with TVA found, try to get any product
            $anyProduct = Produse::select('TVA')->first();
            if ($anyProduct) {
                return response()->json([
                    'success' => true,
                    'tva_value' => $anyProduct->TVA ?? 0
                ]);
            }
            
            return response()->json([
                'success' => true,
                'tva_value' => 0
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'tva_value' => 0,
                'message' => 'Error fetching TVA: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update TVA for all products
    public function updateAllTVA(Request $request)
    {
        $validatedData = $request->validate([
            'tva_value' => 'required|numeric|min:0|max:100'
        ]);

        try {
            // Update all products in the produse table
            $updated = Produse::query()->update(['TVA' => $validatedData['tva_value']]);
            
            return response()->json([
                'success' => true,
                'message' => 'TVA actualizat cu succes pentru toate produsele.',
                'updated_count' => $updated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'A apărut o eroare la actualizarea TVA: ' . $e->getMessage()
            ], 500);
        }
    }
	
	public function updateProdusePrice(Request $request)
	{
		$rawPrice = trim($request->price);
		if ($rawPrice === "-") {
			return response()->json([
				'success' => true,
				'message' => 'Temporary negative sign detected. No update performed.',
			], 200);
		}
	
		// Validate the input
		$validatedData = $request->validate([
			'idprodus' => 'required|numeric|min:1',
			//'price' => 'required|numeric|min:0',
			'price'    => 'required|numeric',
		]);

		// Check if the product exists
		$product = Produse::where('idprodus', $validatedData['idprodus'])->first();
		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product not found.',
			], 404);
		}
		
		if ($validatedData['price'] < 0) {
			return response()->json([
				'success' => true,
				'message' => 'Negative price detected. Product price not updated (as expected).',
			], 200);
		}

		// Update the price
		$product->pret = $validatedData['price'];
		$product->save();

		return response()->json([
			'success' => true,
			'message' => 'Product price updated successfully.',
			'product' => $product,
		]);
	}
}