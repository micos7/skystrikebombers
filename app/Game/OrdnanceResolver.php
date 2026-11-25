<?php

namespace App\Game;

use App\Enums\CellOutcome;
use App\Enums\MissileType;
use App\Models\GamePlane;
use Illuminate\Support\Collection;

class OrdnanceResolver
{
    /**
     * Compute clamped target coordinates for a missile strike.
     *
     * @return list<array{x: int, y: int}>
     */
    public function targetCells(
        MissileType $missile,
        int $targetX,
        int $targetY,
        int $gridSize,
        ?string $orientation = 'H',
    ): array {
        $offsets = match ($missile) {
            MissileType::Standard => [[0, 0]],
            MissileType::Radar => [
                [-1, -1], [0, -1], [1, -1],
                [-1, 0],  [0, 0],  [1, 0],
                [-1, 1],  [0, 1],  [1, 1],
            ],
            MissileType::Cluster => [
                [0, 0], [-1, 0], [1, 0], [0, -1], [0, 1],
            ],
            MissileType::Carpet => $orientation === 'V'
                ? [[0, -1], [0, 0], [0, 1], [0, 2]]
                : [[-1, 0], [0, 0], [1, 0], [2, 0]],
            MissileType::Emp => [
                [0, 0], [1, 0], [0, 1], [1, 1],
            ],
        };

        $cells = [];

        foreach ($offsets as [$dx, $dy]) {
            $cx = $targetX + $dx;
            $cy = $targetY + $dy;

            if ($cx >= 0 && $cx < $gridSize && $cy >= 0 && $cy < $gridSize) {
                $cells[] = ['x' => $cx, 'y' => $cy];
            }
        }

        return $cells;
    }

