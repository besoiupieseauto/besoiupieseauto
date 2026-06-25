<?php
namespace App\Http\Controllers;

use App\Services\SamedayMockService;
use Illuminate\Http\Request;

class SamedayApiController extends Controller
{
    protected $mockService;
    
    public function __construct()
    {
        $this->mockService = new SamedayMockService();
    }
    
    public function authenticate()
    {
        return response()->json($this->mockService->authenticate());
    }
    
    public function getPickupPoints(Request $request)
    {
        $city = $request->query('city');
        return response()->json($this->mockService->getPickupPoints($city));
    }
    
    public function getServices()
    {
        return response()->json($this->mockService->getServices());
    }
    
    public function getCities(Request $request)
    {
        $county = $request->query('county');
        return response()->json($this->mockService->getCities($county));
    }
    
    public function getCounties()
    {
        return response()->json($this->mockService->getCounties());
    }
    
    public function calculatePrice(Request $request)
    {
        return response()->json($this->mockService->calculatePrice($request->all()));
    }
    
    public function createAwb(Request $request)
    {
        return response()->json($this->mockService->createAwb($request->all()));
    }
    
    public function getMockAwbPdf($id)
    {
        // Generate a simple PDF for AWB
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(40, 10, 'Mock AWB #' . $id);
        $pdf->Output('D', 'awb-' . $id . '.pdf');
    }
}