<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Business;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditScoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Business $business;
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

        // Seed some transactions
        for ($i = 0; $i < 10; $i++) {
            Transaction::create([
                'business_id'      => $this->business->id,
                'type'             => 'incoming',
                'amount'           => rand(5000, 50000),
                'counterparty_name' => 'Customer ' . $i,
                'transacted_at'    => now()->subDays($i),
                'mpesa_reference'  => 'REF' . $i,
            ]);
        }
    }

    public function test_user_can_view_businesses(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->getJson('/api/v1/businesses');

        $response->assertStatus(200)
                 ->assertJsonCount(1);
    }

    public function test_user_can_get_transaction_summary(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->getJson("/api/v1/businesses/{$this->business->id}/summary");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'total_incoming',
                     'total_outgoing',
                     'net_position',
                     'transaction_count',
                 ]);

        $this->assertEquals(10, $response->json('transaction_count'));
    }

    public function test_credit_score_returns_404_when_no_score(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->getJson("/api/v1/businesses/{$this->business->id}/credit-score");

        $response->assertStatus(404);
    }

    public function test_user_cannot_access_another_users_business(): void
    {
        $otherUser     = User::factory()->create();
        $otherBusiness = $otherUser->businesses()->create([
            'name'   => 'Other Store',
            'type'   => 'retail',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->getJson("/api/v1/businesses/{$otherBusiness->id}/summary");

        $response->assertStatus(403);
    }
}