<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CreditScore;
use App\Models\ScoreExplanation;
use App\Models\ScoreAppeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CreditScoreController extends Controller
{
    public function show(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $score = $business->latestCreditScore()->with('explanations')->first();

        if (!$score) {
            return response()->json(['message' => 'No credit score yet. Request one first.'], 404);
        }

        return response()->json($score);
    }

    public function request(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $mlServiceUrl = env('ML_SERVICE_URL', 'http://ml:8001');

        $response = Http::timeout(10)->get("{$mlServiceUrl}/score/{$business->id}");

        if ($response->failed()) {
            return response()->json([
                'message' => 'Could not calculate credit score.',
                'error' => $response->json('detail') ?? 'ML service unavailable',
            ], 502);
        }

        $data = $response->json();

        $creditScore = CreditScore::create([
            'business_id' => $business->id,
            'score' => $data['score'],
            'grade' => $data['grade'],
            'transaction_frequency_score' => $data['factors']['transaction_frequency_score'],
            'cash_flow_stability_score' => $data['factors']['cash_flow_stability_score'],
            'network_health_score' => $data['factors']['network_health_score'],
            'repayment_likelihood' => $data['factors']['repayment_likelihood'],
            'factors' => $data['factors'],
            'calculated_at' => $data['calculated_at'],
        ]);

        $this->generateExplanations($creditScore, $data['factors']);

        return response()->json($creditScore->load('explanations'), 201);
    }

    public function appeal(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $validated = $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        $latestScore = $business->latestCreditScore;

        if (!$latestScore) {
            return response()->json(['message' => 'No credit score to appeal.'], 404);
        }

        $appeal = ScoreAppeal::create([
            'business_id' => $business->id,
            'credit_score_id' => $latestScore->id,
            'reason' => $validated['reason'],
            'status' => 'pending',
            'score_before' => $latestScore->score,
            'due_at' => now()->addHours(48),
        ]);

        return response()->json($appeal, 201);
    }

    private function generateExplanations(CreditScore $score, array $factors): void
    {
        $explanations = [
            [
                'factor' => 'transaction_frequency',
                'value' => $factors['transaction_frequency_score'],
                'impact' => $factors['transaction_frequency_score'] >= 60 ? 'positive' : 'negative',
                'explanation_en' => $factors['transaction_frequency_score'] >= 60
                    ? 'Your business transacts consistently, which builds trust with lenders.'
                    : 'Your business has gaps in transaction activity. More consistent activity could improve your score.',
                'explanation_sw' => $factors['transaction_frequency_score'] >= 60
                    ? 'Biashara yako inafanya manunuzi mara kwa mara, hii inajenga uwiano kwa wakopeshaji.'
                    : 'Biashara yako ina mapengo katika shughuli za miamala. Shughuli za mara kwa mara zinaweza kuboresha alama yako.',
            ],
            [
                'factor' => 'cash_flow_stability',
                'value' => $factors['cash_flow_stability_score'],
                'impact' => $factors['cash_flow_stability_score'] >= 60 ? 'positive' : 'negative',
                'explanation_en' => $factors['cash_flow_stability_score'] >= 60
                    ? 'Your cash flow is stable from week to week.'
                    : 'Your cash flow varies significantly. Lenders see this as higher risk.',
                'explanation_sw' => $factors['cash_flow_stability_score'] >= 60
                    ? 'Mtiririko wako wa fedha ni thabiti kila wiki.'
                    : 'Mtiririko wako wa fedha unabadilika sana. Wakopeshaji huona hii kama hatari zaidi.',
            ],
            [
                'factor' => 'network_health',
                'value' => $factors['network_health_score'],
                'impact' => $factors['network_health_score'] >= 60 ? 'positive' : 'negative',
                'explanation_en' => 'Your business network strength is based on transaction volume and counterparty diversity.',
                'explanation_sw' => 'Nguvu ya mtandao wa biashara yako inategemea kiwango cha miamala na utofauti wa wahusika.',
            ],
        ];

        foreach ($explanations as $exp) {
            ScoreExplanation::create([
                'credit_score_id' => $score->id,
                'business_id' => $score->business_id,
                'factor' => $exp['factor'],
                'impact' => $exp['impact'],
                'weight' => $exp['value'],
                'explanation_en' => $exp['explanation_en'],
                'explanation_sw' => $exp['explanation_sw'],
            ]);
        }
    }
}