<?php

namespace App\Http\Controllers\Api\Bookkeeping;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    private function business(Request $request)
    {
        return $request->user()->businesses()->firstOrFail();
    }

    public function index(Request $request)
    {
        $suppliers = $this->business($request)
            ->suppliers()
            ->with(['invoices' => fn($q) => $q->whereIn('status', ['unpaid', 'partial'])])
            ->get()
            ->map(function ($s) {
                $s->total_owed = $s->totalOwed();
                return $s;
            });
        return response()->json($suppliers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'contact_person' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $supplier = $this->business($request)->suppliers()->create($validated);
        return response()->json($supplier, 201);
    }

    public function addInvoice(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $business = $this->business($request);
        $invoice = SupplierInvoice::create([
            'business_id' => $business->id,
            'supplier_id' => $supplier->id,
            'amount' => $validated['amount'],
            'amount_paid' => 0,
            'balance_due' => $validated['amount'],
            'due_date' => $validated['due_date'] ?? null,
            'status' => 'unpaid',
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json($invoice, 201);
    }

    public function payInvoice(Request $request, SupplierInvoice $invoice)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'settled_from' => 'nullable|in:cash,mpesa,bank,wallet',
            'reference' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request, $invoice) {
            $amount = min((float) $validated['amount'], (float) $invoice->balance_due);

            SupplierPayment::create([
                'supplier_invoice_id' => $invoice->id,
                'business_id' => $invoice->business_id,
                'amount' => $amount,
                'settled_from' => $validated['settled_from'] ?? 'cash',
                'reference' => $validated['reference'] ?? null,
                'paid_at' => now(),
            ]);

            $newBalance = max((float) $invoice->balance_due - $amount, 0);
            $invoice->update([
                'amount_paid' => (float) $invoice->amount_paid + $amount,
                'balance_due' => $newBalance,
                'status' => $newBalance <= 0 ? 'paid' : 'partial',
            ]);

            return response()->json([
                'message' => 'Payment recorded.',
                'amount_paid' => $amount,
                'remaining_balance' => $newBalance,
                'status' => $newBalance <= 0 ? 'paid' : 'partial',
            ]);
        });
    }
}