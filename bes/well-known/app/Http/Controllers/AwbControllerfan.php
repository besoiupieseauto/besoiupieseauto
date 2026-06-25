<?php

namespace App\Http\Controllers;

use App\Services\FanCourier\FanCourierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AwbControllerfan extends Controller
{
    protected $fanCourierService;
    
    public function __construct(FanCourierService $fanCourierService)
    {
        $this->fanCourierService = $fanCourierService;
    }
    
    /**
     * Create a new AWB
     */
    public function create(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            // Add your validation rules based on FanCourier requirements
            'service_type' => 'required',
            'recipient_name' => 'required',
            'recipient_address' => 'required',
            'recipient_phone' => 'required',
            // Add other required fields
        ]);
        
        $response = $this->fanCourierService->createAwb($validatedData);
        
        if (!empty($response['error'])) {
            return redirect()->back()->withErrors(['message' => $response['message']]);
        }
        
        return redirect()->route('awbs.index')->with('success', 'AWB created successfully with number: ' . $response['awb_number']);
    }
    
    /**
     * View AWB PDF
     */
    public function viewPdf($awbNumber)
    {
        $response = $this->fanCourierService->getAwbPdf($awbNumber);
        
        if (!empty($response['error'])) {
            return redirect()->back()->withErrors(['message' => $response['message']]);
        }
        
        // Return PDF as response
        return Response::make($response['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $awbNumber . '.pdf"'
        ]);
    }
    
    /**
     * Download AWB PDF
     */
    public function downloadPdf($awbNumber)
    {
        $response = $this->fanCourierService->getAwbPdf($awbNumber);
        
        if (!empty($response['error'])) {
            return redirect()->back()->withErrors(['message' => $response['message']]);
        }
        
        // Return PDF as download
        return Response::make($response['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="AWB-' . $awbNumber . '.pdf"'
        ]);
    }
    
    /**
     * Download multiple AWBs as a single PDF
     */
    public function downloadMultiple(Request $request)
    {
        $validatedData = $request->validate([
            'awb_numbers' => 'required|array',
            'awb_numbers.*' => 'required|string'
        ]);
        
        $response = $this->fanCourierService->downloadMultipleAwbs($validatedData['awb_numbers']);
        
        if (!empty($response['error'])) {
            return redirect()->back()->withErrors(['message' => $response['message']]);
        }
        
        // Return PDF as download
        return Response::make($response['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="multiple-awbs.pdf"'
        ]);
    }
}