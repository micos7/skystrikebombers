<?php

namespace App\Data;

use App\Models\GamePlayer;
use App\Models\GuestPlayer;
use App\Models\User;

readonly class PlayerIdentity
{
    public function __construct(
        public ?User $user,
        public ?GuestPlayer $guest,
    ) {}

    public function id(): string
    {
        if ($this->user !== null) {
            return 'user:'.$this->user->getKey();
        }

        return 'guest:'.($this->guest?->getKey() ?? 'anonymous');
    }

    public function getAuthIdentifier(): string
    {
        return $this->id();
    }

    public function callsign(): string
    {
        if ($this->user !== null) {
            return $this->user->name;
        }

        return $this->guest !== null ? $this->guest->callsign : 'Pilot';
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    public function matchesGamePlayer(GamePlayer $player): bool
    {
        if ($player->is_ai) {
            return false;
        }

        if ($this->user !== null && $player->user_id !== null) {
            return (int) $player->user_id === (int) $this->user->getKey();
        }

        if ($this->guest !== null && $player->guest_player_id !== null) {
            return (string) $player->guest_player_id === (string) $this->guest->getKey();
        }

        return false;
    }

    /**
     * @return array{id: string, callsign: string, is_guest: bool, wins: int, losses: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'callsign' => $this->callsign(),
            'is_guest' => $this->isGuest(),
            'wins' => $this->guest !== null ? $this->guest->wins : 0,
            'losses' => $this->guest !== null ? $this->guest->losses : 0,
        ];
    }
}
