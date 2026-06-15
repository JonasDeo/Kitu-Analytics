<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Transaction;
use App\Services\MpesaSmsParser;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $transactions = $business->transactions()
            ->orderBy('transacted_at', 'desc')
            ->paginate(50);

        return response()->json($transactions);
    }

    public function store(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $request->validate([
            'type' => 'required|in:incoming,outgoing,payment,withdrawal',
            'amount' => 'required|numeric|min:0',
            'transacted_at' => 'required|date',
            'counterparty_name' => 'nullable|string',
            'counterparty_phone' => 'nullable|string',
            'mpesa_reference' => 'nullable|string|unique:transactions',
            'balance_after' => 'nullable|numeric',
        ]);

        $transaction = $business->transactions()->create($request->validated());

        return response()->json($transaction, 201);
    }

    public function parseSms(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $request->validate([
            'sms_text' => 'required|string',
        ]);

        $parser = new MpesaSmsParser();
        $parsed = $parser->parse($request->sms_text);

        if (!$parsed) {
            return response()->json(['message' => 'Could not parse SMS.'], 422);
        }

        $transaction = $business->transactions()->create([
            ...$parsed,
            'raw_sms' => $request->sms_text,
        ]);

        return response()->json($transaction, 201);
    }
}