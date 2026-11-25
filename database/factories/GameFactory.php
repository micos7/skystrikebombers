<?php

namespace Database\Factories;

use App\Enums\GameMode;
use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mode = GameMode::Tactical;

        return [
            'code' => 'SKY-'.strtoupper(Str::random(5)),
            'mode' => $mode,
            'status' => GameStatus::Waiting,
            'grid_size' => $mode->gridSize(),
            'is_public' => true,
            'is_vs_ai' => false,
            'settings' => [
                'turn_timer_seconds' => $mode->defaultTurnTimerSeconds(),
                'extra_turn_on_kill' => $mode->defaultExtraTurnOnKill(),
            ],
            'turn_number' => 0,
        ];
    }
}
