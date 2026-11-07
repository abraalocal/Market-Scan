<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stand-ins for abraa.com and GHL used by the fake drivers (development and tests only)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_fake_abraa_suppliers', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->json('keywords')->nullable();
            $table->timestamps();
        });
        Schema::create('ms_fake_abraa_products', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->timestamps();
        });
        Schema::create('ms_fake_ghl_contacts', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->json('data');
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_fake_ghl_contacts');
        Schema::dropIfExists('ms_fake_abraa_products');
        Schema::dropIfExists('ms_fake_abraa_suppliers');
    }
};
