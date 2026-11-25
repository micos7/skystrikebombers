<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_planes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('game_player_id')->constrained('game_players')->cascadeOnDelete();
            $table->string('plane_type', 24);
            $table->unsignedSmallInteger('origin_x');
            $table->unsignedSmallInteger('origin_y');
            $table->string('orientation', 4);
            $table->jsonb('cells');
            $table->jsonb('hit_cells');
            $table->unsignedSmallInteger('cockpit_hp')->default(1);
            $table->unsignedSmallInteger('max_cockpit_hp')->default(1);
            $table->boolean('is_destroyed')->default(false);
            $table->unsignedInteger('destroyed_at_turn')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'game_player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_planes');
    }
};
