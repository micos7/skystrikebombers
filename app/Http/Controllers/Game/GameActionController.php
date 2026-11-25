<?php

namespace App\Http\Controllers\Game;

use App\Enums\MissileType;
use App\Game\GameEngine;
use App\Game\GameStatePresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\FireShotRequest;
use App\Http\Requests\Game\PlaceFleetRequest;
use App\Models\Game;
use App\Services\PlayerIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GameActionController extends Controller
{
    public function __construct(
        private readonly PlayerIdentityService $identityService,
        private readonly GameEngine $engine,
        private readonly GameStatePresenter $presenter,
    ) {}

    public function placeFleet(PlaceFleetRequest $request, Game $game): JsonResponse
    {
        $identity = $this->identityService->resolve($request);

        try {
            $updated = $this->engine->placeFleet($game, $identity, $request->validated('planes'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->presenter->present($updated, $identity));
    }

    public function fire(FireShotRequest $request, Game $game): JsonResponse
    {
        $identity = $this->identityService->resolve($request);
        $validated = $request->validated();

        try {
            $this->engine->fireShot(
                game: $game,
                actor: $identity,
                missile: MissileType::from($validated['missile_type']),
                targetX: (int) $validated['target_x'],
                targetY: (int) $validated['target_y'],
                orientation: $validated['orientation'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->presenter->present($game->fresh(), $identity));
    }

    public function forfeit(Request $request, Game $game): JsonResponse
    {
        $identity = $this->identityService->resolve($request);

        try {
            $updated = $this->engine->forfeit($game, $identity);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->presenter->present($updated, $identity));
    }

    public function rematch(Request $request, Game $game): JsonResponse
    {
        $identity = $this->identityService->resolve($request);

        try {
            $rematchGame = $this->engine->requestRematch($game, $identity);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'game' => $this->presenter->present($game->fresh(), $identity),
            'rematch_game_code' => $rematchGame?->code,
        ]);
    }
}
