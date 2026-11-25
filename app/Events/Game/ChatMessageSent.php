<?php

namespace App\Events\Game;

use App\Models\Game;
use App\Models\GameMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Game $game,
        public readonly GameMessage $message,
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
        return 'ChatMessageSent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'sender_slot' => $this->message->sender_slot,
            'sender_callsign' => $this->message->sender_callsign,
            'body' => $this->message->body,
            'is_emote' => $this->message->is_emote,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
