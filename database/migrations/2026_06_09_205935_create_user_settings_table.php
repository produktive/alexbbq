<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subscribable', 'push_subscriptions_subscribable_morph_idx');
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('user_settings');
    }
};
