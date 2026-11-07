<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Capture App users (FR-01)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 20)->unique();
            $table->enum('role', ['field_sales', 'admin', 'owner']);
            $table->string('area', 120)->nullable();
            $table->unsignedSmallInteger('daily_visit_target')->default(10);
            $table->unsignedSmallInteger('daily_supplier_target')->default(5);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->enum('locale', ['en', 'ar'])->default('en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_users');
    }
};
