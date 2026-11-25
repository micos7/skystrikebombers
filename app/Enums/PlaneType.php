<?php

namespace App\Enums;

enum PlaneType: string
{
    case Interceptor = 'interceptor';
    case Fighter = 'fighter';
    case Stealth = 'stealth';
    case Heavy = 'heavy';

    public function label(): string
    {
        return match ($this) {
            self::Interceptor => 'Interceptor',
            self::Fighter => 'Fighter-Bomber',
            self::Stealth => 'Stealth Bomber',
            self::Heavy => 'Heavy Stratofortress',
        };
    }

    public function cockpitHp(): int
    {
        return match ($this) {
            self::Heavy => 2,
            self::Interceptor, self::Fighter, self::Stealth => 1,
        };
    }

    public function isRadarStealth(): bool
    {
        return $this === self::Stealth;
    }

    public function perkDescription(): string
    {
        return match ($this) {
            self::Interceptor => 'Compact 6-cell delta frame. Grants +1 Radar Recon charge.',
            self::Fighter => 'Iconic 10-cell Avioane cross frame. Grants +1 Cluster Bomb charge.',
            self::Stealth => '8-cell flying wing. Radar Cloak hides it from enemy Radar Recon sweeps.',
            self::Heavy => '13-cell fortress. Reinforced Cockpit (2 HP) survives 1 non-EMP cockpit hit.',
        };
    }
}
