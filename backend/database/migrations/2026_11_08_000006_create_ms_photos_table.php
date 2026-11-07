<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Photos of a visit
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('ms_visits')->cascadeOnDelete();
            $table->string('client_id', 64);
            $table->enum('type', ['card', 'storefront', 'shelf', 'closeup']);
            $table->string('path');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['visit_id', 'client_id'], 'uniq_client');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_photos');
    }
};
