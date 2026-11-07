<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// category > brand > line > product (FR-23)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_product_refs', function (Blueprint $table) {
            $table->id();
            $table->enum('level', ['category', 'brand', 'line', 'product']);
            $table->string('name', 190);
            $table->string('name_ar', 190)->nullable();
            $table->json('aliases')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('ms_product_refs');
            $table->unsignedBigInteger('abraa_category_id')->nullable();
            $table->unsignedBigInteger('abraa_product_id')->nullable();
            $table->string('size', 40)->nullable();
            $table->string('barcode', 32)->nullable();
            $table->timestamps();
            $table->unique(['level', 'parent_id', 'name'], 'uniq_ref');
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_product_refs');
    }
};
