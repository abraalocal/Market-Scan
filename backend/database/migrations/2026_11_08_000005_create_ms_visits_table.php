<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A shop visit or owner listing (FR-05..FR-09)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_visits', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('user_id')->constrained('ms_users');
            $table->foreignId('supplier_id')->nullable()->constrained('ms_suppliers');
            $table->string('shop_name', 190);
            $table->enum('source', ['field_visit', 'owner']);
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->enum('status', ['draft', 'processing', 'review', 'needs_review', 'submitted', 'pending_approval', 'live'])->default('draft');
            $table->json('extraction')->nullable();
            $table->json('candidates')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_visits');
    }
};
