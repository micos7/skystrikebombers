<?php

namespace App\Http\Controllers\Game;

use App\Enums\GameStatus;
use App\Game\GameEngine;
use App\Game\GameStatePresenter;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\PlayerIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class GameController extends Controller
{
    public function __construct(
        private readonly PlayerIdentityService $identityService,
        private readonly GameEngine $engine,
        private readonly GameStatePresenter $presenter,
    ) {}

    public function show(Request $request, Game $game): Response
    {
        $identity = $this->identityService->resolve($request);

        return Inertia::render('games/Play', [
            'initialGame' => $this->presenter->present($game, $identity),
        ]);
    }

    public function joinByLink(Request $request, string $code): RedirectResponse
    {
        $normalized = strtoupper(trim($code));
        $game = Game::query()->with('players')->where('code', $normalized)->first();

        if ($game === null) {
            return to_route('home')->with('error', "Mission code {$normalized} was not found.");
        }

        $identity = $this->identityService->resolve($request);

        $alreadyJoined = $game->players->contains(
            static fn ($player): bool => $identity->matchesGamePlayer($player)
        );

        if (! $alreadyJoined && $game->status === GameStatus::Waiting) {
            try {
                $this->engine->joinGame($game, $identity);
            } catch (InvalidArgumentException $e) {
                return to_route('home')->with('error', $e->getMessage());
            }
        }

        return to_route('games.show', ['game' => $game->code]);
    }

    public function join(Request $request, Game $game): RedirectResponse
    {
        $identity = $this->identityService->resolve($request);

        try {
            $this->engine->joinGame($game, $identity);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['game' => $e->getMessage()]);
        }

        return to_route('games.show', ['game' => $game->code]);
    }

    public function state(Request $request, Game $game): JsonResponse
    {
        $identity = $this->identityService->resolve($request);

        return response()->json($this->presenter->present($game, $identity));
    }
}
