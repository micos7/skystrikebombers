<?php

namespace App\Game;

use App\Data\PlayerIdentity;
use App\Enums\GameMode;
use App\Enums\GameStatus;
use App\Enums\MissileType;
use App\Events\Game\FleetReadyUpdated;
use App\Events\Game\GameFinished;
use App\Events\Game\PlayerJoined;
use App\Events\Game\RematchCreated;
use App\Events\Game\RematchRequested;
use App\Events\Game\ShotFired;
use App\Models\Game;
use App\Models\GamePlane;
use App\Models\GamePlayer;
use App\Models\GameShot;
use App\Support\SafeBroadcaster;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GameEngine
{
    public function __construct(
        private readonly PlaneGeometry $geometry,
        private readonly OrdnanceResolver $ordnance,
        private readonly AiCommander $aiCommander,
        private readonly SafeBroadcaster $broadcaster,
    ) {}

    /**
     * @param  array{is_public?: bool, is_vs_ai?: bool, turn_timer_seconds?: ?int, extra_turn_on_kill?: bool}  $options
     */
    public function createGame(PlayerIdentity $host, GameMode $mode, array $options = []): Game
    {
        return DB::transaction(function () use ($host, $mode, $options): Game {
            $isVsAi = (bool) ($options['is_vs_ai'] ?? false);
            $isPublic = ! $isVsAi && (bool) ($options['is_public'] ?? false);

            $game = Game::query()->create([
                'code' => $this->generateUniqueCode(),
                'mode' => $mode,
                'status' => $isVsAi ? GameStatus::Placing : GameStatus::Waiting,
                'grid_size' => $mode->gridSize(),
                'is_public' => $isPublic,
                'is_vs_ai' => $isVsAi,
                'settings' => [
                    'turn_timer_seconds' => array_key_exists('turn_timer_seconds', $options)
                        ? $options['turn_timer_seconds']
                        : $mode->defaultTurnTimerSeconds(),
                    'extra_turn_on_kill' => (bool) ($options['extra_turn_on_kill'] ?? $mode->defaultExtraTurnOnKill()),
                ],
                'turn_number' => 0,
            ]);

            $game->players()->create([
                'slot' => 1,
                'user_id' => $host->user?->getKey(),
                'guest_player_id' => $host->guest?->getKey(),
                'is_ai' => false,
                'callsign' => $host->callsign(),
                'is_ready' => false,
                'ammo' => $mode->startingAmmo(),
                'last_active_at' => now(),
            ]);

            if ($isVsAi) {
                $aiPlayer = $game->players()->create([
                    'slot' => 2,
                    'is_ai' => true,
                    'callsign' => 'AI Commander',
                    'is_ready' => false,
                    'ammo' => $mode->startingAmmo(),
                    'last_active_at' => now(),
                ]);

                $this->deployFleetForPlayer(
                    $game,
                    $aiPlayer,
                    $this->geometry->generateRandomFleet($mode, $game->grid_size)
                );
            }

            return $game->fresh(['players.planes']);
        });
    }

    public function joinGame(Game $game, PlayerIdentity $guest): GamePlayer
    {
        $joinedPlayer = DB::transaction(function () use ($game, $guest): GamePlayer {
            /** @var Game $locked */
            $locked = Game::query()->with('players')->lockForUpdate()->findOrFail($game->getKey());

            $existing = $locked->players->first(
                static fn (GamePlayer $player): bool => $guest->matchesGamePlayer($player)
            );

            if ($existing !== null) {
                return $existing;
            }

            if ($locked->status !== GameStatus::Waiting || $locked->players->count() >= 2) {
                throw new InvalidArgumentException('This mission is already full or no longer accepting challengers.');
            }

            $player = $locked->players()->create([
                'slot' => 2,
                'user_id' => $guest->user?->getKey(),
                'guest_player_id' => $guest->guest?->getKey(),
                'is_ai' => false,
                'callsign' => $guest->callsign(),
                'is_ready' => false,
                'ammo' => $locked->mode->startingAmmo(),
                'last_active_at' => now(),
            ]);

            $locked->update([
                'status' => GameStatus::Placing,
            ]);

            return $player;
        });

        $this->broadcaster->dispatch(new PlayerJoined($game->fresh(), $joinedPlayer));

        return $joinedPlayer;
    }

    /**
     * @param  list<array{plane_type: string, origin_x: int, origin_y: int, orientation: string}>  $placements
     */
    public function placeFleet(Game $game, PlayerIdentity $actor, array $placements): Game
    {
        $updated = DB::transaction(function () use ($game, $actor, $placements): Game {
            /** @var Game $locked */
            $locked = Game::query()->with('players')->lockForUpdate()->findOrFail($game->getKey());

            if ($locked->status !== GameStatus::Placing) {
                throw new InvalidArgumentException('Fleet deployment is only allowed during the deployment phase.');
            }

            $player = $locked->players->first(
                static fn (GamePlayer $item): bool => $actor->matchesGamePlayer($item)
            );

            if ($player === null) {
                throw new InvalidArgumentException('You are not a participant in this mission.');
            }

            $this->deployFleetForPlayer($locked, $player, $placements);

            $locked->load('players');
            $allReady = $locked->players->count() === 2
                && $locked->players->every(static fn (GamePlayer $item): bool => $item->is_ready);

            if ($allReady) {
                $locked->update([
                    'status' => GameStatus::Playing,
                    'current_turn_slot' => $locked->is_vs_ai ? 1 : random_int(1, 2),
                    'turn_number' => 0,
                    'started_at' => now(),
                    'turn_started_at' => now(),
                ]);
            }

            return $locked->fresh(['players.planes']);
        });

        $actorPlayer = $updated->players->first(
            static fn (GamePlayer $item): bool => $actor->matchesGamePlayer($item)
        );

        if ($actorPlayer !== null) {
            $this->broadcaster->dispatch(new FleetReadyUpdated($updated, $actorPlayer));
        }

        return $updated;
    }

    public function fireShot(
        Game $game,
        PlayerIdentity $actor,
        MissileType $missile,
        int $targetX,
        int $targetY,
        ?string $orientation = null,
    ): GameShot {
        $result = DB::transaction(function () use ($game, $actor, $missile, $targetX, $targetY, $orientation): array {
            /** @var Game $locked */
            $locked = Game::query()->with(['players.planes', 'shots'])->lockForUpdate()->findOrFail($game->getKey());

            if ($locked->status !== GameStatus::Playing) {
                throw new InvalidArgumentException('This mission is not currently in combat.');
            }

            $attacker = $locked->players->first(
                static fn (GamePlayer $item): bool => $actor->matchesGamePlayer($item)
            );

            if ($attacker === null) {
                throw new InvalidArgumentException('You are not a participant in this mission.');
            }

            $shot = $this->executeShotForPlayer($locked, $attacker, $missile, $targetX, $targetY, $orientation);

            return [
                'game' => $locked->fresh(['players.planes', 'shots']),
                'shot' => $shot,
            ];
        });

        /** @var Game $updatedGame */
        $updatedGame = $result['game'];
        /** @var GameShot $shot */
        $shot = $result['shot'];

        $this->broadcaster->dispatch(new ShotFired($updatedGame, $shot));

        if ($updatedGame->status === GameStatus::Finished) {
            $this->broadcaster->dispatch(new GameFinished($updatedGame));

            return $shot;
        }

        // If playing vs AI and it is now AI's turn, execute AI turn(s) automatically.
        if ($updatedGame->is_vs_ai) {
            $this->processAiTurns($updatedGame);
        }

        return $shot;
    }

    public function forfeit(Game $game, PlayerIdentity $actor): Game
    {
        $updated = DB::transaction(function () use ($game, $actor): Game {
            /** @var Game $locked */
            $locked = Game::query()->with('players.guestPlayer')->lockForUpdate()->findOrFail($game->getKey());

            if (in_array($locked->status, [GameStatus::Finished, GameStatus::Abandoned], true)) {
                return $locked;
            }

            $player = $locked->players->first(
                static fn (GamePlayer $item): bool => $actor->matchesGamePlayer($item)
            );

            if ($player === null) {
                throw new InvalidArgumentException('You are not a participant in this mission.');
            }

            $opponent = $locked->players->first(
                static fn (GamePlayer $item): bool => $item->slot !== $player->slot
            );

            if ($opponent === null) {
                $locked->update([
                    'status' => GameStatus::Abandoned,
                    'finished_at' => now(),
                ]);

                return $locked->fresh(['players']);
            }

            $locked->update([
                'status' => GameStatus::Finished,
                'winner_slot' => $opponent->slot,
                'finished_at' => now(),
            ]);

            $this->recordMatchOutcome($opponent, $player);

            return $locked->fresh(['players.planes']);
        });

        if ($updated->status === GameStatus::Finished) {
            $this->broadcaster->dispatch(new GameFinished($updated));
        }

        return $updated;
    }

    public function requestRematch(Game $game, PlayerIdentity $actor): ?Game
    {
        $newGame = DB::transaction(function () use ($game, $actor): ?Game {
            /** @var Game $locked */
            $locked = Game::query()->with('players')->lockForUpdate()->findOrFail($game->getKey());

            if ($locked->status !== GameStatus::Finished) {
                throw new InvalidArgumentException('Rematch is only available after the mission ends.');
            }

            if ($locked->rematch_game_id !== null) {
                return Game::query()->find($locked->rematch_game_id);
            }

            $player = $locked->players->first(
                static fn (GamePlayer $item): bool => $actor->matchesGamePlayer($item)
            );

            if ($player === null) {
                throw new InvalidArgumentException('You are not a participant in this mission.');
            }

            $player->update(['rematch_requested' => true]);

            $opponent = $locked->players->first(
                static fn (GamePlayer $item): bool => $item->slot !== $player->slot
            );

            if ($opponent === null) {
                return null;
            }

            if ($locked->is_vs_ai || $opponent->rematch_requested) {
                $rematch = Game::query()->create([
                    'code' => $this->generateUniqueCode(),
                    'mode' => $locked->mode,
                    'status' => GameStatus::Placing,
                    'grid_size' => $locked->grid_size,
                    'is_public' => false,
                    'is_vs_ai' => $locked->is_vs_ai,
                    'settings' => $locked->settings,
                    'turn_number' => 0,
                ]);

                foreach ($locked->players as $existingPlayer) {
                    $created = $rematch->players()->create([
                        'slot' => $existingPlayer->slot,
                        'user_id' => $existingPlayer->user_id,
                        'guest_player_id' => $existingPlayer->guest_player_id,
                        'is_ai' => $existingPlayer->is_ai,
                        'callsign' => $existingPlayer->callsign,
                        'is_ready' => false,
                        'ammo' => $locked->mode->startingAmmo(),
                        'last_active_at' => now(),
                    ]);

                    if ($created->is_ai) {
                        $this->deployFleetForPlayer(
                            $rematch,
                            $created,
                            $this->geometry->generateRandomFleet($rematch->mode, $rematch->grid_size)
                        );
                    }
                }

                $locked->update(['rematch_game_id' => $rematch->getKey()]);

                return $rematch;
            }

            return null;
        });

        if ($newGame !== null) {
            $this->broadcaster->dispatch(new RematchCreated($game->fresh(), $newGame));
        } else {
            $requestingPlayer = $game->fresh(['players'])->players->first(
                static fn (GamePlayer $item): bool => $actor->matchesGamePlayer($item)
            );

            if ($requestingPlayer !== null) {
                $this->broadcaster->dispatch(new RematchRequested($game->fresh(), $requestingPlayer));
            }
        }

        return $newGame;
    }

    /**
     * @param  list<array{plane_type: string, origin_x: int, origin_y: int, orientation: string}>  $placements
     */
    private function deployFleetForPlayer(Game $game, GamePlayer $player, array $placements): void
    {
        $resolved = $this->geometry->validateFleet($game->mode, $game->grid_size, $placements);

        $player->planes()->delete();

        foreach ($resolved as $item) {
            $hp = $item['plane_type']->cockpitHp();

            GamePlane::query()->create([
                'game_id' => $game->getKey(),
                'game_player_id' => $player->getKey(),
                'plane_type' => $item['plane_type'],
                'origin_x' => $item['origin_x'],
                'origin_y' => $item['origin_y'],
                'orientation' => $item['orientation'],
                'cells' => $item['cells'],
                'hit_cells' => [],
                'cockpit_hp' => $hp,
                'max_cockpit_hp' => $hp,
                'is_destroyed' => false,
            ]);
        }

        $player->update([
            'is_ready' => true,
            'last_active_at' => now(),
        ]);
    }

    private function executeShotForPlayer(
        Game $game,
        GamePlayer $attacker,
        MissileType $missile,
        int $targetX,
        int $targetY,
        ?string $orientation,
    ): GameShot {
        if ($game->current_turn_slot !== $attacker->slot) {
            throw new InvalidArgumentException('It is not your turn to fire.');
        }

        if ($targetX < 0 || $targetX >= $game->grid_size || $targetY < 0 || $targetY >= $game->grid_size) {
            throw new InvalidArgumentException('Target coordinates are outside airspace bounds.');
        }

        $nextTurn = $game->turn_number + 1;

        if ($missile->isSpecial() && $attacker->isEmpJammed($nextTurn)) {
            throw new InvalidArgumentException('Your special ordnance is EMP-jammed this turn. Use Standard SAM.');
        }

        $remaining = $attacker->remainingAmmo($missile);

        if ($remaining !== null && $remaining <= 0) {
            throw new InvalidArgumentException("No {$missile->label()} ordnance remaining.");
        }

        /** @var GamePlayer $defender */
        $defender = $game->players->first(
            static fn (GamePlayer $item): bool => $item->slot !== $attacker->slot
        );

        $resolution = $this->ordnance->resolve(
            missile: $missile,
            targetX: $targetX,
            targetY: $targetY,
            gridSize: $game->grid_size,
            turnNumber: $nextTurn,
            defenderPlanes: $defender->planes,
            orientation: $orientation ?? 'H',
        );

        foreach ($defender->planes as $plane) {
            if ($plane->isDirty()) {
                $plane->save();
            }
        }

        if ($remaining !== null) {
            $ammo = $attacker->ammo ?? [];
            $ammo[$missile->value] = max(0, $remaining - 1);
            $attacker->ammo = $ammo;
        }

        $attacker->last_active_at = now();
        $attacker->save();

        if ($resolution['emp_jam_applied']) {
            $defender->emp_jammed_until_turn = $nextTurn + 1;
            $defender->save();
        }

        $shot = GameShot::query()->create([
            'game_id' => $game->getKey(),
            'attacker_slot' => $attacker->slot,
            'turn_number' => $nextTurn,
            'missile_type' => $missile,
            'target_x' => $targetX,
            'target_y' => $targetY,
            'orientation' => $missile === MissileType::Carpet ? ($orientation ?? 'H') : null,
            'results' => $resolution['results'],
            'radar_count' => $resolution['radar_count'],
            'destroyed_planes' => $resolution['destroyed_planes'],
        ]);

        $game->turn_number = $nextTurn;
        $game->turn_started_at = now();

        if ($resolution['all_destroyed']) {
            $game->status = GameStatus::Finished;
            $game->winner_slot = $attacker->slot;
            $game->finished_at = now();
            $game->save();

            $this->recordMatchOutcome($attacker, $defender);
        } else {
            $keepTurn = $game->extraTurnOnKill() && $resolution['cockpit_kill_scored'];
            $game->current_turn_slot = $keepTurn ? $attacker->slot : $defender->slot;
            $game->save();
        }

        return $shot;
    }

    private function processAiTurns(Game $game): void
    {
        $guard = 0;

        while ($guard < 5) {
            $guard++;

            $step = DB::transaction(function () use ($game): ?array {
                /** @var Game $locked */
                $locked = Game::query()->with(['players.planes', 'shots'])->lockForUpdate()->findOrFail($game->getKey());

                if ($locked->status !== GameStatus::Playing) {
                    return null;
                }

                $aiPlayer = $locked->players->firstWhere('is_ai', true);

                if ($aiPlayer === null || $locked->current_turn_slot !== $aiPlayer->slot) {
                    return null;
                }

                $decision = $this->aiCommander->chooseShot($locked, $aiPlayer);
                $shot = $this->executeShotForPlayer(
                    $locked,
                    $aiPlayer,
                    $decision['missile'],
                    $decision['x'],
                    $decision['y'],
                    $decision['orientation'],
                );

                return [
                    'game' => $locked->fresh(['players.planes', 'shots']),
                    'shot' => $shot,
                ];
            });

            if ($step === null) {
                break;
            }

            /** @var Game $updated */
            $updated = $step['game'];
            /** @var GameShot $aiShot */
            $aiShot = $step['shot'];

            $this->broadcaster->dispatch(new ShotFired($updated, $aiShot), toOthers: false);

            if ($updated->status === GameStatus::Finished) {
                $this->broadcaster->dispatch(new GameFinished($updated), toOthers: false);
                break;
            }
        }
    }

    private function recordMatchOutcome(GamePlayer $winner, GamePlayer $loser): void
    {
        if ($winner->guest_player_id !== null) {
            $winner->guestPlayer?->increment('wins');
        }

        if ($loser->guest_player_id !== null) {
            $loser->guestPlayer?->increment('losses');
        }
    }

    private function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';

            for ($i = 0; $i < 5; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $code = 'SKY-'.$suffix;
        } while (Game::query()->where('code', $code)->exists());

        return $code;
    }
}
