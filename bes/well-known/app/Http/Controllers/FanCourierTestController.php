<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SeniorProgramming\FanCourier\Facades\FanCourier;

class FanCourierTestController extends Controller
{
    public function testConnection()
    {
        try {
            // यह सरल टेस्ट है जो शहरों की सूची प्राप्त करता है
            $cities = FanCourier::city();
            
            return response()->json([
                'success' => true,
                'message' => 'FanCourier API कनेक्शन सफल रहा!',
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'FanCourier API कनेक्शन विफल: ' . $e->getMessage()
            ], 500);
        }
    }
    
    
    
    
    
public function generateAwbWithParams(Request $request)
{
    try {
        $result = FanCourier::generateAwb(['fisier' => [
            [
                'tip_serviciu' => $request->input('tip_serviciu', 'standard'),
                'nr_plicuri' => $request->input('nr_plicuri', 1),
                'nr_colete' => $request->input('nr_colete', 0),
                'greutate' => $request->input('greutate', 1),
                'plata_expeditie' => $request->input('plata_expeditie', 'ramburs'),
                'ramburs_bani' => $request->input('ramburs_bani', 100),
                'plata_ramburs_la' => $request->input('plata_ramburs_la', 'destinatar'),
                'valoare_declarata' => $request->input('valoare_declarata', 100),
                'persoana_contact_expeditor' => $request->input('persoana_contact_expeditor', 'Test User'),
                'nume_destinar' => $request->input('nume_destinar', 'Test Destination'),
                'persoana_contact' => $request->input('persoana_contact', 'Test Contact'),
                'telefon' => $request->input('telefon', '123456789'),
                'email' => $request->input('email', 'example@example.com'),
                'judet' => $request->input('judet', 'Bucuresti'),
                'localitate' => $request->input('localitate', 'Bucuresti'),
                'strada' => $request->input('strada', 'Test Street'),
                'nr' => $request->input('nr', '1')
            ]
        ]]);
        
        return response()->json([
            'success' => true,
            'message' => 'AWB successfully generated',
            'result' => $result
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}
    
    
    
}