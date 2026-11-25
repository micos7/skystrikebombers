<?php

namespace App\Events\Game;

use App\Models\Game;
use App\Models\GameShot;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShotFired implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Game $game,
        public readonly GameShot $shot,
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
        return 'ShotFired';
    }

    /**
     * Only public shot outcomes and destroyed-plane reveals are broadcast.
     * Un-hit plane coordinates are never included.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_code' => $this->game->code,
            'status' => $this->game->status->value,
            'current_turn_slot' => $this->game->current_turn_slot,
            'turn_number' => $this->game->turn_number,
            'winner_slot' => $this->game->winner_slot,
            'shot' => [
                'id' => $this->shot->id,
                'attacker_slot' => $this->shot->attacker_slot,
                'turn_number' => $this->shot->turn_number,
                'missile_type' => $this->shot->missile_type->value,
                'target_x' => $this->shot->target_x,
                'target_y' => $this->shot->target_y,
                'orientation' => $this->shot->orientation,
                'results' => $this->shot->results,
                'radar_count' => $this->shot->radar_count,
                'destroyed_planes' => $this->shot->destroyed_planes ?? [],
            ],
        ];
    }
}
