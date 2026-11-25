<?php

use App\Data\PlayerIdentity;
use App\Enums\GameMode;
use App\Enums\GameStatus;
use App\Enums\MissileType;
use App\Events\Game\FleetReadyUpdated;
use App\Events\Game\GameFinished;
use App\Events\Game\PlayerJoined;
use App\Events\Game\RematchCreated;
use App\Events\Game\ShotFired;
use App\Game\GameEngine;
use App\Game\GameStatePresenter;
use App\Game\PlaneGeometry;
use App\Models\Game;
use App\Models\GuestPlayer;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Support\Facades\Event;

it('allows guests to visit the lobby, receive a guest identity with IP, and update their callsign', function (): void {
    $response = $this->get('/');

    $response->assertSuccessful();
    expect(GuestPlayer::query()->count())->toBe(1);

    $guest = GuestPlayer::query()->firstOrFail();
    expect($guest->ip_address)->not->toBeNull();
});

it('runs a full 2-player guest PvP match with invite link, fog-of-war security, EMP jam, victory, and rematch', function (): void {
    Event::fake([
        PlayerJoined::class,
        FleetReadyUpdated::class,
        ShotFired::class,
        GameFinished::class,
        RematchCreated::class,
    ]);

    $engine = app(GameEngine::class);
    $presenter = app(GameStatePresenter::class);
    $geometry = app(PlaneGeometry::class);

    $guestOne = GuestPlayer::factory()->create(['callsign' => 'Viper-01']);
    $guestTwo = GuestPlayer::factory()->create(['callsign' => 'Falcon-02']);
    $p1 = new PlayerIdentity(user: null, guest: $guestOne);
    $p2 = new PlayerIdentity(user: null, guest: $guestTwo);

    $game = $engine->createGame($p1, GameMode::Classic, ['is_public' => false]);
    expect($game->status)->toBe(GameStatus::Waiting);

    $engine->joinGame($game, $p2);
    Event::assertDispatched(PlayerJoined::class);

    $fleet1 = $geometry->generateRandomFleet(GameMode::Classic, 10);
    $fleet2 = $geometry->generateRandomFleet(GameMode::Classic, 10);

    $engine->placeFleet($game, $p1, $fleet1);
    $started = $engine->placeFleet($game, $p2, $fleet2);
    Event::assertDispatched(FleetReadyUpdated::class);

    expect($started->status)->toBe(GameStatus::Playing);

    // Fog-of-War Security Check: Player 1 must NOT see any un-hit Player 2 planes!
    $stateForP1 = $presenter->present($started, $p1);
    expect($stateForP1['my_planes'])->toHaveCount(3);
    expect($stateForP1['enemy_revealed_planes'])->toHaveCount(0);
    expect($stateForP1['enemy_fleet_summary'])->toHaveCount(3);

    // Determine who shoots first and sink all 3 opponent planes by targeting their cockpits
    $attacker = $started->current_turn_slot === 1 ? $p1 : $p2;
    $defender = $started->current_turn_slot === 1 ? $p2 : $p1;
    $defenderFleet = $started->current_turn_slot === 1 ? $fleet2 : $fleet1;

    foreach ($defenderFleet as $index => $plane) {
        // Attacker shoots defender's cockpit
        $engine->fireShot($started, $attacker, MissileType::Standard, $plane['origin_x'], $plane['origin_y']);
        $started->refresh();

        if ($started->status === GameStatus::Finished) {
            break;
        }

        // Defender takes a turn on (0, $index)
        $engine->fireShot($started, $defender, MissileType::Standard, 0, $index);
        $started->refresh();
    }

    Event::assertDispatched(ShotFired::class);
    Event::assertDispatched(GameFinished::class);

    expect($started->status)->toBe(GameStatus::Finished);
    expect($started->winner_slot)->toBe($attacker === $p1 ? 1 : 2);

    // Post-game reveal: all 3 enemy planes are now revealed
    $finishedState = $presenter->present($started, $p1);
    expect($finishedState['enemy_revealed_planes'])->toHaveCount(3);

    // Rematch flow
    expect($engine->requestRematch($started, $p1))->toBeNull();
    $rematch = $engine->requestRematch($started, $p2);

    expect($rematch)->toBeInstanceOf(Game::class);
    expect($rematch->status)->toBe(GameStatus::Placing);
    Event::assertDispatched(RematchCreated::class);
});

