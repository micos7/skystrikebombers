<?php

namespace App\Models;

use App\Enums\GameMode;
use App\Enums\GameStatus;
use Carbon\CarbonInterface;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property GameMode $mode
 * @property GameStatus $status
 * @property int $grid_size
 * @property bool $is_public
 * @property bool $is_vs_ai
 * @property array<string, mixed>|null $settings
 * @property int|null $current_turn_slot
 * @property int|null $winner_slot
 * @property int $turn_number
 * @property CarbonInterface|null $turn_started_at
 * @property string|null $rematch_game_id
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $finished_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'mode',
        'status',
        'grid_size',
        'is_public',
        'is_vs_ai',
        'settings',
        'current_turn_slot',
        'winner_slot',
        'turn_number',
        'turn_started_at',
        'rematch_game_id',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => GameMode::class,
            'status' => GameStatus::class,
            'grid_size' => 'integer',
            'is_public' => 'boolean',
            'is_vs_ai' => 'boolean',
            'settings' => 'array',
            'current_turn_slot' => 'integer',
            'winner_slot' => 'integer',
            'turn_number' => 'integer',
            'turn_started_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * @return HasMany<GamePlayer, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class)->orderBy('slot');
    }

    /**
     * @return HasMany<GamePlane, $this>
     */
    public function planes(): HasMany
    {
        return $this->hasMany(GamePlane::class);
    }

    /**
     * @return HasMany<GameShot, $this>
     */
    public function shots(): HasMany
    {
        return $this->hasMany(GameShot::class)->orderBy('turn_number');
    }

    /**
     * @return HasMany<GameMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(GameMessage::class)->orderBy('created_at');
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function rematchGame(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rematch_game_id');
    }

    public function turnTimerSeconds(): ?int
    {
        $value = $this->settings['turn_timer_seconds'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function extraTurnOnKill(): bool
    {
        return (bool) ($this->settings['extra_turn_on_kill'] ?? false);
    }
}
