<?php

namespace App\Http\Controllers\Game;

use App\Enums\GameMode;
use App\Enums\GameStatus;
use App\Game\GameEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\CreateGameRequest;
use App\Models\Game;
use App\Services\PlayerIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LobbyController extends Controller
{
    public function __construct(
        private readonly PlayerIdentityService $identityService,
        private readonly GameEngine $engine,
    ) {}

    public function index(Request $request): Response
    {
        $identity = $this->identityService->resolve($request);

        $publicGames = Game::query()
            ->with('players')
            ->where('is_public', true)
            ->where('status', GameStatus::Waiting)
            ->latest()
            ->limit(20)
            ->get()
            ->map(static function (Game $game): array {
                $host = $game->players->firstWhere('slot', 1);

                return [
                    'code' => $game->code,
                    'mode' => $game->mode->value,
                    'mode_label' => $game->mode->label(),
                    'grid_size' => $game->grid_size,
                    'host_callsign' => $host !== null ? $host->callsign : 'Commander',
                    'turn_timer_seconds' => $game->turnTimerSeconds(),
                    'extra_turn_on_kill' => $game->extraTurnOnKill(),
                    'created_at' => $game->created_at?->diffForHumans(),
                ];
            })
            ->values()
            ->all();

        $myGamesQuery = Game::query()
            ->with('players')
            ->whereHas('players', function ($query) use ($identity): void {
                if ($identity->user !== null) {
                    $query->where('user_id', $identity->user->getKey());
                } elseif ($identity->guest !== null) {
                    $query->where('guest_player_id', $identity->guest->getKey());
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->whereIn('status', [
                GameStatus::Waiting,
                GameStatus::Placing,
                GameStatus::Playing,
                GameStatus::Finished,
            ])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $myGames = $myGamesQuery
            ->map(static function (Game $game) use ($identity): array {
                $me = $game->players->first(static fn ($p): bool => $identity->matchesGamePlayer($p));
                $opponent = $game->players->first(static fn ($p): bool => $me === null || $p->slot !== $me->slot);

                return [
                    'code' => $game->code,
                    'mode_label' => $game->mode->label(),
                    'status' => $game->status->value,
                    'is_vs_ai' => $game->is_vs_ai,
                    'opponent_callsign' => $opponent !== null ? $opponent->callsign : 'Awaiting Challenger',
                    'is_winner' => $game->status === GameStatus::Finished && $me !== null && $game->winner_slot === $me->slot,
                    'updated_at' => $game->updated_at?->diffForHumans(),
                ];
            })
            ->values()
            ->all();

        $modes = array_map(
            static fn (GameMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
                'description' => $mode->description(),
                'grid_size' => $mode->gridSize(),
                'plane_count' => count($mode->fleetRoster()),
                'default_turn_timer_seconds' => $mode->defaultTurnTimerSeconds(),
                'default_extra_turn_on_kill' => $mode->defaultExtraTurnOnKill(),
            ],
            GameMode::cases()
        );

        return Inertia::render('games/Lobby', [
            'identity' => $identity->toArray(),
            'modes' => $modes,
            'publicGames' => $publicGames,
            'myGames' => $myGames,
        ]);
    }

    public function store(CreateGameRequest $request): RedirectResponse
    {
        $identity = $this->identityService->resolve($request);
        $validated = $request->validated();

        $mode = GameMode::from($validated['mode']);

        $game = $this->engine->createGame($identity, $mode, [
            'is_public' => (bool) ($validated['is_public'] ?? false),
            'is_vs_ai' => (bool) ($validated['is_vs_ai'] ?? false),
            'turn_timer_seconds' => array_key_exists('turn_timer_seconds', $validated)
                ? ($validated['turn_timer_seconds'] !== null ? (int) $validated['turn_timer_seconds'] : null)
                : $mode->defaultTurnTimerSeconds(),
            'extra_turn_on_kill' => (bool) ($validated['extra_turn_on_kill'] ?? $mode->defaultExtraTurnOnKill()),
        ]);

        return to_route('games.show', ['game' => $game->code]);
    }

    public function updateCallsign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'callsign' => ['required', 'string', 'min:2', 'max:24', 'regex:/^[A-Za-z0-9 _\-]+$/'],
        ]);

        $identity = $this->identityService->resolve($request);
        $this->identityService->updateCallsign($identity, $validated['callsign']);

        return back();
    }
}
