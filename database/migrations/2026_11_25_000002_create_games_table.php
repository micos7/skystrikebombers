<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 16)->unique();
            $table->string('mode', 24);
            $table->string('status', 24)->index();
            $table->unsignedSmallInteger('grid_size')->default(12);
            $table->boolean('is_public')->default(false)->index();
            $table->boolean('is_vs_ai')->default(false);
            $table->jsonb('settings')->nullable();
            $table->unsignedSmallInteger('current_turn_slot')->nullable();
            $table->unsignedSmallInteger('winner_slot')->nullable();
            $table->unsignedInteger('turn_number')->default(0);
            $table->timestamp('turn_started_at')->nullable();
            $table->uuid('rematch_game_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
