<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedSmallInteger('sender_slot');
            $table->string('sender_callsign', 32);
            $table->string('body', 280);
            $table->boolean('is_emote')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['game_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_messages');
    }
};
