<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Local copy of suppliers, linked to abraa.com and GHL (FR-31, FR-36)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('abraa_supplier_id')->nullable()->unique();
            $table->string('ghl_contact_id', 64)->nullable()->unique();
            $table->string('company_name', 190);
            $table->string('contact_name', 120)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('landline', 20)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('website', 190)->nullable();
            $table->string('area', 120)->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->enum('source', ['field_visit', 'owner']);
            $table->timestamp('last_visit_at')->nullable();
            $table->timestamps();
            $table->index('company_name');
            $table->index('email');
            $table->index(['gps_lat', 'gps_lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_suppliers');
    }
};
