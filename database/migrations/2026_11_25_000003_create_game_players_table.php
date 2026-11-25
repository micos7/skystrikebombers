<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_players', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedSmallInteger('slot');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('guest_player_id')->nullable()->constrained('guest_players')->nullOnDelete();
            $table->boolean('is_ai')->default(false);
            $table->string('callsign', 32);
            $table->boolean('is_ready')->default(false);
            $table->jsonb('ammo')->nullable();
            $table->unsignedInteger('emp_jammed_until_turn')->nullable();
            $table->boolean('rematch_requested')->default(false);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'slot']);
            $table->index(['guest_player_id', 'game_id']);
            $table->index(['user_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_players');
    }
};
