<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /**
     * Display invoice creation form from an order
     */
    public function createFromOrder(Request $request, $orderId, $orderType = 0)
    {
        try {
            Log::info('Creating invoice from order', ['order_id' => $orderId, 'order_type' => $orderType]);
            
            // Start DB transaction
            DB::beginTransaction();
            
            // Default tables based on order type
            $orderTable = 'comenzi';
            $orderDetailTable = 'detaliu';
            $paymentType = 1; // Default payment type
            
            // Determine which tables to use based on order type
            switch ($orderType) {
                case 0: // Regular order
                    $orderTable = 'comenzi';
                    $orderDetailTable = 'detaliu';
                    $paymentType = 1;
                    break;
                case 1: // External order
                    $orderTable = 'comenzi_ext';
                    $orderDetailTable = 'detaliu_ext';
                    $paymentType = 3;
                    break;
                case 2: // Return order
                    $orderTable = 'facturi';
                    $orderDetailTable = 'facturidetails';
                    $paymentType = 1;
                    break;
            }
            
            // Fetch client ID from order
            if ($orderType == 2) {
                // Get order details for return order
                $order = DB::table($orderTable)
                    ->select('CustomerID as idclient')
                    ->where('OrderID', $orderId)
                    ->first();
            } else {
                // Get order details for regular/external order
                $order = DB::table($orderTable)
                    ->select('idclient')
                    ->where('idcomanda', $orderId)
                    ->first();
            }
            
            if (!$order) {
                return redirect()->route('orders.index')
                    ->with('error', 'Order not found');
            }
            
            // Get the next invoice number
            $lastInvoice = DB::table('facturi')
                ->orderBy('OrderID', 'desc')
                ->select('OrderID', 'id_fact')
                ->first();
                
            $invoiceNumber = $lastInvoice ? $lastInvoice->OrderID + 1 : 1;
            $invoiceId = $lastInvoice ? $lastInvoice->id_fact + 1 : 1;
            
            // Get current date
            $currentDate = Carbon::now();
            $formattedDate = $currentDate->format('Y-m-d H:i:s');
            
            // Create invoice header
            $invoiceData = [
                'OrderId' => $invoiceNumber,
                'CustomerID' => $order->idclient,
                'EmployeeID' => 2, // Default employee ID
                'OrderDate' => $formattedDate,
                'RequiredDate' => $formattedDate, // Due date same as creation date
                'seria' => 'BPA_C',
                'valid' => 1,
                'tip_incas' => $paymentType,
                'id_chitanta' => 0,
                'id_comanda' => $orderId,
                'tip_comanda' => $orderType,
                'id_fact' => $invoiceId
            ];
            
            DB::table('facturi')->insert($invoiceData);
            
            // Update the original order with invoice reference
            if ($orderType == 2) {
                DB::table($orderTable)
                    ->where('OrderID', $orderId)
                    ->update(['id_fact' => $invoiceNumber]);
            } else {
                DB::table($orderTable)
                    ->where('idcomanda', $orderId)
                    ->update(['id_factura' => $invoiceNumber]);
            }
            
            // Get order products and add them to invoice details
            if ($orderType == 2) {
                // Get products for return order
                $orderProducts = DB::table($orderTable)
                    ->join($orderDetailTable, "$orderTable.OrderID", '=', "$orderDetailTable.OrderID")
                    ->join('produse', "$orderDetailTable.ProductId", '=', 'produse.idprodus')
                    ->where("$orderTable.OrderID", $orderId)
                    ->select(
                        "$orderDetailTable.ProductId as idprodus",
                        "$orderDetailTable.Quantity as cantitate",
                        "$orderDetailTable.UnitPrice as pret",
                        'produse.TVA',
                        'produse.um'
                    )
                    ->get();
            } else {
                // Get products for regular/external order
                $orderProducts = DB::table($orderTable)
                    ->join($orderDetailTable, "$orderTable.idcomanda", '=', "$orderDetailTable.idcomanda")
                    ->join('produse', "$orderDetailTable.idprodus", '=', 'produse.idprodus')
                    ->where("$orderTable.idcomanda", $orderId)
                    ->select(
                        "$orderDetailTable.idprodus",
                        "$orderDetailTable.cantitate",
                        "$orderDetailTable.pret",
                        'produse.TVA',
                        'produse.um'
                    )
                    ->get();
            }
            
            // Calculate total price from order products to validate before invoice generation
            $totalOrderPrice = 0;
            foreach ($orderProducts as $product) {
                $totalOrderPrice += $product->cantitate * $product->pret;
            }
            
            // Check if total price is 0 or less - prevent invoice generation
            if ($totalOrderPrice <= 0) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Factura nu poate fi generată deoarece totalul comenzii este 0 sau mai mic.');
            }
            
            // Add products to invoice details
            foreach ($orderProducts as $product) {
                $quantity = $product->cantitate;
                $priceWithVat = $product->pret;
                $tvaRate = $product->TVA;
                
                // Handle return invoices - use negative quantities
                if ($orderType == 2) {
                    $quantity = $quantity * (-1);
                    $basePrice = $priceWithVat;
                } else {
                    // Calculate base price without VAT for regular orders
                    $basePrice = $priceWithVat / (($tvaRate + 100) / 100);
                }
                
                // Calculate values
                $totalValue = $basePrice * $quantity;
                $tvaValue = $totalValue * $tvaRate / 100;
                $totalWithVat = $totalValue + $tvaValue;
                
                // Format values to handle decimal precision
                $basePrice = number_format($basePrice, 2, '.', '');
                $totalValue = number_format($totalValue, 2, '.', '');
                $tvaValue = number_format($tvaValue, 2, '.', '');
                $totalWithVat = number_format($totalWithVat, 2, '.', '');
                
                // Insert invoice detail
                DB::table('facturidetails')->insert([
                    'OrderID' => $invoiceNumber,
                    'ProductID' => $product->idprodus,
                    'UnitPrice' => $basePrice,
                    'Quantity' => $quantity,
                    'tva' => $tvaValue,
                    'total' => $totalWithVat
                ]);
            }
            
            // Commit transaction
            DB::commit();
            
            // Redirect to invoice edit page
            return redirect()->route('invoices.edit', $invoiceNumber);
            
        } catch (\Exception $e) {
            // Rollback in case of error
            DB::rollBack();
            
            Log::error('Error creating invoice from order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('orders.index')
                ->with('error', 'Error creating invoice: ' . $e->getMessage());
        }
    }
    
    /**
     * Display invoice editing form
     */
    public function edit($id)
    {
        try {
            // Get invoice data
            $invoice = DB::table('facturi')
                ->join('clienti', 'facturi.CustomerID', '=', 'clienti.idclienti')
                ->where('facturi.OrderID', $id)
                ->select(
                    'facturi.*',
                    'clienti.idclienti',
                    'clienti.companie',
                    'clienti.nume',
                    'clienti.telefon',
                    'clienti.cif'
                )
                ->first();
                
            if (!$invoice) {
                return redirect()->route('invoices.index')
                    ->with('error', 'Invoice not found');
            }
            
            // Format dates for display
            $invoice->formattedDate = Carbon::parse($invoice->OrderDate)->format('d/m/Y');
            $invoice->formattedDueDate = Carbon::parse($invoice->RequiredDate)->format('d/m/Y');
            
            // Store invoice ID in session
            session(['id_factura' => $id]);
            
            // Get employees for dropdown
            $employees = DB::table('employees')
                ->orderBy('LastName')
                ->get();
                
            // Get payment types for dropdown
            $paymentTypes = DB::table('tip_plata')
                ->orderBy('id_plata')
                ->get();
                
            return view('invoices.edit', compact('invoice', 'employees', 'paymentTypes'));
            
        } catch (\Exception $e) {
            Log::error('Error editing invoice', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('invoices.index')
                ->with('error', 'Error loading invoice: ' . $e->getMessage());
        }
    }
    
    /**
     * Update invoice information
     */
    public function update(Request $request, $id)
    {
        try {
            Log::info('Updating invoice', ['invoice_id' => $id, 'data' => $request->all()]);
            
            // Validate request
            $validated = $request->validate([
                'id_client' => 'required|integer',
                'vanzator_nou' => 'required|integer',
                'id_incasare' => 'required|integer',
                'data' => 'required|string',
                'datascadenta' => 'required|string'
            ]);
            
            // Parse dates
            $orderDate = Carbon::createFromFormat('d/m/Y', $request->data)->format('Y-m-d H:i:s');
            $dueDate = Carbon::createFromFormat('d/m/Y', $request->datascadenta)->format('Y-m-d H:i:s');
            
            // Update invoice header
            DB::table('facturi')
                ->where('OrderID', $id)
                ->update([
                    'CustomerID' => $request->id_client,
                    'EmployeeID' => $request->vanzator_nou,
                    'OrderDate' => $orderDate,
                    'RequiredDate' => $dueDate,
                    'tip_incas' => $request->id_incasare
                ]);
                
            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully',
                'redirect' => $request->tip_cmd != 3 
                    ? route('invoices.print', $id)
                    : route('invoices.index')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating invoice', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating invoice: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get temporary products for the invoice editing
     */
    public function getInvoiceProducts()
    {
        try {
            $invoiceId = session('id_factura');
            $sessionId = session()->getId();
            
            if (!$invoiceId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No invoice ID in session'
                ], 400);
            }
            
            // First, delete any existing temp records for this session
            DB::table('tmp')->where('session_id', $sessionId)->delete();
            
            // Then copy invoice details to temp table
            $invoiceDetails = DB::table('facturidetails')
                ->where('OrderID', $invoiceId)
                ->get();
                
            foreach ($invoiceDetails as $detail) {
                $unitPrice = $detail->total / $detail->Quantity;
                
                DB::table('tmp')->insert([
                    'id_produs' => $detail->ProductID,
                    'cantitate_tmp' => $detail->Quantity,
                    'pret_tmp' => $unitPrice,
                    'session_id' => $sessionId
                ]);
            }
            
            // Get products with details
            $products = DB::table('produse')
                ->join('tmp', 'produse.idprodus', '=', 'tmp.id_produs')
                ->where('tmp.session_id', $sessionId)
                ->select(
                    'tmp.id_tmp',
                    'produse.cod_produs',
                    'tmp.cantitate_tmp',
                    'produse.denumire',
                    'produse.TVA',
                    'produse.um',
                    'tmp.pret_tmp'
                )
                ->get();
                
            // Calculate totals
            $totalValue = 0;
            $totalVat = 0;
            
            $formattedProducts = [];
            $index = 1;
            
            foreach ($products as $product) {
                $unitPrice = $product->pret_tmp / (($product->TVA + 100) / 100);
                $baseValue = $unitPrice * $product->cantitate_tmp;
                $vatAmount = $baseValue * $product->TVA / 100;
                $totalWithVat = $baseValue + $vatAmount;
                
                $totalValue += $baseValue;
                $totalVat += $vatAmount;
                
                $formattedProducts[] = [
                    'id_tmp' => $product->id_tmp,
                    'index' => $index++,
                    'cod_produs' => $product->cod_produs,
                    'denumire' => $product->denumire,
                    'um' => $product->um,
                    'cantitate' => $product->cantitate_tmp,
                    'pret_unitar' => number_format($unitPrice, 2),
                    'valoare' => number_format($baseValue, 2),
                    'tva_amount' => number_format($vatAmount, 2),
                    'tva_rate' => $product->TVA,
                    'total' => number_format($totalWithVat, 2)
                ];
            }
            
            return response()->json([
                'success' => true,
                'products' => $formattedProducts,
                'totals' => [
                    'subtotal' => number_format($totalValue, 2),
                    'vat' => number_format($totalVat, 2),
                    'total' => number_format($totalValue + $totalVat, 2)
                ],
                'currency' => 'lei'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting invoice products', [
                'invoice_id' => session('id_factura'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading invoice products: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Add product to invoice
     */
    public function addProduct(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'cantitate' => 'required|numeric',
                'pret_unitar' => 'required|numeric'
            ]);
            
            $sessionId = session()->getId();
            $productId = $request->id;
            $quantity = $request->cantitate;
            $unitPrice = $request->pret_unitar;
            
            // Insert product to temporary table
            DB::table('tmp')->insert([
                'id_produs' => $productId,
                'cantitate_tmp' => $quantity,
                'pret_tmp' => $unitPrice,
                'session_id' => $sessionId
            ]);
            
            return $this->getInvoiceProducts();
            
        } catch (\Exception $e) {
            Log::error('Error adding product to invoice', [
                'invoice_id' => session('id_factura'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error adding product: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove product from invoice
     */
    public function removeProduct(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer'
            ]);
            
            $sessionId = session()->getId();
            
            // Delete product from temporary table
            DB::table('tmp')
                ->where('id_tmp', $request->id)
                ->where('session_id', $sessionId)
                ->delete();
                
            return $this->getInvoiceProducts();
            
        } catch (\Exception $e) {
            Log::error('Error removing product from invoice', [
                'invoice_id' => session('id_factura'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error removing product: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Save invoice products from temporary table
     */
    public function saveInvoiceProducts()
    {
        try {
            $invoiceId = session('id_factura');
            $sessionId = session()->getId();
            
            if (!$invoiceId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No invoice ID in session'
                ], 400);
            }
            
            // Start transaction
            DB::beginTransaction();
            
            // Delete existing invoice details
            DB::table('facturidetails')
                ->where('OrderID', $invoiceId)
                ->delete();
                
            // Get products from temporary table
            $tempProducts = DB::table('tmp')
                ->join('produse', 'tmp.id_produs', '=', 'produse.idprodus')
                ->where('tmp.session_id', $sessionId)
                ->select(
                    'tmp.id_produs',
                    'tmp.cantitate_tmp',
                    'tmp.pret_tmp',
                    'produse.TVA'
                )
                ->get();
                
            // Add products to invoice details
            foreach ($tempProducts as $product) {
                $quantity = $product->cantitate_tmp;
                $priceWithVat = $product->pret_tmp;
                $tvaRate = $product->TVA;
                
                // Calculate base price without VAT
                $basePrice = $priceWithVat / (($tvaRate + 100) / 100);
                
                // Calculate values
                $totalValue = $basePrice * $quantity;
                $tvaValue = $totalValue * $tvaRate / 100;
                $totalWithVat = $totalValue + $tvaValue;
                
                // Format values to handle decimal precision
                $basePrice = number_format($basePrice, 2, '.', '');
                $totalWithVat = number_format($totalWithVat, 2, '.', '');
                $tvaValue = number_format($tvaValue, 2, '.', '');
                
                // Insert invoice detail
                DB::table('facturidetails')->insert([
                    'OrderID' => $invoiceId,
                    'ProductID' => $product->id_produs,
                    'UnitPrice' => $basePrice,
                    'Quantity' => $quantity,
                    'tva' => $tvaValue,
                    'total' => $totalWithVat
                ]);
            }
            
            // Commit transaction
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Invoice products saved successfully'
            ]);
            
        } catch (\Exception $e) {
            // Rollback in case of error
            DB::rollBack();
            
            Log::error('Error saving invoice products', [
                'invoice_id' => session('id_factura'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error saving invoice: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Print invoice
     */
    public function printInvoice($id)
    {
        try {
            // Get invoice data
            $invoice = DB::table('facturi')
                ->join('clienti', 'facturi.CustomerID', '=', 'clienti.idclienti')
                ->leftJoin('employees', 'facturi.EmployeeID', '=', 'employees.EmployeeId')
                ->where('facturi.OrderID', $id)
                ->select(
                    'facturi.*',
                    'clienti.companie',
                    'clienti.nume',
                    'clienti.telefon',
                    'clienti.cif',
                    'clienti.adresa',
                    DB::raw("CONCAT(employees.FirstName, ' ', employees.LastName) as employee_name")
                )
                ->first();
                
            if (!$invoice) {
                return redirect()->route('invoices.index')
                    ->with('error', 'Invoice not found');
            }
            
            // Get invoice products
            $products = DB::table('facturidetails')
                ->join('produse', 'facturidetails.ProductID', '=', 'produse.idprodus')
                ->where('facturidetails.OrderID', $id)
                ->select(
                    'facturidetails.*',
                    'produse.denumire',
                    'produse.cod_produs',
                    'produse.TVA',
                    'produse.um'
                )
                ->get();
                
            // Calculate totals
            $subtotal = 0;
            $totalVat = 0;
            
            foreach ($products as $product) {
                $subtotal += $product->UnitPrice * $product->Quantity;
                $totalVat += $product->tva;
            }
            
            // Format dates for display
            $invoice->formattedDate = Carbon::parse($invoice->OrderDate)->format('d.m.Y');
            $invoice->formattedDueDate = Carbon::parse($invoice->RequiredDate)->format('d.m.Y');
            
            // Get payment type name
            $paymentType = DB::table('tip_plata')
                ->where('id_plata', $invoice->tip_incas)
                ->value('denumire');
                
            return view('invoices.print', compact(
                'invoice',
                'products',
                'subtotal',
                'totalVat',
                'paymentType'
            ));
            
        } catch (\Exception $e) {
            Log::error('Error printing invoice', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('invoices.index')
                ->with('error', 'Error printing invoice: ' . $e->getMessage());
        }
    }
    
    /**
     * Display list of invoices
     */
    public function index()
    {
        try {
            // Get latest invoices for display
            $invoices = DB::table('facturi')
                ->join('clienti', 'facturi.CustomerID', '=', 'clienti.idclienti')
                ->leftJoin('tip_plata', 'facturi.tip_incas', '=', 'tip_plata.id_plata')
                ->orderBy('facturi.OrderID', 'desc')
                ->select(
                    'facturi.OrderID',
                    'facturi.seria',
                    'facturi.OrderDate',
                    'facturi.RequiredDate',
                    'facturi.valid',
                    'clienti.nume',
                    'clienti.companie',
                    DB::raw('(SELECT SUM(total) FROM facturidetails WHERE facturidetails.OrderID = facturi.OrderID) as total'),
                    'tip_plata.denumire as payment_type'
                )
                ->take(50)
                ->get();
                
            // Format data for view
            foreach ($invoices as $invoice) {
                $invoice->formattedDate = Carbon::parse($invoice->OrderDate)->format('d/m/Y');
                $invoice->formattedDueDate = Carbon::parse($invoice->RequiredDate)->format('d/m/Y');
                $invoice->clientName = $invoice->companie ?: $invoice->nume;
                $invoice->formattedTotal = number_format($invoice->total, 2);
                $invoice->invoiceNumber = $invoice->seria . ' ' . $invoice->OrderID;
            }
            
            return view('invoices.index', compact('invoices'));
            
        } catch (\Exception $e) {
            Log::error('Error loading invoices', [
                'error' => $e->getMessage()
            ]);
            
            return view('invoices.index')
                ->with('error', 'Error loading invoices: ' . $e->getMessage());
        }
    }
}