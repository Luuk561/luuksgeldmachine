<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type'); // 'incremental', 'full', 'initial'
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('status'); // 'success', 'failed', 'running'
            $table->integer('records_imported')->default(0);
            $table->integer('records_enriched')->default(0);
            $table->integer('records_aggregated')->default(0);
            $table->text('error_message')->nullable();
            $table->json('details')->nullable(); // Extra metadata (days fetched, etc.)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
