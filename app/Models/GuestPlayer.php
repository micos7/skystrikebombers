<?php

namespace App\Models;

use Database\Factories\GuestPlayerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $token_hash
 * @property string $callsign
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $wins
 * @property int $losses
 * @property Carbon|null $last_seen_at
 */
class GuestPlayer extends Model
{
    /** @use HasFactory<GuestPlayerFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'token_hash',
        'callsign',
        'ip_address',
        'user_agent',
        'wins',
        'losses',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wins' => 'integer',
            'losses' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<GamePlayer, $this>
     */
    public function gamePlayers(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }
}
