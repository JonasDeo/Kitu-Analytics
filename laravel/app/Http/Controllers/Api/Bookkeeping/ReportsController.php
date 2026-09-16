<?php

namespace App\Http\Controllers\Api\Bookkeeping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    private function business(Request $request)
    {
        return $request->user()->businesses()->firstOrFail();
    }

    public function daily(Request $request)
    {
        $business  = $this->business($request);
        $date      = $request->query('date', today()->toDateString());
        $branchId  = $request->query('branch_id');

        $salesQuery = $business->sales()->whereDate('sold_at', $date)->with('items.product');
        if ($branchId) $salesQuery->where('branch_id', $branchId);
        $sales = $salesQuery->get();

        $expensesQuery = $business->bkExpenses()->whereDate('expensed_at', $date);
        if ($branchId) $expensesQuery->where('branch_id', $branchId);
        $expenses = $expensesQuery->get();

        $totalRevenue   = $sales->sum('total_amount');
        $totalCollected = $sales->sum('amount_paid');
        $totalOwed      = $sales->sum('balance_owed');
        $totalExpenses  = $expenses->sum('amount');
        $netProfit      = $totalCollected - $totalExpenses;

        return response()->json([
            'date'            => $date,
            'branch_id'       => $branchId,
            'sales_count'     => $sales->count(),
            'total_revenue'   => $totalRevenue,
            'total_collected' => $totalCollected,
            'total_owed'      => $totalOwed,
            'total_expenses'  => $totalExpenses,
            'net_profit'      => $netProfit,
            'sales'           => $sales,
            'expenses'        => $expenses,
        ]);
    }

    public function summary(Request $request)
    {
        $business  = $this->business($request);
        $days      = (int) $request->query('days', 30);
        $branchId  = $request->query('branch_id');
        $from      = now()->subDays($days);

        $salesQuery = $business->sales()->where('sold_at', '>=', $from);
        if ($branchId) $salesQuery->where('branch_id', $branchId);

        $salesData = $salesQuery->selectRaw('
            COUNT(*) as total_sales,
            SUM(total_amount) as total_revenue,
            SUM(amount_paid) as total_collected,
            SUM(balance_owed) as total_outstanding,
            COUNT(CASE WHEN is_partial THEN 1 END) as partial_sales
        ')->first();

        $expensesQuery = $business->bkExpenses()->where('expensed_at', '>=', $from);
        if ($branchId) $expensesQuery->where('branch_id', $branchId);
        $expenseData = $expensesQuery->selectRaw('SUM(amount) as total_expenses, COUNT(*) as expense_count')->first();

        $topProductsQuery = \DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.business_id', $business->id)
            ->where('sales.sold_at', '>=', $from);
        if ($branchId) $topProductsQuery->where('sales.branch_id', $branchId);

        $topProducts = $topProductsQuery
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.id, products.name, SUM(sale_items.quantity) as qty_sold, SUM(sale_items.total_price) as revenue')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return response()->json([
            'period_days' => $days,
            'branch_id'   => $branchId,
            'from'        => $from->toDateString(),
            'to'          => now()->toDateString(),
            'sales'       => $salesData,
            'expenses'    => $expenseData,
            'net_profit'  => (float)($salesData->total_collected ?? 0) - (float)($expenseData->total_expenses ?? 0),
            'top_products' => $topProducts,
        ]);
    }

    public function products(Request $request)
    {
        $business = $this->business($request);

        $products = $business->products()
            ->with('stockLevels.branch')
            ->where('is_active', true)
            ->get()
            ->map(function ($p) {
                $p->profit_margin = $p->profitMargin();
                $p->is_low_stock = $p->stockLevels->some(fn($s) => $s->isLowStock());
                $p->total_stock = $p->stockLevels->sum('quantity');
                return $p;
            });

        $lowStock = $products->filter(fn($p) => $p->is_low_stock);

        return response()->json([
            'total_products' => $products->count(),
            'low_stock_count' => $lowStock->count(),
            'low_stock_items' => $lowStock->values(),
            'products' => $products,
        ]);
    }

    public function debtors(Request $request)
    {
        $business = $this->business($request);

        $debtors = $business->customers()
            ->with('balance')
            ->get()
            ->filter(fn($c) => $c->totalOwed() > 0)
            ->map(function ($c) {
                $c->total_owed = $c->totalOwed();
                return $c;
            })
            ->sortByDesc('total_owed')
            ->values();

        $supplierDebt = $business->suppliers()
            ->get()
            ->map(function ($s) {
                $s->total_owed = $s->totalOwed();
                return $s;
            })
            ->filter(fn($s) => $s->total_owed > 0)
            ->sortByDesc('total_owed')
            ->values();

        return response()->json([
            'customers_who_owe_us' => [
                'count' => $debtors->count(),
                'total' => $debtors->sum('total_owed'),
                'list' => $debtors,
            ],
            'suppliers_we_owe' => [
                'count' => $supplierDebt->count(),
                'total' => $supplierDebt->sum('total_owed'),
                'list' => $supplierDebt,
            ],
        ]);
    }
}