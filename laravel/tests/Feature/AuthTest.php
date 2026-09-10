<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Test User',
            'phone'    => '0712345678',
            'password' => 'secret123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'user_id', 'otp']);

        $this->assertDatabaseHas('users', ['phone' => '0712345678']);
    }

    public function test_user_can_verify_otp(): void
    {
        $user = User::factory()->create([
            'phone'       => '0712345678',
            'is_verified' => false,
        ]);

        Cache::put("otp:{$user->phone}", 123456, now()->addMinutes(10));

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '0712345678',
            'otp'   => '123456',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user']);
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'phone'    => '0712345678',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone'    => '0712345678',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'phone'    => '0712345678',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone'    => '0712345678',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                         ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $user->id]);
    }
}