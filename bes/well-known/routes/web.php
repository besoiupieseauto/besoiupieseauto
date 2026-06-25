<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;


use App\Http\Controllers\OrderController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProduseController;

use App\Http\Controllers\FacturiController;
use App\Http\Controllers\ComenziController;
use App\Http\Controllers\UtilizatoriController;
use App\Http\Controllers\PieseautoController;
use App\Http\Controllers\SearchingController;
use App\Http\Controllers\SupplierSearchNewController;
use App\Http\Controllers\IncasariController;
use App\Http\Controllers\ApiCredentialController;

use App\Http\Controllers\SamedayController;
use App\Http\Controllers\AwbController;
use Fancourier\Fancourier;
use Fancourier\Request\GetCosts;
use App\Http\Controllers\FanCourierController;
use App\Http\Controllers\AwbControllerfan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/', function () {
   return redirect()->route('login');
});

Route::post('/login', [LoginController::class, 'login'])->name('login');

Route::get('/awb-print/{id_awb}', [AwbController::class, 'printAwb'])
    ->name('awb.print')
    ->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    /************ orders ************/

    // Add these routes to your web.php file
    // Order resource routes
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    //Route::get('/orders/utvin', [OrderController::class, 'utvin'])->name('orders.utvin');
    Route::get('/orders/data', [OrderController::class, 'getOrdersData'])->name('orders.data');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
	Route::get('/orders/{order}/send-whatsapp', [OrderController::class, 'sendWhatsapp'])->name('orders.sendWhatsapp');


    // Order product management routes
    Route::post('/orders/{order}/update-product', [OrderController::class, 'updateProduct'])->name('orders.update-product');
    Route::post('/orders/{order}/delete-product', [OrderController::class, 'deleteProduct'])->name('orders.delete-product');
    Route::post('/orders/{order}/update-product-tmp', [OrderController::class, 'updateProductTmp'])->name('orders.update-product-tmp');
    Route::post('/orders/{order}/delete-product-tmp', [OrderController::class, 'deleteProductTmp'])->name('orders.delete-product=tmp');

    // Additional update routes for orders
    Route::post('/orders/update-color', [OrderController::class, 'updateColor'])->name('orders.update-color');
    Route::post('/orders/update-supplier', [OrderController::class, 'updateSupplier'])->name('orders.update-supplier');
    Route::post('/orders/update-location', [OrderController::class, 'updateLocation'])->name('orders.update-location');

    Route::post('/orders/update-total', [OrderController::class, 'updateTotal']);

    // Existing routes
    Route::post('/orders/add-tmp-product', [OrderController::class, 'addTempProduct'])->name('orders.add-tmp-product');
    Route::get('/orders/get-tmp-products', [OrderController::class, 'getTempProducts'])->name('orders.get-tmp-products');
    Route::post('/orders/delete-tmp-product', [OrderController::class, 'deleteTempProduct'])->name('orders.delete-tmp-product');
    Route::get('/get-localities', [OrderController::class, 'getLocalities'])->name('get-localities');

    // Routes for Products
	Route::middleware(['permission:produse'])->group(function () {
		// Specific routes must be defined BEFORE resource routes to avoid route conflicts
		Route::get('produse-data', [ProduseController::class, 'getData'])->name('produse.data');
		Route::get('produse/get-current-tva', [ProduseController::class, 'getCurrentTVA'])->name('produse.get-current-tva');
		Route::post('produse/update-all-tva', [ProduseController::class, 'updateAllTVA'])->name('produse.update-all-tva');
		// Resource route must be last to avoid matching specific routes
		Route::resource('produse', ProduseController::class);
	});


    // Add this to your routes/web.php file with other Order routes
    Route::post('/orders/update-status', [OrderController::class, 'updateStatus'])->name('orders.update-status');

    Route::get('/get-customer-info', [OrderController::class, 'getCustomerInfo'])->name('orders.get-customer-info');
    Route::post('/orders/send-sms', [OrderController::class, 'sendSms'])->name('orders.send-sms');
    Route::get('/orders/{order}/check-sms-status', [OrderController::class, 'checkSmsStatus'])->name('orders.check-sms-status');

    // Invoice creation route
    Route::get('/edit-factura/{id}', [OrderController::class, 'showEditFactura'])->name('edit.factura');
    Route::post('/generate-invoice-pdf/{orderId}', [OrderController::class, 'generateInvoicePdf'])->name('generate.invoice.pdf');

    Route::get('/print-invoice/{invoice_id}', [OrderController::class, 'generatePdf'])->name('print.invoice');
    Route::get('/reload-order-factura-product/{order}', [OrderController::class, 'orderFacturaProduct'])->name('reload-order-factura-product');
    Route::get('/reload-order-product', [OrderController::class, 'tempOrderProduct'])->name('reload-order-product');

    // Client routes
    //Route::post('/storeclient', [ClientController::class, 'store'])->name('clients.store');
    Route::post('/saveClient', [ClientController::class, 'saveClient'])->name('clients.save');
        
    /********* clients **********/
	Route::middleware(['permission:clienti'])->group(function () {
		Route::resource('clients', ClientController::class);
	});
    Route::get('clients-data', [ClientController::class, 'getData'])->name('clients.data');
    Route::get('/clients/{id}/edit', [ComenziController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{id}', [ComenziController::class, 'update'])->name('clients.update');
    Route::get('/get-localities/{judet}', [ClientController::class, 'getLocalities'])->name('get.localities');

    // Add ANAF route
    Route::post('/anaf-info', [ClientController::class, 'getAnafInfo'])->name('anaf.info');

    // Add this route if it doesn't exist yet
    Route::get('/clients/search', [ClientController::class, 'search'])->name('client.search');


	Route::middleware(['permission:comenzi_externe'])->group(function () {
		// ComenziController routes
		Route::get('/comenzi', [ComenziController::class, 'index'])->name('comenzi.index');
		Route::get('/comenzi/create', [ComenziController::class, 'create'])->name('comenzi.create');
		Route::post('/comenzi', [ComenziController::class, 'store'])->name('comenzi.store');
		Route::get('/comenzi/{id}/print', [ComenziController::class, 'print'])->name('comenzi.print');
		Route::get('/comenzi/invoice/{id}', [ComenziController::class, 'invoice'])->name('comenzi.invoice');
		Route::get('/comenzi/print-invoice/{id}', [ComenziController::class, 'printInvoice'])->name('comenzi.print-invoice');
		Route::get('/comenzi/edit-extreme/{id}', [ComenziController::class, 'editExtreme'])->name('comenzi.edit_extreme');
		Route::post('/comenzi/generate-invoice-pdf/{id}', [ComenziController::class, 'generateInvoicePdf'])->name('generate.extreme.invoice.pdf');
		Route::get('/comenzi/{id}/send-whatsapp', [ComenziController::class, 'sendWhatsapp'])->name('comenzi.sendWhatsapp');
	});

    // AJAX routes
    Route::get('/comenzi/get-data', [ComenziController::class, 'getData'])->name('comenzi.get-data');
    Route::get('/comenzi/get-date', [ComenziController::class, 'getDate'])->name('comenzi.get-date');
    Route::post('/comenzi/update-status', [ComenziController::class, 'updateStatus'])->name('comenzi.update-status');
    Route::post('/comenzi/update-color', [ComenziController::class, 'updateColor'])->name('comenzi.update-color');
    Route::post('/comenzi/update-total', [ComenziController::class, 'updateTotal'])->name('comenzi.update-total');
    Route::post('/comenzi/update-awb', [ComenziController::class, 'updateAwb'])->name('comenzi.update-awb');
    Route::post('/comenzi/send-sms', [ComenziController::class, 'sendSms'])->name('comenzi.send-sms');
    Route::post('/comenzi/update-supplier', [ComenziController::class, 'updateSupplier'])->name('comenzi.update-supplier');
    Route::post('/comenzi/delete/{id}', [ComenziController::class, 'deleteOrder'])->name('comenzi.delete');

	Route::get('/comenzi/fetch-courier-status', [ComenziController::class, 'fetchCourierStatus'])->name('comenzi.fetchCourierStatus');

    Route::get('/comenzi/get-client-for-sms/{id}', [ComenziController::class, 'getClientForSms'])->name('comenzi.get-client-for-sms');
    // SMS routes

    Route::get('/comenzi/check-sms/{id}', [ComenziController::class, 'checkSmsStatus'])->name('comenzi.check-sms');

    Route::get('/check-order-invoice/{id}', [ComenziController::class, 'checkOrderInvoice'])->name('comenzi.check-invoice');

    Route::get('/comenzi/{id}/edit', [ComenziController::class, 'edit'])->name('comenzi.edit');
    Route::put('/comenzi/{id}', [ComenziController::class, 'update'])->name('comenzi.update');

    Route::get('/comenzi/get-order-products/{id}', [ComenziController::class, 'getOrderProducts']);
    Route::get('/comenzi/fancourier/get-pickup-points', [FanCourierController::class, 'getFancourierPickupPoints']);
    Route::get('/comenzi/fancourier/get-counties', [FanCourierController::class, 'getCounties']);
    Route::get('/comenzi/fancourier/get-localities', [FanCourierController::class, 'getLocalities']);



    Route::post('/comenzi/add-product-to-order', [ComenziController::class, 'addProductToOrder']);
    Route::post('/comenzi/delete-order-product', [ComenziController::class, 'deleteOrderProduct']);

    // Existing routes
    // Essential routes for invoice management
    Route::post('/facturi/add-product-to-order', [ComenziController::class, 'addProductToOrder']);
    Route::post('/facturi/delete-order-product', [ComenziController::class, 'deleteOrderProduct']);


	Route::middleware(['permission:facturi'])->group(function () {
		Route::get('/facturi', [FacturiController::class, 'index'])->name('facturi.index');
		Route::get('/facturi/data', [FacturiController::class, 'getData'])->name('facturi.data');
		Route::get('/facturi/create', [FacturiController::class, 'create'])->name('facturi.create');
		Route::post('/facturi', [FacturiController::class, 'store'])->name('facturi.store');
		Route::get('/facturi/{id}/edit', [FacturiController::class, 'edit'])->name('facturi.edit');
		Route::get('/facturi/{id}/edit-sub', [FacturiController::class, 'editSub'])->name('facturi.edit_sub');

		Route::put('/facturi/{id}', [FacturiController::class, 'update'])->name('facturi.update');
		Route::put('/facturi/{id}/update-details', [FacturiController::class, 'updateDetails'])->name('facturi.update_details');
		Route::delete('/facturi/{id}', [FacturiController::class, 'destroy'])->name('facturi.destroy');
		Route::get('/facturi/{id}/print', [FacturiController::class, 'print'])->name('facturi.print');

		// Temporary product management for invoice creation/editing
		Route::post('/facturi/add-tmp-product', [FacturiController::class, 'addTmpProduct'])->name('facturi.add_tmp_product');
		Route::get('/facturi/get-tmp-products', [FacturiController::class, 'getTmpProducts'])->name('facturi.get_tmp_products');
		Route::post('/facturi/delete-tmp-product', [FacturiController::class, 'deleteTmpProduct'])->name('facturi.delete_tmp_product');
		Route::post('/facturi/update-tmp-product', [FacturiController::class, 'updateTmpProduct'])->name('facturi.update_tmp_product');

		// Invoice detail management routes
		Route::get('/facturi/{id}/details', [FacturiController::class, 'getInvoiceDetails'])->name('facturi.get_details');
		Route::post('/facturi/{id}/add-detail', [FacturiController::class, 'addDetailToInvoice'])->name('facturi.add_detail');
		Route::delete('/facturi/{invoiceId}/detail/{detailId}', [FacturiController::class, 'deleteDetail'])->name('facturi.delete_detail');
	});


    // Client management routes
    Route::get('/ajax/clients/search', [FacturiController::class, 'searchClients'])->name('clients.search');
    Route::post('/storeclient', [FacturiController::class, 'storeclient'])->name('storeclient');
    Route::get('/get-localities', [FacturiController::class, 'getLocalities']);

    // Product management routes
    Route::get('/search-products', [FacturiController::class, 'searchProducts']);
    Route::post('/storepro', [FacturiController::class, 'storepro'])->name('storepro');
    Route::post('/update-produse-price', [ProduseController::class, 'updateProdusePrice'])->name('updateProdusePrice');

    // Utility route
    Route::get('/cleanup-tmp-products', [FacturiController::class, 'cleanupTmpProducts']);

    Route::get('/comenzi/get-client-for-awb/{id}', [ComenziController::class, 'getClientForAwb']);

    Route::post('/comenzi/create-sameday-awb', [ComenziController::class, 'createSamedayAwb'])->name('comenzi.create-sameday-awb');

    Route::post('/comenzi/create-fancourier-awb', [FanCourierController::class, 'createFanCourierAwb'])->name('comenzi.create-fancourier-awb');

	Route::middleware(['permission:incasari'])->group(function () {
		//incari start//
		Route::get('/incasari', [IncasariController::class, 'index'])->name('incasari.index');
		Route::get('/incasari/data', [IncasariController::class, 'getData'])->name('incasari.data');
		
		Route::get('/incasari/daily/price', [IncasariController::class, 'getDailyPrice'])->name('incasari.getDailyPrice');
		Route::post('/incasari/daily/price/update', [IncasariController::class, 'updateDailyPrice'])->name('incasari.updateDailyPrice');
		//incari close//
	});
	
	Route::middleware(['permission:ultilizatori'])->group(function () {
		Route::get('/utilizatori', [UtilizatoriController::class, 'index'])->name('utilizatori.index');
		Route::get('/utilizatori/data', [UtilizatoriController::class, 'getData'])->name('utilizatori.data');
		Route::get('/utilizatori/create', [UtilizatoriController::class, 'create'])->name('utilizatori.create');
		Route::post('/utilizatori', [UtilizatoriController::class, 'store'])->name('utilizatori.store');
		Route::get('/utilizatori/{id}/edit', [UtilizatoriController::class, 'edit'])->name('utilizatori.edit');
		Route::put('/utilizatori/{id}', [UtilizatoriController::class, 'update'])->name('utilizatori.update');
		Route::delete('/utilizatori/{id}', [UtilizatoriController::class, 'destroy'])->name('utilizatori.destroy');
	});
	
	Route::middleware(['permission:pieseauto'])->group(function () {
		Route::get('/pieseauto', [PieseautoController::class, 'index'])->name('pieseauto.index');
		Route::post('/pieseauto/import', [PieseAutoController::class, 'import'])->name('pieseauto.import');
		Route::get('/pieseauto/fetch', [PieseAutoController::class, 'fetchOrders']);
	});
	
	Route::middleware(['permission:searching'])->group(function () {
		Route::get('/searching', [SearchingController::class, 'index'])->name('searching.index');
		// Keep existing route name for frontend compatibility, but run pooled implementation.
		Route::post('/searching/search-suppliers', [SupplierSearchNewController::class, 'searchSuppliers'])->name('searching.searchSuppliers');
		// Backward-compatible aliases used by searching/index_new.blade.php.
		Route::post('/searching/search-suppliers-new', [SupplierSearchNewController::class, 'searchSuppliers'])->name('searching.searchSuppliersNew');
		Route::get('/searching-new', [SupplierSearchNewController::class, 'index'])->name('searching.new.index');
		Route::get('/searching/index-new', [SupplierSearchNewController::class, 'index'])->name('searching.indexNew');
		Route::post('/searching-new/search-suppliers', [SupplierSearchNewController::class, 'searchSuppliers'])->name('searching.new.searchSuppliers');
		Route::post('/searching/cart/add', [SearchingController::class, 'cartAdd'])->name('searching.cartAdd');
		Route::get('/searching/cart/show', [SearchingController::class, 'cartShow'])->name('searching.cartShow');
		Route::post('/searching/cart/update', [SearchingController::class, 'cartUpdate'])->name('searching.cartUpdate');
		Route::post('/searching/cart/remove', [SearchingController::class, 'cartRemove'])->name('searching.cartRemove');
		Route::get('/searching/cart/site-produse/list', [SearchingController::class, 'siteProduseListForCart'])->name('searching.siteProduseList');
		Route::post('/searching/cart/site-produse/add', [SearchingController::class, 'cartAddSiteProduse'])->name('searching.cartAddSiteProduse');
		Route::post('/searching/cart/place-order', [SearchingController::class, 'placeOrder'])->name('searching.placeOrder');
		Route::post('/searching/cart/update-variant', [SearchingController::class, 'cartUpdateVariant'])->name('searching.cartUpdateVariant');
		Route::post('/searching/cart/update-product-name', [SearchingController::class, 'cartUpdateProductName'])->name('searching.cartUpdateProductName');
		Route::post('/searching/cart/update-manufacturer', [SearchingController::class, 'cartUpdateManufacturer'])->name('searching.cartUpdateManufacturer');
		Route::post('/searching/cart/excluded-autototal/load-by-day', [SearchingController::class, 'loadExcludedAutototalCartByDay'])->name('searching.excludedAutototalCartLoadByDay');
		Route::post('/searching/cart/excluded-autototal/delete-saved-day', [SearchingController::class, 'deleteSavedAutototalExcludedDay'])->name('searching.excludedAutototalSavedDayDelete');
		Route::get('/searching/cart/excluded-autototal/show', [SearchingController::class, 'excludedAutototalCartShow'])->name('searching.excludedAutototalCartShow');
		Route::post('/searching/cart/excluded-autototal/place-order', [SearchingController::class, 'placeExcludedAutototalOrder'])->name('searching.placeExcludedAutototalOrder');
		Route::post('/searching/cart/excluded-autototal/remove', [SearchingController::class, 'removeExcludedAutototalCartItem'])->name('searching.removeExcludedAutototalCartItem');

		Route::get('/searching/orders', [SearchingController::class, 'getOrders'])->name('searching.getOrders');

		Route::post('/searching/wishlist/save', [SearchingController::class, 'saveWishlist'])->name('searching.wishlistSave');
		Route::get('/searching/wishlist/load/{id}', [SearchingController::class, 'loadWishlist'])->name('searching.wishlistLoad');
		Route::get('/searching/wishlist/saved', [SearchingController::class, 'listSavedWishlists'])->name('searching.wishlistSaved');
		Route::get('/searching/wishlist/offer/{id}', [SearchingController::class, 'wishlistCreateOffer'])->name('searching.wishlistCreateOffer');
		Route::get('/searching/wishlist/whatsapp/{id}', [SearchingController::class, 'wishlistWhatsApp'])->name('searching.wishlistWhatsApp');
		Route::delete('/searching/wishlist/delete/{id}', [SearchingController::class, 'deleteSavedWishlist'])->name('searching.wishlistDelete');
		
		// Promotions routes
		Route::get('/searching/promotions', [SearchingController::class, 'getPromotions'])->name('searching.getPromotions');
		Route::post('/searching/promotions/save', [SearchingController::class, 'savePromotion'])->name('searching.savePromotion');
		Route::delete('/searching/promotions/delete/{id}', [SearchingController::class, 'deletePromotion'])->name('searching.deletePromotion');
	});
	
	Route::middleware(['permission:apicredentials'])->group(function () {
		Route::get('/api-credentials', [ApiCredentialController::class, 'index'])
        ->name('apicredentials.index');

		// Update credentials
		Route::post('/api-credentials', [ApiCredentialController::class, 'updateAll'])
        ->name('apicredentials.updateAll');
	});

	
    Route::get('/test-fancourier', 'App\Http\Controllers\FanCourierTestController@testConnection');
	
	
	Route::get('/lkq-import', function () {
		abort_unless(
			request('key') === env('LKQ_CRON_SECRET'),
			403
		);

		app()->call(\App\Services\LKQImportService::class.'@run');
	});
});

Route::get('/cron/fancourier-tracking', [FanCourierController::class, 'cronFancourierTracking']);

Route::post('/change-theme', [ProfileController::class, 'changeTheme'])->name('theme.change');

require __DIR__.'/auth.php';
