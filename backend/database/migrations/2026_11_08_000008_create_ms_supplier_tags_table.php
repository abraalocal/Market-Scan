<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Product keywords on a supplier (FR-40..FR-43)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_supplier_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('ms_suppliers')->cascadeOnDelete();
            $table->foreignId('ref_id')->constrained('ms_product_refs');
            $table->boolean('self_reported')->default(false);
            $table->boolean('seen_on_last_visit')->default(true);
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('evidence_photo_id')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['supplier_id', 'ref_id'], 'uniq_tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_supplier_tags');
    }
};
