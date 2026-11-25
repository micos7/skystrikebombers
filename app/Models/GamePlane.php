<?php

namespace App\Models;

use App\Enums\Orientation;
use App\Enums\PlaneType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $game_id
 * @property string $game_player_id
 * @property PlaneType $plane_type
 * @property int $origin_x
 * @property int $origin_y
 * @property Orientation $orientation
 * @property list<array{x: int, y: int, part: string}> $cells
 * @property list<string> $hit_cells
 * @property int $cockpit_hp
 * @property int $max_cockpit_hp
 * @property bool $is_destroyed
 * @property int|null $destroyed_at_turn
 */
class GamePlane extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'game_player_id',
        'plane_type',
        'origin_x',
        'origin_y',
        'orientation',
        'cells',
        'hit_cells',
        'cockpit_hp',
        'max_cockpit_hp',
        'is_destroyed',
        'destroyed_at_turn',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plane_type' => PlaneType::class,
            'orientation' => Orientation::class,
            'origin_x' => 'integer',
            'origin_y' => 'integer',
            'cells' => 'array',
            'hit_cells' => 'array',
            'cockpit_hp' => 'integer',
            'max_cockpit_hp' => 'integer',
            'is_destroyed' => 'boolean',
            'destroyed_at_turn' => 'integer',
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
     * @return BelongsTo<GamePlayer, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'game_player_id');
    }

    /**
     * @return array{x: int, y: int, part: string}|null
     */
    public function cellAt(int $x, int $y): ?array
    {
        foreach ($this->cells as $cell) {
            if ($cell['x'] === $x && $cell['y'] === $y) {
                return $cell;
            }
        }

        return null;
    }
}
