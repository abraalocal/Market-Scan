<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Visit> */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'user_id' => User::factory(), 'shop_name' => fake()->company(), 'source' => 'field_visit', 'status' => 'draft', 'captured_at' => now()];
    }
}
