<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Lender;
use App\Models\RevenueEvent;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LenderController extends Controller
{
    public function getCreditScore(Request $request, string $phone)
    {
        $lender = $this->authenticateLender($request);

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json(['message' => 'No user found with this phone number.'], 404);
        }

        $business = $user->businesses()->with('latestCreditScore')->first();

        if (!$business || !$business->latestCreditScore) {
            return response()->json(['message' => 'No credit score available for this business.'], 404);
        }

        // Check user has consented to lender access
        if (!$user->hasConsented('lender_access')) {
            return response()->json(['message' => 'User has not consented to lender access.'], 403);
        }

        $score = $business->latestCreditScore;

        // Log revenue event (TZS 2,500 per query)
        RevenueEvent::create([
            'lender_id' => $lender->id,
            'event_type' => 'api_query',
            'reference' => 'QRY-' . strtoupper(Str::random(10)),
            'amount_tzs' => 2500,
            'status' => 'billed',
            'billed_at' => now(),
            'metadata' => [
                'business_id' => $business->id,
                'score' => $score->score,
                'queried_by' => $lender->name,
            ],
        ]);

        // Immutable audit trail
        AuditLog::create([
            'event' => 'lender.credit_score_query',
            'auditable_type' => 'CreditScore',
            'auditable_id' => $score->id,
            'user_id' => null,
            'new_values' => [
                'lender_id' => $lender->id,
                'business_id' => $business->id,
                'score' => $score->score,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'phone' => $phone,
            'business_name' => $business->name,
            'score' => $score->score,
            'grade' => $score->grade,
            'repayment_likelihood' => $score->repayment_likelihood,
            'calculated_at' => $score->calculated_at,
            'factors' => [
                'transaction_frequency' => $score->transaction_frequency_score,
                'cash_flow_stability' => $score->cash_flow_stability_score,
                'network_health' => $score->network_health_score,
            ],
            'recommendation' => $this->recommendation($score->score, $lender->min_credit_score),
            'billed_tzs' => 2500,
        ]);
    }

    public function getBusinessProfile(Request $request, string $phone)
    {
        $lender = $this->authenticateLender($request);

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json(['message' => 'No user found.'], 404);
        }

        if (!$user->hasConsented('lender_access')) {
            return response()->json(['message' => 'User has not consented to lender access.'], 403);
        }

        $business = $user->businesses()
            ->with(['latestCreditScore', 'transactions' => fn($q) => $q->latest('transacted_at')->limit(5)])
            ->first();

        if (!$business) {
            return response()->json(['message' => 'No business profile found.'], 404);
        }

        $totalIncoming = $business->transactions()->where('type', 'incoming')->sum('amount');
        $totalOutgoing = $business->transactions()->whereIn('type', ['outgoing', 'withdrawal'])->sum('amount');
        $transactionCount = $business->transactions()->count();

        return response()->json([
            'business_name' => $business->name,
            'type' => $business->type,
            'industry' => $business->industry,
            'location' => $business->location,
            'employee_count' => $business->employee_count,
            'total_incoming_tzs' => $totalIncoming,
            'total_outgoing_tzs' => $totalOutgoing,
            'net_position_tzs' => $totalIncoming - $totalOutgoing,
            'total_transactions' => $transactionCount,
            'credit_score' => $business->latestCreditScore?->score,
            'credit_grade' => $business->latestCreditScore?->grade,
        ]);
    }

    public function portfolio(Request $request)
    {
        $lender = $this->authenticateLender($request);

        $queries = RevenueEvent::where('lender_id', $lender->id)
            ->where('event_type', 'api_query')
            ->count();

        $totalBilled = RevenueEvent::where('lender_id', $lender->id)
            ->where('status', 'billed')
            ->sum('amount_tzs');

        $recentQueries = RevenueEvent::where('lender_id', $lender->id)
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'lender' => $lender->name,
            'total_queries' => $queries,
            'total_billed_tzs' => $totalBilled,
            'recent_activity' => $recentQueries,
        ]);
    }

    public function revenue(Request $request)
    {
        $lender = $this->authenticateLender($request);

        $events = RevenueEvent::where('lender_id', $lender->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($events);
    }

    private function authenticateLender(Request $request): Lender
    {
        $apiKey = $request->header('X-Lender-API-Key');

        if (!$apiKey) {
            abort(401, 'API key required. Pass X-Lender-API-Key header.');
        }

        $lender = Lender::where('api_key', $apiKey)
            ->where('status', 'active')
            ->first();

        if (!$lender) {
            abort(401, 'Invalid or inactive API key.');
        }

        return $lender;
    }

    private function recommendation(int $score, int $minScore): string
    {
        if ($score >= $minScore && $score >= 650) {
            return 'APPROVE - Score meets lending threshold.';
        } elseif ($score >= $minScore && $score >= 500) {
            return 'CONSIDER - Score meets minimum threshold, review manually.';
        } else {
            return 'DECLINE - Score below lending threshold.';
        }
    }
}