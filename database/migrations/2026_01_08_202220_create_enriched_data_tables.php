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
        // Sites (master list of all affiliate sites)
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique(); // e.g. loopbandentest.nl
            $table->string('name'); // e.g. Loopbanden Test
            $table->string('fathom_site_id')->nullable(); // e.g. OELLVBPM
            $table->string('niche')->nullable(); // e.g. fitness, pets, cooking
            $table->timestamps();
        });

        // Pages (all pages across all sites)
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('url', 500); // Full URL or path
            $table->string('pathname', 500); // Clean pathname (e.g. /producten/foo)
            $table->string('content_type')->nullable(); // blog, review, product, category, home
            $table->string('title')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'pathname']);
            $table->unique(['site_id', 'pathname']);
        });

        // Products (master product list)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('ean')->nullable()->unique(); // Primary identifier
            $table->string('bol_product_id')->nullable(); // Fallback identifier
            $table->string('name', 500);
            $table->timestamps();

            $table->index('ean');
            $table->index('bol_product_id');
        });

        // Enriched Orders (from Bol API)
        Schema::create('enriched_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id'); // Bol order ID
            $table->string('order_item_id')->unique(); // Bol order item ID
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('site_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('page_id')->nullable()->constrained()->onDelete('set null');
            $table->date('order_date');
            $table->timestamp('order_datetime');
            $table->integer('quantity');
            $table->decimal('commission', 10, 2);
            $table->decimal('price_excl_vat', 10, 2);
            $table->decimal('price_incl_vat', 10, 2);
            $table->string('status'); // e.g. approved, pending, cancelled
            $table->boolean('status_final');
            $table->boolean('approved_for_payment');
            $table->string('site_code')->nullable(); // Raw from Bol (for mapping)
            $table->timestamps();

            $table->index('order_date');
            $table->index(['site_id', 'order_date']);
            $table->index(['product_id', 'order_date']);
            $table->index(['page_id', 'order_date']);
        });

        // Enriched Pageviews (from Fathom API)
        Schema::create('enriched_pageviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_id')->nullable()->constrained()->onDelete('set null');
            $table->date('date');
            $table->integer('pageviews');
            $table->integer('uniques');
            $table->integer('visits');
            $table->timestamps();

            $table->unique(['site_id', 'page_id', 'date']);
            $table->index(['site_id', 'date']);
            $table->index(['page_id', 'date']);
        });

        // Enriched Clicks (for future: affiliate click tracking)
        Schema::create('enriched_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('page_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('clicked_at');
            $table->string('referrer')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'clicked_at']);
            $table->index(['product_id', 'clicked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enriched_clicks');
        Schema::dropIfExists('enriched_pageviews');
        Schema::dropIfExists('enriched_orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('sites');
    }
};
