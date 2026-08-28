<?php

namespace App\Http\Controllers\Api\Bookkeeping;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private function business(Request $request)
    {
        return $request->user()->businesses()->firstOrFail();
    }

    public function index(Request $request)
    {
        $business = $this->business($request);
        $products = $business->products()
            ->with('stockLevels.branch')
            ->where('is_active', true)
            ->get()
            ->map(function ($product) {
                $product->profit_margin = $product->profitMargin();
                return $product;
            });
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string',
            'barcode' => 'nullable|string',
            'unit' => 'nullable|string',
            'category' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'initial_stock' => 'nullable|numeric|min:0',
            'branch_id' => 'nullable|exists:branches,id',
            'low_stock_threshold' => 'nullable|numeric|min:0',
        ]);

        $business = $this->business($request);
        $product = $business->products()->create($validated);

        // Initialize stock level if branch provided
        if (!empty($validated['branch_id'])) {
            StockLevel::create([
                'product_id' => $product->id,
                'branch_id' => $validated['branch_id'],
                'quantity' => $validated['initial_stock'] ?? 0,
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 5,
            ]);

            if (!empty($validated['initial_stock'])) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'branch_id' => $validated['branch_id'],
                    'user_id' => $request->user()->id,
                    'type' => 'restock',
                    'quantity_change' => $validated['initial_stock'],
                    'quantity_after' => $validated['initial_stock'],
                    'note' => 'Initial stock',
                    'moved_at' => now(),
                ]);
            }
        }

        return response()->json($product->load('stockLevels'), 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'cost_price' => 'sometimes|numeric|min:0',
            'sale_price' => 'sometimes|numeric|min:0',
            'category' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);
        $product->update($validated);
        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);
        return response()->json(['message' => 'Product deactivated.']);
    }

    public function stock(Product $product)
    {
        return response()->json($product->stockLevels()->with('branch')->get());
    }

    public function restock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'quantity' => 'required|numeric|min:0.001',
            'note' => 'nullable|string',
        ]);

        $stockLevel = StockLevel::firstOrCreate(
            ['product_id' => $product->id, 'branch_id' => $validated['branch_id']],
            ['quantity' => 0, 'low_stock_threshold' => 5]
        );

        $stockLevel->increment('quantity', $validated['quantity']);
        $stockLevel->refresh();

        StockMovement::create([
            'product_id' => $product->id,
            'branch_id' => $validated['branch_id'],
            'user_id' => $request->user()->id,
            'type' => 'restock',
            'quantity_change' => $validated['quantity'],
            'quantity_after' => $stockLevel->quantity,
            'note' => $validated['note'] ?? 'Manual restock',
            'moved_at' => now(),
        ]);

        return response()->json([
            'product' => $product->name,
            'branch_id' => $validated['branch_id'],
            'quantity_added' => $validated['quantity'],
            'new_quantity' => $stockLevel->quantity,
        ]);
    }
}