<?php

namespace App\Http\Controllers\Api\Bookkeeping;

use App\Http\Controllers\Controller;
use App\Models\BkExpense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    private function business(Request $request)
    {
        return $request->user()->businesses()->firstOrFail();
    }

    public function index(Request $request)
    {
        $expenses = $this->business($request)
            ->bkExpenses()
            ->orderBy('expensed_at', 'desc')
            ->paginate(30);
        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'category' => 'required|in:rent,salary,utilities,stock,transport,other',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
            'expensed_at' => 'nullable|date',
        ]);

        $business = $this->business($request);
        $expense = $business->bkExpenses()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'expensed_at' => $validated['expensed_at'] ?? now(),
        ]);

        return response()->json($expense, 201);
    }
}