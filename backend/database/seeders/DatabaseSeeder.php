<?php

namespace Database\Seeders;

use App\Models\ProductRef;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Development data only. In APP_ENV=local the OTP 123456 logs these users in. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['phone' => '+971500000001'], ['name' => 'Omar', 'role' => 'admin', 'area' => 'Dubai']);
        User::updateOrCreate(['phone' => '+971500000002'], ['name' => 'Sara', 'role' => 'field_sales', 'area' => 'Deira, Dubai']);
        User::updateOrCreate(['phone' => '+971500000003'], ['name' => 'Ahmed', 'role' => 'field_sales', 'area' => 'Al Nahda, Sharjah']);
        User::updateOrCreate(['phone' => '+971500000004'], ['name' => 'Joy', 'role' => 'field_sales', 'area' => 'Al Qusais, Dubai']);

        $this->productTree();

        if (class_exists(FakeRemoteSeeder::class)) {
            $this->call(FakeRemoteSeeder::class);
        }
    }

    private function productTree(): void
    {
        $brands = [
            'Hair Care' => [
                'Kérastase' => ['كيراستاس', ['Kerastase']],
                'Olaplex' => ['أولابلكس', []],
                'Color Wow' => ['كولر واو', ['ColorWow']],
                '&honey' => ['آند هني', ['and honey', 'andhoney']],
                'Nevada' => ['نيفادا', []],
                'SheaMoisture' => ['شيا مويستشر', ['Shea Moisture']],
                'Pantene' => ['بانتين', []],
            ],
            'Skin Care' => ['Nivea' => ['نيفيا', []], 'CeraVe' => ['سيرافي', []], 'Vaseline' => ['فازلين', []]],
            'Body Care' => ['Dove' => ['دوف', []], 'Palmolive' => ['بالموليف', []]],
            'Fragrance' => [], 'Makeup' => [], 'General' => [],
        ];
        foreach ($brands as $cat => $list) {
            $c = ProductRef::firstOrCreate(['level' => 'category', 'parent_id' => null, 'name' => $cat], ['abraa_category_id' => config("abraa.categories.$cat")]);
            foreach ($list as $brand => [$ar, $aliases]) {
                ProductRef::firstOrCreate(['level' => 'brand', 'parent_id' => $c->id, 'name' => $brand], ['name_ar' => $ar, 'aliases' => $aliases]);
            }
        }
    }
}
