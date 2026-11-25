<?php

namespace App\Support;

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

class SafeBroadcaster
{
    public function dispatch(object $event, bool $toOthers = true): void
    {
        try {
            $pending = broadcast($event);

            if ($toOthers) {
                $pending->toOthers();
            }
        } catch (BroadcastException $e) {
            Log::warning('WebSocket broadcast failed; falling back to HTTP state sync.', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
