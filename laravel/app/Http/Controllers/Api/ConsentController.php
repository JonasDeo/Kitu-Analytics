<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentRecord;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    const CONSENT_TYPES = [
        'data_processing',
        'lender_access',
        'model_training',
    ];

    const CONSENT_VERSION = '1.0';

    public function index(Request $request)
    {
        $consents = $request->user()->consentRecords()
            ->whereNull('withdrawn_at')
            ->get();

        return response()->json($consents);
    }

    public function grant(Request $request)
    {
        $request->validate([
            'consent_type' => 'required|in:data_processing,lender_access,model_training',
        ]);

        $texts = [
            'data_processing' => 'Nakubali Kitu Analytics kushughulikia data yangu ya M-Pesa kwa lengo la uchambuzi wa fedha.',
            'lender_access' => 'Nakubali wakopesha wanaoidhinishwa kuona alama yangu ya mkopo.',
            'model_training' => 'Nakubali data yangu itumiwe kuboresha mifumo ya akili bandia ya Kitu.',
        ];

        $consent = ConsentRecord::create([
            'user_id' => $request->user()->id,
            'consent_type' => $request->consent_type,
            'granted' => true,
            'consent_version' => self::CONSENT_VERSION,
            'consent_text_shown' => $texts[$request->consent_type],
            'channel' => 'web',
            'ip_address' => $request->ip(),
            'granted_at' => now(),
        ]);

        return response()->json($consent, 201);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'consent_type' => 'required|in:data_processing,lender_access,model_training',
        ]);

        $consent = $request->user()->consentRecords()
            ->where('consent_type', $request->consent_type)
            ->whereNull('withdrawn_at')
            ->firstOrFail();

        $consent->update(['withdrawn_at' => now()]);

        return response()->json(['message' => 'Consent withdrawn successfully.']);
    }
}