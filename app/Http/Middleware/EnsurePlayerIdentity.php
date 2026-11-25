<?php

namespace App\Http\Middleware;

use App\Services\PlayerIdentityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayerIdentity
{
    public function __construct(
        private readonly PlayerIdentityService $identityService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->identityService->resolve($request);

        return $next($request);
    }
}
