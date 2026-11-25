<?php

namespace App\Game;

use App\Enums\CellOutcome;
use App\Enums\MissileType;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameShot;

class AiCommander
{
    /**
     * Choose the AI's next shot using a Hunt-and-Target tactical heuristic.
     *
     * @return array{missile: MissileType, x: int, y: int, orientation: ?string}
     */
    public function chooseShot(Game $game, GamePlayer $aiPlayer): array
    {
        $gridSize = $game->grid_size;
        $aiShots = $game->shots->where('attacker_slot', $aiPlayer->slot);

        $firedCells = [];
        $destroyedCells = [];
        $crackedCockpits = [];
        $activeHits = [];

        /** @var GameShot $shot */
        foreach ($aiShots as $shot) {
            foreach (($shot->destroyed_planes ?? []) as $plane) {
                foreach ($plane['cells'] as $cell) {
                    $destroyedCells["{$cell['x']},{$cell['y']}"] = true;
                }
            }

            if ($shot->missile_type === MissileType::Radar) {
                continue;
            }

            foreach ($shot->results as $res) {
                $key = "{$res['x']},{$res['y']}";
                $firedCells[$key] = true;

                if ($res['outcome'] === CellOutcome::CockpitCracked->value) {
                    $crackedCockpits[$key] = ['x' => (int) $res['x'], 'y' => (int) $res['y']];
                } elseif ($res['outcome'] === CellOutcome::Hit->value) {
                    $activeHits[$key] = ['x' => (int) $res['x'], 'y' => (int) $res['y']];
                }
            }
        }

        // Priority 1: Finish any cracked armored cockpit immediately.
        foreach ($crackedCockpits as $key => $coord) {
            if (! isset($destroyedCells[$key])) {
                return [
                    'missile' => MissileType::Standard,
                    'x' => $coord['x'],
                    'y' => $coord['y'],
                    'orientation' => null,
                ];
            }
        }

        $isJammed = $aiPlayer->isEmpJammed($game->turn_number + 1);

        // Priority 2: Target mode around un-destroyed hits.
        $targetCandidates = [];

        foreach ($activeHits as $key => $coord) {
            if (isset($destroyedCells[$key])) {
                continue;
            }

            foreach ([[0, -1], [1, 0], [0, 1], [-1, 0]] as [$dx, $dy]) {
                $nx = $coord['x'] + $dx;
                $ny = $coord['y'] + $dy;
                $nKey = "{$nx},{$ny}";

                if ($nx >= 0 && $nx < $gridSize && $ny >= 0 && $ny < $gridSize && ! isset($firedCells[$nKey])) {
                    $targetCandidates[$nKey] = ['x' => $nx, 'y' => $ny];
                }
            }
        }

        if ($targetCandidates !== []) {
            $candidates = array_values($targetCandidates);
            $pick = $candidates[array_rand($candidates)];

            if (! $isJammed && ($aiPlayer->remainingAmmo(MissileType::Emp) ?? 0) > 0 && count($activeHits) >= 2) {
                return [
                    'missile' => MissileType::Emp,
                    'x' => min($pick['x'], $gridSize - 2),
                    'y' => min($pick['y'], $gridSize - 2),
                    'orientation' => null,
                ];
            }

            if (! $isJammed && ($aiPlayer->remainingAmmo(MissileType::Carpet) ?? 0) > 0 && count($targetCandidates) >= 3) {
                return [
                    'missile' => MissileType::Carpet,
                    'x' => $pick['x'],
                    'y' => $pick['y'],
                    'orientation' => random_int(0, 1) === 0 ? 'H' : 'V',
                ];
            }

            return [
                'missile' => MissileType::Standard,
                'x' => $pick['x'],
                'y' => $pick['y'],
                'orientation' => null,
            ];
        }

        // Priority 3: Early Radar Recon sweep if available.
        if (! $isJammed && ($aiPlayer->remainingAmmo(MissileType::Radar) ?? 0) > 0 && $aiShots->count() % 4 === 0) {
            return [
                'missile' => MissileType::Radar,
                'x' => random_int(1, $gridSize - 2),
                'y' => random_int(1, $gridSize - 2),
                'orientation' => null,
            ];
        }

        // Priority 4: Cluster Bomb sweep in open airspace.
        if (! $isJammed && ($aiPlayer->remainingAmmo(MissileType::Cluster) ?? 0) > 0 && $aiShots->count() >= 2) {
            $x = random_int(1, $gridSize - 2);
            $y = random_int(1, $gridSize - 2);

            if (! isset($firedCells["{$x},{$y}"])) {
                return [
                    'missile' => MissileType::Cluster,
                    'x' => $x,
                    'y' => $y,
                    'orientation' => null,
                ];
            }
        }

        // Priority 5: Checkerboard parity hunt.
        $parity = [];
        $fallback = [];

        for ($y = 0; $y < $gridSize; $y++) {
            for ($x = 0; $x < $gridSize; $x++) {
                if (isset($firedCells["{$x},{$y}"])) {
                    continue;
                }

                $fallback[] = ['x' => $x, 'y' => $y];

                if (($x + $y) % 2 === 0) {
                    $parity[] = ['x' => $x, 'y' => $y];
                }
            }
        }

        $pool = $parity !== [] ? $parity : $fallback;
        $pick = $pool !== [] ? $pool[array_rand($pool)] : ['x' => 0, 'y' => 0];

        return [
            'missile' => MissileType::Standard,
            'x' => $pick['x'],
            'y' => $pick['y'],
            'orientation' => null,
        ];
    }
}
