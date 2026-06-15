<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\CreditScoreController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\LenderController;
use App\Http\Controllers\Api\ConsentController;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Health check
    Route::get('/health', fn() => response()->json([
        'status' => 'ok',
        'service' => 'kitu-api',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
    ]));

    /*
    |--------------------------------------------------------------------------
    | Authenticated SME routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Consent
        Route::get('/consent', [ConsentController::class, 'index']);
        Route::post('/consent/grant', [ConsentController::class, 'grant']);
        Route::post('/consent/withdraw', [ConsentController::class, 'withdraw']);

        // Businesses
        Route::get('/businesses', [BusinessController::class, 'index']);
        Route::post('/businesses', [BusinessController::class, 'store']);
        Route::get('/businesses/{business}', [BusinessController::class, 'show']);
        Route::put('/businesses/{business}', [BusinessController::class, 'update']);

        // Transactions
        Route::get('/businesses/{business}/transactions', [TransactionController::class, 'index']);
        Route::post('/businesses/{business}/transactions', [TransactionController::class, 'store']);
        Route::post('/businesses/{business}/transactions/parse-sms', [TransactionController::class, 'parseSms']);

        // Credit scores
        Route::get('/businesses/{business}/credit-score', [CreditScoreController::class, 'show']);
        Route::post('/businesses/{business}/credit-score/request', [CreditScoreController::class, 'request']);
        Route::post('/businesses/{business}/credit-score/appeal', [CreditScoreController::class, 'appeal']);

        // Alerts
        Route::get('/businesses/{business}/alerts', [AlertController::class, 'index']);
        Route::patch('/businesses/{business}/alerts/{alert}/read', [AlertController::class, 'markRead']);
    });

    /*
    |--------------------------------------------------------------------------
    | Lender API routes (API key auth)
    |--------------------------------------------------------------------------
    */
    Route::prefix('lender')->middleware('auth:sanctum')->group(function () {
        Route::get('/credit-score/{phone}', [LenderController::class, 'getCreditScore']);
        Route::get('/business-profile/{phone}', [LenderController::class, 'getBusinessProfile']);
        Route::get('/portfolio', [LenderController::class, 'portfolio']);
        Route::get('/revenue', [LenderController::class, 'revenue']);
    });
});