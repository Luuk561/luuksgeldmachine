<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enriched hourly data from Fathom
        Schema::create('enriched_site_totals_hourly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->unsignedTinyInteger('hour'); // 0-23
            $table->unsignedInteger('uniques')->default(0);
            $table->unsignedInteger('visits')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'date', 'hour']);
            $table->index(['date', 'hour']);
        });

        // Pre-computed hourly metrics (global and per-site)
        Schema::create('metrics_hourly', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('hour'); // 0-23
            $table->foreignId('site_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('status_filter', 20)->default('approved_pending');
            $table->decimal('commission', 10, 2)->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('pageviews')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('visits')->default(0);
            $table->decimal('rpv', 10, 4)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['date', 'hour', 'site_id', 'status_filter'], 'metrics_hourly_unique');
            $table->index(['date', 'hour']);
            $table->index(['site_id', 'date', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics_hourly');
        Schema::dropIfExists('enriched_site_totals_hourly');
    }
};
