<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Http;
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

    public function summary(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $totalIncoming = $business->transactions()
            ->where('type', 'incoming')
            ->sum('amount');

        $totalOutgoing = $business->transactions()
            ->whereIn('type', ['outgoing', 'withdrawal'])
            ->sum('amount');

        $transactionCount = $business->transactions()->count();

        $incomingCount = $business->transactions()
            ->where('type', 'incoming')
            ->count();

        $outgoingCount = $business->transactions()
            ->whereIn('type', ['outgoing', 'withdrawal'])
            ->count();

        $firstTransaction = $business->transactions()
            ->orderBy('transacted_at', 'asc')
            ->value('transacted_at');

        $lastTransaction = $business->transactions()
            ->orderBy('transacted_at', 'desc')
            ->value('transacted_at');

        return response()->json([
            'total_incoming' => (float) $totalIncoming,
            'total_outgoing' => (float) $totalOutgoing,
            'net_position' => (float) ($totalIncoming - $totalOutgoing),
            'transaction_count' => $transactionCount,
            'incoming_count' => $incomingCount,
            'outgoing_count' => $outgoingCount,
            'first_transaction_at' => $firstTransaction,
            'last_transaction_at' => $lastTransaction,
        ]);
    }

    public function parsePhoto(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $request->validate([
            'photo' => 'required|image|max:10240', // max 10MB
        ]);

        $mlServiceUrl = env('ML_SERVICE_URL', 'http://ml:8001');

        // Forward the image to the ML service
        $response = Http::timeout(30)->attach(
            'file',
            file_get_contents($request->file('photo')->getRealPath()),
            $request->file('photo')->getClientOriginalName(),
            ['Content-Type' => $request->file('photo')->getMimeType()]
        )->post("{$mlServiceUrl}/ocr/parse-mpesa");

        if ($response->failed()) {
            return response()->json([
                'message' => 'OCR processing failed.',
                'error'   => $response->json('detail') ?? 'ML service error',
            ], 502);
        }

        $data = $response->json();

        if ($data['transactions_found'] === 0) {
            return response()->json([
                'message'             => 'No M-Pesa transactions found in the image.',
                'extracted_text'      => $data['extracted_text'],
                'transactions_found'  => 0,
            ], 422);
        }

        // Save each parsed transaction to the database
        $saved = [];
        foreach ($data['transactions'] as $tx) {
            // Skip duplicates by reference
            if (!empty($tx['mpesa_reference'])) {
                $exists = $business->transactions()
                    ->where('mpesa_reference', $tx['mpesa_reference'])
                    ->exists();
                if ($exists) continue;
            }

            $transaction = $business->transactions()->create([
                'type'               => $tx['type'],
                'amount'             => $tx['amount'],
                'counterparty_name'  => $tx['counterparty_name'] ?? 'Unknown',
                'counterparty_phone' => $tx['counterparty_phone'] ?? null,
                'mpesa_reference'    => $tx['mpesa_reference'] ?? null,
                'transacted_at'      => $tx['transacted_at'],
                'raw_sms'            => 'OCR extracted',
            ]);

            $saved[] = $transaction;
        }

        return response()->json([
            'message'            => count($saved) . ' transactions extracted and saved.',
            'transactions_found' => $data['transactions_found'],
            'transactions_saved' => count($saved),
            'transactions'       => $saved,
            'extracted_text'     => $data['extracted_text'],
        ]);
    }
}