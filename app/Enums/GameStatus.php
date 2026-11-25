<?php

namespace App\Enums;

enum GameStatus: string
{
    case Waiting = 'waiting';
    case Placing = 'placing';
    case Playing = 'playing';
    case Finished = 'finished';
    case Abandoned = 'abandoned';
}
