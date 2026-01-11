<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fathom_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->onDelete('cascade');
            $table->string('fathom_event_id'); // UUID from Fathom
            $table->string('event_name'); // Full event name
            $table->boolean('is_affiliate_click')->default(false); // Filter for affiliate clicks
            $table->timestamps();

            $table->unique(['site_id', 'fathom_event_id']);
            $table->index(['site_id', 'is_affiliate_click']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fathom_events');
    }
};
