<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Business;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookkeepingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Business $business;
    private Branch $branch;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user     = User::factory()->create();
        $this->token    = $this->user->createToken('test')->plainTextToken;
        $this->business = $this->user->businesses()->create([
            'name'   => 'Test Store',
            'type'   => 'retail',
            'status' => 'active',
        ]);
        $this->branch = $this->business->branches()->create([
            'name'    => 'Main Branch',
            'is_main' => true,
        ]);
    }

    public function test_can_create_product(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->postJson('/api/v1/bk/products', [
                             'name'       => 'Unga wa Ngano',
                             'cost_price' => 3500,
                             'sale_price' => 4500,
                             'branch_id'  => $this->branch->id,
                             'initial_stock' => 50,
                         ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'Unga wa Ngano']);

        $this->assertDatabaseHas('products', ['name' => 'Unga wa Ngano']);
        $this->assertDatabaseHas('stock_levels', ['quantity' => 50]);
    }

    public function test_sale_decrements_stock(): void
    {
        $product = $this->business->products()->create([
            'name'       => 'Test Product',
            'cost_price' => 1000,
            'sale_price' => 1500,
        ]);

        StockLevel::create([
            'product_id'          => $product->id,
            'branch_id'           => $this->branch->id,
            'quantity'            => 20,
            'low_stock_threshold' => 5,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->postJson('/api/v1/bk/sales', [
                             'branch_id'   => $this->branch->id,
                             'amount_paid' => 3000,
                             'items'       => [[
                                 'product_id' => $product->id,
                                 'quantity'   => 2,
                                 'unit_price' => 1500,
                             ]],
                         ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id,
            'quantity'   => 18,
        ]);
    }

    public function test_partial_payment_creates_balance_owed(): void
    {
        $product = $this->business->products()->create([
            'name'       => 'Test Product',
            'cost_price' => 1000,
            'sale_price' => 5000,
        ]);

        StockLevel::create([
            'product_id'          => $product->id,
            'branch_id'           => $this->branch->id,
            'quantity'            => 10,
            'low_stock_threshold' => 2,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->postJson('/api/v1/bk/sales', [
                             'branch_id'   => $this->branch->id,
                             'amount_paid' => 3000,  // only partial
                             'items'       => [[
                                 'product_id' => $product->id,
                                 'quantity'   => 1,
                                 'unit_price' => 5000,
                             ]],
                         ]);

        $response->assertStatus(201)
                 ->assertJsonFragment([
                     'total_amount'  => '5000.00',
                     'amount_paid'   => '3000.00',
                     'balance_owed'  => '2000.00',
                     'is_partial'    => true,
                     'status'        => 'partial',
                 ]);
    }

    public function test_daily_report_returns_correct_totals(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->getJson('/api/v1/bk/reports/daily');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'date',
                     'sales_count',
                     'total_revenue',
                     'total_collected',
                     'total_owed',
                     'net_profit',
                 ]);
    }
}