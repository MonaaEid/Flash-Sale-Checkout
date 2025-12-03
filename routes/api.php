<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::get('/home', function () {
    return 'Welcome to Flash Sale API';
});
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/products/holds', [SaleController::class, 'createHold']);
Route::post('/orders', [SaleController::class, 'createOrder']);
Route::post('/payments/webhook', [SaleController::class, 'paymentWebhook']);

Route::get('/holds', [SaleController::class, 'holdsIndex']);
