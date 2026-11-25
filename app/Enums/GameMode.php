<?php

namespace App\Enums;

enum GameMode: string
{
    case Classic = 'classic';
    case Tactical = 'tactical';
    case Blitz = 'blitz';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic Avioane',
            self::Tactical => 'Tactical Squadron',
            self::Blitz => 'Blitz Dogfight',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Classic => '10x10 airspace, 3 Classic Fighter-Bombers, standard SAM missiles only.',
            self::Tactical => '12x12 airspace, 4 distinct aircraft classes, and full 5-weapon tactical arsenal.',
            self::Blitz => '10x10 fast-paced dogfight, 3 planes, 25s turn timer, bonus turn on cockpit kill.',
        };
    }

    public function gridSize(): int
    {
        return match ($this) {
            self::Classic, self::Blitz => 10,
            self::Tactical => 12,
        };
    }

    /**
     * @return list<PlaneType>
     */
    public function fleetRoster(): array
    {
        return match ($this) {
            self::Classic => [
                PlaneType::Fighter,
                PlaneType::Fighter,
                PlaneType::Fighter,
            ],
            self::Tactical => [
                PlaneType::Heavy,
                PlaneType::Stealth,
                PlaneType::Fighter,
                PlaneType::Interceptor,
            ],
            self::Blitz => [
                PlaneType::Fighter,
                PlaneType::Stealth,
                PlaneType::Interceptor,
            ],
        };
    }

    /**
     * @return array<string, int|null>
     */
    public function startingAmmo(): array
    {
        return match ($this) {
            self::Classic => [
                MissileType::Standard->value => null,
                MissileType::Radar->value => 0,
                MissileType::Cluster->value => 0,
                MissileType::Carpet->value => 0,
                MissileType::Emp->value => 0,
            ],
            self::Tactical => [
                MissileType::Standard->value => null,
                MissileType::Radar->value => 3,
                MissileType::Cluster->value => 2,
                MissileType::Carpet->value => 2,
                MissileType::Emp->value => 1,
            ],
            self::Blitz => [
                MissileType::Standard->value => null,
                MissileType::Radar->value => 2,
                MissileType::Cluster->value => 1,
                MissileType::Carpet->value => 1,
                MissileType::Emp->value => 1,
            ],
        };
    }

    public function defaultTurnTimerSeconds(): int
    {
        return match ($this) {
            self::Classic => 60,
            self::Tactical => 60,
            self::Blitz => 25,
        };
    }

    public function defaultExtraTurnOnKill(): bool
    {
        return match ($this) {
            self::Blitz => true,
            self::Classic, self::Tactical => false,
        };
    }
}
