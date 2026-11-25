<?php

namespace App\Enums;

enum MissileType: string
{
    case Standard = 'standard';
    case Radar = 'radar';
    case Cluster = 'cluster';
    case Carpet = 'carpet';
    case Emp = 'emp';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard SAM',
            self::Radar => 'Radar Recon',
            self::Cluster => 'Cluster Bomb',
            self::Carpet => 'Carpet Bomb',
            self::Emp => 'EMP Bunker-Buster',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Standard => '1x1 precision strike. Instant cockpit kill on standard aircraft.',
            self::Radar => '3x3 non-lethal recon sweep. Counts non-stealth aircraft cells.',
            self::Cluster => '5-cell (+) cross blast. Damages wings/body; cracks but cannot instant-kill cockpits.',
            self::Carpet => '1x4 line strike (Horizontal or Vertical). Ideal for sweeping wings.',
            self::Emp => '2x2 armor-piercing blast. One-shots Heavy cockpits and jams enemy special weapons for 1 turn.',
        };
    }

    public function isSpecial(): bool
    {
        return $this !== self::Standard;
    }
}
