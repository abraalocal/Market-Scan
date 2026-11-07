<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Products not on abraa.com yet (FR-50..FR-56)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_new_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('ms_visits');
            $table->foreignId('ref_id')->unique()->constrained('ms_product_refs');
            $table->enum('status', ['searching', 'ready', 'needs_person', 'published', 'rejected'])->default('searching');
            $table->decimal('match_confidence', 4, 3)->nullable();
            $table->text('match_reasons')->nullable();
            $table->string('source_name', 120)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('desc_en')->nullable();
            $table->text('desc_ar')->nullable();
            $table->json('specs')->nullable();
            $table->json('images')->nullable();
            $table->unsignedBigInteger('abraa_category_id')->nullable();
            $table->unsignedSmallInteger('moq')->default(12);
            $table->unsignedBigInteger('image_photo_id')->nullable();
            $table->unsignedBigInteger('abraa_product_id')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_new_products');
    }
};
