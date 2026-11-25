<?php

namespace App\Game;

use App\Enums\GameMode;
use App\Enums\Orientation;
use App\Enums\PlaneType;
use InvalidArgumentException;

class PlaneGeometry
{
    /**
     * Relative offsets (dx, dy, part) when facing North with cockpit at (0, 0).
     *
     * @return list<array{dx: int, dy: int, part: string}>
     */
    public function baseTemplate(PlaneType $type): array
    {
        return match ($type) {
            PlaneType::Interceptor => [
                ['dx' => 0, 'dy' => 0, 'part' => 'cockpit'],
                ['dx' => -1, 'dy' => 1, 'part' => 'wing'],
                ['dx' => 0, 'dy' => 1, 'part' => 'body'],
                ['dx' => 1, 'dy' => 1, 'part' => 'wing'],
                ['dx' => 0, 'dy' => 2, 'part' => 'body'],
                ['dx' => 0, 'dy' => 3, 'part' => 'tail'],
            ],
            PlaneType::Fighter => [
                ['dx' => 0, 'dy' => 0, 'part' => 'cockpit'],
                ['dx' => -2, 'dy' => 1, 'part' => 'wing'],
                ['dx' => -1, 'dy' => 1, 'part' => 'wing'],
                ['dx' => 0, 'dy' => 1, 'part' => 'body'],
                ['dx' => 1, 'dy' => 1, 'part' => 'wing'],
                ['dx' => 2, 'dy' => 1, 'part' => 'wing'],
                ['dx' => 0, 'dy' => 2, 'part' => 'body'],
                ['dx' => -1, 'dy' => 3, 'part' => 'tail'],
                ['dx' => 0, 'dy' => 3, 'part' => 'tail'],
                ['dx' => 1, 'dy' => 3, 'part' => 'tail'],
            ],
            PlaneType::Stealth => [
                ['dx' => 0, 'dy' => 0, 'part' => 'cockpit'],
                ['dx' => -1, 'dy' => 1, 'part' => 'wing'],
                ['dx' => 0, 'dy' => 1, 'part' => 'body'],
                ['dx' => 1, 'dy' => 1, 'part' => 'wing'],
                ['dx' => -2, 'dy' => 2, 'part' => 'wing'],
                ['dx' => -1, 'dy' => 2, 'part' => 'tail'],
                ['dx' => 1, 'dy' => 2, 'part' => 'tail'],
                ['dx' => 2, 'dy' => 2, 'part' => 'wing'],
            ],
            PlaneType::Heavy => [
                ['dx' => 0, 'dy' => 0, 'part' => 'cockpit'],
                ['dx' => -2, 'dy' => 1, 'part' => 'wing'],
                ['dx' => -1, 'dy' => 1, 'part' => 'wing'],
                ['dx' => 0, 'dy' => 1, 'part' => 'body'],
                ['dx' => 1, 'dy' => 1, 'part' => 'wing'],
                ['dx' => 2, 'dy' => 1, 'part' => 'wing'],
                ['dx' => -1, 'dy' => 2, 'part' => 'wing'],
                ['dx' => 0, 'dy' => 2, 'part' => 'body'],
                ['dx' => 1, 'dy' => 2, 'part' => 'wing'],
                ['dx' => 0, 'dy' => 3, 'part' => 'body'],
                ['dx' => -1, 'dy' => 4, 'part' => 'tail'],
                ['dx' => 0, 'dy' => 4, 'part' => 'tail'],
                ['dx' => 1, 'dy' => 4, 'part' => 'tail'],
            ],
        };
    }

    /**
     * Compute absolute board coordinates for a plane placement.
     *
     * @return list<array{x: int, y: int, part: string}>
     */
    public function cellsFor(PlaneType $type, int $originX, int $originY, Orientation $orientation): array
    {
        $cells = [];

        foreach ($this->baseTemplate($type) as $offset) {
            [$rx, $ry] = $this->rotate($offset['dx'], $offset['dy'], $orientation);

            $cells[] = [
                'x' => $originX + $rx,
                'y' => $originY + $ry,
                'part' => $offset['part'],
            ];
        }

        return $cells;
    }

