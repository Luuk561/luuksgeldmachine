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
        Schema::create('sync_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g. 'last_incremental_sync', 'last_full_sync'
            $table->text('value'); // timestamp or other metadata
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_metadata');
    }
};
