<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Business;
use App\Models\CreditScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UssdTest extends TestCase
{
    use RefreshDatabase;

    private function ussd(string $phone, string $text, string $session = 'TEST123'): \Illuminate\Testing\TestResponse
    {
        return $this->post('/api/v1/ussd/callback', [
            'sessionId'   => $session,
            'serviceCode' => '*384*8562#',
            'phoneNumber' => $phone,
            'text'        => $text,
        ]);
    }

    public function test_main_menu_is_displayed(): void
    {
        $response = $this->ussd('+255712345678', '');

        $response->assertStatus(200)
                 ->assertSeeText('CON')
                 ->assertSeeText('Karibu Kitu Analytics')
                 ->assertSeeText('1. Angalia alama ya mkopo');
    }

    public function test_unregistered_user_sees_registration_prompt(): void
    {
        $response = $this->ussd('+255700999888', '1');

        $response->assertStatus(200)
                 ->assertSeeText('END')
                 ->assertSeeText('Hujasajiliwa bado');
    }

    public function test_registered_user_can_check_score(): void
    {
        $user = User::factory()->create(['phone' => '0700111333']);
        $business = $user->businesses()->create([
            'name' => 'Test Store', 'type' => 'retail', 'status' => 'active',
        ]);

        CreditScore::create([
            'business_id'         => $business->id,
            'score'               => 650,
            'grade'               => 'B',
            'repayment_likelihood' => 65.0,
            'calculated_at'       => now(),
        ]);

        $response = $this->ussd('+255700111333', '1');

        $response->assertStatus(200)
                 ->assertSeeText('650')
                 ->assertSeeText('Daraja: B');
    }

    public function test_registration_flow_creates_user(): void
    {
        // Step 1 — choose register
        $this->ussd('+255700777555', '5', 'REG_SESSION');

        // Step 2 — enter name
        $this->ussd('+255700777555', '5*Juma Mwalimu', 'REG_SESSION');

        // Step 3 — enter business name
        $this->ussd('+255700777555', '5*Juma Mwalimu*Juma Hardware', 'REG_SESSION');

        // Step 4 — choose business type
        $response = $this->ussd('+255700777555', '5*Juma Mwalimu*Juma Hardware*1', 'REG_SESSION');

        $response->assertStatus(200)
                 ->assertSeeText('Umesajiliwa');

        $this->assertDatabaseHas('users', ['phone' => '0700777555']);
        $this->assertDatabaseHas('businesses', ['name' => 'Juma Hardware']);
    }

    public function test_invalid_menu_choice_shows_error(): void
    {
        $response = $this->ussd('+255712345678', '9');

        $response->assertStatus(200)
                 ->assertSeeText('Chaguo si sahihi');
    }
}