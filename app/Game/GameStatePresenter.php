<?php

namespace App\Game;

use App\Data\PlayerIdentity;
use App\Enums\GameStatus;
use App\Enums\MissileType;
use App\Enums\PlaneType;
use App\Models\Game;
use App\Models\GameMessage;
use App\Models\GamePlane;
use App\Models\GamePlayer;
use App\Models\GameShot;

class GameStatePresenter
{
    /**
     * Build a strict Fog-of-War game state payload for a specific viewer.
     *
     * @return array<string, mixed>
     */
    public function present(Game $game, PlayerIdentity $viewer): array
    {
        $game->loadMissing([
            'players.planes',
            'shots',
            'messages',
            'rematchGame',
        ]);

        $myPlayer = $game->players->first(
            static fn (GamePlayer $player): bool => $viewer->matchesGamePlayer($player)
        );

        $opponent = $myPlayer !== null
            ? $game->players->first(static fn (GamePlayer $player): bool => $player->slot !== $myPlayer->slot)
            : $game->players->firstWhere('slot', 2);

        $isFinished = $game->status === GameStatus::Finished;

        $mySlot = $myPlayer?->slot;
        $opponentSlot = $opponent?->slot;

        $myPlanes = $myPlayer !== null
            ? $myPlayer->planes->map(fn (GamePlane $plane): array => $this->serializePlane($plane))->values()->all()
            : [];

        $enemyDestroyedPlanes = $opponent !== null
            ? $opponent->planes
                ->filter(static fn (GamePlane $plane): bool => $isFinished || $plane->is_destroyed)
                ->map(fn (GamePlane $plane): array => $this->serializePlane($plane))
                ->values()
                ->all()
            : [];

        $enemyFleetSummary = $opponent !== null
            ? $opponent->planes->map(static fn (GamePlane $plane): array => [
                'plane_type' => $plane->plane_type->value,
                'label' => $plane->plane_type->label(),
                'is_destroyed' => $plane->is_destroyed,
                'cockpit_cracked' => ! $plane->is_destroyed && $plane->cockpit_hp < $plane->max_cockpit_hp,
            ])->values()->all()
            : [];

        $myShots = $mySlot !== null
            ? $game->shots
                ->where('attacker_slot', $mySlot)
                ->map(fn (GameShot $shot): array => $this->serializeShot($shot))
                ->values()
                ->all()
            : [];

        $enemyShots = $opponentSlot !== null
            ? $game->shots
                ->where('attacker_slot', $opponentSlot)
                ->map(fn (GameShot $shot): array => $this->serializeShot($shot))
                ->values()
                ->all()
            : [];

        $combatLog = $game->shots
            ->sortByDesc('turn_number')
            ->take(25)
            ->map(function (GameShot $shot) use ($game): array {
                $attacker = $game->players->firstWhere('slot', $shot->attacker_slot);

                return [
                    'id' => $shot->id,
                    'turn_number' => $shot->turn_number,
                    'attacker_slot' => $shot->attacker_slot,
                    'attacker_callsign' => $attacker !== null ? $attacker->callsign : 'Pilot',
                    'missile_type' => $shot->missile_type->value,
                    'missile_label' => $shot->missile_type->label(),
                    'target_x' => $shot->target_x,
                    'target_y' => $shot->target_y,
                    'orientation' => $shot->orientation,
                    'results' => $shot->results,
                    'radar_count' => $shot->radar_count,
                    'destroyed_count' => count($shot->destroyed_planes ?? []),
                    'created_at' => $shot->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $turnSecondsRemaining = null;
        $turnTimerSeconds = $game->turnTimerSeconds();

        if ($game->status === GameStatus::Playing && $turnTimerSeconds !== null && $game->turn_started_at !== null) {
            $elapsed = (int) $game->turn_started_at->diffInSeconds(now());
            $turnSecondsRemaining = max(0, $turnTimerSeconds - $elapsed);
        }

        return [
            'id' => $game->id,
            'code' => $game->code,
            'mode' => $game->mode->value,
            'mode_label' => $game->mode->label(),
            'status' => $game->status->value,
            'grid_size' => $game->grid_size,
            'is_public' => $game->is_public,
            'is_vs_ai' => $game->is_vs_ai,
            'settings' => [
                'turn_timer_seconds' => $turnTimerSeconds,
                'extra_turn_on_kill' => $game->extraTurnOnKill(),
            ],
            'fleet_roster' => array_map(
                static fn (PlaneType $type): array => [
                    'plane_type' => $type->value,
                    'label' => $type->label(),
                    'cockpit_hp' => $type->cockpitHp(),
                    'is_stealth' => $type->isRadarStealth(),
                    'perk' => $type->perkDescription(),
                ],
                $game->mode->fleetRoster()
            ),
            'missiles_meta' => array_map(
                static fn (MissileType $missile): array => [
                    'type' => $missile->value,
                    'label' => $missile->label(),
                    'description' => $missile->description(),
                    'is_special' => $missile->isSpecial(),
                ],
                MissileType::cases()
            ),
            'current_turn_slot' => $game->current_turn_slot,
            'winner_slot' => $game->winner_slot,
            'turn_number' => $game->turn_number,
            'turn_seconds_remaining' => $turnSecondsRemaining,
            'invite_url' => route('games.join.link', ['code' => $game->code]),
            'rematch_game_code' => $game->rematchGame?->code,
            'viewer' => [
                'id' => $viewer->id(),
                'callsign' => $viewer->callsign(),
                'is_guest' => $viewer->isGuest(),
                'slot' => $mySlot,
                'is_participant' => $myPlayer !== null,
                'is_my_turn' => $mySlot !== null
                    && $game->status === GameStatus::Playing
                    && $game->current_turn_slot === $mySlot,
                'is_ready' => $myPlayer !== null ? $myPlayer->is_ready : false,
                'ammo' => ($myPlayer !== null ? $myPlayer->ammo : null) ?? $game->mode->startingAmmo(),
                'emp_jammed' => $myPlayer !== null && $myPlayer->isEmpJammed($game->turn_number + 1),
                'rematch_requested' => $myPlayer !== null ? $myPlayer->rematch_requested : false,
            ],
            'players' => $game->players->map(static fn (GamePlayer $player): array => [
                'slot' => $player->slot,
                'callsign' => $player->callsign,
                'is_ai' => $player->is_ai,
                'is_ready' => $player->is_ready,
                'rematch_requested' => $player->rematch_requested,
                'planes_alive' => $player->planes->where('is_destroyed', false)->count(),
                'planes_total' => $player->planes->count(),
            ])->values()->all(),
            'my_planes' => $myPlanes,
            'enemy_revealed_planes' => $enemyDestroyedPlanes,
            'enemy_fleet_summary' => $enemyFleetSummary,
            'my_shots' => $myShots,
            'enemy_shots' => $enemyShots,
            'combat_log' => $combatLog,
            'messages' => $game->messages
                ->take(-40)
                ->map(static fn (GameMessage $message): array => [
                    'id' => $message->id,
                    'sender_slot' => $message->sender_slot,
                    'sender_callsign' => $message->sender_callsign,
                    'body' => $message->body,
                    'is_emote' => $message->is_emote,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlane(GamePlane $plane): array
    {
        return [
            'id' => $plane->id,
            'plane_type' => $plane->plane_type->value,
            'label' => $plane->plane_type->label(),
            'origin_x' => $plane->origin_x,
            'origin_y' => $plane->origin_y,
            'orientation' => $plane->orientation->value,
            'cells' => $plane->cells,
            'hit_cells' => $plane->hit_cells ?? [],
            'cockpit_hp' => $plane->cockpit_hp,
            'max_cockpit_hp' => $plane->max_cockpit_hp,
            'is_destroyed' => $plane->is_destroyed,
            'destroyed_at_turn' => $plane->destroyed_at_turn,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShot(GameShot $shot): array
    {
        return [
            'id' => $shot->id,
            'attacker_slot' => $shot->attacker_slot,
            'turn_number' => $shot->turn_number,
            'missile_type' => $shot->missile_type->value,
            'target_x' => $shot->target_x,
            'target_y' => $shot->target_y,
            'orientation' => $shot->orientation,
            'results' => $shot->results,
            'radar_count' => $shot->radar_count,
            'destroyed_planes' => $shot->destroyed_planes ?? [],
            'created_at' => $shot->created_at?->toIso8601String(),
        ];
    }
}
