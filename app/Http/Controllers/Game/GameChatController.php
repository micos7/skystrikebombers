<?php

namespace App\Http\Controllers\Game;

use App\Events\Game\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\SendMessageRequest;
use App\Models\Game;
use App\Models\GameMessage;
use App\Services\PlayerIdentityService;
use App\Support\SafeBroadcaster;
use Illuminate\Http\JsonResponse;

class GameChatController extends Controller
{
    public function __construct(
        private readonly PlayerIdentityService $identityService,
        private readonly SafeBroadcaster $broadcaster,
    ) {}

    public function store(SendMessageRequest $request, Game $game): JsonResponse
    {
        $identity = $this->identityService->resolve($request);
        $game->loadMissing('players');

        $player = $game->players->first(
            static fn ($item): bool => $identity->matchesGamePlayer($item)
        );

        if ($player === null) {
            return response()->json(['message' => 'Only mission participants can use tactical comms.'], 403);
        }

        $validated = $request->validated();

        $message = GameMessage::query()->create([
            'game_id' => $game->getKey(),
            'sender_slot' => $player->slot,
            'sender_callsign' => $player->callsign,
            'body' => trim($validated['body']),
            'is_emote' => (bool) ($validated['is_emote'] ?? false),
        ]);

        $this->broadcaster->dispatch(new ChatMessageSent($game, $message));

        return response()->json([
            'id' => $message->id,
            'sender_slot' => $message->sender_slot,
            'sender_callsign' => $message->sender_callsign,
            'body' => $message->body,
            'is_emote' => $message->is_emote,
            'created_at' => $message->created_at?->toIso8601String(),
        ], 201);
    }
}
