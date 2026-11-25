<?php

namespace App\Events\Game;

use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Game $game,
        public readonly GamePlayer $player,
    ) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel("game.{$this->game->code}")];
    }

    public function broadcastAs(): string
    {
        return 'PlayerJoined';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_code' => $this->game->code,
            'status' => $this->game->status->value,
            'player' => [
                'slot' => $this->player->slot,
                'callsign' => $this->player->callsign,
                'is_ai' => $this->player->is_ai,
                'is_ready' => $this->player->is_ready,
            ],
        ];
    }
}
