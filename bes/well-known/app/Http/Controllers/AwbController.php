<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

// Sameday imports
use Sameday\Sameday;
use Sameday\SamedayClient;
use Sameday\Objects\Types\AwbPdfType;
use Sameday\Requests\SamedayGetAwbPdfRequest;

// FanCourier SDK
use Fancourier\Fancourier;
use Fancourier\Request\PrintAwb;

class AwbController extends Controller
{
    protected $client;
    
    public function __construct()
    {
        // Initialize Sameday client with production credentials
        $this->client = new Sameday(
            new SamedayClient(
                'besoiupieseautoAPI', // Production username
                'MXV/zuLmJg==',       // Production password
                null,
                true                  // Set to true for production environment
            )
        );
    }
    
    public function printAwb(Request $request, $id_awb)
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        // Get AWB number and courier account from request
        $nrawb = $id_awb;
        $contawb = $request->query('cont_awb', 'Utvin');
        
        if ($contawb == 'same') {
            // SameDay courier implementation
            return $this->handleSamedayAwb($nrawb);
        } else {
            // FanCourier implementation
            return $this->handleFancourierAwb($nrawb, $contawb);
        }
    }
    
    private function handleSamedayAwb($nrawb)
    {
        try {
            $pdf = $this->client->getAwbPdf(new SamedayGetAwbPdfRequest(
                $nrawb,
                new AwbPdfType(AwbPdfType::A4)
            ));
            
            $file = $pdf->getPdf();
            $tempPath = storage_path('app/temp/' . $nrawb . '.pdf');
            
            // Make sure the directory exists
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            
            file_put_contents($tempPath, $file);
            
            return response()->file($tempPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error("Error downloading Sameday AWB PDF: " . $e->getMessage(), [
                'awb' => $nrawb,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Handle FanCourier AWB printing
     *
     * @param string $nrawb
     * @param string $contawb
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    private function handleFancourierAwb($nrawb, $contawb)
    {
        try {
            // Initialize FanCourier instance based on the account type
            if ($contawb == 'Utvin') {
                $fan = Fancourier::utvinInstance();
            }
            else if ($contawb == 'Timisoara') {
                $fan = Fancourier::timisInstance();
            }
            else if ($contawb == 'Test') {
                $fan = Fancourier::testInstance();
            }
            else {
                return response()->json(['error' => 'Unknown Account type!!!'], 500);
            }

            $request = new PrintAwb();

            $request->setSize('A5')->setAwb($nrawb);

            $response = $fan->printAwb($request);

            if ($response->isOk()) {
                $file = $response->getData();

                // Store temporary file
                $tempPathForCopy = 'temp/' . $nrawb . '.pdf';
                $tempPath = storage_path('app/temp/' . $nrawb . '.pdf');
                
                // Make sure the directory exists
                if (!file_exists(dirname($tempPath))) {
                    mkdir(dirname($tempPath), 0755, true);
                }

                // Store the file in the local storage
                Storage::put($tempPathForCopy, $file);

                // Return file response and delete after send
                return response()->file($tempPath)->deleteFileAfterSend(true);
            }
            else {
                Log::error("Error downloading FanCourier AWB PDF: Error reading file from response", [
                    'awb' => $nrawb,
                    'account' => $contawb
                ]);

                return response()->json(['error' => 'Error downloading FanCourier AWB PDF: Error reading file from response'], 500);
            }
        } catch (\Exception $e) {
            Log::error("Error downloading FanCourier AWB PDF: " . $e->getMessage(), [
                'awb' => $nrawb,
                'account' => $contawb,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}