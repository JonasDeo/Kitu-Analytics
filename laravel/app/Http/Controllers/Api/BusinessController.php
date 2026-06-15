<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $businesses = $request->user()->businesses()->with('latestCreditScore')->get();
        return response()->json($businesses);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:retail,vendor,service,agricultural',
            'industry' => 'nullable|string',
            'location' => 'nullable|string',
            'phone' => 'nullable|string',
            'monthly_revenue_estimate' => 'nullable|numeric|min:0',
            'employee_count' => 'nullable|integer|min:1',
        ]);

        $business = $request->user()->businesses()->create($request->validated());

        return response()->json($business, 201);
    }

    public function show(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        return response()->json(
            $business->load(['latestCreditScore', 'alerts' => fn($q) => $q->active()->unread()])
        );
    }

    public function update(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:retail,vendor,service,agricultural',
            'industry' => 'sometimes|string',
            'location' => 'sometimes|string',
            'monthly_revenue_estimate' => 'sometimes|numeric|min:0',
            'employee_count' => 'sometimes|integer|min:1',
        ]);

        $business->update($request->validated());

        return response()->json($business);
    }
}