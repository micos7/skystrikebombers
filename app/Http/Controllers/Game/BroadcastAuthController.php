<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Services\PlayerIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class BroadcastAuthController extends Controller
{
    public function __construct(
        private readonly PlayerIdentityService $identityService,
    ) {}

    public function __invoke(Request $request): mixed
    {
        $identity = $this->identityService->resolve($request);

        $request->setUserResolver(static fn () => $identity);

        return Broadcast::auth($request);
    }
}
