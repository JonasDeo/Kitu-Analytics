<?php

namespace App\Http\Controllers\Api\Bookkeeping;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\BkPayment;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\CustomerBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    private function business(Request $request)
    {
        return $request->user()->businesses()->firstOrFail();
    }

    public function index(Request $request)
    {
        $business = $this->business($request);
        $sales = $business->sales()
            ->with(['items.product', 'customer', 'branch'])
            ->orderBy('sold_at', 'desc')
            ->paginate(30);
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'customer_id' => 'nullable|exists:customers,id',
            'channel' => 'nullable|in:walk_in,phone,whatsapp',
            'amount_paid' => 'required|numeric|min:0',
            'payment_method' => 'nullable|in:cash,mpesa,bank,other',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $business = $this->business($request);

            // Calculate total
            $totalAmount = collect($validated['items'])->sum(
                fn($item) => $item['quantity'] * $item['unit_price']
            );

            $amountPaid = (float) $validated['amount_paid'];
            $balanceOwed = max($totalAmount - $amountPaid, 0);
            $isPartial = $balanceOwed > 0;

            // Create sale
            $sale = $business->sales()->create([
                'branch_id' => $validated['branch_id'],
                'user_id' => $request->user()->id,
                'customer_id' => $validated['customer_id'] ?? null,
                'channel' => $validated['channel'] ?? 'walk_in',
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
                'balance_owed' => $balanceOwed,
                'is_partial' => $isPartial,
                'status' => $isPartial ? 'partial' : 'completed',
                'note' => $validated['note'] ?? null,
                'sold_at' => now(),
            ]);

            // Create sale items + decrement stock
            foreach ($validated['items'] as $item) {
                $totalPrice = $item['quantity'] * $item['unit_price'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $totalPrice,
                ]);

                // Decrement stock
                $stock = StockLevel::where('product_id', $item['product_id'])
                    ->where('branch_id', $validated['branch_id'])
                    ->first();

                if ($stock) {
                    $stock->decrement('quantity', $item['quantity']);
                    $stock->refresh();

                    StockMovement::create([
                        'product_id' => $item['product_id'],
                        'branch_id' => $validated['branch_id'],
                        'user_id' => $request->user()->id,
                        'type' => 'sale',
                        'quantity_change' => -$item['quantity'],
                        'quantity_after' => $stock->quantity,
                        'reference' => "sale_{$sale->id}",
                        'moved_at' => now(),
                    ]);
                }
            }

            // Record payment
            if ($amountPaid > 0) {
                BkPayment::create([
                    'business_id' => $business->id,
                    'sale_id' => $sale->id,
                    'customer_id' => $validated['customer_id'] ?? null,
                    'amount' => $amountPaid,
                    'method' => $validated['payment_method'] ?? 'cash',
                    'is_partial' => $isPartial,
                    'paid_at' => now(),
                ]);
            }

            // Update customer balance if partial payment
            if ($isPartial && !empty($validated['customer_id'])) {
                $balance = CustomerBalance::firstOrCreate(
                    ['customer_id' => $validated['customer_id'], 'business_id' => $business->id],
                    ['total_owed' => 0, 'total_credit' => 0, 'net_balance' => 0]
                );
                $balance->increment('total_owed', $balanceOwed);
                $balance->increment('net_balance', $balanceOwed);
                $balance->update(['last_transaction_at' => now()]);
            }

            return response()->json($sale->load(['items.product', 'customer', 'payments']), 201);
        });
    }

    public function show(Sale $sale)
    {
        return response()->json($sale->load(['items.product', 'customer', 'branch', 'payments']));
    }

    public function recordPayment(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|in:cash,mpesa,bank,other',
            'note' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request, $sale) {
            $amount = min((float) $validated['amount'], (float) $sale->balance_owed);

            BkPayment::create([
                'business_id' => $sale->business_id,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'amount' => $amount,
                'method' => $validated['method'] ?? 'cash',
                'is_partial' => false,
                'note' => $validated['note'] ?? null,
                'paid_at' => now(),
            ]);

            $newBalance = max((float) $sale->balance_owed - $amount, 0);
            $sale->update([
                'amount_paid' => (float) $sale->amount_paid + $amount,
                'balance_owed' => $newBalance,
                'is_partial' => $newBalance > 0,
                'status' => $newBalance <= 0 ? 'completed' : 'partial',
            ]);

            // Update customer balance
            if ($sale->customer_id) {
                $balance = CustomerBalance::where('customer_id', $sale->customer_id)
                    ->where('business_id', $sale->business_id)
                    ->first();
                if ($balance) {
                    $balance->decrement('total_owed', $amount);
                    $balance->decrement('net_balance', $amount);
                    $balance->update(['last_transaction_at' => now()]);
                }
            }

            return response()->json([
                'message' => 'Payment recorded.',
                'amount_paid' => $amount,
                'remaining_balance' => $newBalance,
                'sale_status' => $newBalance <= 0 ? 'completed' : 'partial',
            ]);
        });
    }
}