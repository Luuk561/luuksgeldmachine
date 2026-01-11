<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add status_filter to all metrics tables
        Schema::table('metrics_global', function (Blueprint $table) {
            $table->string('status_filter')->default('approved_pending')->after('period_type');
            $table->dropUnique(['date', 'period_type']);
            $table->unique(['date', 'period_type', 'status_filter']);
        });

        Schema::table('metrics_site', function (Blueprint $table) {
            $table->string('status_filter')->default('approved_pending')->after('period_type');
            $table->dropUnique(['site_id', 'date', 'period_type']);
            $table->unique(['site_id', 'date', 'period_type', 'status_filter']);
        });

        Schema::table('metrics_page', function (Blueprint $table) {
            $table->string('status_filter')->default('approved_pending')->after('period_type');
            $table->dropUnique(['page_id', 'date', 'period_type']);
            $table->unique(['page_id', 'date', 'period_type', 'status_filter']);
        });

        Schema::table('metrics_product', function (Blueprint $table) {
            $table->string('status_filter')->default('approved_pending')->after('period_type');
            $table->dropUnique(['product_id', 'date', 'period_type']);
            $table->unique(['product_id', 'date', 'period_type', 'status_filter']);
        });
    }

    public function down(): void
    {
        Schema::table('metrics_global', function (Blueprint $table) {
            $table->dropUnique(['date', 'period_type', 'status_filter']);
            $table->unique(['date', 'period_type']);
            $table->dropColumn('status_filter');
        });

        Schema::table('metrics_site', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'date', 'period_type', 'status_filter']);
            $table->unique(['site_id', 'date', 'period_type']);
            $table->dropColumn('status_filter');
        });

        Schema::table('metrics_page', function (Blueprint $table) {
            $table->dropUnique(['page_id', 'date', 'period_type', 'status_filter']);
            $table->unique(['page_id', 'date', 'period_type']);
            $table->dropColumn('status_filter');
        });

        Schema::table('metrics_product', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'date', 'period_type', 'status_filter']);
            $table->unique(['product_id', 'date', 'period_type']);
            $table->dropColumn('status_filter');
        });
    }
};