    /**
     * Validate a full fleet placement against mode roster, grid bounds, and overlap rules.
     *
     * @param  list<array{plane_type: string, origin_x: int, origin_y: int, orientation: string}>  $placements
     * @return list<array{plane_type: PlaneType, origin_x: int, origin_y: int, orientation: Orientation, cells: list<array{x: int, y: int, part: string}>}>
     */
    public function validateFleet(GameMode $mode, int $gridSize, array $placements): array
    {
        $expected = array_map(
            static fn (PlaneType $type): string => $type->value,
            $mode->fleetRoster()
        );
        sort($expected);

        $provided = array_map(
            static fn (array $item): string => (string) $item['plane_type'],
            $placements
        );
        sort($provided);

        if ($expected !== $provided) {
            throw new InvalidArgumentException('Fleet composition does not match the game mode roster.');
        }

        $occupied = [];
        $resolved = [];

        foreach ($placements as $placement) {
            $type = PlaneType::from($placement['plane_type']);
            $orientation = Orientation::from($placement['orientation']);
            $originX = (int) $placement['origin_x'];
            $originY = (int) $placement['origin_y'];

            $cells = $this->cellsFor($type, $originX, $originY, $orientation);

            foreach ($cells as $cell) {
                if ($cell['x'] < 0 || $cell['x'] >= $gridSize || $cell['y'] < 0 || $cell['y'] >= $gridSize) {
                    throw new InvalidArgumentException("Aircraft {$type->label()} extends outside the {$gridSize}x{$gridSize} airspace.");
                }

                $key = "{$cell['x']},{$cell['y']}";

                if (isset($occupied[$key])) {
                    throw new InvalidArgumentException('Aircraft placements cannot overlap.');
                }

                $occupied[$key] = true;
            }

            $resolved[] = [
                'plane_type' => $type,
                'origin_x' => $originX,
                'origin_y' => $originY,
                'orientation' => $orientation,
                'cells' => $cells,
            ];
        }

        return $resolved;
    }

    /**
     * Generate a valid randomized fleet for Auto-Deploy or AI Commander.
     *
     * @return list<array{plane_type: string, origin_x: int, origin_y: int, orientation: string}>
     */
    public function generateRandomFleet(GameMode $mode, int $gridSize): array
    {
        $roster = $mode->fleetRoster();
        $orientations = Orientation::cases();

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $occupied = [];
            $placements = [];
            $success = true;

            foreach ($roster as $planeType) {
                $placed = false;

                for ($try = 0; $try < 120; $try++) {
                    $orientation = $orientations[random_int(0, count($orientations) - 1)];
                    $originX = random_int(0, $gridSize - 1);
                    $originY = random_int(0, $gridSize - 1);
                    $cells = $this->cellsFor($planeType, $originX, $originY, $orientation);

                    if (! $this->canPlace($cells, $gridSize, $occupied)) {
                        continue;
                    }

                    foreach ($cells as $cell) {
                        $occupied["{$cell['x']},{$cell['y']}"] = true;
                    }

                    $placements[] = [
                        'plane_type' => $planeType->value,
                        'origin_x' => $originX,
                        'origin_y' => $originY,
                        'orientation' => $orientation->value,
                    ];
                    $placed = true;
                    break;
                }

                if (! $placed) {
                    $success = false;
                    break;
                }
            }

            if ($success) {
                return $placements;
            }
        }

        throw new InvalidArgumentException('Unable to generate a valid random fleet layout.');
    }

    /**
     * @param  list<array{x: int, y: int, part: string}>  $cells
     * @param  array<string, bool>  $occupied
     */
    private function canPlace(array $cells, int $gridSize, array $occupied): bool
    {
        foreach ($cells as $cell) {
            if ($cell['x'] < 0 || $cell['x'] >= $gridSize || $cell['y'] < 0 || $cell['y'] >= $gridSize) {
                return false;
            }

            if (isset($occupied["{$cell['x']},{$cell['y']}"])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function rotate(int $dx, int $dy, Orientation $orientation): array
    {
        return match ($orientation) {
            Orientation::North => [$dx, $dy],
            Orientation::East => [-$dy, $dx],
            Orientation::South => [-$dx, -$dy],
            Orientation::West => [$dy, -$dx],
        };
    }
}
