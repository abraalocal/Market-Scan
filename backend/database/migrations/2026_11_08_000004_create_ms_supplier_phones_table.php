<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phones used for dedupe (FR-04, FR-30)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_supplier_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('ms_suppliers')->cascadeOnDelete();
            $table->string('phone', 20)->unique();
            $table->enum('kind', ['mobile', 'whatsapp', 'landline'])->default('mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_supplier_phones');
    }
};
