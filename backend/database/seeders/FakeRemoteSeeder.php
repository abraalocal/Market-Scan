<?php

namespace Database\Seeders;

use App\Remote\AbraaClient;
use App\Remote\FakeAbraaClient;
use App\Remote\FakeGhlClient;
use App\Remote\GhlClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample suppliers and products in the stand-in abraa.com and GHL (only when their drivers are fake):
 * Al Noor on both (linked), Gulf Beauty only on abraa.com, Star Perfumes only in GHL.
 */
class FakeRemoteSeeder extends Seeder
{
    public function run(): void
    {
        $abraa = app(AbraaClient::class);
        $ghl = app(GhlClient::class);
        if (! $abraa instanceof FakeAbraaClient || ! $ghl instanceof FakeGhlClient) {
            return;
        }
        DB::table('ms_fake_abraa_suppliers')->delete();
        DB::table('ms_fake_abraa_products')->delete();
        DB::table('ms_fake_ghl_contacts')->delete();

        $alNoorGhl = $ghl->createContact(['company_name' => 'Al Noor Cosmetics', 'contact_name' => 'Khalid Rahman', 'phones' => ['+971502237781'], 'email' => 'sales@alnoorcosmetics.ae', 'area' => 'Deira, Dubai'], ['Hair Care', 'Kérastase']);
        $alNoor = $abraa->createSupplier(['company_name' => 'Al Noor Cosmetics', 'contact_name' => 'Khalid Rahman', 'phones' => ['+971502237781'], 'email' => 'sales@alnoorcosmetics.ae', 'area' => 'Deira, Dubai', 'ghl_contact_id' => $alNoorGhl]);
        $ghl->updateContact($alNoorGhl, ['abraa_supplier_id' => $alNoor]);
        $abraa->addKeywords($alNoor, ['Hair Care', 'Kérastase', 'Nutritive']);

        $abraa->createSupplier(['company_name' => 'Gulf Beauty Trading', 'phones' => ['+971554102290'], 'area' => 'Naif, Deira']);
        $ghl->createContact(['company_name' => 'Star Perfumes LLC', 'phones' => ['+971529081144'], 'area' => 'Deira, Dubai'], ['Fragrance']);

        foreach ([
            ['brand' => 'Kérastase', 'line' => 'Nutritive', 'name' => 'Bain Satin Shampoo', 'size' => '1L'],
            ['brand' => 'Olaplex', 'line' => 'No.4', 'name' => 'Bond Maintenance Shampoo', 'size' => '250 ml'],
            ['brand' => 'SheaMoisture', 'line' => 'Coconut & Hibiscus', 'name' => 'Curl Enhancing Smoothie', 'size' => '340 g'],
            ['brand' => 'Nivea', 'line' => 'Creme', 'name' => 'Tin', 'size' => '150 ml', 'barcode' => '4005808158062'],
        ] as $p) {
            $abraa->createProduct($p);
        }
    }
}
