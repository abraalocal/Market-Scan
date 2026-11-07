<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** /integrations/*: X-Service-Token must equal SERVICE_TOKEN. */
class ServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('marketscan.service_token');
        $given = $request->header('X-Service-Token');
        if ($expected === '' || ! is_string($given) || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Invalid service token.'], 401);
        }

        return $next($request);
    }
}
