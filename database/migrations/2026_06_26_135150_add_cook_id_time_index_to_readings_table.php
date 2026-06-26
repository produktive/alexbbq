<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->index(['cook_id', 'time']);
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropIndex(['cook_id', 'time']);
        });
    }
};
