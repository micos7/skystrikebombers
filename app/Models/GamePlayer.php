<?php

namespace App\Models;

use App\Enums\MissileType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $game_id
 * @property int $slot
 * @property int|null $user_id
 * @property string|null $guest_player_id
 * @property bool $is_ai
 * @property string $callsign
 * @property bool $is_ready
 * @property array<string, int|null>|null $ammo
 * @property int|null $emp_jammed_until_turn
 * @property bool $rematch_requested
 * @property CarbonInterface|null $last_active_at
 */
class GamePlayer extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'slot',
        'user_id',
        'guest_player_id',
        'is_ai',
        'callsign',
        'is_ready',
        'ammo',
        'emp_jammed_until_turn',
        'rematch_requested',
        'last_active_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'is_ai' => 'boolean',
            'is_ready' => 'boolean',
            'ammo' => 'array',
            'emp_jammed_until_turn' => 'integer',
            'rematch_requested' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<GuestPlayer, $this>
     */
    public function guestPlayer(): BelongsTo
    {
        return $this->belongsTo(GuestPlayer::class);
    }

    /**
     * @return HasMany<GamePlane, $this>
     */
    public function planes(): HasMany
    {
        return $this->hasMany(GamePlane::class);
    }

    public function remainingAmmo(MissileType $missile): ?int
    {
        if ($missile === MissileType::Standard) {
            return null;
        }

        $ammo = $this->ammo ?? [];

        return (int) ($ammo[$missile->value] ?? 0);
    }

    public function isEmpJammed(int $currentTurn): bool
    {
        return $this->emp_jammed_until_turn !== null && $currentTurn <= $this->emp_jammed_until_turn;
    }
}
