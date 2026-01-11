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
        // Create new table for page-level click aggregates
        Schema::create('enriched_page_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->foreignId('page_id')->nullable()->constrained('pages')->onDelete('cascade');
            $table->date('date');
            $table->integer('clicks')->default(0);
            $table->integer('unique_clicks')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'page_id', 'date']);
            $table->index(['page_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enriched_page_clicks');
    }
};
