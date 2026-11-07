<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Supplier updates waiting for admin approval (FR-32..FR-35)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_supplier_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('ms_visits');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('abraa_supplier_id')->nullable();
            $table->string('ghl_contact_id', 64)->nullable();
            $table->json('current_values');
            $table->json('proposed_values');
            $table->json('keywords_to_add');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_supplier_changes');
    }
};
