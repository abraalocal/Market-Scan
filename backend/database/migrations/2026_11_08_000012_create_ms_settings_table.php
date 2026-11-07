<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admin switches, e.g. auto_publish (FR-54)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_settings', function (Blueprint $table) {
            $table->string('name', 64)->primary();
            $table->json('value');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_settings');
    }
};
