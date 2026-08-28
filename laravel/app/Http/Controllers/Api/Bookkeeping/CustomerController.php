<?php

namespace App\Http\Controllers\Api\Bookkeeping;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\SmsService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    private function business(Request $request)
    {
        return $request->user()->businesses()->firstOrFail();
    }

    public function index(Request $request)
    {
        $customers = $this->business($request)
            ->customers()
            ->with('balance')
            ->get()
            ->map(function ($c) {
                $c->total_owed = $c->totalOwed();
                return $c;
            });
        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
            'note' => 'nullable|string',
        ]);

        $customer = $this->business($request)->customers()->create($validated);
        return response()->json($customer, 201);
    }

    public function show(Request $request, Customer $customer)
    {
        return response()->json(
            $customer->load(['sales' => fn($q) => $q->latest('sold_at')->limit(10), 'balance'])
        );
    }

    public function sendReminder(Request $request, Customer $customer)
    {
        if (!$customer->phone) {
            return response()->json(['message' => 'Customer has no phone number.'], 422);
        }

        $balance = $customer->balance;
        if (!$balance || $balance->net_balance <= 0) {
            return response()->json(['message' => 'Customer has no outstanding balance.'], 422);
        }

        $business = $this->business($request);
        $formatted = number_format($balance->net_balance);

        $sms = new SmsService();
        $sent = $sms->sendAlert(
            $customer->phone,
            "Habari {$customer->name}, una deni la TZS {$formatted} kwa {$business->name}. Tafadhali lipa haraka iwezekanavyo. Asante."
        );

        return response()->json([
            'message' => $sent ? 'Reminder sent.' : 'SMS failed — check Africa\'s Talking config.',
            'sms_sent' => $sent,
            'amount_owed' => $balance->net_balance,
        ]);
    }
}