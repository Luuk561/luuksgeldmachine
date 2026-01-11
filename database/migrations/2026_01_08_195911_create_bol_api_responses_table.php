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
        Schema::create('bol_api_responses', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint'); // 'order-report', 'commission-report', 'promotion-report'
            $table->date('start_date');
            $table->date('end_date');
            $table->json('response_data'); // Raw JSON from Bol API
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['endpoint', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bol_api_responses');
    }
};
