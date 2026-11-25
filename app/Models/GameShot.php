<?php

namespace App\Models;

use App\Enums\MissileType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $game_id
 * @property int $attacker_slot
 * @property int $turn_number
 * @property MissileType $missile_type
 * @property int $target_x
 * @property int $target_y
 * @property string|null $orientation
 * @property list<array{x: int, y: int, outcome: string, plane_type: ?string}> $results
 * @property int|null $radar_count
 * @property list<array{id: string, plane_type: string, origin_x: int, origin_y: int, orientation: string, cells: list<array{x: int, y: int, part: string}>}>|null $destroyed_planes
 * @property Carbon|null $created_at
 */
class GameShot extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'attacker_slot',
        'turn_number',
        'missile_type',
        'target_x',
        'target_y',
        'orientation',
        'results',
        'radar_count',
        'destroyed_planes',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attacker_slot' => 'integer',
            'turn_number' => 'integer',
            'missile_type' => MissileType::class,
            'target_x' => 'integer',
            'target_y' => 'integer',
            'results' => 'array',
            'radar_count' => 'integer',
            'destroyed_planes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