    /**
     * Resolve an ordnance strike against the defender's planes.
     *
     * @param  Collection<int, GamePlane>  $defenderPlanes
     * @return array{
     *     results: list<array{x: int, y: int, outcome: string, plane_type: ?string}>,
     *     radar_count: ?int,
     *     destroyed_planes: list<array{id: string, plane_type: string, origin_x: int, origin_y: int, orientation: string, cells: list<array{x: int, y: int, part: string}>}>,
     *     cockpit_kill_scored: bool,
     *     all_destroyed: bool,
     *     emp_jam_applied: bool,
     * }
     */
    public function resolve(
        MissileType $missile,
        int $targetX,
        int $targetY,
        int $gridSize,
        int $turnNumber,
        Collection $defenderPlanes,
        ?string $orientation = 'H',
    ): array {
        $targeted = $this->targetCells($missile, $targetX, $targetY, $gridSize, $orientation);

        if ($missile === MissileType::Radar) {
            return $this->resolveRadar($targeted, $defenderPlanes);
        }

        $results = [];
        $newlyDestroyed = [];
        $newlyDestroyedIds = [];
        $cockpitKillScored = false;

        foreach ($targeted as $coord) {
            $cx = $coord['x'];
            $cy = $coord['y'];
            $key = "{$cx},{$cy}";

            $hitPlane = null;
            $hitCell = null;

            foreach ($defenderPlanes as $plane) {
                $found = $plane->cellAt($cx, $cy);

                if ($found !== null) {
                    $hitPlane = $plane;
                    $hitCell = $found;
                    break;
                }
            }

            if ($hitPlane === null || $hitCell === null) {
                $results[] = [
                    'x' => $cx,
                    'y' => $cy,
                    'outcome' => CellOutcome::Miss->value,
                    'plane_type' => null,
                ];

                continue;
            }

            $hitCells = $hitPlane->hit_cells ?? [];

            if (! in_array($key, $hitCells, true)) {
                $hitCells[] = $key;
                $hitPlane->hit_cells = $hitCells;
            }

            if ($hitPlane->is_destroyed) {
                $results[] = [
                    'x' => $cx,
                    'y' => $cy,
                    'outcome' => CellOutcome::Hit->value,
                    'plane_type' => $hitPlane->plane_type->value,
                ];

                continue;
            }

            $outcome = CellOutcome::Hit;

            if ($hitCell['part'] === 'cockpit') {
                if ($missile === MissileType::Cluster) {
                    $hitPlane->cockpit_hp = max(1, $hitPlane->cockpit_hp - 1);
                    $outcome = CellOutcome::CockpitCracked;
                } elseif ($missile === MissileType::Emp) {
                    $hitPlane->cockpit_hp = 0;
                    $hitPlane->is_destroyed = true;
                    $hitPlane->destroyed_at_turn = $turnNumber;
                    $outcome = CellOutcome::CockpitKilled;
                    $cockpitKillScored = true;
                } else {
                    $hitPlane->cockpit_hp = max(0, $hitPlane->cockpit_hp - 1);

                    if ($hitPlane->cockpit_hp <= 0) {
                        $hitPlane->is_destroyed = true;
                        $hitPlane->destroyed_at_turn = $turnNumber;
                        $outcome = CellOutcome::CockpitKilled;
                        $cockpitKillScored = true;
                    } else {
                        $outcome = CellOutcome::CockpitCracked;
                    }
                }
            } elseif (count($hitPlane->hit_cells) >= count($hitPlane->cells)) {
                $hitPlane->cockpit_hp = 0;
                $hitPlane->is_destroyed = true;
                $hitPlane->destroyed_at_turn = $turnNumber;
            }

            if ($hitPlane->is_destroyed && ! isset($newlyDestroyedIds[$hitPlane->id])) {
                $newlyDestroyedIds[$hitPlane->id] = true;
                $newlyDestroyed[] = [
                    'id' => $hitPlane->id,
                    'plane_type' => $hitPlane->plane_type->value,
                    'origin_x' => $hitPlane->origin_x,
                    'origin_y' => $hitPlane->origin_y,
                    'orientation' => $hitPlane->orientation->value,
                    'cells' => $hitPlane->cells,
                ];
            }

            $results[] = [
                'x' => $cx,
                'y' => $cy,
                'outcome' => $outcome->value,
                'plane_type' => $hitPlane->is_destroyed ? $hitPlane->plane_type->value : null,
            ];
        }

        $allDestroyed = $defenderPlanes->every(
            static fn (GamePlane $plane): bool => $plane->is_destroyed
        );

        return [
            'results' => $results,
            'radar_count' => null,
            'destroyed_planes' => $newlyDestroyed,
            'cockpit_kill_scored' => $cockpitKillScored,
            'all_destroyed' => $allDestroyed,
            'emp_jam_applied' => $missile === MissileType::Emp,
        ];
    }

    /**
     * @param  list<array{x: int, y: int}>  $targeted
     * @param  Collection<int, GamePlane>  $defenderPlanes
     * @return array{
     *     results: list<array{x: int, y: int, outcome: string, plane_type: ?string}>,
     *     radar_count: ?int,
     *     destroyed_planes: list<array{id: string, plane_type: string, origin_x: int, origin_y: int, orientation: string, cells: list<array{x: int, y: int, part: string}>}>,
     *     cockpit_kill_scored: bool,
     *     all_destroyed: bool,
     *     emp_jam_applied: bool,
     * }
     */
    private function resolveRadar(array $targeted, Collection $defenderPlanes): array
    {
        $detected = 0;
        $results = [];

        foreach ($targeted as $coord) {
            $cx = $coord['x'];
            $cy = $coord['y'];

            foreach ($defenderPlanes as $plane) {
                if ($plane->is_destroyed || $plane->plane_type->isRadarStealth()) {
                    continue;
                }

                if ($plane->cellAt($cx, $cy) !== null) {
                    $detected++;
                    break;
                }
            }

            $results[] = [
                'x' => $cx,
                'y' => $cy,
                'outcome' => CellOutcome::RadarPing->value,
                'plane_type' => null,
            ];
        }

        return [
            'results' => $results,
            'radar_count' => $detected,
            'destroyed_planes' => [],
            'cockpit_kill_scored' => false,
            'all_destroyed' => false,
            'emp_jam_applied' => false,
        ];
    }
}