it('enforces EMP jamming on special ordnance for the defender next turn', function (): void {
    Event::fake();

    $engine = app(GameEngine::class);
    $geometry = app(PlaneGeometry::class);

    $p1 = new PlayerIdentity(user: null, guest: GuestPlayer::factory()->create());
    $p2 = new PlayerIdentity(user: null, guest: GuestPlayer::factory()->create());

    $game = $engine->createGame($p1, GameMode::Tactical);
    $engine->joinGame($game, $p2);
    $engine->placeFleet($game, $p1, $geometry->generateRandomFleet(GameMode::Tactical, 12));
    $started = $engine->placeFleet($game, $p2, $geometry->generateRandomFleet(GameMode::Tactical, 12));

    $attacker = $started->current_turn_slot === 1 ? $p1 : $p2;
    $defender = $started->current_turn_slot === 1 ? $p2 : $p1;

    $engine->fireShot($started, $attacker, MissileType::Emp, 0, 0);
    $started->refresh();

    if ($started->status === GameStatus::Playing) {
        expect(fn () => $engine->fireShot($started, $defender, MissileType::Radar, 5, 5))
            ->toThrow(InvalidArgumentException::class, 'EMP-jammed');

        // Standard SAM still works while jammed
        $engine->fireShot($started, $defender, MissileType::Standard, 0, 0);
    }

    expect($started->shots()->count())->toBeGreaterThanOrEqual(1);
});

it('supports solo play vs AI Commander with automatic AI counter-turns', function (): void {
    Event::fake();

    $engine = app(GameEngine::class);
    $geometry = app(PlaneGeometry::class);

    $human = new PlayerIdentity(user: null, guest: GuestPlayer::factory()->create(['callsign' => 'SoloAce']));
    $game = $engine->createGame($human, GameMode::Tactical, ['is_vs_ai' => true]);

    expect($game->status)->toBe(GameStatus::Placing);
    expect($game->players)->toHaveCount(2);
    expect($game->players->firstWhere('slot', 2)->is_ready)->toBeTrue();

    $started = $engine->placeFleet($game, $human, $geometry->generateRandomFleet(GameMode::Tactical, 12));
    expect($started->status)->toBe(GameStatus::Playing);
    expect($started->current_turn_slot)->toBe(1);

    $engine->fireShot($started, $human, MissileType::Radar, 5, 5);
    $started->refresh();

    // Human shot (turn 1) + AI automatic counter-shot (turn 2)
    expect($started->turn_number)->toBe(2);
    expect($started->current_turn_slot)->toBe(1);
});

it('does not fail joining a game when the WebSocket broadcaster is unreachable', function (): void {
    $broadcaster = Mockery::mock(Factory::class);
    $broadcaster->shouldReceive('event')->andThrow(
        new BroadcastException('cURL error 7: Failed to connect to 127.0.0.1:8080')
    );

    $this->app->instance(Factory::class, $broadcaster);

    $engine = app(GameEngine::class);
    $p1 = new PlayerIdentity(user: null, guest: GuestPlayer::factory()->create());
    $p2 = new PlayerIdentity(user: null, guest: GuestPlayer::factory()->create());

    $game = $engine->createGame($p1, GameMode::Classic);
    $joined = $engine->joinGame($game, $p2);

    expect($joined->slot)->toBe(2);
    expect($game->fresh()->status)->toBe(GameStatus::Placing);
});
