<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'phone'             => '07' . fake()->numerify('########'),
            'email'             => fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'role'              => 'business_owner',
            'is_verified'       => true,
            'phone_verified_at' => now(),
        ];
    }
}