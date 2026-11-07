<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return ['company_name' => fake()->unique()->company(), 'area' => 'Deira', 'gps_lat' => 25.2711, 'gps_lng' => 55.3075, 'source' => 'field_visit'];
    }

    public function withPhone(string $e164, string $kind = 'whatsapp'): static
    {
        return $this->state(['whatsapp' => $kind === 'landline' ? null : $e164])
            ->afterCreating(fn (Supplier $s) => $s->phones()->create(['phone' => $e164, 'kind' => $kind]));
    }
}
