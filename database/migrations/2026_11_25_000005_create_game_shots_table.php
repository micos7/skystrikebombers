<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_shots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedSmallInteger('attacker_slot');
            $table->unsignedInteger('turn_number');
            $table->string('missile_type', 24);
            $table->unsignedSmallInteger('target_x');
            $table->unsignedSmallInteger('target_y');
            $table->string('orientation', 4)->nullable();
            $table->jsonb('results');
            $table->unsignedSmallInteger('radar_count')->nullable();
            $table->jsonb('destroyed_planes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['game_id', 'turn_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_shots');
    }
};
