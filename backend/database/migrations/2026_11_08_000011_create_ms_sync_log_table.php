<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Every push to abraa.com and GHL (FR-36, NFR-08)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ms_sync_log', function (Blueprint $table) {
            $table->id();
            $table->enum('target', ['abraa', 'ghl']);
            $table->string('action', 40);
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->json('payload');
            $table->enum('status', ['queued', 'ok', 'failed'])->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('remote_id', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ms_sync_log');
    }
};
