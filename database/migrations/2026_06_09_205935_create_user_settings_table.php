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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('food_min')->default(32);
            $table->unsignedSmallInteger('food_max')->default(203);
            $table->unsignedSmallInteger('bbq_min')->default(225);
            $table->unsignedSmallInteger('bbq_max')->default(275);
            $table->unsignedTinyInteger('alert_interval_minutes')->default(5);
            $table->timestamp('last_food_alert_at')->nullable();
            $table->timestamp('last_bbq_alert_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
