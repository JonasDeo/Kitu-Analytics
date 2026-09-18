<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Services\MlService;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $businesses = $request->user()
            ->businesses()
            ->with('latestCreditScore')
            ->get();

        return response()->json($businesses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:retail,vendor,service,agricultural',
            'industry' => 'nullable|string',
            'location' => 'nullable|string',
            'phone' => 'nullable|string',
            'monthly_revenue_estimate' => 'nullable|numeric|min:0',
            'employee_count' => 'nullable|integer|min:1',
        ]);

        $business = $request->user()
            ->businesses()
            ->create($validated);

        return response()->json($business, 201);
    }

    public function show(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        return response()->json(
            $business->load([
                'latestCreditScore',
                'alerts' => fn ($q) => $q->active()->unread()
            ])
        );
    }

    public function update(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:retail,vendor,service,agricultural',
            'industry' => 'sometimes|string',
            'location' => 'sometimes|string',
            'monthly_revenue_estimate' => 'sometimes|numeric|min:0',
            'employee_count' => 'sometimes|integer|min:1',
        ]);

        $business->update($validated);

        return response()->json($business);
    }

    public function network(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $ml = new MlService();
        $response = $ml->get("/network/{$business->id}");

        if ($response->failed()) {
            return response()->json([
                'message' => 'Network analysis unavailable.'
            ], 502);
        }

        return response()->json($response->json());
    }

    public function forecast(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $ml = new MlService();
        $response = $ml->get("/forecast/{$business->id}");

        if ($response->failed()) {
            return response()->json([
                'message' => 'Forecast unavailable.'
            ], 502);
        }

        return response()->json($response->json());
    }

    public function botCompliance(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $ml = new MlService();
        $response = $ml->get("/bot-compliance/{$business->id}");

        if ($response->failed()) {
            return response()->json([
                'message' => 'Compliance report unavailable.'
            ], 502);
        }

        return response()->json($response->json());
    }

    public function creditReport(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $ml = new MlService();
        $response = $ml->get("/report/{$business->id}");

        if ($response->failed()) {
            return response()->json([
                'message' => 'Could not generate report.'
            ], 502);
        }

        return response(
            $response->body(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    "attachment; filename=kitu_credit_report_{$business->id}.pdf",
            ]
        );
    }

    public function fraudCheck(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $ml = new MlService();
        $response = $ml->get("/fraud/{$business->id}");

        if ($response->failed()) {
            return response()->json([
                'message' => 'Fraud check unavailable.'
            ], 502);
        }

        // Log fraud check in audit trail
        AuditLog::create([
            'event' => 'fraud.check_performed',
            'auditable_type' => 'Business',
            'auditable_id' => $business->id,
            'user_id' => $request->user()->id,
            'new_values' => $response->json(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json($response->json());
    }
}