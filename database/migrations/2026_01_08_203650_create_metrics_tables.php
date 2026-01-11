<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global metrics (all sites combined)
        Schema::create('metrics_global', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('period_type'); // 'daily', '7d', '30d'
            $table->decimal('commission', 10, 2)->default(0);
            $table->integer('orders')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('pageviews')->default(0);
            $table->integer('visitors')->default(0); // uniques
            $table->integer('visits')->default(0);
            $table->decimal('rpv', 10, 4)->default(0); // Revenue per visitor
            $table->decimal('conversion_rate', 5, 2)->default(0); // orders / clicks * 100
            $table->timestamps();

            $table->unique(['date', 'period_type']);
            $table->index('date');
        });

        // Site metrics
        Schema::create('metrics_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('period_type'); // 'daily', '7d', '30d'
            $table->decimal('commission', 10, 2)->default(0);
            $table->integer('orders')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('pageviews')->default(0);
            $table->integer('visitors')->default(0);
            $table->integer('visits')->default(0);
            $table->decimal('rpv', 10, 4)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'date', 'period_type']);
            $table->index(['site_id', 'date']);
        });

        // Page metrics
        Schema::create('metrics_page', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('period_type'); // 'daily', '7d', '30d'
            $table->decimal('commission', 10, 2)->default(0);
            $table->integer('orders')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('pageviews')->default(0);
            $table->integer('visitors')->default(0);
            $table->integer('visits')->default(0);
            $table->decimal('rpv', 10, 4)->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['page_id', 'date', 'period_type']);
            $table->index(['site_id', 'date']);
            $table->index(['page_id', 'date']);
        });

        // Product metrics
        Schema::create('metrics_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('period_type'); // 'daily', '7d', '30d'
            $table->decimal('commission', 10, 2)->default(0);
            $table->integer('orders')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->decimal('avg_commission_per_order', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'date', 'period_type']);
            $table->index(['product_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics_product');
        Schema::dropIfExists('metrics_page');
        Schema::dropIfExists('metrics_site');
        Schema::dropIfExists('metrics_global');
    }
};
