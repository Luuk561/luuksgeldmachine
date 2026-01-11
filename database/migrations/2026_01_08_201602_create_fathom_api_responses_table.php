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
        Schema::create('fathom_api_responses', function (Blueprint $table) {
            $table->id();
            $table->string('site_id'); // Fathom site ID (e.g. OELLVBPM)
            $table->string('aggregation_type'); // 'total', 'pathname', etc.
            $table->date('start_date');
            $table->date('end_date');
            $table->json('response_data'); // Raw JSON from Fathom API
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['site_id', 'aggregation_type', 'start_date', 'end_date'], 'fathom_api_site_agg_dates_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fathom_api_responses');
    }
};
