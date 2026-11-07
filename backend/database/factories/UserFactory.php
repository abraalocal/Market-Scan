<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return ['name' => fake()->firstName(), 'phone' => '+9715'.fake()->unique()->numerify('########'), 'role' => 'field_sales', 'area' => 'Deira, Dubai', 'locale' => 'en'];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function owner(): static
    {
        return $this->state(['role' => 'owner', 'area' => null]);
    }
}
