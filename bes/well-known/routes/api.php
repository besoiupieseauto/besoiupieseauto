<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FanCourierController;
use App\Http\Controllers\SamedayController;

/*
|--------------------------------------------------------------------------
| API Routes
|-------------------------------------------------------------------------- 
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// FanCourier API routes — autentificare Sanctum obligatorie
Route::middleware('auth:sanctum')->prefix('fancourier')->group(function () {
	Route::get('/services', [FanCourierController::class, 'getServices']);
    Route::post('/create-awb', [FanCourierController::class, 'createAwb']);
    Route::post('/calculate-price', [FanCourierController::class, 'calculatePrice']);
});

// SameDay API routes — autentificare Sanctum obligatorie
Route::middleware('auth:sanctum')->prefix('sameday')->group(function () {
    Route::get('/services', [SamedayController::class, 'getServices']);
    Route::get('/pickup-points', [SamedayController::class, 'getPickupPoints']);
    Route::post('/calculate-price', [SamedayController::class, 'calculatePrice']);
    Route::post('/create-awb', [SamedayController::class, 'createAwb']);
    Route::get('/awb-status/{awbNumber}', [SamedayController::class, 'getAwbStatus']);
});
