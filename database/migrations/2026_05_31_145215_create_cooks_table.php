<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smoker_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX cooks_single_active ON cooks ((1)) WHERE ended_at IS NULL');
        } elseif ($driver === 'mysql') {
            DB::statement('CREATE UNIQUE INDEX cooks_single_active ON cooks ((IF(ended_at IS NULL, 1, NULL)))');
        } elseif ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX cooks_single_active ON cooks ((1)) WHERE ended_at IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cooks');
    }
};
