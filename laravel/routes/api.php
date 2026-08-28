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

        Route::get('/businesses/{business}/summary', [TransactionController::class, 'summary']);
        
        // Credit scores
        Route::get('/businesses/{business}/credit-score', [CreditScoreController::class, 'show']);
        Route::post('/businesses/{business}/credit-score/request', [CreditScoreController::class, 'request']);
        Route::post('/businesses/{business}/credit-score/appeal', [CreditScoreController::class, 'appeal']);

        // Alerts
        Route::get('/businesses/{business}/alerts', [AlertController::class, 'index']);
        Route::patch('/businesses/{business}/alerts/{alert}/read', [AlertController::class, 'markRead']);

        // Network analysis
        Route::get('/businesses/{business}/network', [BusinessController::class, 'network']);

        // Cash flow forecast
        Route::get('/businesses/{business}/forecast', [BusinessController::class, 'forecast']);

        // BoT compliance
        Route::get('/businesses/{business}/bot-compliance', [BusinessController::class, 'botCompliance']);

        // PDF credit report
        Route::get('/businesses/{business}/credit-report', [BusinessController::class, 'creditReport']);

        // Fraud Detection
        Route::get('/businesses/{business}/fraud', [BusinessController::class, 'fraudCheck']);

        Route::post('/businesses/{business}/credit-score/enhanced', [CreditScoreController::class, 'enhanced']);

        // ── Bookkeeping Module ────────────────────────────────────────────────────
        Route::prefix('bk')->group(function () {

            // Branches
            Route::get('/branches', [\App\Http\Controllers\Api\Bookkeeping\BranchController::class, 'index']);
            Route::post('/branches', [\App\Http\Controllers\Api\Bookkeeping\BranchController::class, 'store']);
            Route::put('/branches/{branch}', [\App\Http\Controllers\Api\Bookkeeping\BranchController::class, 'update']);

            // Products
            Route::get('/products', [\App\Http\Controllers\Api\Bookkeeping\ProductController::class, 'index']);
            Route::post('/products', [\App\Http\Controllers\Api\Bookkeeping\ProductController::class, 'store']);
            Route::put('/products/{product}', [\App\Http\Controllers\Api\Bookkeeping\ProductController::class, 'update']);
            Route::delete('/products/{product}', [\App\Http\Controllers\Api\Bookkeeping\ProductController::class, 'destroy']);
            Route::get('/products/{product}/stock', [\App\Http\Controllers\Api\Bookkeeping\ProductController::class, 'stock']);
            Route::post('/products/{product}/restock', [\App\Http\Controllers\Api\Bookkeeping\ProductController::class, 'restock']);

            // Sales — the core loop
            Route::get('/sales', [\App\Http\Controllers\Api\Bookkeeping\SaleController::class, 'index']);
            Route::post('/sales', [\App\Http\Controllers\Api\Bookkeeping\SaleController::class, 'store']);
            Route::get('/sales/{sale}', [\App\Http\Controllers\Api\Bookkeeping\SaleController::class, 'show']);
            Route::post('/sales/{sale}/pay', [\App\Http\Controllers\Api\Bookkeeping\SaleController::class, 'recordPayment']);

            // Customers
            Route::get('/customers', [\App\Http\Controllers\Api\Bookkeeping\CustomerController::class, 'index']);
            Route::post('/customers', [\App\Http\Controllers\Api\Bookkeeping\CustomerController::class, 'store']);
            Route::get('/customers/{customer}', [\App\Http\Controllers\Api\Bookkeeping\CustomerController::class, 'show']);
            Route::post('/customers/{customer}/remind', [\App\Http\Controllers\Api\Bookkeeping\CustomerController::class, 'sendReminder']);

            // Suppliers
            Route::get('/suppliers', [\App\Http\Controllers\Api\Bookkeeping\SupplierController::class, 'index']);
            Route::post('/suppliers', [\App\Http\Controllers\Api\Bookkeeping\SupplierController::class, 'store']);
            Route::post('/suppliers/{supplier}/invoices', [\App\Http\Controllers\Api\Bookkeeping\SupplierController::class, 'addInvoice']);
            Route::post('/suppliers/invoices/{invoice}/pay', [\App\Http\Controllers\Api\Bookkeeping\SupplierController::class, 'payInvoice']);

            // Expenses
            Route::get('/expenses', [\App\Http\Controllers\Api\Bookkeeping\ExpenseController::class, 'index']);
            Route::post('/expenses', [\App\Http\Controllers\Api\Bookkeeping\ExpenseController::class, 'store']);

            // Reports
            Route::get('/reports/daily', [\App\Http\Controllers\Api\Bookkeeping\ReportsController::class, 'daily']);
            Route::get('/reports/summary', [\App\Http\Controllers\Api\Bookkeeping\ReportsController::class, 'summary']);
            Route::get('/reports/products', [\App\Http\Controllers\Api\Bookkeeping\ReportsController::class, 'products']);
            Route::get('/reports/debtors', [\App\Http\Controllers\Api\Bookkeeping\ReportsController::class, 'debtors']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Lender API routes (API key auth)
    |--------------------------------------------------------------------------
    */
    Route::prefix('lender')->group(function () {
        Route::get('/credit-score/{phone}', [LenderController::class, 'getCreditScore']);
        Route::get('/business-profile/{phone}', [LenderController::class, 'getBusinessProfile']);
        Route::get('/portfolio', [LenderController::class, 'portfolio']);
        Route::get('/revenue', [LenderController::class, 'revenue']);
        Route::get('/report/{phone}', [LenderController::class, 'getCreditReport']);
        Route::post('/repayment-outcome', [LenderController::class, 'postRepaymentOutcome']);
        Route::get('/pre-approvals', [LenderController::class, 'getPreApprovals']);
    });
});