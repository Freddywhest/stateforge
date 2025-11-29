<?php

namespace Roddy\StateForge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Roddy\StateForge\ClientIdentifier;

class StateForgeMiddleware
{
    public function __construct(protected ClientIdentifier $clientIdentifier) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Set client cookie if it doesn't exist
        if (!$request->cookie('stateforge_client_id')) {
            $response->cookie($this->clientIdentifier->getClientCookie());
        }

        return $response;
    }
}
