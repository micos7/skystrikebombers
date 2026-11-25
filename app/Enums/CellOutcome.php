<?php

namespace App\Enums;

enum CellOutcome: string
{
    case Miss = 'miss';
    case Hit = 'hit';
    case CockpitCracked = 'cockpit_cracked';
    case CockpitKilled = 'cockpit_killed';
    case RadarPing = 'radar_ping';
}
