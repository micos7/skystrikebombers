<?php

use App\Data\PlayerIdentity;
use App\Models\Game;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('game.{code}', function (mixed $actor, string $code): array|bool {
    if (! $actor instanceof PlayerIdentity) {
        return false;
    }

    $game = Game::query()
        ->with('players')
        ->where('code', $code)
        ->first();

    if ($game === null) {
        return false;
    }

    $matched = $game->players->first(
        fn ($player) => $actor->matchesGamePlayer($player)
    );

    if ($matched === null) {
        return false;
    }

    return [
        'id' => $actor->id(),
        'callsign' => $matched->callsign,
        'slot' => $matched->slot,
    ];
});
