<?php

namespace App\Http\Controllers\Api\Bookkeeping;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    private function business(Request $request)
    {
        return $request->user()->businesses()->firstOrFail();
    }

    public function index(Request $request)
    {
        $branches = $this->business($request)->branches()->get();
        return response()->json($branches);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'is_main' => 'nullable|boolean',
        ]);

        $business = $this->business($request);

        // If first branch, make it main automatically
        if ($business->branches()->count() === 0) {
            $validated['is_main'] = true;
        }

        $branch = $business->branches()->create($validated);
        return response()->json($branch, 201);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);
        $branch->update($validated);
        return response()->json($branch);
    }
}