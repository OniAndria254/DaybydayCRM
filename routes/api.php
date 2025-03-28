<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\TasksController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\OffersController;
use App\Http\Controllers\DiscountSettingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Toutes les routes nécessitent une authentification avec un token.
|
*/

// Routes d'authentification
Route::group(['namespace' => 'App\Http\Controllers\Api'], function () {
    Route::post('/login', [AuthController::class, 'login']);

    // Routes protégées nécessitant un token
    Route::group(['middleware' => 'auth:api'], function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
 
        // Routes API pour les clients
        Route::get('/clients/count', [ClientsController::class, 'getClientCount']);
        Route::get('/clients/all', [ClientsController::class, 'getAllClients']);
        Route::get('/clients/{external_id}/details', [ClientsController::class, 'getClientDetails']);

        // Routes API pour les paiements
        Route::get('/payments/total', [PaymentsController::class, 'getTotalPayments']);
        Route::get('/payments/all', [PaymentsController::class, 'getAllPayments']);
        Route::post('/payments/update/{external_id}', [PaymentsController::class, 'updatePaymentApi']);
        Route::delete('/payments/delete/{external_id}', [PaymentsController::class, 'deletePaymentApi']);

        Route::get('/tasks/count', [TasksController::class, 'getTaskCount']);
        Route::get('/tasks/all', [TasksController::class, 'getAllTasks']);

        // Routes API pour les graphiques
        Route::get('/tasks/by-status', [TasksController::class, 'getTasksByStatus']);
        Route::get('/clients/by-industry', [ClientsController::class, 'getClientsByIndustry']);

        // Routes API pour les graphiques
        Route::get('/offers/by-status', [OffersController::class, 'getOffersValueByStatus']);
        Route::get('/invoices/by-status', [InvoicesController::class, 'getInvoicesByStatus']);

        
        Route::get('/settings/discount', [DiscountSettingController::class, 'getDiscountSetting']);
        Route::post('/settings/discount', [DiscountSettingController::class, 'updateDiscountSetting']);

        Route::get('/invoices/total', [InvoicesController::class, 'getTotalInvoices']);
        // Route::get('/offers/total', [InvoicesController::class, 'getTotalOffers']);
        Route::get('/offers/count', [OffersController::class, 'getOfferCount']);



    });
});
