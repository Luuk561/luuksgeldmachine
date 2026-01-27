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
        // Hourly pageviews per page
        Schema::create('enriched_pageviews_hourly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_id')->nullable()->constrained()->onDelete('set null');
            $table->date('date');
            $table->unsignedTinyInteger('hour'); // 0-23
            $table->unsignedInteger('pageviews')->default(0);
            $table->unsignedInteger('uniques')->default(0);
            $table->unsignedInteger('visits')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'page_id', 'date', 'hour']);
            $table->index(['site_id', 'date', 'hour']);
            $table->index(['page_id', 'date', 'hour']);
        });

        // Hourly click aggregates
        Schema::create('enriched_click_aggregates_hourly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->date('date');
            $table->unsignedTinyInteger('hour'); // 0-23
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'page_id', 'product_id', 'date', 'hour'], 'hourly_clicks_unique');
            $table->index(['site_id', 'date', 'hour']);
            $table->index(['page_id', 'date', 'hour']);
            $table->index(['product_id', 'date', 'hour']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enriched_click_aggregates_hourly');
        Schema::dropIfExists('enriched_pageviews_hourly');
    }
};
